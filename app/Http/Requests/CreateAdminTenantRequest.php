<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateAdminTenantRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'username' => 'nullable|string|max:50|alpha_dash|unique:users,username',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'tenant_id' => 'required|exists:tenants,id',
            'role' => 'required|in:admin,technician,collector',
        ];
    }
}
