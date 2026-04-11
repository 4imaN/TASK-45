<?php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LoanRequestResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'user' => new UserResource($this->whenLoaded('user')),
            'resource' => new ResourceResource($this->whenLoaded('resource')),
            'quantity' => $this->quantity,
            'status' => $this->status,
            'requested_at' => $this->requested_at,
            'due_date' => $this->due_date,
            'notes' => $this->notes,
            'approval' => $this->whenLoaded('approval'),
            'checkout' => new CheckoutResource($this->whenLoaded('checkout')),
            'created_at' => $this->created_at,
        ];
    }
}
