# catalog-excel-import Specification

## Purpose

Import a complete product catalog (categories, models, products, technical compositions) from an Excel file using Maatwebsite Excel. The import is idempotent — re-importing the same file does not create duplicates.

## Requirements

### Requirement: Excel import class

The system MUST provide a `CatalogImport` class (implementing `Maatwebsite\Excel\Concerns\ToCollection`) that processes an Excel file with sheets for categories, models, products, and compositions.

#### Scenario: Import processes all four entity types

- GIVEN an Excel file with sheets: `categorias`, `modelos`, `productos`, `composiciones`
- WHEN `CatalogImport::collection()` is called
- THEN categories MUST be imported first, then models, then products, then compositions
- AND each sheet's rows MUST be processed in order

#### Scenario: Empty sheet is skipped

- GIVEN the Excel file has an empty `composiciones` sheet
- WHEN the import processes that sheet
- THEN no database operations MUST occur for compositions
- AND the import MUST continue successfully

### Requirement: Import ordering and foreign key resolution

The import MUST process entities in dependency order: categories → models → products → compositions. Each entity MUST reference its parent by a stable lookup key, not by row position.

#### Scenario: Model references category by name

- GIVEN a model row with `categoria = "Matrimonial"`
- WHEN the import processes the model
- THEN it MUST find or create a Category with `name = "Matrimonial"`
- AND the model's `category_id` MUST reference that category

#### Scenario: Product references model by code

- GIVEN a product row with `modelo = "MATREX"`
- WHEN the import processes the product
- THEN it MUST find or create a ProductModel with `name = "MATREX"`
- AND the product's `product_model_id` MUST reference that model

#### Scenario: Composition references product by code

- GIVEN a composition row with `codigo_producto = "MX-001"`
- WHEN the import processes the composition
- THEN it MUST find the Product with `product_code = "MX-001"`
- AND the composition's `product_id` MUST reference that product

### Requirement: Find-or-create pattern (upsert idempotent)

Every entity MUST use a find-or-create pattern keyed on a unique business identifier. Re-importing the same file MUST NOT create duplicate records.

#### Scenario: Re-import does not duplicate categories

- GIVEN an Excel file with category "Matrimonial" already imported
- WHEN the same file is imported again
- THEN only one Category with `name = "Matrimonial"` MUST exist
- AND the category's `id` MUST be the same as before

#### Scenario: Re-import updates existing products

- GIVEN a product `MX-001` with `name = "Matrex Original"` already imported
- WHEN the same file is imported with `name = "Matrex Plus"` for `MX-001`
- THEN the product's `name` MUST be updated to `"Matrex Plus"`
- AND no duplicate product `MX-001` MUST be created

#### Scenario: New entities are created on first import

- GIVEN no Category named "Individual" exists
- WHEN the import processes a row with `categoria = "Individual"`
- THEN a new Category MUST be created with `name = "Individual"`
- AND the category MUST be persisted to the database

### Requirement: Filament header action

The `ProductResource` table MUST include an "Importar Catálogo" header action that opens a file upload modal for Excel import.

#### Scenario: Import action visible in header

- GIVEN the user is on the ProductResource index page
- WHEN viewing the table header actions
- THEN an "Importar Catálogo" action MUST be visible
- AND clicking it MUST open a modal with a file upload field accepting `.xlsx` and `.xls`

#### Scenario: Import action requires file

- GIVEN the user opens the import modal
- WHEN clicking "Importar" without selecting a file
- THEN the form MUST show a validation error
- AND no import MUST be triggered

### Requirement: Import template download

The system MUST provide a downloadable Excel template that users can fill in before importing.

#### Scenario: Template download action

- GIVEN the user is on the ProductResource index page
- WHEN clicking a "Descargar Template" action
- THEN an Excel file MUST be downloaded
- AND the file MUST contain empty sheets: `categorias`, `modelos`, `productos`, `composiciones`
- AND each sheet MUST have column headers matching the import format

### Requirement: Import result feedback

After import completes, the system MUST display a summary of what was imported.

#### Scenario: Successful import notification

- GIVEN an Excel file with 5 categories, 10 models, 50 products, 50 compositions
- WHEN the import completes successfully
- THEN a success notification MUST show: "5 categorías, 10 modelos, 50 productos, 50 composiciones importados"

#### Scenario: Import with validation errors

- GIVEN an Excel file with 3 rows missing required fields
- WHEN the import processes those rows
- THEN those rows MUST be skipped with error logging
- AND the notification MUST show the count of successful and failed rows
- AND the import MUST NOT halt on non-critical row errors

### Acceptance Criteria

- [ ] `CatalogImport` processes Excel with 4 sheets in correct order
- [ ] Find-or-create pattern prevents duplicates on re-import
- [ ] Existing records are updated, not duplicated
- [ ] "Importar Catálogo" header action opens file upload modal
- [ ] "Descargar Template" provides a fillable Excel template
- [ ] Import result shows success/failure counts
- [ ] Row-level validation errors are logged and skipped, not fatal
- [ ] All existing tests pass after implementation
