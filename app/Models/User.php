<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use SensitiveParameter;

class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'is_active', 'is_platform_admin'];

    protected $attributes = ['is_active' => true, 'is_platform_admin' => false];

    protected $hidden = ['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes'];

    /** @var array<int, Membership|null> */
    protected array $membershipCache = [];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_platform_admin' => 'boolean',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class)->withoutGlobalScopes();
    }

    /** Keanggotaan yang berlaku hari ini di perusahaan tertentu. */
    public function activeMembership(Company|int|null $company): ?Membership
    {
        $companyId = $company instanceof Company ? $company->id : $company;

        if ($companyId === null) {
            return null;
        }

        if (! array_key_exists($companyId, $this->membershipCache)) {
            $membership = Membership::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('user_id', $this->id)
                ->first();

            $this->membershipCache[$companyId] = $membership?->isCurrentlyValid() ? $membership : null;
        }

        return $this->membershipCache[$companyId];
    }

    public function forgetMembershipCache(): void
    {
        $this->membershipCache = [];
    }

    public function roleIn(Company|int|null $company): ?Role
    {
        return $this->activeMembership($company)?->role;
    }

    public function canIn(string $permission, Company|int|null $company): bool
    {
        return $this->is_active && (bool) $this->roleIn($company)?->can($permission);
    }

    // --- Filament -------------------------------------------------------

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return match ($panel->getId()) {
            'platform' => $this->is_platform_admin,
            default => true,
        };
    }

    public function getTenants(Panel $panel): array|Collection
    {
        $ids = $this->memberships()->get()
            ->filter(fn (Membership $membership) => $membership->isCurrentlyValid())
            ->pluck('company_id');

        return Company::query()->whereIn('id', $ids)->where('is_active', true)->orderBy('name')->get();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->is_active && $tenant->is_active && $this->activeMembership($tenant) !== null;
    }

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(#[SensitiveParameter] ?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    public function saveAppAuthenticationRecoveryCodes(#[SensitiveParameter] ?array $codes): void
    {
        $this->app_authentication_recovery_codes = $codes;
        $this->save();
    }
}
