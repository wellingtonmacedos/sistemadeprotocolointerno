<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use App\Notifications\ResetPasswordNotification;
use App\Support\Tenant;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use BelongsToInstitution, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'institution_id', 'role_id', 'sector_id', 'councilor_id',
        'name', 'email', 'document', 'phone', 'password',
        'is_active', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (User $user) {
            if ($user->councilor_id !== null) {
                Councilor::syncUserLink($user->id, $user->councilor_id);
            }
        });

        static::updated(function (User $user) {
            if ($user->wasChanged('councilor_id')) {
                Councilor::syncUserLink($user->id, $user->councilor_id);
            }
        });
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function councilor(): BelongsTo
    {
        return $this->belongsTo(Councilor::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role?->slug === 'super_admin';
    }

    /** Envia o e-mail de redefinição de senha (mensagem em português). */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function isCouncilor(): bool
    {
        return $this->role?->slug === 'vereador';
    }

    /**
     * O usuário deve enxergar a "experiência de vereador": tem o perfil
     * "Vereador" ou está vinculado a um registro de vereador (ex.: Presidente).
     */
    public function actsAsCouncilor(): bool
    {
        return $this->isCouncilor() || $this->councilor_id !== null;
    }

    public function hasRole(string ...$slugs): bool
    {
        return in_array($this->role?->slug, $slugs, true);
    }

    public function hasPermission(string $slug): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->role?->permissions->contains('slug', $slug) ?? false;
    }

    public function hasAnyPermission(array $slugs): bool
    {
        foreach ($slugs as $slug) {
            if ($this->hasPermission($slug)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mandatos vigentes do usuário (via vereador vinculado). Base para as
     * permissões de atuação por comissão: membro ativo = já iniciado e ainda
     * não encerrado (end_date nulo ou futuro).
     */
    public function activeCommissionMemberships(): Builder
    {
        if ($this->councilor_id === null) {
            return CommissionMember::query()->whereRaw('1 = 0');
        }

        $today = now()->toDateString();

        return CommissionMember::query()
            ->where('councilor_id', $this->councilor_id)
            ->where('start_date', '<=', $today)
            ->where(fn ($query) => $query->whereNull('end_date')->orWhere('end_date', '>=', $today));
    }

    /** IDs das comissões em que o usuário atua hoje. */
    public function activeCommissionIds(): array
    {
        return $this->activeCommissionMemberships()
            ->pluck('commission_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** IDs das comissões em que o usuário exerce algum dos papéis informados. */
    public function commissionIdsWithRole(array $roles): array
    {
        return $this->activeCommissionMemberships()
            ->whereIn('role', $roles)
            ->pluck('commission_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /** Papéis do usuário em uma comissão (mandato vigente). */
    public function commissionRoles(int $commissionId): array
    {
        return $this->activeCommissionMemberships()
            ->where('commission_id', $commissionId)
            ->pluck('role')
            ->all();
    }

    /** O usuário atua hoje nesta comissão? */
    public function isActiveCommissionMember(int $commissionId): bool
    {
        return in_array($commissionId, $this->activeCommissionIds(), true);
    }

    /** O usuário atua nesta comissão com algum dos papéis informados? */
    public function hasCommissionRole(int $commissionId, array $roles): bool
    {
        return count(array_intersect($this->commissionRoles($commissionId), $roles)) > 0;
    }

    /** Instituições disponíveis para o usuário (todas para o Super Admin). */
    public static function scopeForTenant($query)
    {
        if (Tenant::isBypassed() || Tenant::id() === null) {
            return $query;
        }

        return $query->where('institution_id', Tenant::id());
    }
}
