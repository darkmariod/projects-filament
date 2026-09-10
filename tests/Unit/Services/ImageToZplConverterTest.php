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

        // Cabecera ^GFA: ^GFA,totalBytes,totalBytes,bytesPorFila,hexData
        // El cuarto campo es BYTES POR FILA, no la altura.
        $parts = explode(',', $result);
        $this->assertGreaterThanOrEqual(5, count($parts), 'GFA should have at least 5 comma-separated parts');
        $this->assertSame('^GFA', $parts[0]);

        $totalBytes = (int) $parts[1];
        $rowBytes   = (int) $parts[3];
        $this->assertGreaterThan(0, $totalBytes);
        $this->assertGreaterThan(0, $rowBytes);

        // Hex data should be present
        $hexData = $parts[4] ?? '';
        $this->assertNotEmpty($hexData);
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/i', $hexData);
    }

    /**
     * El cuarto campo de ^GFA son los bytes por fila. Enviar ahi la altura hace
     * que la impresora reinterprete el ancho: una imagen de 288x120 se dibuja
     * como 960x36 y se sale de una etiqueta de 760 puntos, sin dar ningun error.
     *
     * @test
     */
    public function gfa_row_bytes_field_describes_the_width_not_the_height(): void
    {
        // 100x40 entra dentro de los limites (320x120), asi que no se redimensiona.
        $relative = 'products/gfa-row-bytes.png';
        $absolute = storage_path("app/public/{$relative}");
        @mkdir(dirname($absolute), 0755, true);

        $img = imagecreatetruecolor(100, 40);
        imagefilledrectangle($img, 0, 0, 99, 39, imagecolorallocate($img, 255, 255, 255));
        imagefilledrectangle($img, 10, 10, 30, 30, imagecolorallocate($img, 0, 0, 0));
        imagepng($img, $absolute);
        imagedestroy($img);

        $result = (new ImageToZplConverter())->convert($relative);
        @unlink($absolute);

        $this->assertIsString($result);

        $parts      = explode(',', $result);
        $totalBytes = (int) $parts[1];
        $rowBytes   = (int) $parts[3];

        $this->assertSame(13, $rowBytes, 'Una imagen de 100 px de ancho ocupa ceil(100/8) = 13 bytes por fila');
        $this->assertSame(13 * 40, $totalBytes, 'El total son bytes por fila x altura');
        $this->assertSame(40, intdiv($totalBytes, $rowBytes), 'totalBytes / bytesPorFila debe devolver la altura original');
    }

    /**
     * Cada byte del grafico se transmite como dos digitos hexadecimales. Sin el
     * relleno, dechex() devuelve un solo caracter para los valores menores a 16
     * y el resto de la imagen queda desplazado, sin que la impresora avise.
     *
     * @test
     */
    public function every_graphic_byte_is_encoded_as_two_hex_digits(): void
    {
        // Mitad negra y mitad blanca: garantiza bytes 0x00 (un digito sin relleno)
        // y bytes 0xFF (dos digitos) en la misma imagen.
        $relative = 'products/gfa-hex-padding.png';
        $absolute = storage_path("app/public/{$relative}");
        @mkdir(dirname($absolute), 0755, true);

        $img = imagecreatetruecolor(64, 10);
        imagefilledrectangle($img, 0, 0, 31, 9, imagecolorallocate($img, 0, 0, 0));
        imagefilledrectangle($img, 32, 0, 63, 9, imagecolorallocate($img, 255, 255, 255));
        imagepng($img, $absolute);
        imagedestroy($img);

        $result = (new ImageToZplConverter())->convert($relative);
        @unlink($absolute);

        $this->assertIsString($result);

        $parts      = explode(',', $result);
        $totalBytes = (int) $parts[1];
        $hexData    = $parts[4];

        $this->assertSame(
            $totalBytes * 2,
            strlen($hexData),
            'El bloque hex debe medir exactamente dos caracteres por byte declarado'
        );
    }
}
