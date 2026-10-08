<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateInvoiceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'tenant_id' => 'required|exists:tenants,id',
            'period' => 'required|string|max:7',
            'amount' => 'required|numeric|min:0',
            'due_date' => 'required|date',
        ];
    }
}
