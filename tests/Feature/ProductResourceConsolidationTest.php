<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\CategoryResource;
use App\Filament\Resources\ProductModelResource;
use App\Filament\Resources\TechnicalCompositionResource;
use Tests\TestCase;

class ProductResourceConsolidationTest extends TestCase
{
    /** @test */
    public function it_shows_categories_and_models_but_hides_composition(): void
    {
        // Categorías y Modelos ahora son visibles en el menú "Productos"
        $this->assertFalse(
            $this->getHiddenNavigation(CategoryResource::class),
            'CategoryResource debe ser visible en la navegación'
        );

        $this->assertFalse(
            $this->getHiddenNavigation(ProductModelResource::class),
            'ProductModelResource debe ser visible en la navegación'
        );

        // Composiciones Técnicas se sigue editando dentro del producto → oculta
        $this->assertTrue(
            $this->getHiddenNavigation(TechnicalCompositionResource::class),
            'TechnicalCompositionResource debe seguir oculta de la navegación'
        );
    }

    /** @test */
    public function it_groups_products_resources_under_productos(): void
    {
        $this->assertSame('Productos', $this->getNavigationGroup(CategoryResource::class));
        $this->assertSame('Productos', $this->getNavigationGroup(ProductModelResource::class));
    }

    private function getHiddenNavigation(string $resourceClass): bool
    {
        $reflection = new \ReflectionClass($resourceClass);

        // Si la propiedad ya no existe, el recurso es visible por defecto.
        if (! $reflection->hasProperty('shouldHideNavigation')) {
            return false;
        }

        $property = $reflection->getProperty('shouldHideNavigation');
        $property->setAccessible(true);

        return $property->getValue() === true;
    }

    private function getNavigationGroup(string $resourceClass): ?string
    {
        $reflection = new \ReflectionClass($resourceClass);
        $property = $reflection->getProperty('navigationGroup');
        $property->setAccessible(true);

        return $property->getValue();
    }
}
