<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    public function toArray($request): array
    {
        $slot = $this->whenLoaded('venueTimeSlot', function () {
            return [
                'id' => $this->venueTimeSlot->id,
                'date' => $this->venueTimeSlot->date,
                'start_time' => $this->venueTimeSlot->start_time,
                'end_time' => $this->venueTimeSlot->end_time,
            ];
        });

        return [
            'id' => $this->id,
            'user' => new UserResource($this->whenLoaded('user')),
            'user_id' => $this->user_id,
            'resource' => new ResourceResource($this->whenLoaded('resource')),
            'resource_id' => $this->resource_id,
            'venue' => $this->whenLoaded('venue'),
            'venue_time_slot' => $slot,
            'reservation_type' => $this->reservation_type,
            'type' => $this->reservation_type,
            'status' => $this->status,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'notes' => $this->notes,
            'approval' => $this->whenLoaded('approval'),
            'created_at' => $this->created_at,
        ];
    }
}
