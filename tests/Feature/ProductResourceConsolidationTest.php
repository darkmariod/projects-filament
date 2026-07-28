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
    public function it_hides_model_category_composition_from_nav(): void
    {
        // Access the static property via reflection to avoid Filament bootstrapping
        $this->assertTrue(
            $this->getHiddenNavigation(ProductModelResource::class),
            'ProductModelResource should be hidden from navigation'
        );

        $this->assertTrue(
            $this->getHiddenNavigation(CategoryResource::class),
            'CategoryResource should be hidden from navigation'
        );

        $this->assertTrue(
            $this->getHiddenNavigation(TechnicalCompositionResource::class),
            'TechnicalCompositionResource should be hidden from navigation'
        );
    }

    private function getHiddenNavigation(string $resourceClass): bool
    {
        $reflection = new \ReflectionClass($resourceClass);
        $property = $reflection->getProperty('shouldHideNavigation');
        $property->setAccessible(true);

        return $property->getValue() === true;
    }
}
