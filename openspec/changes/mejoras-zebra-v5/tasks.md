# Tasks: Mejoras al sistema de Garantías/Zebra — 5 items

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 280–380 |
| 400-line budget risk | Medium |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 (bugfixes + consolidation) → PR 2 (import + image) |
| Delivery strategy | ask-on-risk |
| Chain strategy | stacked-to-main |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: Medium

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | Fix barcode bug + reduce serial in stickers + consolidate modules | PR 1 | Low risk; touches Services + Filament Resources only |
| 2 | Excel import + image-to-ZPL converter | PR 2 | Medium risk; new files, GD dependency check; depends on PR 1 for ProductResource |

## Phase 1: Bug Fix — Serial Barcode (serial-aleatorio)

- [x] 1.1 **RED**: Add test `it_sets_barcode_equal_to_serial_in_generateLabelsForBatch` in `tests/Unit/Services/SerialGeneratorServiceTest.php` — assert `$label->barcode === $label->serial` after `generateLabelsForBatch()` (spec: "Batch label barcode equals serial")
- [x] 1.2 **GREEN**: In `app/Services/SerialGeneratorService.php` line 143, change `'barcode' => $product->barcode` to `'barcode' => $item['serial']`
- [x] 1.3 **RED**: Add test `it_uses_serial_as_barcode_not_product_barcode` in `tests/Unit/Services/SerialGeneratorServiceTest.php` — create product with `barcode='0000000000001'`, generate label, assert label barcode ≠ product barcode
- [x] 1.4 **GREEN**: In `app/Filament/Resources/LabelResource/Pages/CreateLabel.php` lines 43-48, replace product barcode fallback with serial-based barcode: `$data['barcode'] = $data['serial'] ?? '';` (remove `Product::find` lookup for barcode)
- [x] 1.5 **REFACTOR**: Verify existing tests pass: `php artisan test tests/Unit/Services/SerialGeneratorServiceTest.php`

## Phase 2: Label Layout — Remove Serial from Stickers (label-layout-optimization)

- [x] 2.1 **RED**: Add test `it_contains_serial_exactly_twice_in_full_zpl` in `tests/Unit/Services/ZebraZplServiceTest.php` — render a label, assert `mb_substr_count($zpl, $serial) === 2`
- [x] 2.2 **GREEN**: In `app/Services/ZebraZplRenderer.php` `buildQualitySticker()`, remove line 80: `$zpl->text($col1, $y, 13, "N°: {$data['serial']}");` — this removes serial from both sticker calls (Ensamble + Cerrador)
- [x] 2.3 **GREEN**: Adjust Y-positions in `buildQualitySticker()` to close the gap left by removed serial line — shift signature fields up by ~20 dots each; update box separator positions in `render()` (lines 48, 51) accordingly
- [x] 2.4 **RED**: Add test `it_renders_serial_in_composition_section` — assert serial appears below Lote field in composition Y-range (≥315); add test `it_renders_serial_in_main_label` — assert `"N°: {serial}"` in main label Y-range (≥730)
- [x] 2.5 **REFACTOR**: Update existing test `it_generates_portrait_label_layout_matching_physical_zpl` to reflect removed serial from stickers and adjusted Y-positions

## Phase 3: Module Consolidation (product-module-consolidation)

- [x] 3.1 **GREEN**: Add `protected static bool $shouldHideNavigation = true;` to `app/Filament/Resources/ProductModelResource.php`
- [x] 3.2 **GREEN**: Add `protected static bool $shouldHideNavigation = true;` to `app/Filament/Resources/CategoryResource.php`
- [x] 3.3 **GREEN**: Add `protected static bool $shouldHideNavigation = true;` to `app/Filament/Resources/TechnicalCompositionResource.php`
- [x] 3.4 **GREEN**: In `app/Filament/Resources/ProductResource.php` `form()`, add `Select::make('productModel.category_id')` with `->createOptionForm()` (name, code fields) to the Datos del producto section
- [x] 3.5 **GREEN**: In `app/Filament/Resources/ProductResource.php` `form()`, update `Select::make('product_model_id')` to include `->createOptionForm()` with category_id Select + name, code, type, class, warranty_years fields (per design.md D4)
- [x] 3.6 **RED**: Add test `it_hides_model_category_composition_from_nav` in `tests/Feature/ProductResourceConsolidationTest.php` — assert `shouldHideNavigation === true` on all three resources
- [x] 3.7 **REFACTOR**: Verify `php artisan test` passes — navigation shows only "Productos" under Configuración

## Phase 4: Excel Import (catalog-excel-import)

- [x] 4.1 **GREEN**: Create `app/Imports/CatalogImport.php` implementing `WithMultipleSheets` with 4 sheet classes (CategoriaSheet, ModeloSheet, ProductoSheet, ComposicionSheet) using find-or-create pattern; dependency order: categories → models → products → compositions
- [x] 4.2 **GREEN**: Create `app/Exports/CatalogTemplateExport.php` implementing `WithMultipleSheets` with 4 template sheets, each with `WithHeadings` and `WithStyles` (bold white headers, dark red fill — matching LabelsExport pattern)
- [x] 4.3 **GREEN**: In `app/Filament/Resources/ProductResource/Pages/ListProducts.php`, add "Importar Catálogo" header action (file upload modal for .xlsx/.xls) and "Descargar Template" header action using `CatalogImport` and `CatalogTemplateExport`
- [x] 4.4 **RED**: Add tests in `tests/Feature/CatalogImportTest.php`: (a) import creates all entity types — use `Excel::fake()`, assert Category/ProductModel/Product/TechnicalComposition counts; (b) re-import is idempotent — import twice, assert no duplicates; (c) template download returns xlsx
- [x] 4.5 **REFACTOR**: Verify `php artisan test` passes including new feature tests

## Phase 5: Image-to-ZPL Converter (product-image-zpl)

- [x] 5.1 **PREREQ**: Verify GD extension: run `php -m | grep -i gd`; if missing, add `php8.2-gd` to Dockerfile (stop if missing and no Dockerfile access)
- [x] 5.2 **GREEN**: Create `app/Services/ImageToZplConverter.php` with `convert(string $relativePath): ?string` method — GD-based monochrome conversion (luminance threshold 128), resize to max 320×120 dots, file-based cache in `storage/app/zpl-cache/`, returns `^GFA` ZPL string or null on failure
- [x] 5.3 **RED**: Add tests in `tests/Unit/Services/ImageToZplConverterTest.php`: (a) converts test PNG to valid `^GFA` string; (b) returns null on missing file; (c) cache hit on second call; (d) returns null on unsupported format
- [x] 5.4 **GREEN**: In `app/Services/ZebraZplRenderer.php` `extractData()`, add `'image' => $product->image ?? null` to returned array
- [x] 5.5 **GREEN**: In `app/Services/ZebraZplRenderer.php` `buildMainLabel()`, replace static logo logic (lines 233-241) with: try `ImageToZplConverter::convert($data['image'])` → use result; on null → fallback to `logoZpl()`; on null → fallback text "PARAISO". Add `use App\Services\ImageToZplConverter;` import
- [x] 5.6 **REFACTOR**: Verify `php artisan test` passes including converter tests; verify existing `PARAISO` text still appears when no product image

## Phase 6: Final Verification

- [ ] 6.1 Run full test suite: `php artisan test` — all tests green
- [ ] 6.2 Verify nav sidebar shows only "Productos" under Configuración (no Modelos, Categorías, Composiciones)
- [ ] 6.3 Verify Label serial `barcode` column stores serial value in both batch and individual creation flows
