# Spec: Datos dinámicos en stickers de calidad

## Context
Los stickers de calidad actualmente muestran datos genéricos o estáticos. Necesitan mostrar los datos REALES del LabelBatch asociado.

## Scenario 1: Sticker 1 muestra datos del batch
**GIVEN** un label con un LabelBatch que tiene operator="Juan Pérez", customer_batch_number="LOTE-202601-001", customer_batch_date=2026-01-15
**WHEN** se genera el ZPL
**THEN** el sticker 1 contiene "Juan Pérez", "LOTE-202601-001", "15/01/2026"

## Scenario 2: Sticker 2 muestra datos del batch
**GIVEN** un label con un LabelBatch que tiene operator="María García", internal_batch_code="COL-012026"
**WHEN** se genera el ZPL
**THEN** el sticker 2 contiene "María García" y "COL-012026"

## Scenario 3: Composición técnica muestra operador y lote
**GIVEN** un label con un LabelBatch que tiene operator="Carlos López"
**WHEN** se genera el ZPL
**THEN** la sección de composición contiene "Operador: Carlos López"

## Scenario 4: Datos vacíos no rompen el ZPL
**GIVEN** un label con un LabelBatch que tiene operator=null, customer_batch_number=null
**WHEN** se genera el ZPL
**THEN** el ZPL se genera correctamente sin errores