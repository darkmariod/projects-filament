# Tasks: Etiquetas Colchones

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~340 |
| 400-line budget risk | Low |
| Chained PRs recommended | No |
| Suggested split | Single PR |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending |

Decision needed before apply: Yes
Chained PRs recommended: No
Chain strategy: pending
400-line budget risk: Low

## Phase 1: Serial Aleatorio (Capability A)

- [ ] **A.1** Replace `getLastSequence()` with `generateRandomSequence()` in `SerialGeneratorService` — `random_int(0, 99999999)` + anti-collision loop (`MAX_RETRIES=1000`). Files: `app/Services/SerialGeneratorService.php`. Deps: none. Effort: M (~40ch).
- [ ] **A.2** Update tests: adapt sequential test to random, add anti-collision, max retries, DV consistency. Files: `tests/Unit/Services/SerialGeneratorServiceTest.php`. Deps: A.1. Effort: M (~80ch).

## Phase 2: Public Page Badge (Capability B)

- [ ] **B.1** Add `<span class="badge badge-gray">NÚMERO ÚNICO</span>` inside `serial-box` in public Blade. Files: `resources/views/public/product.blade.php`. Deps: none. Effort: S (~5ch).
- [ ] **B.2** HTTP test: "NÚMERO ÚNICO" renders for all statuses (`GET /p/{serial}`). Deps: B.1. Effort: S (~30ch).

## Phase 3: Batch Navigation + Search (Capabilities C + D)

- [ ] **C.1** Add `$table->tabs()` in `LabelBatchResource` with status groups (Todos, Activo, Generado, Impreso, Anulado) + counts. Files: `app/Filament/Resources/LabelBatchResource.php`. Deps: none. Effort: S (~25ch).
- [ ] **D.1** Add `->searchable()` to `customer_batch_number` column in `LabelBatchResource` table. Files: `app/Filament/Resources/LabelBatchResource.php`. Deps: none. Effort: S (~3ch).
- [ ] **CD.2** Filament tests: tabs render/filter/counts, search by `customer_batch_number`. Deps: C.1, D.1. Effort: M (~50ch).

## Phase 4: Label Reprint (Capability E)

- [ ] **E.1** Add `createQueueForLabel(Label $label, ...)` to `PrintQueueService`. Files: `app/Services/PrintQueueService.php`. Deps: none. Effort: S (~30ch).
- [ ] **E.2** Add `Action::make('reimprimir')` to `LabelResource` — modal (printer selection), visible unless anulled, ZPL generation, PrintQueueItem, LabelLog, notification. Files: `app/Filament/Resources/LabelResource.php`. Deps: E.1. Effort: M (~60ch).
- [ ] **E.3** Tests: action visibility, modal, queue persistence, failure handling. Deps: E.2. Effort: M (~50ch).
