<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateReservationRequestForm extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $rules = [
            'resource_id' => 'required|exists:resources,id',
            'reservation_type' => 'required|in:equipment,venue',
            'notes' => 'nullable|string|max:1000',
            'idempotency_key' => 'required|string|max:255',
            'class_id' => 'nullable|exists:classes,id',
            'assignment_id' => 'nullable|exists:assignments,id',
        ];

        if ($this->input('reservation_type') === 'venue') {
            $rules['venue_id'] = 'required|exists:venues,id';
            $rules['venue_time_slot_id'] = 'required|exists:venue_time_slots,id';
            // start_date/end_date derived from the slot
        } else {
            $rules['start_date'] = 'required|date';
            $rules['end_date'] = 'required|date|after_or_equal:start_date';
        }

        return $rules;
    }
}
