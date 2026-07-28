# Delta for serial-aleatorio

## MODIFIED Requirements

### Requirement: Random 8-digit serials

The system MUST generate 8 random digits via `random_int()` for each serial position instead of incrementing a sequential counter.

#### Scenario: Happy path — batch generates random serials

- GIVEN a LabelBatch with quantity N (e.g. 100)
- WHEN `SerialGeneratorService::generateForBatch()` is called
- THEN each of the N serials uses the format `YYMM-PRODUCTCODE-V-SSSSSSSS-DV`
- AND the SSSSSSSS portion MUST be 8 random digits (not sequential)
- AND every serial in the batch MUST have a valid Luhn DV

#### Scenario: Anti-collision — retry on duplicate serial

- GIVEN an existing Label with serial `2506-MATREX-V-12345678-5`
- WHEN generation produces the same 8-digit sequence `12345678`
- THEN the system MUST detect the collision
- AND MUST regenerate a new random 8-digit sequence
- AND MUST repeat until a unique sequence is found (up to MAX_RETRIES)

#### Scenario: Anti-collision — max retries exceeded

- GIVEN all possible 8-digit sequences for a given prefix `YYMM-PRODUCTCODE-V-` are exhausted
- WHEN generation fails to find a unique sequence within MAX_RETRIES
- THEN the system MUST throw a `RuntimeException`
- AND MUST NOT create any labels for the batch

### Requirement: Luhn DV on random input

The DV calculator MUST compute the Luhn check digit from the combined `YYMM + PRODUCTCODE + V + SSSSSSSS` string, regardless of whether SSSSSSSS is sequential or random.

#### Scenario: DV unchanged for random input

- GIVEN a string `2506MATREXV12345678`
- WHEN `calculateDV()` is called
- THEN it returns the same Luhn digit as before this change
- AND the algorithm is unchanged — only the input digits changed from sequential to random

#### Scenario: DV handles non-numeric prefix correctly

- GIVEN product codes with mixed characters (e.g. `MATREX`)
- WHEN `calculateDV()` strips non-numeric characters
- THEN the DV is computed only from the numeric digits in the input

### Requirement: No schema changes

The database schema MUST NOT change. The `sequence_number` column MAY store `0` or remain populated with a non-sequential value.

#### Scenario: sequence_number populated

- GIVEN a batch of labels generated with random serials
- WHEN inspecting `sequence_number` in the labels table
- THEN each row has a value (the random digits or `0`)
- AND no new columns or migrations are required

### Requirement: Barcode set from serial, not product

The `barcode` column on each Label MUST store the generated serial value, NOT the product's static barcode. (Previously: barcode was copied from `$product->barcode`, causing every label in a batch to share the same barcode instead of having its own serial as barcode.)

#### Scenario: Batch label barcode equals serial

- GIVEN a LabelBatch for product MATREX with serial `2607-MATREX-V-00000042-3`
- WHEN `generateLabelsForBatch()` creates the Label record
- THEN the `barcode` column MUST equal `2607-MATREX-V-00000042-3`
- AND MUST NOT equal the product's static barcode field

#### Scenario: Individual label barcode equals serial

- GIVEN a product with `barcode = "0000000000001"` and generated serial `2607-MATREX-V-00000001-7`
- WHEN `CreateLabel::mutateFormDataBeforeCreate()` populates the barcode field
- THEN the `barcode` field MUST equal `2607-MATREX-V-00000001-7`
- AND MUST NOT fallback to `$product->barcode`

#### Scenario: Barcode column accepts serial format

- GIVEN the `barcode` column is `string nullable` in the schema
- WHEN a serial in format `YYMM-PRODUCTCODE-V-SSSSSSSS-DV` (up to 48 chars) is stored
- THEN the value fits within the column without truncation
- AND no migration is required

### Acceptance Criteria

- [ ] All serials use 8 random digits verified by `random_int()`
- [ ] No two serials share the same 8-digit sequence for the same prefix
- [ ] Luhn DV is valid for every generated serial
- [ ] Collision detected and retried programmatically
- [ ] Exception thrown when collision retries exhausted
- [ ] Zero schema changes required
- [ ] Existing tests for sequential generation updated or replaced
- [ ] `barcode` column stores the serial value, not the product barcode
- [ ] `CreateLabel` no longer copies `$product->barcode` into the barcode field
