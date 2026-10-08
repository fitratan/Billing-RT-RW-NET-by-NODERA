<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantRequest extends FormRequest
{
    public function rules(): array
    {
        $id = $this->route('id');
        return [
            'name' => 'required|string|max:255',
            'slug' => 'required|alpha_dash|unique:tenants,slug,' . $id,
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'is_active' => 'boolean',
            'expired_at' => 'nullable|date',
        ];
    }
}
