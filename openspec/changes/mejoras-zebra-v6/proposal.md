# Mejoras Etiquetas v6 — Datos dinámicos + Logo por categoría

## Intent
El sistema de etiquetas Zebra actual muestra datos genéricos en los stickers de calidad y no soporta logos por categoría. Este cambio actualiza los stickers para mostrar datos REALES del sistema y agrega soporte para logos dinámicos por categoría, mejorando la trazabilidad y la personalización visual.

## Scope
- **IN**: Actualizar stickers de calidad con datos del LabelBatch, agregar logos por categoría, mejorar etiqueta de prueba, actualizar fallback de logos
- **OUT**: Cambios en la estructura de stickers (posición Y, tamaño), cambios en el formulario de creación de lotes, cambios en el modelo LabelBatch

## Approach
1. Mejora 1: Mapear datos existentes del LabelBatch a los stickers de calidad
2. Mejora 2: Agregar datos de trazabilidad al sticker 2
3. Mejora 3: Crear migración para logo en categorías, actualizar jerarquía de logo, crear etiqueta de prueba mejorada

## Risk
- **Bajo**: Los cambios son principalmente en el renderer ZPL, no en la lógica de negocio
- **Medio**: Requiere migración de base de datos para categories.logo
- **Mitigación**: TDD estricto, tests para cada nivel de fallback