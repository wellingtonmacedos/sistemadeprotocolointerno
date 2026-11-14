<?php

namespace App\Models;

use App\Enums\PropositionModality;
use App\Enums\PropositionOrigin;
use App\Enums\PropositionStatus;
use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Proposition extends Model
{
    use BelongsToInstitution, SoftDeletes;

    protected $fillable = [
        'institution_id', 'modality', 'origin', 'number', 'year', 'official_number', 'title',
        'ementa', 'text', 'justification', 'observations', 'author_councilor_id',
        'author_external_name',
        'status', 'submitted_at', 'reviewed_by', 'reviewed_at', 'review_notes',
        'registered_by', 'registered_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'modality' => PropositionModality::class,
            'origin' => PropositionOrigin::class,
            'status' => PropositionStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'registered_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Councilor::class, 'author_councilor_id');
    }

    /**
     * Identificação da autoria: vereador autor ou, nas proposições de origem
     * externa, o nome/cargo informado manualmente.
     */
    public function authorName(): string
    {
        return $this->author?->name ?? $this->author_external_name ?? '—';
    }

    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(Councilor::class, 'proposition_authors')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(PropositionAttachment::class);
    }

    public function forwardings(): HasMany
    {
        return $this->hasMany(PropositionForwarding::class)->latest('forwarded_at');
    }

    public function opinions(): HasMany
    {
        return $this->hasMany(CommissionOpinion::class)->latest('opinion_date');
    }

    public function agendaItems(): HasMany
    {
        return $this->hasMany(SessionAgendaItem::class);
    }

    public function votings(): HasMany
    {
        return $this->hasMany(Voting::class)->latest('held_at');
    }

    public function postVotingActions(): HasMany
    {
        return $this->hasMany(PostVotingAction::class)->latest('performed_at');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function registrar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }
}
