# Serial Aleatorio Specification

## Purpose

Replace the sequential counter in serial generation with 8 random digits plus Luhn check digit, maintaining the existing format `YYMM-PRODUCTCODE-V-SSSSSSSS-DV`.

## Requirements

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

### Acceptance Criteria

- [ ] All serials use 8 random digits verified by `random_int()`
- [ ] No two serials share the same 8-digit sequence for the same prefix
- [ ] Luhn DV is valid for every generated serial
- [ ] Collision detected and retried programmatically
- [ ] Exception thrown when collision retries exhausted
- [ ] Zero schema changes required
- [ ] Existing tests for sequential generation updated or replaced
