# label-layout-optimization Specification

## Purpose

Reduce redundant serial display on ZPL labels: remove the serial from quality stickers (shown twice in `buildQualitySticker`), keeping it only in the composition section and main label block. Adjust Y-positions to eliminate gaps.

## Requirements

### Requirement: Serial removed from quality stickers

The `buildQualitySticker` method MUST NOT render the serial number. The "N°: {serial}" line currently at `$y` position in each sticker MUST be removed.

#### Scenario: Quality sticker 1 (Ensamble) has no serial

- GIVEN a label rendered via `ZebraZplRenderer::render()`
- WHEN the first quality sticker (Ensamble) is built at Y=15
- THEN the ZPL output MUST NOT contain `"N°: "` or the serial value within the Y range 15–98
- AND the sticker MUST still show product code, batch date, lot number, quality control fields

#### Scenario: Quality sticker 2 (Cerrador/Trazabilidad) has no serial

- GIVEN a label rendered via `ZebraZplRenderer::render()`
- WHEN the second quality sticker (Cerrador) is built at Y=115
- THEN the ZPL output MUST NOT contain `"N°: "` or the serial value within the Y range 115–198
- AND the sticker MUST still show type IV, model name, measurements, signature fields

### Requirement: Serial appears exactly twice in ZPL

The complete ZPL output for a label MUST contain the serial string exactly 2 times: once in the composition section and once in the main label block.

#### Scenario: Serial count in full ZPL output

- GIVEN a label with serial `2607-MATREX-V-00000001-7`
- WHEN `ZebraZplRenderer::render()` produces the full ZPL string
- THEN the serial `2607-MATREX-V-00000001-7` MUST appear exactly 2 times in the output
- AND those occurrences MUST be in the composition section and the main label section

#### Scenario: Serial absent from sticker sections

- GIVEN the same label
- WHEN counting serial occurrences within the sticker Y-ranges (Y < 210)
- THEN the count MUST be 0

### Requirement: Adjusted Y-positions avoid layout gaps

Removing the serial line from quality stickers reduces their height. The subsequent elements (boxes, signature row, composition) MUST shift upward to avoid empty whitespace.

#### Scenario: No gap between sticker 1 and sticker 2

- GIVEN the ZPL output with serial removed from stickers
- WHEN measuring the Y-gap between sticker 1 bottom and sticker 2 top
- THEN the gap MUST be ≤ 15 dots (reduced from current ~15 dots after serial removal)
- AND the box separator between stickers MUST remain in place

#### Scenario: Composition section starts higher

- GIVEN the previous layout had composition starting at Y=315
- WHEN serial is removed from stickers and positions are adjusted
- THEN the composition section MAY start at a lower Y value
- AND the total label height MUST NOT exceed 1600 dots

### Requirement: Composition and main label serial unchanged

The serial MUST remain in the composition section (below the "Lote" field) and in the main label block ("CONTROL DE CALIDAD" section). These renderings MUST NOT change.

#### Scenario: Composition section still shows serial

- GIVEN the ZPL output
- WHEN the composition section is rendered (Y ≥ 315)
- THEN the serial MUST appear below the batch date and lot fields
- AND the font scaling logic (`fitFont`) MUST remain functional

#### Scenario: Main label still shows serial

- GIVEN the ZPL output
- WHEN the main label block is rendered (Y ≥ 730)
- THEN `"N°: {serial}"` MUST appear in the right column after "CONTROL DE CALIDAD"

### Acceptance Criteria

- [ ] Quality stickers do not render the serial
- [ ] Serial appears exactly 2 times in the full ZPL output
- [ ] Composition section renders serial with existing font scaling
- [ ] Main label renders serial in the "N°:" field
- [ ] Y-position adjustments eliminate visible gaps in the label
- [ ] Total ZPL height stays within 1600 dots
- [ ] Python USB print script is not affected (reads by position, not serial in stickers)
