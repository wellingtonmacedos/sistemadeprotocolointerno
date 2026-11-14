<?php

namespace App\Models;

use App\Enums\ForwardingStatus;
use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PropositionForwarding extends Model
{
    use BelongsToInstitution, SoftDeletes;

    protected $fillable = [
        'institution_id', 'proposition_id', 'commission_id', 'status', 'deadline', 'notes',
        'forwarded_by', 'forwarded_at', 'received_by', 'received_at',
        'completed_by', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ForwardingStatus::class,
            'deadline' => 'date',
            'forwarded_at' => 'datetime',
            'received_at' => 'datetime',
            'completed_at' => 'datetime',
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

    public function forwardedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'forwarded_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function opinions(): HasMany
    {
        return $this->hasMany(CommissionOpinion::class, 'forwarding_id');
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }
}
