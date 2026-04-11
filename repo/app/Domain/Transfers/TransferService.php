<?php
namespace App\Domain\Transfers;

use App\Models\{TransferRequest, CustodyRecord, DepartmentHandoff, InventoryLot, User, AuditLog};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransferService
{
    public function initiateTransfer(User $initiator, array $data): TransferRequest
    {
        return DB::transaction(function () use ($initiator, $data) {
            $lot = InventoryLot::where('id', $data['inventory_lot_id'])->lockForUpdate()->firstOrFail();

            // Check the lot belongs to source department (lot-level dept takes precedence, fall back to resource dept)
            $lotDeptId = $lot->department_id ?? $lot->resource->department_id;
            if ($lotDeptId !== (int)$data['from_department_id']) {
                throw new \App\Common\Exceptions\BusinessRuleException('Inventory lot does not belong to source department.');
            }

            // Check not already in transit
            $inTransit = CustodyRecord::where('inventory_lot_id', $lot->id)
                ->where('custody_type', 'in_transit')
                ->whereNull('ended_at')
                ->exists();

            if ($inTransit) {
                throw new \App\Common\Exceptions\BusinessRuleException('Item is already in transit.');
            }

            // Check requested quantity against true available (subtracts checkouts, approved loans, reservations, other transfers)
            $availService = app(\App\Domain\Availability\AvailabilityService::class);
            $trueAvailable = $availService->getLotAvailableQuantity($lot);
            $requestedQty = $data['quantity'] ?? 1;
            if ($requestedQty > $trueAvailable) {
                throw new \App\Common\Exceptions\BusinessRuleException(
                    "Transfer quantity ({$requestedQty}) exceeds available quantity ({$trueAvailable}). Some units may be checked out or reserved."
                );
            }

            $transfer = TransferRequest::create([
                'resource_id' => $lot->resource_id,
                'inventory_lot_id' => $lot->id,
                'from_department_id' => $data['from_department_id'],
                'to_department_id' => $data['to_department_id'],
                'initiated_by' => $initiator->id,
                'status' => 'pending',
                'quantity' => $data['quantity'] ?? 1,
                'reason' => $data['reason'] ?? null,
                'idempotency_key' => $data['idempotency_key'],
            ]);

            // Create source hold custody record
            CustodyRecord::create([
                'transfer_request_id' => $transfer->id,
                'inventory_lot_id' => $lot->id,
                'department_id' => $data['from_department_id'],
                'custody_type' => 'source_hold',
                'custodian_id' => $initiator->id,
                'started_at' => now(),
            ]);

            AuditLog::create([
                'user_id' => $initiator->id,
                'action' => 'transfer_initiated',
                'auditable_type' => TransferRequest::class,
                'auditable_id' => $transfer->id,
            ]);

            return $transfer;
        });
    }

    public function approveTransfer(TransferRequest $transfer, User $approver): TransferRequest
    {
        return DB::transaction(function () use ($transfer, $approver) {
            $transfer = TransferRequest::whereKey($transfer->id)->lockForUpdate()->firstOrFail();

            if ($transfer->status !== 'pending') {
                throw new \App\Common\Exceptions\BusinessRuleException('Transfer is not pending.');
            }

            $transfer->update([
                'status' => 'approved',
                'approved_by' => $approver->id,
            ]);

            AuditLog::create([
                'user_id' => $approver->id,
                'action' => 'transfer_approved',
                'auditable_type' => TransferRequest::class,
                'auditable_id' => $transfer->id,
            ]);

            return $transfer->fresh();
        });
    }

    public function markInTransit(TransferRequest $transfer, User $handler): TransferRequest
    {
        return DB::transaction(function () use ($transfer, $handler) {
            $transfer = TransferRequest::whereKey($transfer->id)->lockForUpdate()->firstOrFail();

            if ($transfer->status !== 'approved') {
                throw new \App\Common\Exceptions\BusinessRuleException('Transfer must be approved first.');
            }

            // End source hold
            CustodyRecord::where('transfer_request_id', $transfer->id)
                ->where('custody_type', 'source_hold')
                ->whereNull('ended_at')
                ->update(['ended_at' => now()]);

            // Create in-transit record
            CustodyRecord::create([
                'transfer_request_id' => $transfer->id,
                'inventory_lot_id' => $transfer->inventory_lot_id,
                'department_id' => $transfer->from_department_id,
                'custody_type' => 'in_transit',
                'custodian_id' => $handler->id,
                'started_at' => now(),
            ]);

            $transfer->update(['status' => 'in_transit']);

            return $transfer->fresh();
        });
    }

    public function completeTransfer(TransferRequest $transfer, User $receiver): TransferRequest
    {
        return DB::transaction(function () use ($transfer, $receiver) {
            $transfer = TransferRequest::whereKey($transfer->id)->lockForUpdate()->firstOrFail();

            if ($transfer->status !== 'in_transit') {
                throw new \App\Common\Exceptions\BusinessRuleException('Transfer must be in transit.');
            }

            // End in-transit custody
            CustodyRecord::where('transfer_request_id', $transfer->id)
                ->where('custody_type', 'in_transit')
                ->whereNull('ended_at')
                ->update(['ended_at' => now()]);

            // Create destination received
            CustodyRecord::create([
                'transfer_request_id' => $transfer->id,
                'inventory_lot_id' => $transfer->inventory_lot_id,
                'department_id' => $transfer->to_department_id,
                'custody_type' => 'destination_received',
                'custodian_id' => $receiver->id,
                'started_at' => now(),
            ]);

            $lot = InventoryLot::where('id', $transfer->inventory_lot_id)->lockForUpdate()->firstOrFail();

            // Revalidate: after completing this transfer, remaining lot must not go negative.
            // getLotAvailableQuantity already deducts this transfer (it's still in_transit),
            // so we add it back to get the "available excluding this transfer" number.
            $availService = app(\App\Domain\Availability\AvailabilityService::class);
            $availableExcludingSelf = $availService->getLotAvailableQuantity($lot) + $transfer->quantity;
            if ($transfer->quantity > $availableExcludingSelf) {
                throw new \App\Common\Exceptions\BusinessRuleException(
                    'Cannot complete transfer: available quantity has changed since initiation.'
                );
            }

            if ($transfer->quantity >= $lot->serviceable_quantity) {
                // Full lot transfer: move the lot to destination department
                $lot->update(['department_id' => $transfer->to_department_id]);
            } else {
                // Partial transfer: reduce source lot, create new lot in destination department
                $lot->decrement('serviceable_quantity', $transfer->quantity);
                $lot->decrement('total_quantity', $transfer->quantity);

                InventoryLot::create([
                    'resource_id' => $lot->resource_id,
                    'department_id' => $transfer->to_department_id,
                    'lot_number' => $lot->lot_number . '-T' . $transfer->id,
                    'total_quantity' => $transfer->quantity,
                    'serviceable_quantity' => $transfer->quantity,
                    'location' => null,
                    'condition' => $lot->condition,
                    'notes' => "Split from lot {$lot->lot_number} via transfer #{$transfer->id}",
                ]);
            }

            // Create handoff record
            DepartmentHandoff::create([
                'transfer_request_id' => $transfer->id,
                'from_custodian_id' => $transfer->initiated_by,
                'to_custodian_id' => $receiver->id,
                'handed_off_at' => now(),
            ]);

            $transfer->update(['status' => 'completed']);

            Log::channel('operations')->info('Transfer completed', [
                'transfer_id' => $transfer->id, 'receiver_id' => $receiver->id,
            ]);

            AuditLog::create([
                'user_id' => $receiver->id,
                'action' => 'transfer_completed',
                'auditable_type' => TransferRequest::class,
                'auditable_id' => $transfer->id,
            ]);

            return $transfer->fresh();
        });
    }

    public function cancelTransfer(TransferRequest $transfer, User $canceller): TransferRequest
    {
        return DB::transaction(function () use ($transfer, $canceller) {
            $transfer = TransferRequest::whereKey($transfer->id)->lockForUpdate()->firstOrFail();

            if (!in_array($transfer->status, ['pending', 'approved'])) {
                throw new \App\Common\Exceptions\BusinessRuleException('Cannot cancel transfer in this state.');
            }

            $transfer->update(['status' => 'cancelled']);

            CustodyRecord::where('transfer_request_id', $transfer->id)
                ->whereNull('ended_at')
                ->update(['ended_at' => now()]);

            AuditLog::create([
                'user_id' => $canceller->id,
                'action' => 'transfer_cancelled',
                'auditable_type' => TransferRequest::class,
                'auditable_id' => $transfer->id,
            ]);

            Log::channel('operations')->info('Transfer cancelled', [
                'transfer_id' => $transfer->id, 'cancelled_by' => $canceller->id,
            ]);

            return $transfer->fresh();
        });
    }
}
