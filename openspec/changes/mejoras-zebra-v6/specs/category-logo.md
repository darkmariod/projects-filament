# Spec: Logo por categoría y etiqueta de prueba

## Context
El cliente necesita logos dinámicos por categoría y una etiqueta de prueba mejorada que muestre el logo correspondiente al producto seleccionado.

## Scenario 1: Categoría puede tener logo
**GIVEN** una categoría "Colchones"
**WHEN** se sube un logo PNG a través de Filament
**THEN** el logo se guarda en storage/app/public/categories/ y la categoría tiene el campo logo actualizado

## Scenario 2: Fallback chain de logos en etiquetas normales
**GIVEN** un producto con imagen, una categoría con logo, y el logo Paraíso genérico
**WHEN** se genera la etiqueta
**THEN** se usa la imagen del producto (prioridad 1)

**GIVEN** un producto sin imagen, una categoría con logo
**WHEN** se genera la etiqueta
**THEN** se usa el logo de la categoría (prioridad 2)

**GIVEN** un producto sin imagen, una categoría sin logo
**WHEN** se genera la etiqueta
**THEN** se usa el logo Paraíso genérico (prioridad 3)

**GIVEN** un producto sin imagen, categoría sin logo, logo Paraíso no existe
**WHEN** se genera la etiqueta
**THEN** se muestra texto "PARAISO" (prioridad 4)

## Scenario 3: Etiqueta de prueba con producto seleccionado
**GIVEN** un producto de la categoría "Colchones" con logo
**WHEN** se genera la etiqueta de prueba con ese producto
**THEN** la etiqueta muestra "ETIQUETA DE PRUEBA", el logo de la categoría, datos del producto, y tiene borde visible

## Scenario 4: Etiqueta de prueba sin producto (retrocompatible)
**GIVEN** que no se selecciona ningún producto
**WHEN** se genera la etiqueta de prueba
**THEN** se genera la etiqueta de prueba básica actual (texto "ETIQUETA DE PRUEBA" sin logo)

## Scenario 5: ImageToZplConverter reutilizado para logos de categoría
**GIVEN** una categoría con un logo PNG
**WHEN** se convierte a ZPL
**THEN** se genera un ^GFA válido de máximo 320×120 dots