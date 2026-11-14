<?php

namespace App\Models;

use App\Enums\SessionStatus;
use App\Enums\SessionType;
use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LegislativeSession extends Model
{
    use BelongsToInstitution, SoftDeletes;

    protected $fillable = [
        'institution_id', 'number', 'type', 'date', 'time', 'location',
        'president_councilor_id', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'type' => SessionType::class,
            'status' => SessionStatus::class,
            'date' => 'date',
        ];
    }

    public function president(): BelongsTo
    {
        return $this->belongsTo(Councilor::class, 'president_councilor_id');
    }

    public function agendaItems(): HasMany
    {
        return $this->hasMany(SessionAgendaItem::class, 'session_id')->orderBy('position');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SessionDocument::class, 'session_id')->latest();
    }

    public function votings(): HasMany
    {
        return $this->hasMany(Voting::class, 'session_id');
    }

    public function nextPosition(): int
    {
        return (int) $this->agendaItems()->max('position') + 1;
    }
}
