<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'description' => 'required|string|max:500',
            'amount' => 'required|numeric|min:0',
            'category' => 'required|string|max:100',
            'date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
