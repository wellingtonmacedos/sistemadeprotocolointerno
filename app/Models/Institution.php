<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Institution extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'cnpj', 'address', 'city', 'state', 'zip',
        'phone', 'email', 'logo_path', 'settings', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function sectors(): HasMany
    {
        return $this->hasMany(Sector::class);
    }

    public function councilors(): HasMany
    {
        return $this->hasMany(Councilor::class);
    }

    public function protocols(): HasMany
    {
        return $this->hasMany(Protocol::class);
    }

    public function propositions(): HasMany
    {
        return $this->hasMany(Proposition::class);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }
}
