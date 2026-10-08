<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePackageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'type' => 'required|in:subscription,pppoe',
            'monthly_price' => 'required|numeric|min:0',
            'max_customers' => 'nullable|integer|min:1',
            'max_routers' => 'nullable|integer|min:0',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ];
    }
}
