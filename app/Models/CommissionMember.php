<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Composição histórica de uma comissão: mandato de um vereador (cargo + período).
 */
class CommissionMember extends Model
{
    use BelongsToInstitution, SoftDeletes;

    protected $fillable = [
        'institution_id', 'commission_id', 'councilor_id', 'role',
        'start_date', 'end_date', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function commission(): BelongsTo
    {
        return $this->belongsTo(Commission::class);
    }

    public function councilor(): BelongsTo
    {
        return $this->belongsTo(Councilor::class);
    }

    /** Mandato vigente (sem data de término). */
    public function isCurrent(): bool
    {
        return $this->end_date === null;
    }
}
