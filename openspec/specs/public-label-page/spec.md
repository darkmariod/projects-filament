# Public Label Page Specification

## Purpose

Highlight the "número único" (serial) on the `/p/{serial}` public page, making it visually prominent so visitors immediately recognize it as the product's unique identifier.

## Requirements

### Requirement: Prominent serial display

The public page MUST display the serial in a visually emphasized container with a clear "NÚMERO ÚNICO" label or badge.

#### Scenario: Happy path — serial displayed with unique badge

- GIVEN a valid label with serial `2506-MATREX-V-12345678-5`
- WHEN visiting `/p/2506-MATREX-V-12345678-5`
- THEN the serial SHOULD be displayed inside a styled container
- AND a badge or label reading "NÚMERO ÚNICO" MUST be visible near the serial
- AND the design MUST distinguish it from other product information

#### Scenario: Styling consistency

- GIVEN the existing page design (red header, gray sections)
- WHEN the "NÚMERO ÚNICO" badge is rendered
- THEN it MUST use the existing badge color scheme (e.g. `badge` + `badge-green`) or a new dedicated style
- AND it MUST NOT break the existing layout or responsive behavior

### Requirement: Badge visibility across all label statuses

The "NÚMERO ÚNICO" indicator MUST appear regardless of the label's status (available, registered, anulled).

#### Scenario: Registered label shows badge

- GIVEN a label with status `registered`
- WHEN visiting `/p/{serial}`
- THEN the "NÚMERO ÚNICO" badge is visible alongside the warranty registration info
- AND the serial remains readable

#### Scenario: Anulled label shows badge

- GIVEN a label with status `anulled`
- WHEN visiting `/p/{serial}`
- THEN the "NÚMERO ÚNICO" badge is visible alongside the anulled indicator

### Acceptance Criteria

- [ ] "NÚMERO ÚNICO" badge visible on the public page
- [ ] Badge renders for all label statuses (available, registered, anulled)
- [ ] Serial remains the focal point of the page
- [ ] No layout regressions on mobile viewport
