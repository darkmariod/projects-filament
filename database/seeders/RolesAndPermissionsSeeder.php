<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permisos = [
            'ver categorias',
            'crear categorias',
            'editar categorias',
            'eliminar categorias',
            'ver modelos',
            'crear modelos',
            'editar modelos',
            'eliminar modelos',
            'ver productos',
            'crear productos',
            'editar productos',
            'eliminar productos',
            'ver composiciones',
            'crear composiciones',
            'editar composiciones',
            'eliminar composiciones',
            'ver configuracion zebra',
            'crear configuracion zebra',
            'editar configuracion zebra',
            'eliminar configuracion zebra',
            'ver lotes',
            'crear lotes',
            'generar etiquetas',
            'descargar zpl',
            'descargar pdf etiquetas',
            'anular lotes',
            'ver etiquetas',
            'descargar zpl individual',
            'descargar pdf individual',
            'anular etiquetas',
            'ver garantias',
            'anular garantias',
            'descargar certificado',
            'exportar garantias',
            'ver clientes',
            'editar clientes',
            'ver bitacora',
            'consultar seriales',
            'ver usuarios',
            'crear usuarios',
            'editar usuarios',
            'eliminar usuarios',
            'ver roles',
            'crear roles',
            'editar roles',
            'eliminar roles',
            'asignar permisos',
        ];

        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso]);
        }

        $admin = Role::firstOrCreate(['name' => 'administrador']);
        $admin->syncPermissions(Permission::all());

        $produccion = Role::firstOrCreate(['name' => 'produccion']);
        $produccion->syncPermissions([
            'ver categorias',
            'ver modelos',
            'ver productos',
            'ver composiciones',
            'ver lotes',
            'descargar zpl',
            'descargar pdf etiquetas',
            'ver etiquetas',
            'descargar zpl individual',
            'descargar pdf individual',
            'consultar seriales',
        ]);

        $ventas = Role::firstOrCreate(['name' => 'ventas']);
        $ventas->syncPermissions([
            'ver productos',
            'ver etiquetas',
            'ver garantias',
            'ver clientes',
            'descargar certificado',
            'exportar garantias',
            'consultar seriales',
        ]);

        $consulta = Role::firstOrCreate(['name' => 'consulta']);
        $consulta->syncPermissions([
            'ver categorias',
            'ver modelos',
            'ver productos',
            'ver lotes',
            'ver etiquetas',
            'ver garantias',
            'consultar seriales',
        ]);

        $usuarioAdmin = User::firstOrCreate(
            ['email' => 'admin@paraiso.com'],
            [
                'name'     => 'Administrador',
                'password' => Hash::make('password123'),
            ]
        );
        $usuarioAdmin->syncRoles(['administrador']);

        $usuarioProduccion = User::firstOrCreate(
            ['email' => 'produccion@paraiso.com'],
            [
                'name'     => 'Usuario Produccion',
                'password' => Hash::make('password123'),
            ]
        );
        $usuarioProduccion->syncRoles(['produccion']);

        $usuarioVentas = User::firstOrCreate(
            ['email' => 'ventas@paraiso.com'],
            [
                'name'     => 'Usuario Ventas',
                'password' => Hash::make('password123'),
            ]
        );
        $usuarioVentas->syncRoles(['ventas']);

        $usuarioConsulta = User::firstOrCreate(
            ['email' => 'consulta@paraiso.com'],
            [
                'name'     => 'Usuario Consulta',
                'password' => Hash::make('password123'),
            ]
        );
        $usuarioConsulta->syncRoles(['consulta']);

        $this->command->info('Roles, permisos y usuarios creados correctamente.');
    }
}