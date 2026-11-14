<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sector extends Model
{
    use BelongsToInstitution, SoftDeletes;

    protected $fillable = [
        'institution_id', 'name', 'slug', 'description', 'acronym', 'display_order',
        'responsible_user_id', 'is_active',
        'can_receive', 'can_dispatch', 'can_attach',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'display_order' => 'integer',
            'can_receive' => 'boolean',
            'can_dispatch' => 'boolean',
            'can_attach' => 'boolean',
        ];
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
