<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

abstract class BasePolicy
{
    protected string $resource;

    public function before(User $user): ?bool
    {
        return null;
    }

    protected function hasPermission(User $user, string $action): bool
    {
        return $user->hasPermissionTo("{$action} {$this->resource}");
    }

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'ver');
    }

    public function view(User $user, Model $model): bool
    {
        return $this->hasPermission($user, 'ver');
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'crear');
    }

    public function update(User $user, Model $model): bool
    {
        return $this->hasPermission($user, 'editar');
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->hasPermission($user, 'eliminar');
    }
}
