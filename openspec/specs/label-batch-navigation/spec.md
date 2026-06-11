# Label Batch Navigation Specification

## Purpose

Add filter tabs to the LabelBatchResource index table, allowing users to quickly switch between status groups without using the dropdown filter.

## Requirements

### Requirement: Status tabs on the index table

The LabelBatchResource table MUST display tabs above the rows, one per status, plus an "All" option.

#### Scenario: Tabs displayed correctly

- GIVEN the user is on the LabelBatchResource index page
- THEN the table MUST show tabs labeled "Todos", "Activo", "Generado", "Impreso", "Anulado"
- AND each tab SHOULD display a count of records in that status
- AND the "Todos" tab MUST be selected by default

#### Scenario: Clicking a tab filters the table

- GIVEN the user clicks the "Generado" tab
- WHEN the table reloads
- THEN only batches with `status = 'generated'` SHOULD appear
- AND the URL SHOULD reflect the active tab

#### Scenario: Tab persists on navigation back

- GIVEN the user selected the "Impreso" tab
- WHEN navigating to edit a batch and returning to the index
- THEN the tab selection MAY reset to "Todos"

### Requirement: Tab integration with existing filters

The tabs MUST work alongside the existing status dropdown filter without conflict.

#### Scenario: Tab and filter interaction

- GIVEN a tab (e.g. "Generado") is active
- WHEN the user also selects a value in the existing "Estado" filter
- THEN the tab selection MAY override or AND-combine with the filter
- AND the behavior MUST not produce an empty result that confuses the user

### Acceptance Criteria

- [ ] Tabs render for each status: Todos, Activo, Generado, Impreso, Anulado
- [ ] Clicking a tab filters results correctly
- [ ] Count badges shown on each tab
- [ ] No conflict with existing status dropdown filter
