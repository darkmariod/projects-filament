<?php

namespace App\Policies;

use App\Models\User;

class LabelLogPolicy extends BasePolicy
{
    protected string $resource = 'bitacora';

    // Available for future use — no UI consumer yet
    public function querySerials(User $user): bool
    {
        return $user->hasPermissionTo('consultar seriales');
    }
}
