<?php

namespace App\Models;

use App\Enums\PropositionModality;
use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Fluxo legislativo configurável por instituição e modalidade.
 * As etapas são definidas nas versões (LegislativeFlowVersion).
 */
class LegislativeFlow extends Model
{
    use BelongsToInstitution, SoftDeletes;

    protected $fillable = ['institution_id', 'name', 'modality', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(LegislativeFlowVersion::class)->orderByDesc('version');
    }

    /** Versão vigente (publicada). */
    public function currentVersion(): ?LegislativeFlowVersion
    {
        return $this->versions()->where('is_current', true)->first();
    }

    public function modalityEnum(): ?PropositionModality
    {
        return PropositionModality::tryFrom($this->modality);
    }

    public function modalityLabel(): string
    {
        return $this->modalityEnum()?->label() ?? $this->modality;
    }
}
