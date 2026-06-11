<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Category;
use App\Models\ProductModel;
use App\Models\Product;
use App\Models\TechnicalComposition;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportControlCalidad extends Command
{
    protected $signature = 'import:control-calidad
        {file? : Ruta al archivo Excel (default: storage/app/import/control-calidad.xlsx)}';

    protected $description = 'Importa productos desde el Excel de Control de Calidad QR';

    private array $stats = [
        'categories'      => 0,
        'product_models'  => 0,
        'products'        => 0,
        'compositions'    => 0,
        'skipped'         => 0,
        'errors'          => 0,
    ];

    public function handle(): int
    {
        $file = $this->argument('file')
            ?? storage_path('app/import/control-calidad.xlsx');

        if (!file_exists($file)) {
            $this->error("Archivo no encontrado: {$file}");
            $this->line("Subí el Excel a: storage/app/import/control-calidad.xlsx");
            return Command::FAILURE;
        }

        $this->info("📄 Leyendo: {$file}");
        $rows = $this->parseExcel($file);

        if (empty($rows)) {
            $this->error('No se encontraron datos en el Excel.');
            return Command::FAILURE;
        }

        $this->info("📊 {$this->count($rows)} productos encontrados");
        $this->newLine();

        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        foreach ($rows as $row) {
            try {
                $this->importRow($row);
            } catch (\Throwable $e) {
                $this->stats['errors']++;
                $this->warn("\n  ⚠ Error en {$row['codigo']}: {$e->getMessage()}");
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->showSummary();

        return Command::SUCCESS;
    }

    private function parseExcel(string $file): array
    {
        $spreadsheet = IOFactory::load($file);
        $worksheet = $spreadsheet->getActiveSheet();
        $highestRow = $worksheet->getHighestRow();
        $highestCol = $worksheet->getHighestColumn();

        // Read row by row without formula evaluation
        $rows = [];
        for ($rowIdx = 1; $rowIdx <= $highestRow; $rowIdx++) {
            $rowData = [];
            for ($colIdx = 'A'; strcmp($colIdx, $highestCol) <= 0; $colIdx++) {
                $cell = $worksheet->getCell($colIdx . $rowIdx);
                $val = $cell->getValue(); // raw value, no formula evaluation
                // Convert DateTime to string
                if ($val instanceof \DateTimeInterface) {
                    $val = $val->format('Y-m-d');
                }
                $rowData[] = $val;
            }
            $rows[] = $rowData;
        }

        // Find header row (row with 'LINEA')
        $headerIdx = null;
        foreach ($rows as $idx => $row) {
            $first = $row[0] ?? '';
            if (is_string($first) && strtoupper(trim($first)) === 'LINEA') {
                $headerIdx = $idx;
                break;
            }
        }

        if ($headerIdx === null) {
            $this->error('No se encontró la fila de encabezados (LINEA)');
            return [];
        }

        $headers = array_map(fn($v) => is_string($v) ? trim($v) : '', $rows[$headerIdx]);

        // Map columns
        $colMap = $this->mapColumns($headers);

        $data = [];
        for ($i = $headerIdx + 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $code = $this->val($row, $colMap['codigo'] ?? null);
            if (empty($code)) {
                continue; // skip empty rows
            }

            $data[] = [
                'linea'        => $this->val($row, $colMap['linea'] ?? null),
                'nombre'       => $this->val($row, $colMap['nombre'] ?? null),
                'codigo'       => $code,
                'medida'       => $this->val($row, $colMap['medida'] ?? null),
                'referencia'   => $this->val($row, $colMap['referencia'] ?? null),
                'tipo'         => $this->val($row, $colMap['tipo'] ?? null),
                'clase'        => $this->val($row, $colMap['clase'] ?? null),
                'plazas'       => $this->val($row, $colMap['plazas'] ?? null),
                'textil'       => $this->val($row, $colMap['textil'] ?? null),
                'espuma'       => $this->val($row, $colMap['espuma'] ?? null),
                'fecha'        => $this->val($row, $colMap['fecha'] ?? null),
                'lote'         => $this->val($row, $colMap['lote'] ?? null),
                'frase1'       => $this->val($row, $colMap['frase1'] ?? null),
                'frase2'       => $this->val($row, $colMap['frase2'] ?? null),
                'frase3'       => $this->val($row, $colMap['frase3'] ?? null),
                'conservacion' => $this->val($row, $colMap['conservacion'] ?? null),
                'ean'          => $this->val($row, $colMap['ean'] ?? null),
                'unico'        => $this->val($row, $colMap['unico'] ?? null),
                'qr'           => $this->val($row, $colMap['qr'] ?? null),
                'norma'        => $this->val($row, $colMap['norma'] ?? null),
                'operador'     => $this->val($row, $colMap['operador'] ?? null),
            ];
        }

        return $data;
    }

    private function mapColumns(array $headers): array
    {
        $map = [];
        foreach ($headers as $i => $h) {
            $h = mb_strtoupper(trim($h));

            if (str_contains($h, 'LINEA'))                         $map['linea'] = $i;
            elseif (str_contains($h, 'NOMBRE'))                     $map['nombre'] = $i;
            elseif (str_contains($h, 'CODIGO') && !str_contains($h, 'EAN') && !str_contains($h, 'QR') && !str_contains($h, 'VERIF')) $map['codigo'] = $i;
            elseif (str_contains($h, 'MEDIDA'))                     $map['medida'] = $i;
            elseif (str_contains($h, 'REFERENCIA'))                 $map['referencia'] = $i;
            elseif (str_contains($h, 'TIPO'))                       $map['tipo'] = $i;
            elseif (str_contains($h, 'CLASE'))                      $map['clase'] = $i;
            elseif (str_contains($h, 'PLAZAS'))                     $map['plazas'] = $i;
            elseif (str_contains($h, 'TEXTIL'))                     $map['textil'] = $i;
            elseif (str_contains($h, 'ESPUMA'))                     $map['espuma'] = $i;
            elseif (str_contains($h, 'FECHA'))                      $map['fecha'] = $i;
            elseif (str_contains($h, 'LOTE'))                       $map['lote'] = $i;
            elseif (str_contains($h, 'FRASE') && (str_contains($h, '1') || str_contains($h, 'AUTO'))) $map['frase1'] = $i;
            elseif (preg_match('/FRASE\s*2/', $h))                  $map['frase2'] = $i;
            elseif (preg_match('/FRASE\s*3/', $h))                  $map['frase3'] = $i;
            elseif (str_contains($h, 'CONSERV'))                    $map['conservacion'] = $i;
            elseif (str_contains($h, 'EAN'))                        $map['ean'] = $i;
            elseif (str_contains($h, 'UNICO') || str_contains($h, 'VERIF')) $map['unico'] = $i;
            elseif (str_contains($h, 'QR'))                         $map['qr'] = $i;
            elseif (str_contains($h, 'NORMA'))                      $map['norma'] = $i;
            elseif (str_contains($h, 'OPERADOR'))                   $map['operador'] = $i;
        }

        return $map;
    }

    private function importRow(array $row): void
    {
        // --- 1. Category (LINEA) ---
        $category = Category::firstOrCreate(
            ['code' => strtoupper(\Illuminate\Support\Str::slug($row['linea']))],
            [
                'name'        => $row['linea'],
                'description' => "Línea {$row['linea']}",
                'active'      => true,
            ]
        );
        if ($category->wasRecentlyCreated) {
            $this->stats['categories']++;
        }

        // --- 2. ProductModel (NOMBRE + TIPO) ---
        $modelCode = $this->extractModelCode($row['codigo']);
        $productModel = ProductModel::firstOrCreate(
            ['code' => $modelCode],
            [
                'category_id'    => $category->id,
                'name'           => $row['nombre'],
                'type'           => $row['tipo'] ?? null,
                'class'          => null, // class varies by product
                'warranty_years' => 1,
                'active'         => true,
            ]
        );
        if ($productModel->wasRecentlyCreated) {
            $this->stats['product_models']++;
        }

        // --- 3. Product (each row = one variant) ---
        $measurements = $this->parseMeasurements($row['medida']);
        $productName = trim($row['nombre'] . ' ' . ($row['referencia'] ?? ''));

        $product = Product::firstOrCreate(
            ['product_code' => $row['codigo']],
            [
                'product_model_id' => $productModel->id,
                'name'             => $productName,
                'barcode'          => is_numeric($row['ean']) ? (string) $row['ean'] : $row['ean'],
                'width_cm'         => $measurements['width'],
                'length_cm'        => $measurements['length'],
                'height_cm'        => $measurements['height'],
                'measurements_text' => $row['medida'],
                'class'            => $row['clase'] ?? null,
                'plazas'           => $row['plazas'] ?? null,
                'description'      => $row['referencia'] ?? null,
                'active'           => true,
            ]
        );

        if ($product->wasRecentlyCreated) {
            $this->stats['products']++;
        } else {
            $this->stats['skipped']++;
            return; // product already exists, skip composition
        }

        // --- 4. TechnicalComposition ---
        $legalParts = array_filter([
            $row['frase1'] ?? null,
            $row['frase2'] ?? null,
            $row['frase3'] ?? null,
        ]);

        $productionPhrases = array_filter([
            $row['frase2'] ?? null,
            $row['frase3'] ?? null,
        ]);

        TechnicalComposition::create([
            'product_id'               => $product->id,
            'commercial_name'          => $row['nombre'],
            'product_family'           => $row['linea'],
            'cover_material'           => $row['textil'] ?? null,
            'foam_description'         => $row['espuma'] ?? null,
            'general_composition'      => implode("\n\n", $productionPhrases),
            'conservation_instructions' => $row['conservacion'] ?? null,
            'legal_text'               => implode("\n\n", $legalParts),
            'inen_standard'            => $row['norma'] ?? null,
            'manufacturing_country'    => 'Ecuador',
            'manufacturer'             => 'Productos Paraíso del Ecuador C.L',
            'active'                   => true,
        ]);

        $this->stats['compositions']++;
    }

    private function extractModelCode(string $productCode): string
    {
        // "CR SE 080" → "CR SE" (remove last number)
        $parts = explode(' ', trim($productCode));
        if (count($parts) <= 2) {
            return $parts[0];
        }
        // Keep all parts except the last (which is the size code)
        array_pop($parts);
        return implode(' ', $parts);
    }

    private function parseMeasurements(?string $medida): array
    {
        $result = ['width' => null, 'length' => null, 'height' => null];
        if (empty($medida)) return $result;

        // Format: "80X190X22" or "80x190x22"
        $parts = preg_split('/[xX]/', trim($medida));
        if (count($parts) === 3) {
            $result['width']  = (float) trim($parts[0]);
            $result['length'] = (float) trim($parts[1]);
            $result['height'] = (float) trim($parts[2]);
        }

        return $result;
    }

    private function val(array $row, ?int $index): mixed
    {
        if ($index === null || !isset($row[$index])) return null;
        $value = $row[$index];
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '' || $value === '#VALUE!') return null;
        }
        // Convert Excel DateTime to string date
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        return $value;
    }

    protected function count(array $items): int
    {
        return count($items);
    }

    private function showSummary(): void
    {
        $this->table(
            ['Tipo', 'Creados', 'Saltados', 'Errores'],
            [
                ['Categorías',   $this->stats['categories'],     '-', '-'],
                ['Modelos',      $this->stats['product_models'], '-', '-'],
                ['Productos',    $this->stats['products'],       $this->stats['skipped'], '-'],
                ['Composiciones',$this->stats['compositions'],   '-', '-'],
            ]
        );

        if ($this->stats['errors'] > 0) {
            $this->warn("⚠ {$this->stats['errors']} errores durante la importación");
        }

        $this->newLine();
        $this->info('✅ Importación completada');
        $this->line("   Accedé a: http://108.174.152.179:8081/admin");
        $this->line("   → Productos / Modelos de producto / Categorías");
    }
}
