<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Versão de um fluxo legislativo. Uma vez publicada, é imutável:
 * alterações geram nova versão, preservando a referência das proposições.
 */
class LegislativeFlowVersion extends Model
{
    use BelongsToInstitution;

    protected $fillable = [
        'institution_id', 'legislative_flow_id', 'version', 'status',
        'is_current', 'notes', 'published_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'published_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(LegislativeFlow::class, 'legislative_flow_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(LegislativeFlowStep::class)->orderBy('position');
    }

    public function isPublished(): bool
    {
        return $this->status === 'publicado';
    }
}
