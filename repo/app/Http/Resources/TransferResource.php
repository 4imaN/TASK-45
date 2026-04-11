<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\UserResource;

class TransferResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'resource' => $this->whenLoaded('resource'),
            'inventory_lot_id' => $this->inventory_lot_id,
            'from_department' => $this->whenLoaded('fromDepartment'),
            'to_department' => $this->whenLoaded('toDepartment'),
            'from_department_id' => $this->from_department_id,
            'to_department_id' => $this->to_department_id,
            'status' => $this->status,
            'quantity' => $this->quantity,
            'reason' => $this->reason,
            'initiated_by' => $this->whenLoaded('initiatedBy', fn() => new UserResource($this->initiatedBy)),
            'created_at' => $this->created_at,
        ];
    }
}
