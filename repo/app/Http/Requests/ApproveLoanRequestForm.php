<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveLoanRequestForm extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:approved,rejected',
            'reason' => 'nullable|string|max:1000',
        ];
    }
}
