# Design: Mejoras al sistema de Garantías/Zebra — 5 items

## Technical Approach

Five changes ordered by risk (low → high): (1) fix serial duplication bug, (2) remove redundant serial from quality stickers, (3) consolidate Category/Model/Composition into ProductResource, (4) catalog Excel import, (5) product image to ZPL conversion. All changes are additive or refactor-only — zero schema migrations.

## Architecture Decisions

### D1: Random serials replace sequential counter

| Option | Tradeoff | Decision |
|--------|----------|----------|
| `random_int()` 8-digit pool | ~100M combos per prefix; collision retry needed | **Chosen** — spec requirement |
| Sequential with mutex | Predictable but low entropy for batch dedup | Rejected |

**Rationale**: `random_int()` is cryptographically fair. With 10^8 sequences per YYMM+product prefix, collisions are statistically negligible. The existing retry loop (while Label::where exists) already handles the rare case. Add `MAX_RETRIES = 50` with RuntimeException.

### D2: Serial appears exactly 2× in ZPL

| Option | Tradeoff | Decision |
|--------|----------|----------|
| Remove from stickers only | Minimal layout disruption | **Chosen** |
| Remove from composition | Breaks spec (composition must show serial) | Rejected |
| Remove from main label | Breaks spec (main block must show serial) | Rejected |

**Rationale**: Stickers show product code + batch date + lot — enough for QC. Serial redundancy in stickers wastes vertical space. Composition and main label are the canonical locations.

### D3: ImageToZplConverter uses GD (not Imagick)

| Option | Tradeoff | Decision |
|--------|----------|----------|
| GD extension | Already available in PHP 8.2 Docker image | **Chosen** |
| Imagick | More format support but heavier dependency | Rejected |

**Rationale**: GD handles PNG/JPG/WEBP natively via `imagecreatefromstring()`. Monochrome conversion via luminance threshold (`0.299R + 0.587G + 0.114B > 128`). No extra extension needed.

### D4: Consolidation via Filament RelationManager pattern

| Option | Tradeoff | Decision |
|--------|----------|----------|
| Inline sections in ProductResource form | User stays on one page | **Chosen** (with `createOptionForm` for quick-add) |
| Filament RelationManager tabs | More complex, separate pages | Overkill for 3 child entities |
| Hide resources + keep separate pages | Two-step UX | Rejected — defeats consolidation purpose |

**Rationale**: Product form already has manufacturer/composition sections. Add Category as a `Select` with "create new" via `createOptionForm()`. Add ProductModel as `Select` with `createOptionForm()`. Keep `TechnicalComposition` inline in Product edit via `afterStateUpdated` population (existing pattern in CreateProduct.php).

### D5: Catalog import uses `ToCollection` with ordered sheets

| Option | Tradeoff | Decision |
|--------|----------|----------|
| `ToCollection` per sheet (single import class) | One file, clear ordering | **Chosen** |
| Separate import classes per entity | More testable but orchestrator needed | Rejected |
| `ToModel` with upsert | Maatwebsite upsert is limited | Rejected — manual find-or-create more reliable |

**Rationale**: Single `CatalogImport` implementing `WithMultipleSheets` reads sheets in dependency order (categories → models → products → compositions). Each sheet's collection uses find-or-create keyed on business identifiers (name, code, product_code).

## Data Flow

### Serial Generation (Item 1)

```
generateForBatch(batch)
  ├── random_int(0, 99999999) → 8-digit string
  ├── calculateDV(yymm + code + V + sequence) → Luhn check digit
  ├── Label::where('serial', $serial)->exists()? → retry (max 50)
  └── return [{serial, sequence_number}]
```

**Bug fix**: `generateLabelsForBatch()` line 143: change `'barcode' => $product->barcode` → `'barcode' => $item['serial']`. Also fix `CreateLabel::mutateFormDataBeforeCreate()` line 43-48: remove `$product->barcode` fallback, always use serial.

### ZPL Rendering (Items 2 + 5)

```
render(label)
  ├── extractData(label)        // adds 'image' key
  ├── buildQualitySticker ×2    // NO serial line
  ├── buildComposition          // serial present
  └── buildMainLabel
       ├── ImageToZplConverter::convert(product.image)
       │    ├── cache hit → return cached ^GFA
       │    ├── GD convert → cache → return ^GFA
       │    └── fail → return null
       └── fallback: logoZpl() → text 'PARAISO'
```

### Import Flow (Item 4)

```
CatalogImport::collection($rows)
  ├── Sheet 'categorias':    find-or-create Category by name
  ├── Sheet 'modelos':       find-or-create ProductModel by name, resolve category_id
  ├── Sheet 'productos':     find-or-create Product by product_code, resolve model_id
  └── Sheet 'composiciones': find-or-create TechnicalComposition by product_id
```

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `app/Services/SerialGeneratorService.php` | Modify | Switch to `random_int()`, add MAX_RETRIES, fix barcode bug in `generateLabelsForBatch()` |
| `app/Services/ZebraZplRenderer.php` | Modify | Remove serial from `buildQualitySticker()`, adjust Y-positions, add image integration in `buildMainLabel()`, add `'image'` to `extractData()` |
| `app/Services/ImageToZplConverter.php` | Create | GD-based monochrome converter with file-based cache |
| `app/Filament/Resources/ProductResource.php` | Modify | Add Category/Model selects with `createOptionForm()`, add import/template header actions |
| `app/Filament/Resources/CategoryResource.php` | Modify | Add `shouldHideNavigation(): true` to hide from sidebar |
| `app/Filament/Resources/ProductModelResource.php` | Modify | Add `shouldHideNavigation(): true` to hide from sidebar |
| `app/Filament/Resources/TechnicalCompositionResource.php` | Modify | Add `shouldHideNavigation(): true` to hide from sidebar |
| `app/Filament/Resources/LabelResource/Pages/CreateLabel.php` | Modify | Fix barcode fallback to use serial, not product barcode |
| `app/Imports/CatalogImport.php` | Create | Maatwebsite Excel import with 4-sheet processing |
| `app/Exports/CatalogTemplateExport.php` | Create | Downloadable empty template with column headers |

## Interfaces / Contracts

### ImageToZplConverter

```php
class ImageToZplConverter
{
    private const CACHE_DIR = 'app/zpl-cache';
    private const MAX_WIDTH = 320;   // dots, fits right column
    private const MAX_HEIGHT = 120;  // dots
    private const THRESHOLD = 128;   // luminance cutoff for B/W

    /**
     * Convert image file to ^GFA ZPL string.
     * Returns null on missing file, unsupported format, or GD error.
     */
    public function convert(string $relativePath): ?string { }

    // Internal
    private function loadAndResize(string $absolutePath): ?\GdImage { }
    private function toMonochrome(\GdImage $image): string { }  // returns hex rows
    private function buildZplGfa(int $w, int $h, string $hexData): string { }
    private function cachePath(string $relativePath): string { }  // md5-based
}
```

**ZPL ^GFA format**:
```
^GFA,{totalBytes},{rowBytes},{totalRows},{hexData}
```
Where `totalBytes = ceil(width/8) * height`, hex data is row-by-row, 1=black pixel.

### CatalogImport

```php
class CatalogImport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            'categorias'    => new CategoriaSheet(),
            'modelos'       => new ModeloSheet(),
            'productos'     => new ProductoSheet(),
            'composiciones' => new ComposicionSheet(),
        };
    }
}

class CategoriaSheet implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows): void
    {
        // find-or-create by 'name' column
        // upsert: update description, code if changed
    }
}

class ModeloSheet implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows): void
    {
        // resolve category by name → category_id
        // find-or-create by 'name'
    }
}

class ProductoSheet implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows): void
    {
        // resolve model by name → product_model_id
        // find-or-create by 'product_code'
    }
}

class ComposicionSheet implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows): void
    {
        // resolve product by product_code → product_id
        // find-or-create by product_id (1:1 relationship)
    }
}
```

### CatalogTemplateExport

```php
class CatalogTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            'categorias'    => new CategoriaTemplateSheet(),
            'modelos'       => new ModeloTemplateSheet(),
            'productos'     => new ProductoTemplateSheet(),
            'composiciones' => new ComposicionTemplateSheet(),
        ];
    }
}
```

Each template sheet implements `WithHeadings` and `WithStyles` (matching `LabelsExport` pattern — bold white headers on dark red fill).

### ProductResource consolidation changes

```php
// ProductResource::form() — new sections added:

Section::make('Categoría')
    ->schema([
        Forms\Components\Select::make('productModel.category_id')
            ->label('Categoría')
            ->options(Category::pluck('name', 'id'))
            ->searchable()
            ->required()
            ->createOptionForm([
                Forms\Components\TextInput::make('name')->required(),
                Forms\Components\TextInput::make('code')->required(),
            ]),
    ]),

// product_model_id Select updated with createOptionForm:
Forms\Components\Select::make('product_model_id')
    ->label('Modelo')
    ->options(ProductModel::pluck('name', 'id'))
    ->required()
    ->searchable()
    ->createOptionForm([
        Forms\Components\Select::make('category_id')
            ->options(Category::pluck('name', 'id'))
            ->required(),
        Forms\Components\TextInput::make('name')->required(),
        Forms\Components\TextInput::make('code')->required(),
        Forms\Components\TextInput::make('type'),
        Forms\Components\TextInput::make('class'),
        Forms\Components\TextInput::make('warranty_years')->numeric()->default(1),
    ]),

// ListProducts::getHeaderActions() — add import actions:
protected function getHeaderActions(): array
{
    return [
        Actions\CreateAction::make()->label('Crear'),
        Actions\Action::make('importar')
            ->label('Importar Catálogo')
            ->icon('heroicon-o-arrow-up-tray')
            ->form([
                Forms\Components\FileUpload::make('file')
                    ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                          'application/vnd.ms-excel'])
                    ->required(),
            ])
            ->action(function (array $data): void {
                Excel::import(new CatalogImport, $data['file']);
                Notification::make()->success('Catálogo importado correctamente')->send();
            }),
        Actions\Action::make('template')
            ->label('Descargar Template')
            ->icon('heroicon-o-arrow-down-tray')
            ->action(fn() => Excel::download(new CatalogTemplateExport, 'template-catalogo.xlsx')),
    ];
}
```

## Testing Strategy

| Layer | Item | What to Test | Approach |
|-------|------|-------------|----------|
| Unit | Serial | `random_int()` produces 8-digit strings | Mock `Label::where` to simulate collision |
| Unit | Serial | MAX_RETRIES throws RuntimeException | Mock DB to always return true for exists() |
| Unit | Serial | `generateLabelsForBatch` barcode = serial | Assert `Label::barcode === $label->serial` |
| Unit | ZPL | Quality stickers contain no serial | Count `N°:` occurrences in sticker Y-ranges |
| Unit | ZPL | Serial appears exactly 2× in full ZPL | `mb_substr_count($zpl, $serial) === 2` |
| Unit | ZPL | Total height ≤ 1600 dots | Parse `^LL` value |
| Unit | ImageToZplConverter | Valid ^GFA from test PNG | Create GD image, assert starts with `^GFA` |
| Unit | ImageToZplConverter | Null on missing file | Assert `convert('nonexistent.png') === null` |
| Unit | ImageToZplConverter | Cache hit | Call twice, assert no GD call second time |
| Unit | ImageToZplConverter | Null on unsupported format | Mock non-GD-readable file |
| Feature | Import | 4-sheet import creates all entities | Use `Excel::fake()`, assert counts |
| Feature | Import | Re-import is idempotent | Import twice, assert no duplicates |
| Feature | Import | Import with errors skips bad rows | Include invalid rows, assert success count |
| Feature | Import | Template download returns xlsx | Assert response is downloadable |
| Feature | Consolidation | Category hidden from nav | Assert `shouldHideNavigation()` returns true |
| Feature | Consolidation | ProductModel hidden from nav | Assert `shouldHideNavigation()` returns true |
| Feature | Consolidation | TechnicalComposition hidden from nav | Assert `shouldHideNavigation()` returns true |

### Test file locations

```
tests/Unit/Services/SerialGeneratorServiceTest.php   — update existing + add random tests
tests/Unit/Services/ZebraZplServiceTest.php          — update existing for sticker changes
tests/Unit/Services/ImageToZplConverterTest.php      — NEW
tests/Feature/CatalogImportTest.php                  — NEW
tests/Feature/ProductResourceConsolidationTest.php   — NEW
```

## Migration / Rollout

**No database migrations required.** All changes are:
- Service logic modifications (serial, ZPL)
- New service class (ImageToZplConverter)
- Filament UI changes (consolidation, import)
- New import/export classes

**Rollback**: Each item is a revertable git commit. Feature flag `PRODUCT_IMAGE_ZPL=false` (env variable) can disable image conversion in production if GD causes issues.

**Docker prerequisite**: Verify `php -m | grep -i gd` shows GD extension. If missing, install `php8.2-gd` in Dockerfile.

## Open Questions

- [ ] Should `CategoryResource`, `ProductModelResource`, `TechnicalCompositionResource` be deleted entirely or just hidden? Proposal says "Removed" but hiding preserves admin access for edge cases.
- [ ] Should the import process wrap all 4 sheets in a single DB transaction? (Proposal says "rollback en errores críticos" — clarify what's critical)
- [ ] Product image aspect ratio: should `ImageToZplConverter` preserve aspect ratio or stretch to 320×120?
