# Spec: Datos de trazabilidad en sticker 2

## Context
El sticker 2 debe incluir datos completos de trazabilidad: serial, secuencia, lote, fecha de generación y usuario que generó.

## Scenario 1: Sticker 2 muestra serial y secuencia
**GIVEN** un label con serial="PRD-202601-001", sequence_number=5
**WHEN** se genera el ZPL
**THEN** el sticker 2 contiene "PRD-202601-001" y "Seq: 5"

## Scenario 2: Sticker 2 muestra usuario generador
**GIVEN** un LabelBatch generado por un usuario con name="Admin Sistema"
**WHEN** se genera el ZPL
**THEN** el sticker 2 contiene "Gen: Admin Sistema"

## Scenario 3: Sticker 2 muestra código interno del lote
**GIVEN** un LabelBatch con internal_batch_code="COL-012026"
**WHEN** se genera el ZPL
**THEN** el sticker 2 contiene "Lote: COL-012026"

## Scenario 4: Datos de trazabilidad no duplicados
**GIVEN** un label con serial y sequence_number
**WHEN** se genera el ZPL
**THEN** el serial aparece exactamente 2 veces en todo el ZPL (composición + sticker 2)