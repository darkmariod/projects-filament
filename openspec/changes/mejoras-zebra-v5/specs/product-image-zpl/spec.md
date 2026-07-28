# product-image-zpl Specification

## Purpose

Replace the static Paraiso logo in the main label ZPL block with the product's actual image, converted to monochrome bitmap ZPL (`^GFA`). Fall back to `paraiso-logo.gfa` when no product image exists.

## Requirements

### Requirement: Image-to-ZPL conversion service

The system MUST provide an `ImageToZplConverter` service that converts a PNG/JPG/WEBP image file to `^GFA` ZPL monochrome bitmap format suitable for 203dpi thermal printing.

#### Scenario: Convert product image to ZPL

- GIVEN a product image file at `storage/app/public/products/chair.webp` (200×200px)
- WHEN `ImageToZplConverter::convert()` is called with the file path
- THEN it returns a valid `^GFA` ZPL string
- AND the output MUST be monochrome (1-bit black/white)
- AND the output dimensions MUST fit within the main label right-column area (max ~320×120 dots)

#### Scenario: Unsupported format returns null

- GIVEN a product image file in SVG format (unsupported by GD)
- WHEN `ImageToZplConverter::convert()` is called
- THEN it returns `null`
- AND MUST NOT throw an exception

#### Scenario: Missing file returns null

- GIVEN a file path that does not exist on disk
- WHEN `ImageToZplConverter::convert()` is called
- THEN it returns `null`

### Requirement: ZPL conversion caching

Converted ZPL output MUST be cached in `storage/app/zpl-cache/` to avoid repeated image processing.

#### Scenario: First conversion writes cache

- GIVEN a product image at `products/chair.webp` with no cache entry
- WHEN `ImageToZplConverter::convert()` is called
- THEN a file MUST be created at `storage/app/zpl-cache/{md5hash}.gfa`
- AND subsequent calls with the same image MUST return the cached content without re-conversion

#### Scenario: Cache hit skips conversion

- GIVEN a cached ZPL file exists for `products/chair.webp`
- WHEN `ImageToZplConverter::convert()` is called again with the same path
- THEN the cached file content MUST be returned directly
- AND no GD processing MUST occur

### Requirement: Integration in ZebraZplRenderer

`ZebraZplRenderer::buildMainLabel()` MUST use the product image via `ImageToZplConverter` instead of the static `paraiso-logo.gfa` when a product image is available.

#### Scenario: Product has image — renders product image

- GIVEN a product with `image = "products/chair.webp"`
- WHEN `ZebraZplRenderer::render()` builds the main label
- THEN the right-column graphic MUST be the product image converted to ZPL
- AND the static `paraiso-logo.gfa` MUST NOT be used

#### Scenario: Product has no image — falls back to Paraiso logo

- GIVEN a product with `image = null`
- WHEN `ZebraZplRenderer::render()` builds the main label
- THEN the right-column graphic MUST be `paraiso-logo.gfa`
- AND the fallback text "PARAISO" MUST appear only if the logo file is also missing

#### Scenario: Image conversion fails — falls back to logo

- GIVEN a product with a corrupted image file
- WHEN `ImageToZplConverter::convert()` returns `null`
- THEN `ZebraZplRenderer` MUST fall back to `paraiso-logo.gfa`
- AND MUST NOT throw an exception

### Requirement: Product image path extraction

`ZebraZplRenderer::extractData()` MUST include the product image path in its output array.

#### Scenario: extractData returns image path

- GIVEN a label whose product has `image = "products/chair.webp"`
- WHEN `extractData()` is called
- THEN the returned array MUST include `'image' => 'products/chair.webp'`

#### Scenario: extractData returns null for no image

- GIVEN a label whose product has `image = null`
- WHEN `extractData()` is called
- THEN the returned array MUST include `'image' => null`

### Acceptance Criteria

- [ ] `ImageToZplConverter` produces valid `^GFA` ZPL from PNG/JPG/WEBP images
- [ ] Conversion result is cached in `storage/app/zpl-cache/`
- [ ] Main label uses product image when available
- [ ] Fallback to `paraiso-logo.gfa` when no image or conversion fails
- [ ] Fallback to text "PARAISO" when both image and logo are missing
- [ ] `extractData()` includes the product image path
- [ ] No exceptions thrown on missing or unsupported images
