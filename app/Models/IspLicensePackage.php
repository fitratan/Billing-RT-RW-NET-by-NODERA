<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IspLicensePackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'price',
        'max_routers',
        'max_customers',
        'has_source_code',
        'source_code_url',
        'features',
        'badge_text',
        'is_popular',
        'is_active',
        'sort_order',
        'description',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'max_routers' => 'integer',
        'max_customers' => 'integer',
        'has_source_code' => 'boolean',
        'is_popular' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'features' => 'array',
    ];
}
