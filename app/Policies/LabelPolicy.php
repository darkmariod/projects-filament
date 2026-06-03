<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class LabelPolicy extends BasePolicy
{
    protected string $resource = 'etiquetas';

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'ver');
    }

    public function view(User $user, Model $model): bool
    {
        return $this->hasPermission($user, 'ver');
    }

    // No 'crear etiquetas' permission — use 'ver etiquetas' to preserve current behavior
    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'ver');
    }

    // No 'editar etiquetas' permission — use 'ver etiquetas' to preserve current behavior
    public function update(User $user, Model $model): bool
    {
        return $this->hasPermission($user, 'ver');
    }

    // No 'eliminar etiquetas' permission — use 'anular etiquetas'
    public function delete(User $user, Model $model): bool
    {
        return $user->hasPermissionTo('anular etiquetas');
    }

    // Map to 'ver etiquetas' to preserve current behavior (not 'descargar zpl individual')
    public function downloadZpl(User $user, Model $model): bool
    {
        return $user->hasPermissionTo('ver etiquetas');
    }

    // Map to 'ver etiquetas' to preserve current behavior (not 'descargar pdf individual')
    public function downloadPdf(User $user, Model $model): bool
    {
        return $user->hasPermissionTo('ver etiquetas');
    }

    public function annul(User $user, Model $model): bool
    {
        return $user->hasPermissionTo('anular etiquetas');
    }
}
