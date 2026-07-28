<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\ProductModel;
use App\Models\Product;
use App\Models\TechnicalComposition;
use App\Models\ZebraPrintSetting;

class DemoDataSeeder extends Seeder
{
    private string $legalTextFrase2 = 'NO DESPRENDER ESTA ETIQUETA';
    private string $legalTextFrase3 = 'ETIQUETA ELABORADA 100% CON MATERIAL RECICLADO POS CONSUMO. COMPROMETIDOS CON EL MEDIO AMBIENTE.';

    public function run(): void
    {
        // ── CATEGORÍA ─────────────────────────────────────────────────────
        $categoria = Category::firstOrCreate(
            ['code' => 'FLEX'],
            [
                'name'        => 'Flex',
                'description' => 'Línea FLEX — Colchones de resortes',
                'active'      => true,
            ]
        );

        // ── MODELO ────────────────────────────────────────────────────────
        $modelo = ProductModel::firstOrCreate(
            ['code' => 'SENORIAL'],
            [
                'category_id'    => $categoria->id,
                'name'           => 'COL. SEÑORIAL',
                'type'           => 'Tipo IV: COL. RES',
                'warranty_years' => 10,
                'active'         => true,
            ]
        );

        // ── PRODUCTOS ─────────────────────────────────────────────────────
        $productos = [
            [
                'product_code'      => 'CR SE 080',
                'name'              => 'COL. SEÑORIAL',
                'barcode'           => '7861191234260',
                'measurements_text' => '80 x 190 cm',
                'width_cm'          => 80,
                'length_cm'         => 190,
                'height_cm'         => 22,
                'class'             => 'Clase A',
                'plazas'            => '1 PLZ',
            ],
            [
                'product_code'      => 'CR SE 090',
                'name'              => 'COL. SEÑORIAL',
                'barcode'           => '7861191234261',
                'measurements_text' => '90 x 190 cm',
                'width_cm'          => 90,
                'length_cm'         => 190,
                'height_cm'         => 22,
                'class'             => 'Clase B',
                'plazas'            => '1 1/4 PLZ',
            ],
            [
                'product_code'      => 'CR SE 105',
                'name'              => 'COL. SEÑORIAL',
                'barcode'           => '7861191234262',
                'measurements_text' => '105 x 190 cm',
                'width_cm'          => 105,
                'length_cm'         => 190,
                'height_cm'         => 22,
                'class'             => 'Clase C',
                'plazas'            => '1 1/2 PLZ',
            ],
            [
                'product_code'      => 'CR SE 135',
                'name'              => 'COL. SEÑORIAL',
                'barcode'           => '7861191234263',
                'measurements_text' => '135 x 190 cm',
                'width_cm'          => 135,
                'length_cm'         => 190,
                'height_cm'         => 22,
                'class'             => 'Clase D',
                'plazas'            => '2 PLZ',
            ],
            [
                'product_code'      => 'CR SE 160',
                'name'              => 'COL. SEÑORIAL',
                'barcode'           => '7861191234264',
                'measurements_text' => '160 x 200 cm',
                'width_cm'          => 160,
                'length_cm'         => 200,
                'height_cm'         => 22,
                'class'             => 'Clase E',
                'plazas'            => '2 1/2 PLZ',
            ],
            [
                'product_code'      => 'CR SE 200',
                'name'              => 'COL. SEÑORIAL',
                'barcode'           => '7861191234265',
                'measurements_text' => '200 x 200 cm',
                'width_cm'          => 200,
                'length_cm'         => 200,
                'height_cm'         => 22,
                'class'             => 'Clase F',
                'plazas'            => '3 PLZ',
            ],
        ];

        foreach ($productos as $datos) {
            $producto = Product::firstOrCreate(
                ['product_code' => $datos['product_code']],
                [
                    'product_model_id'  => $modelo->id,
                    'name'              => $datos['name'],
                    'barcode'           => $datos['barcode'],
                    'measurements_text' => $datos['measurements_text'],
                    'width_cm'          => $datos['width_cm'],
                    'length_cm'         => $datos['length_cm'],
                    'height_cm'         => $datos['height_cm'],
                    'class'             => $datos['class'],
                    'plazas'            => $datos['plazas'],
                    'active'            => true,
                ]
            );

            // Composición compartida para todos los productos FLEX Señorial
            TechnicalComposition::firstOrCreate(
                ['product_id' => $producto->id],
                [
                    'commercial_name'           => 'COL. SEÑORIAL ' . $datos['product_code'],
                    'product_family'            => 'Colchón de Resortes',
                    'cover_material'            => 'TAPA: 100% POLIÉSTER  BANDA: 100% POLIÉSTER',
                    'springs'                   => null,
                    'foam_description'          => '1 LAMINAD 23 kg/m3 - 2,0 cm   2 LAMINAS DE 12 kg/m3-1.2 cm',
                    'support_material'          => null,
                    'general_composition'       => 'TAPA: 100% POLIÉSTER. ESPUMA: 1 LAMINAD 23 kg/m3 - 2,0 cm   2 LAMINAS DE 12 kg/m3-1.2 cm',
                    'conservation_instructions' => 'LIMPIEZA SOLO CON ASPIRADORA',
                    'legal_text'                => $this->legalTextFrase2 . "\n---\n" . $this->legalTextFrase3,
                    'inen_standard'             => 'NTE INEN 2035',
                    'manufacturing_country'     => 'Ecuador',
                    'manufacturer'              => 'PRODUCTOS PARAISO DEL ECUADOR C.L',
                    'manufacturer_ruc'          => '1790098230001',
                    'manufacturer_address'      => 'AV. PANAMERICANA SUR, KM 25 TAMBILLO-ECUADOR',
                    'website'                   => 'www.paraiso.com.ec',
                    'active'                    => true,
                ]
            );
        }

        // ── CONFIGURACIÓN ZEBRA ───────────────────────────────────────────
        ZebraPrintSetting::firstOrCreate(
            ['name' => 'Zebra ZT411 - Etiqueta Señorial'],
            [
                'connection_type'  => 'network',
                'printer_name'     => 'Zebra_ZT411',
                'printer_model'    => 'Zebra ZT411',
                'dpi'              => 203,
                'label_width_mm'   => 95.00,
                'label_height_mm'  => 200.00,
                'label_gap_mm'     => 3.00,
                'width_dots'       => 760,
                'height_dots'      => 1600,
                'margin_x'         => 20,
                'margin_y'         => 10,
                'qr_size'          => 5,
                'barcode_height'   => 75,
                'printer_ip'       => '192.168.1.200',
                'printer_port'     => 9100,
                'chunk_size'       => 500,
                'active'           => true,
                'show_logo'        => true,
            ]
        );

        $this->command->info('Datos reales de Productos Paraíso cargados correctamente.');
    }
};
