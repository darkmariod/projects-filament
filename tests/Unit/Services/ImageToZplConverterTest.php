<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ImageToZplConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImageToZplConverterTest extends TestCase
{
    use RefreshDatabase;

    private string $testImageRelative;
    private string $testImageAbsolute;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test PNG image in public storage (20×20 px, solid black)
        $this->testImageRelative = 'test-image.png';
        $publicDir = storage_path('app/public');
        if (!is_dir($publicDir)) {
            mkdir($publicDir, 0755, true);
        }

        $this->testImageAbsolute = $publicDir . '/' . $this->testImageRelative;
        $img = imagecreatetruecolor(20, 20);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefilledrectangle($img, 0, 0, 19, 19, $black);
        imagepng($img, $this->testImageAbsolute);
        imagedestroy($img);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testImageAbsolute)) {
            unlink($this->testImageAbsolute);
        }

        // Clean cache directory
        $cacheDir = storage_path('app/zpl-cache');
        if (is_dir($cacheDir)) {
            foreach (glob($cacheDir . '/*.gfa') as $file) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    /** @test */
    public function convert_returns_valid_zpl_string_starting_with_gfa(): void
    {
        $converter = new ImageToZplConverter();
        $result = $converter->convert($this->testImageRelative);

        $this->assertIsString($result);
        $this->assertStringStartsWith('^GFA', $result);
    }

    /** @test */
    public function convert_returns_null_on_missing_file(): void
    {
        $converter = new ImageToZplConverter();
        $result = $converter->convert('nonexistent/image.png');

        $this->assertNull($result);
    }

    /** @test */
    public function convert_handles_non_square_images(): void
    {
        // Create a non-square image (100×40 px) in public storage
        $relativePath = 'test-wide.png';
        $absolutePath = storage_path('app/public/' . $relativePath);
        $img = imagecreatetruecolor(100, 40);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefilledrectangle($img, 0, 0, 99, 39, $white);
        imagefilledrectangle($img, 10, 10, 30, 30, $black);
        imagepng($img, $absolutePath);
        imagedestroy($img);

        $converter = new ImageToZplConverter();
        $result = $converter->convert($relativePath);

        $this->assertIsString($result);
        $this->assertStringStartsWith('^GFA', $result);

        unlink($absolutePath);
    }

    /** @test */
    public function convert_uses_cache_on_second_call(): void
    {
        $converter = new ImageToZplConverter();

        $first = $converter->convert($this->testImageRelative);
        $this->assertIsString($first);

        // Cache directory should now have a file
        $cacheDir = storage_path('app/zpl-cache');
        $cachedFiles = glob($cacheDir . '/*.gfa');
        $this->assertNotEmpty($cachedFiles, 'Cache file should exist after first convert()');

        // Second call should return same content
        $second = $converter->convert($this->testImageRelative);
        $this->assertSame($first, $second);
    }

    /** @test */
    public function convert_returns_null_on_unsupported_format(): void
    {
        // Create a .txt file in public storage
        $relativePath = 'fake-image.txt';
        $absolutePath = storage_path('app/public/' . $relativePath);
        file_put_contents($absolutePath, 'not an image');

        $converter = new ImageToZplConverter();
        $result = $converter->convert($relativePath);

        $this->assertNull($result);

        unlink($absolutePath);
    }

    /** @test */
    public function zpl_output_contains_valid_gfa_structure(): void
    {
        $converter = new ImageToZplConverter();
        $result = $converter->convert($this->testImageRelative);

        $this->assertIsString($result);

        // Parse ^GFA header: ^GFA,totalBytes,totalBytes,height,hexData
        $parts = explode(',', $result);
        $this->assertGreaterThanOrEqual(5, count($parts), 'GFA should have at least 5 comma-separated parts');
        $this->assertSame('^GFA', $parts[0]);

        $totalBytes = (int) $parts[1];
        $height     = (int) $parts[3];
        $this->assertGreaterThan(0, $totalBytes);
        $this->assertGreaterThan(0, $height);

        // Hex data should be present
        $hexData = $parts[4] ?? '';
        $this->assertNotEmpty($hexData);
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/i', $hexData);
    }
}
