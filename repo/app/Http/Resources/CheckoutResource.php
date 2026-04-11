<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\LoanRequestResource;

class CheckoutResource extends JsonResource
{
    public function toArray($request): array
    {
        $model = $this->resource;

        return [
            'id' => $this->id,
            'loan_request_id' => $this->loan_request_id,
            'checked_out_at' => $this->checked_out_at,
            'due_date' => $this->due_date,
            'returned_at' => $this->returned_at,
            'is_overdue' => method_exists($model, 'isOverdue') ? $model->isOverdue() : false,
            'quantity' => $this->quantity,
            'condition_at_checkout' => $this->condition_at_checkout,
            'condition_at_return' => $this->condition_at_return,
            'renewal_count' => $model->renewals()->count(),
            'checked_out_to' => new UserResource($this->whenLoaded('checkedOutTo')),
            'checked_out_by' => new UserResource($this->whenLoaded('checkedOutBy')),
            'loan_request' => new LoanRequestResource($this->whenLoaded('loanRequest')),
            'checkin' => $this->whenLoaded('checkin'),
            'renewals' => $this->whenLoaded('renewals'),
        ];
    }
}
