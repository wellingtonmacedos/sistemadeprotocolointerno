<?php

namespace App\Models;

use App\Enums\OpinionResult;
use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommissionOpinion extends Model
{
    use BelongsToInstitution, SoftDeletes;

    protected $fillable = [
        'institution_id', 'proposition_id', 'commission_id', 'forwarding_id',
        'rapporteur_councilor_id', 'result', 'text', 'opinion_date',
        'proposition_attachment_id', 'registered_by',
    ];

    protected function casts(): array
    {
        return [
            'result' => OpinionResult::class,
            'opinion_date' => 'date',
        ];
    }

    public function proposition(): BelongsTo
    {
        return $this->belongsTo(Proposition::class);
    }

    public function commission(): BelongsTo
    {
        return $this->belongsTo(Commission::class);
    }

    public function forwarding(): BelongsTo
    {
        return $this->belongsTo(PropositionForwarding::class, 'forwarding_id');
    }

    public function rapporteur(): BelongsTo
    {
        return $this->belongsTo(Councilor::class, 'rapporteur_councilor_id');
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(PropositionAttachment::class, 'proposition_attachment_id');
    }

    public function registrar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }
}
