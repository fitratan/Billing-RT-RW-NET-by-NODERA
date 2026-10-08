<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTenantRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'required|alpha_dash|unique:tenants,slug',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:255',
            'package_id' => 'required|exists:packages,id',
            'duration' => 'nullable|integer|in:1,3,6,12',
            'is_active' => 'boolean',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->filled('slug')) {
                $check = \App\Services\SubdomainValidationService::checkAvailability($this->slug);
                if (!$check['available']) {
                    $validator->errors()->add('slug', $check['message']);
                }
            }
        });
    }
}
