<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'resource_id' => 'required|exists:resources,id',
            'quantity' => 'integer|min:1|max:10',
            'notes' => 'nullable|string|max:1000',
            'idempotency_key' => 'required|string|max:255',
            'class_id' => 'nullable|exists:classes,id',
            'assignment_id' => 'nullable|exists:assignments,id',
        ];
    }
}
