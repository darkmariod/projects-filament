<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class WarrantyPolicy extends BasePolicy
{
    protected string $resource = 'garantias';

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'ver');
    }

    public function view(User $user, Model $model): bool
    {
        return $this->hasPermission($user, 'ver');
    }

    // No 'crear garantias' permission — use 'ver garantias' to preserve current behavior
    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'ver');
    }

    // No 'editar garantias' permission — use 'ver garantias' to preserve current behavior
    public function update(User $user, Model $model): bool
    {
        return $this->hasPermission($user, 'ver');
    }

    // No 'eliminar garantias' permission — use 'anular garantias'
    public function delete(User $user, Model $model): bool
    {
        return $user->hasPermissionTo('anular garantias');
    }

    public function downloadCertificate(User $user, Model $model): bool
    {
        return $user->hasPermissionTo('descargar certificado');
    }

    public function exportExcel(User $user): bool
    {
        return $user->hasPermissionTo('exportar garantias');
    }

    public function annul(User $user, Model $model): bool
    {
        return $user->hasPermissionTo('anular garantias');
    }
}
