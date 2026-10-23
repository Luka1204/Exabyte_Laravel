# Diagrama de clases inicial

## Alcance

El diagrama describe clases y dependencias encontradas en el código actual de
Identity, Tenancy y Catalog. No inventa entidades de ventas, inventario o
suscripciones. La arquitectura objetivo y los límites propuestos están en
[`ARCHITECTURE.md`](./ARCHITECTURE.md) y [`MODULES.md`](./MODULES.md).

El diagrama muestra una brecha arquitectónica existente: el contrato
`CategoryRepository` vive bajo `Domain/Contracts`, pero importa `Category` de
Eloquent y devuelve `LengthAwarePaginator` de Laravel. La capa de aplicación
también devuelve el modelo Eloquent. Es un refactor futuro, no una abstracción
nueva creada en esta fase.

## Mermaid

Fuente editable: [`CLASS_DIAGRAM.mmd`](./CLASS_DIAGRAM.mmd). La instalación temporal
de Mermaid CLI falló por falta de espacio en disco (`ENOSPC`); por eso
`CLASS_DIAGRAM.svg` queda pendiente hasta poder generarlo desde esta fuente.

## Procedimiento de actualización

Al cambiar clases, puertos o dependencias entre módulos, actualizar la fuente
Mermaid y este análisis en el mismo cambio; verificar las referencias con el código
y renderizar el SVG desde el `.mmd` cuando se integre el renderer.
