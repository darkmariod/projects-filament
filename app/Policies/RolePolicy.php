<?php

namespace App\Policies;

use App\Models\User;

class RolePolicy extends BasePolicy
{
    protected string $resource = 'roles';

    public function assignPermissions(User $user): bool
    {
        return $user->hasPermissionTo('asignar permisos');
    }
}
