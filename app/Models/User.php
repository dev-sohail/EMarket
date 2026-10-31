<?php

namespace App\Models;

use App\Traits\PrefixedId;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, PrefixedId, SoftDeletes;

    protected $table = 'users';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * 'password' and 'email_verified_at' are NOT fillable.
     *
     * Every caller that legitimately writes them does so explicitly:
     * Admin\UserController::store() and Customer\Auth\RegisterController both
     * assign `$user->password = $plaintext` (which the 'hashed' cast
     * hashes on save), and the password-reset flow uses forceFill(). Leaving
     * 'password' here meant any future `$user->update($request->validated())`
     * whose rules happened to include 'password' would be one refactor away from
     * writing whatever the request carried.
     *
     * 'is_active' stays fillable on purpose: Admin\CustomerController::status()
     * and UserController toggle it through mass assignment.
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'avatar',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (User $user) {
            // Detach role pivots
            $user->roles()->detach();

            // Delete associated customer record if it exists (will fire customer's deleting hook to clean all its data)
            if ($user->customer) {
                $user->customer->delete();
            }
        });
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role', 'user_id', 'role_id');
    }

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class, 'user_id', '_id')->withTrashed();
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('name', $role)->exists();
    }

    public function assignRole(string $role): void
    {
        $roleModel = Role::where('name', $role)->first();

        if ($roleModel && ! $this->roles()->whereKey($roleModel->getKey())->exists()) {
            $this->roles()->attach($roleModel);
        }
    }

    public function syncRoles(string $role): void
    {
        $roleModel = Role::where('name', $role)->first();
        if ($roleModel) {
            $this->roles()->sync([$roleModel->getKey()]);
        }
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->contains(fn ($role) => $role->permissions->contains('name', $permission));
        }

        return $this->roles()->whereHas('permissions', fn ($q) => $q->where('name', $permission))->exists();
    }

    protected function getIdPrefix(): string
    {
        return 'usr';
    }
}
