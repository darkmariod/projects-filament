# Design: Cambios etiquetas colchones

## Technical Approach

5 capabilities independientes, modificaciones localizadas. El cambio core es reemplazar el contador secuencial por `random_int()` con loop anti-colisión en `SerialGeneratorService`. Los cambios B-E son aditivos en Filament Resources y Blade, sin tocar lógica de negocio existente.

## Architecture Decisions

### Decision: Anti-collision loop strategy

| Option | Tradeoff | Decision |
|--------|----------|----------|
| `random_int()` + DB unique check | Simple, ~100M combinaciones por prefijo | ✅ **Chosen** — colisión en 100K labels es ~50% pero loop retry lo cubre |
| UUID v4 como serial | Rompe formato `YYMM-PROD-V-8DIG-DV` existente | ❌ Rejected — el formato no puede cambiar |
| Secuencia + offset aleatorio | Sigue siendo predecible | ❌ Rejected — no cumple el requisito de aleatoriedad |

**Rationale**: El loop con `MAX_RETRIES = 1000` y `RuntimeException` cubre colisiones. 8 dígitos = 100M combinaciones por prefijo `YYMM-PRODUCTCODE-V-`. La probabilidad de colisión en 100K labels es ~50% según paradoja del cumpleaños, pero el loop retry lo resuelve. La DB hace la verificación — no cache, no race conditions.

### Decision: `sequence_number` semantics

| Option | Tradeoff | Decision |
|--------|----------|----------|
| Guardar los 8 dígitos aleatorios | Mantiene compatibilidad, sin schema changes | ✅ **Chosen** |
| Guardar 0 siempre | Más claro semánticamente, rompe `orderBy('sequence_number')` | ❌ Rejected — breaking change innecesario |
| Renombrar columna | Requiere migration | ❌ Rejected — out of scope |

**Rationale**: Al guardar los 8 dígitos aleatorios en `sequence_number`, el `orderBy('sequence_number')` existente en `ZebraZplService` sigue funcionando (aunque el orden sea aleatorio). No hay cambios de schema.

### Decision: Reprint action — new `PrintQueue` vs standalone

| Option | Tradeoff | Decision |
|--------|----------|----------|
| Reusar `PrintQueueService::createQueueForBatch()` | Diseñado para batches, no single label | ❌ Rejected — add extra overhead |
| Nueva Action inline con `PrintQueueItem` directo | Simple, consistente con `sendSingleLabel()` | ✅ **Chosen** |
| Nuevo método `PrintQueueService::createQueueForLabel()` | Encapsulado pero añade complejidad si solo se usa acá | ⚠️ Modified — crear método en service es más testeable |

**Rationale**: La reimpresión individual crea un `PrintQueueItem` suelto (sin `PrintQueue` padre) y envía directo. Existe `ZebraZplService::sendSingleLabel()` que hace el envío TCP pero no persiste. La nueva Action persiste el `PrintQueueItem` y luego envía.

## Data Flow

### Serial Aleatorio (Capability A)

```
LabelBatchResource::generar action
  → SerialGeneratorService::generateLabelsForBatch($batch)
    → generateForBatch($batch)
      → Product::findOrFail(), extraer YYMM, productCode
      → for i=1..quantity:
          → random_int(0, 99999999) → str_pad 8 digits
          → calculateDV(yymm, productCode, 'V', sequence)
          → while Label::where('serial', $serial)->exists(): regenerate
          → if retries > MAX_RETRIES: throw RuntimeException
          → append to $serials[]
      → DB::transaction: Label::insert(), batch→generated
```

### Label Reprint (Capability E)

```
LabelResource::reprint action
  → modal: show printer config (from ZebraPrintSetting or manual IP/port)
  → on confirm:
      → ZebraZplService::generateZplForItem($label)
      → PrintQueueItem::create({status:'pending', zpl_content, label_id})
      → try: ZebraZplService::sendSingleLabel($zpl, $ip, $port)
        → success: PrintQueueItem→markAsPrinted(), LabelLog→create('reprint')
        → failure: PrintQueueItem→incrementAttempt(), LabelLog→create('reprint_failed')
      → Notification::make()
```

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `app/Services/SerialGeneratorService.php` | Modify | Reemplazar `$lastSequence++` por `random_int(0, 99999999)` con loop anti-colisión; `getLastSequence()` se elimina o simplifica a prefijo check |
| `resources/views/public/product.blade.php` | Modify | Agregar `<span class="badge badge-gray">NÚMERO ÚNICO</span>` dentro del `serial-box` |
| `app/Filament/Resources/LabelBatchResource.php` | Modify | Agregar `$table->tabs()` con statuses + counts; agregar `->searchable()` a `customer_batch_number` |
| `app/Filament/Resources/LabelResource.php` | Modify | Agregar `Action::make('reimprimir')` con modal de impresora y envío individual |
| `app/Services/PrintQueueService.php` | Modify | Agregar `createQueueForLabel(Label $label, ...)` para reimpresión individual |
| `tests/Unit/Services/SerialGeneratorServiceTest.php` | Modify | Actualizar tests: `it_generates_sequential_serials` → verifica aleatoriedad; `it_skips_existing_serials` → adaptar a random |

### No Schema Changes
Ninguna de las 5 capabilities requiere migrations.

## Interfaces / Contracts

### SerialGeneratorService — nuevos métodos

```php
class SerialGeneratorService
{
    protected const MAX_RETRIES = 1000;
    protected const SERIAL_LENGTH = 8;

    // El método público no cambia su firma:
    public function generateForBatch(LabelBatch $batch): array;

    // Internamente cambia:
    // protected function getLastSequence(string $productCode, string $yymm): int  →  ELIMINADO
    // protected function generateRandomSequence(): string  →  NUEVO

    // El resto queda igual:
    protected function calculateDV(string $yymm, string $productCode, string $line, string $sequence): int;
    public function buildQrUrl(string $serial): string;
    public function generateLabelsForBatch(LabelBatch $batch): bool;
}
```

### PrintQueueService — nuevo método

```php
class PrintQueueService
{
    // Nuevo método para reimpresión individual:
    public function createQueueForLabel(
        Label $label,
        string $ip = '',
        int $port = 9100,
        ?int $userId = null,
        ?string $connectionType = null,
        ?string $printerName = null
    ): PrintQueueItem;
}
```

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Unit | `SerialGeneratorService::generateForBatch()` con random | Crear batch, verificar que cada serial tiene 8 dígitos aleatorios + DV válido. NO verificar secuencia. Verificar `sequence_number` poblado. |
| Unit | Anti-colisión loop | Pre-crear labels con secuencias existentes, verificar que genera sin duplicados |
| Unit | Anti-colisión max retries | Mock `Label::where('serial', $s)->exists()` → true, verificar `RuntimeException` |
| Unit | DV consistency | `calculateDV('2506', 'MATREX', 'V', '12345678')` → mismo resultado que antes |
| Feature | LabelBatch tabs | Filament test: verificar que tabs renderizan, click filtra, counts correctos |
| Feature | LabelBatch search | Filament test: buscar por `customer_batch_number`, verificar resultados |
| Feature | Label reprint action | Filament test: verificar acción visible/no visible según status, modal se abre |
| Feature | Public label page badge | HTTP test: `GET /p/{serial}` → verificar string "NÚMERO ÚNICO" en HTML |
| Feature | Reprint queue persistence | Test que `PrintQueueItem` se crea con status pending/failed |

## Migration / Rollout

No migration required. Los labels existentes mantienen su serial secuencial. Solo labels nuevos (post-deploy) usan serial aleatorio. Rollback: revertir `SerialGeneratorService.php` vía git para capability A; los cambios B-E se revierten individualmente.

## Known Risks (from Specs)

| Risk | Impact | Mitigation |
|------|--------|------------|
| Colisión en batches grandes (100K ~50%) | Performance del loop | `MAX_RETRIES = 1000` + `RuntimeException`. Monitor en producción |
| `sequence_number` semantics rotas | Consumidores existentes | Verify `ZebraZplService` `orderBy('sequence_number')` — funciona aunque sea aleatorio |
| Filament tabs + filter dropdown coexistiendo | UI confusa | Tabs sobrescriben dropdown (Filament 5.x nativo). No sobrescribir `$table->filters()` existente |
| `sendSingleLabel()` no persiste | Reprint sin audit trail | Nuevo code path en `LabelResource::reimprimir` crea `PrintQueueItem` antes de enviar |

## Open Questions

- None. All 5 capabilities have clear implementation paths.
