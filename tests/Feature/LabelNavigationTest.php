<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\LabelBatchResource;
use App\Filament\Resources\LabelLogResource;
use App\Filament\Resources\LabelResource;
use Tests\TestCase;

class LabelNavigationTest extends TestCase
{
    /** @test */
    public function it_hides_the_duplicated_labels_module(): void
    {
        // "Etiquetas" duplicaba a "Crear etiquetas": las etiquetas individuales
        // se consultan dentro de cada lote, no como módulo aparte.
        $this->assertFalse(
            LabelResource::shouldRegisterNavigation(),
            'LabelResource debe estar oculta — se consulta dentro del lote'
        );

        $this->assertTrue(
            LabelBatchResource::shouldRegisterNavigation(),
            'Crear etiquetas debe seguir visible'
        );

        $this->assertTrue(
            LabelLogResource::shouldRegisterNavigation(),
            'Bitácora de etiquetas debe seguir visible'
        );
    }

    /** @test */
    public function labels_remain_reachable_inside_each_batch(): void
    {
        // Ocultar el módulo no debe dejar las etiquetas inaccesibles.
        $this->assertContains(
            LabelBatchResource\RelationManagers\LabelsRelationManager::class,
            LabelBatchResource::getRelations(),
            'Las etiquetas deben seguir accesibles dentro del lote'
        );
    }

    /** @test */
    public function labels_group_is_ordered_create_then_log(): void
    {
        $this->assertSame('Crear etiquetas', $this->navLabel(LabelBatchResource::class));
        $this->assertSame('Bitácora de etiquetas', $this->navLabel(LabelLogResource::class));

        $this->assertLessThan(
            $this->navSort(LabelLogResource::class),
            $this->navSort(LabelBatchResource::class),
            'Crear etiquetas debe ir antes que Bitácora'
        );
    }

    private function navLabel(string $resourceClass): ?string
    {
        $property = (new \ReflectionClass($resourceClass))->getProperty('navigationLabel');
        $property->setAccessible(true);

        return $property->getValue();
    }

    private function navSort(string $resourceClass): ?int
    {
        $property = (new \ReflectionClass($resourceClass))->getProperty('navigationSort');
        $property->setAccessible(true);

        return $property->getValue();
    }
}
