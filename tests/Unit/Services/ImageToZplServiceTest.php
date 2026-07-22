<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ImageToZplService;
use Tests\TestCase;

class ImageToZplServiceTest extends TestCase
{
    /** @test */
    public function it_encodes_an_image_to_a_gfa_block(): void
    {
        $service = new ImageToZplService();
        $logo    = resource_path('images/paraiso-logo.png');

        if (! is_file($logo)) {
            $this->markTestSkipped('Logo de referencia no disponible.');
        }

        $gfa = $service->encode($logo);

        $this->assertNotNull($gfa);
        $this->assertStringStartsWith('^GFA,', $gfa);
        // Header: ^GFA,{total},{total},{bytesPerRow},{hex}
        $this->assertMatchesRegularExpression('/^\^GFA,\d+,\d+,\d+,[0-9A-F]+$/', $gfa);
    }

    /** @test */
    public function it_returns_null_for_empty_or_missing_image(): void
    {
        $service = new ImageToZplService();

        $this->assertNull($service->forPublicImage(null));
        $this->assertNull($service->forPublicImage(''));
        $this->assertNull($service->forPublicImage('products/no-existe-xyz.png'));
    }
}
