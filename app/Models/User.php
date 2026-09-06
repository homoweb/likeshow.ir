<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $is_active
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /** @var HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Whether the account is allowed to log in and act.
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Persist a checkout draft (product + quantity + target) in the session
     * so it survives the login round-trip.
     *
     * @param  array<string, mixed>  $payload
     */
    public function startCheckoutDraft(array $payload): void
    {
        session()->put('checkout_draft', $payload);
    }

    /**
     * Fetch and clear the checkout draft, if any.
     *
     * @return array<string, mixed>|null
     */
    public function takeCheckoutDraft(): ?array
    {
        /** @var array<string, mixed>|null $draft */
        $draft = session()->pull('checkout_draft');

        return $draft;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
