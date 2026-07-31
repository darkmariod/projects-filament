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
    public function it_hides_categories_models_and_composition_leaving_only_products(): void
    {
        // Registro único desde el formulario de Producto → Categorías oculta del menú
        $this->assertTrue(
            $this->getHiddenNavigation(CategoryResource::class),
            'CategoryResource debe estar oculta — se registra desde el formulario de Producto'
        );

        // Modelos oculta — se registra desde el formulario de Producto
        $this->assertTrue(
            $this->getHiddenNavigation(ProductModelResource::class),
            'ProductModelResource debe estar oculta — se registra desde el formulario de Producto'
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
        // Verifica el comportamiento REAL de Filament: si shouldRegisterNavigation()
        // devuelve false, el recurso NO aparece en el menú.
        return $resourceClass::shouldRegisterNavigation() === false;
    }

    private function getNavigationGroup(string $resourceClass): ?string
    {
        $reflection = new \ReflectionClass($resourceClass);
        $property = $reflection->getProperty('navigationGroup');
        $property->setAccessible(true);

        return $property->getValue();
    }
}
