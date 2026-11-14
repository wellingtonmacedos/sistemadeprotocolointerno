<?php

namespace App\Models;

use App\Enums\LegislativeStepType;
use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Etapa de uma versão de fluxo (configuração).
 * Distinta de movimentação (execução) — ver ProtocolMovement.
 */
class LegislativeFlowStep extends Model
{
    use BelongsToInstitution;

    protected $fillable = [
        'institution_id', 'legislative_flow_version_id', 'position', 'name', 'description',
        'step_type', 'sector_id', 'commission_id', 'responsible_user_id', 'deadline_days',
        'is_required', 'can_skip', 'requires_document', 'advance_condition', 'allows_return',
        'is_conclusive', 'conclusion_status',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'can_skip' => 'boolean',
            'requires_document' => 'boolean',
            'allows_return' => 'boolean',
            'is_conclusive' => 'boolean',
            'position' => 'integer',
            'deadline_days' => 'integer',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(LegislativeFlowVersion::class, 'legislative_flow_version_id');
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function commission(): BelongsTo
    {
        return $this->belongsTo(Commission::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function typeEnum(): ?LegislativeStepType
    {
        return LegislativeStepType::tryFrom($this->step_type);
    }

    public function typeLabel(): string
    {
        return $this->typeEnum()?->label() ?? $this->step_type;
    }
}
