# Label Batch Search Specification

## Purpose

Add name-based search to the LabelBatchResource index table, enabling users to find batches by customer batch number, product name, or operator.

## Requirements

### Requirement: Search by customer batch number

The `customer_batch_number` column MUST be added to the table's search scope.

#### Scenario: Search by customer batch number

- GIVEN a batch with `customer_batch_number = "LOTE-2025-06"`
- WHEN the user types "LOTE-2025" in the search input
- THEN the batch appears in the filtered results
- AND partial matches are supported (LIKE-based search)

#### Scenario: No matches

- GIVEN no batch has "XYZ-NONEXISTENT" in any searchable column
- WHEN the user searches for "XYZ-NONEXISTENT"
- THEN the table shows an empty state with "No results" message

#### Scenario: Empty search restores all records

- GIVEN the search filter is active
- WHEN the user clears the search input
- THEN all records are displayed again without filtering

### Requirement: Existing search columns preserved

The existing searchable columns (`internal_batch_code`, `product.name`, `operator`) MUST remain functional.

#### Scenario: Combined search works

- GIVEN a batch matching both `internal_batch_code` and `customer_batch_number`
- WHEN the user searches by any part of either field
- THEN the batch appears in results
- AND search across all searchable columns works as before

### Acceptance Criteria

- [ ] `customer_batch_number` is searchable in the table
- [ ] Existing search on `internal_batch_code`, `product.name`, `operator` continues to work
- [ ] Partial and full match searches return correct results
- [ ] Empty search restores full record list
