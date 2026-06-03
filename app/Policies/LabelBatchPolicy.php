<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class LabelBatchPolicy extends BasePolicy
{
    protected string $resource = 'lotes';

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

    // No 'editar lotes' permission exists — use 'ver lotes' to preserve current behavior
    public function update(User $user, Model $model): bool
    {
        return $this->hasPermission($user, 'ver');
    }

    // No 'eliminar lotes' permission — use 'anular lotes'
    public function delete(User $user, Model $model): bool
    {
        return $user->hasPermissionTo('anular lotes');
    }

    public function generateLabels(User $user): bool
    {
        return $user->hasPermissionTo('generar etiquetas');
    }

    // Map to 'ver etiquetas' to preserve current behavior (not 'descargar zpl')
    public function downloadZpl(User $user, Model $model): bool
    {
        return $user->hasPermissionTo('ver etiquetas');
    }

    // Map to 'ver etiquetas' to preserve current behavior (not 'descargar pdf etiquetas')
    public function downloadPdf(User $user, Model $model): bool
    {
        return $user->hasPermissionTo('ver etiquetas');
    }

    public function annul(User $user, Model $model): bool
    {
        return $user->hasPermissionTo('anular lotes');
    }
}
