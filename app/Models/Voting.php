<?php

namespace App\Models;

use App\Enums\QuorumRule;
use App\Enums\VoteType;
use App\Enums\VotingResult;
use App\Enums\VotingType;
use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Voting extends Model
{
    use BelongsToInstitution, SoftDeletes;

    protected $fillable = [
        'institution_id', 'session_id', 'session_agenda_item_id', 'proposition_id',
        'turno', 'type', 'result', 'quorum_rule', 'quorum_min_presence',
        'votes_favor', 'votes_against', 'votes_abstention', 'votes_impeded', 'votes_absent',
        'present_count', 'held_at', 'result_registered_at', 'registered_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'turno' => 'integer',
            'type' => VotingType::class,
            'result' => VotingResult::class,
            'quorum_rule' => QuorumRule::class,
            'quorum_min_presence' => 'integer',
            'votes_favor' => 'integer',
            'votes_against' => 'integer',
            'votes_abstention' => 'integer',
            'votes_impeded' => 'integer',
            'votes_absent' => 'integer',
            'present_count' => 'integer',
            'held_at' => 'datetime',
            'result_registered_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(LegislativeSession::class, 'session_id');
    }

    public function agendaItem(): BelongsTo
    {
        return $this->belongsTo(SessionAgendaItem::class, 'session_agenda_item_id');
    }

    public function proposition(): BelongsTo
    {
        return $this->belongsTo(Proposition::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(VotingRecord::class);
    }

    public function registrar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function isFinalized(): bool
    {
        return $this->result !== null;
    }

    /** @return array{favor:int,contra:int,abstencao:int,impedido:int} */
    public function counts(): array
    {
        return [
            'favor' => $this->votes_favor,
            'contra' => $this->votes_against,
            'abstencao' => $this->votes_abstention,
            'impedido' => $this->votes_impeded,
        ];
    }

    /** Recalcula os totais a partir dos votos individuais registrados. */
    public function recomputeCounts(): void
    {
        $byType = $this->records()
            ->selectRaw('vote, count(*) as total')
            ->groupBy('vote')
            ->pluck('total', 'vote');

        $this->forceFill([
            'votes_favor' => $favor = (int) ($byType[VoteType::FAVORAVEL->value] ?? 0),
            'votes_against' => $against = (int) ($byType[VoteType::CONTRARIO->value] ?? 0),
            'votes_abstention' => $abstention = (int) ($byType[VoteType::ABSTENCAO->value] ?? 0),
            'votes_impeded' => $impeded = (int) ($byType[VoteType::IMPEDIDO->value] ?? 0),
            'votes_absent' => (int) ($byType[VoteType::AUSENTE->value] ?? 0),
            // Presentes = compareceram e votaram (exclui ausentes).
            'present_count' => $favor + $against + $abstention + $impeded,
        ])->save();
    }
}
