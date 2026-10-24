# Exabyte — Frontend

Aplicación web de gestión comercial construida con Vue 3, TypeScript, Vuetify y
Vite. El MVP permite operar catálogo, inventario, caja y ventas para la empresa
activa de la sesión.

## Requisitos

- Node.js compatible con la versión de Vite del proyecto.
- Dependencias instaladas con `npm install`.
- API Laravel disponible en `http://localhost:8000` para desarrollo local.

## Desarrollo

Desde esta carpeta:

```powershell
npm run dev
```

Vite inicia en `http://localhost:5173` y reenvía `/api` a Laravel en el puerto
8000. Para compilar y validar tipos:

```powershell
npm run build
npm test
```

La cuenta inicial de desarrollo y su configuración están documentadas en
[`backend/README.md`](../backend/README.md). Definí la contraseña en el entorno
local; no la guardes en el repositorio.

## Flujos incluidos

- Inicio de sesión y selección de empresa.
- Alta/listado de categorías y productos.
- Consulta de existencias, ajustes y movimientos recientes.
- Apertura/cierre de caja y arqueo.
- Punto de venta en efectivo con búsqueda por nombre/SKU, lectura de código por
  teclado (Enter o Tab configurable por estación), control de existencias, cambio,
  historial, anulación y devolución parcial/total autorizadas durante la caja abierta.
- Resumen de ventas, devoluciones y anulaciones filtrable por período y empresa.

El servidor sigue siendo la autoridad para validar permisos, recalcular importes,
controlar existencias y confirmar operaciones. El frontend no incluye todavía
registro/recuperación de cuentas, compras, pagos electrónicos ni reportes de
inventario/compras/caja.
