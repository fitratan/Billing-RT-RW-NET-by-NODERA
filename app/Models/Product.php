<?php

namespace App\Models;

use App\Models\Traits\TenantAware;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

    protected $table = 'products';

    protected $fillable = [
        'tenant_id',
        'category_id',
        'name',
        'slug',
        'sku',
        'product_type',
        'voucher_package_id',
        'price',
        'original_price',
        'stock',
        'badge',
        'image',
        'gallery',
        'short_description',
        'description',
        'specifications',
        'weight_gram',
        'is_featured',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'float',
        'original_price' => 'float',
        'stock' => 'integer',
        'weight_gram' => 'integer',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'gallery' => 'array',
    ];

    protected $appends = [
        'formatted_price',
        'formatted_original_price',
        'discount_percent',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($product) {
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function voucherPackage(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'voucher_package_id');
    }

    public function scopeForTenant($query, $tenantId = null)
    {
        if ($tenantId !== null) {
            return $query->where('tenant_id', $tenantId);
        }
        return $query;
    }

    public function getFormattedPriceAttribute(): string
    {
        return 'Rp ' . number_format($this->price, 0, ',', '.');
    }

    public function getFormattedOriginalPriceAttribute(): ?string
    {
        if (!$this->original_price || $this->original_price <= $this->price) {
            return null;
        }
        return 'Rp ' . number_format($this->original_price, 0, ',', '.');
    }

    public function getDiscountPercentAttribute(): ?int
    {
        if ($this->original_price && $this->original_price > $this->price) {
            return (int) round((($this->original_price - $this->price) / $this->original_price) * 100);
        }
        return null;
    }
}
