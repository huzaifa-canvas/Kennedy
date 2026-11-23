<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class BillCategory extends Model
{
    protected $fillable = [
        'name', 'slug', 'icon', 'icon_image', 'color', 'is_quick_pick', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_quick_pick' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    /** Full URL of the uploaded icon image (admin panel), or null when the Tabler icon is used. */
    public function getIconUrlAttribute(): ?string
    {
        return $this->icon_image ? Storage::disk('public')->url($this->icon_image) : null;
    }

    /**
     * The icon image path without the host, e.g. "storage/category-icons/abc.png".
     * The API sends this; the mobile app prefixes its own base URL.
     */
    public function getIconPathAttribute(): ?string
    {
        return $this->icon_image ? 'storage/' . ltrim($this->icon_image, '/') : null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
