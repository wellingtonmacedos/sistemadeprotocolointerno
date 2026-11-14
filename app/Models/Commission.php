<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Commission extends Model
{
    use BelongsToInstitution, SoftDeletes;

    protected $fillable = [
        'institution_id', 'name', 'acronym', 'description', 'competences',
        'type', 'allowed_modalities', 'is_active', 'display_order',
    ];

    protected function casts(): array
    {
        return [
            'allowed_modalities' => 'array',
            'is_active' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    /** Composição completa (histórica). */
    public function members(): HasMany
    {
        return $this->hasMany(CommissionMember::class)->orderBy('start_date');
    }

    /** Composição vigente (sem data de término). */
    public function currentMembers(): HasMany
    {
        return $this->hasMany(CommissionMember::class)->whereNull('end_date');
    }

    /** Mandato vigente de presidente, se houver. */
    public function president(): ?CommissionMember
    {
        return $this->currentMembers()->where('role', 'presidente')->first();
    }

    /** Mandato vigente de vice-presidente, se houver. */
    public function vicePresident(): ?CommissionMember
    {
        return $this->currentMembers()->where('role', 'vice_presidente')->first();
    }

    public function flowSteps(): HasMany
    {
        return $this->hasMany(LegislativeFlowStep::class);
    }

    /** Protocolos atualmente sob responsabilidade desta comissão. */
    public function protocols(): HasMany
    {
        return $this->hasMany(Protocol::class, 'current_commission_id');
    }

    /** Movimentações recebidas pela comissão. */
    public function incomingMovements(): HasMany
    {
        return $this->hasMany(ProtocolMovement::class, 'to_commission_id');
    }

    /** Movimentações enviadas pela comissão. */
    public function outgoingMovements(): HasMany
    {
        return $this->hasMany(ProtocolMovement::class, 'from_commission_id');
    }

    /** Providências registradas nesta comissão. */
    public function actions(): HasMany
    {
        return $this->hasMany(ProtocolAction::class);
    }
}
