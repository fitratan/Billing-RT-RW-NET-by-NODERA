<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ArisanMember extends Model
{
    use HasFactory;

    protected $table = 'arisan_members';

    protected $fillable = [
        'subscription_id',
        'name',
        'phone_number',
        'pin_hash',
        'magic_token',
        'address_notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function subscription()
    {
        return $this->belongsTo(ArisanSubscription::class, 'subscription_id');
    }

    public function groupMembers()
    {
        return $this->hasMany(ArisanGroupMember::class, 'member_id');
    }

    public function groups()
    {
        return $this->belongsToMany(ArisanGroup::class, 'arisan_group_members', 'member_id', 'group_id')
            ->withPivot('slot_number', 'has_won', 'won_period_id')
            ->withTimestamps();
    }

    /**
     * Generate and save a new magic token for 1-click login.
     */
    public function generateMagicToken(): string
    {
        $this->magic_token = Str::random(40);
        $this->save();

        return $this->magic_token;
    }

    /**
     * Format phone number to standard Indonesian international format (628xxx).
     */
    public function getFormattedPhoneAttribute(): string
    {
        $phone = preg_replace('/[^0-9]/', '', (string) $this->phone_number);

        if (str_starts_with($phone, '08')) {
            return '62' . substr($phone, 1);
        }

        if (str_starts_with($phone, '8')) {
            return '62' . $phone;
        }

        return $phone;
    }
}
