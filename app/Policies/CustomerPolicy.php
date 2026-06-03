<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CustomerPolicy extends BasePolicy
{
    protected string $resource = 'clientes';

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'ver');
    }

    public function view(User $user, Model $model): bool
    {
        return $this->hasPermission($user, 'ver');
    }

    // No 'crear clientes' permission — use 'ver clientes' to preserve current behavior
    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'ver');
    }

    public function update(User $user, Model $model): bool
    {
        return $this->hasPermission($user, 'editar');
    }

    // No 'eliminar clientes' permission — use 'editar clientes' to preserve current behavior
    public function delete(User $user, Model $model): bool
    {
        return $user->hasPermissionTo('editar clientes');
    }
}
