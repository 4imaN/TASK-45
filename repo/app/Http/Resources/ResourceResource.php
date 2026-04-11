<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Domain\Availability\AvailabilityService;
use App\Models\MembershipTier;

class ResourceResource extends JsonResource
{
    public function toArray($request): array
    {
        $model = $this->resource;
        $availableQuantity = null;

        if ($model->relationLoaded('inventoryLots')) {
            $availability = app(AvailabilityService::class);
            $availableQuantity = $availability->getAvailableQuantity($model);
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'resource_type' => $this->resource_type,
            'type' => $this->resource_type, // alias for frontend
            'category' => $this->category,
            'subcategory' => $this->subcategory,
            'department' => $this->whenLoaded('department', fn() => $this->department?->name),
            'department_id' => $this->department_id,
            'vendor' => $this->vendor,
            'manufacturer' => $this->manufacturer,
            'model_number' => $this->model_number,
            'status' => $this->status,
            'tags' => $this->tags,
            'available_quantity' => $availableQuantity,
            'loan_rules' => [
                'max_renewals' => AvailabilityService::DEFAULT_MAX_RENEWALS,
                'max_loan_days' => AvailabilityService::DEFAULT_LOAN_DAYS,
            ],
            'created_at' => $this->created_at,
        ];
    }
}
