<?php

declare(strict_types=1);

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * En Filament v3 las acciones vivían en Filament\Tables\Actions\*. En v5 se
 * movieron a Filament\Actions\*. Usar el namespace viejo no falla al desplegar:
 * revienta en tiempo de ejecución ("Class not found") cuando el usuario abre la
 * pantalla, y solo en esa pantalla. Este test lo detecta antes.
 */
class FilamentActionNamespaceTest extends TestCase
{
    /** @test */
    public function no_filament_file_uses_the_removed_v3_action_namespace(): void
    {
        $ofensores = [];

        $archivos = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(app_path('Filament'))
        );

        foreach ($archivos as $archivo) {
            if ($archivo->isDir() || $archivo->getExtension() !== 'php') {
                continue;
            }

            $contenido = file_get_contents($archivo->getPathname());

            // Coincide con "Filament\Tables\Actions\Algo" y "Tables\Actions\Algo",
            // pero no con HeaderActionsPosition, que sí sigue en ese namespace.
            if (preg_match('/Tables\\\\Actions\\\\(?!HeaderActionsPosition)\w+/', $contenido, $m)) {
                $ofensores[] = str_replace(app_path() . '/', '', $archivo->getPathname())
                    . ' → ' . $m[0];
            }
        }

        $this->assertSame(
            [],
            $ofensores,
            "Usan el namespace de acciones de Filament v3 (removido en v5).\n"
            . "Reemplazar por Filament\\Actions\\*:\n  " . implode("\n  ", $ofensores)
        );
    }

    /** @test */
    public function the_action_classes_used_by_relation_managers_exist(): void
    {
        $this->assertTrue(class_exists(\Filament\Actions\CreateAction::class));
        $this->assertTrue(class_exists(\Filament\Actions\EditAction::class));
        $this->assertTrue(class_exists(\Filament\Actions\DeleteAction::class));
    }
}
