# Módulos y dependencias

## Estado implementado

| Módulo/capa | Responsabilidad actual | Estado |
|---|---|---|
| Identity | Registro transaccional de usuario/empresa owner, login JWT, refresh opaco rotativo, detección de reuso y revocación por sesión | Parcial; sin verificación de email, recuperación de cuenta ni sesión persistente del navegador |
| Tenancy | Alta/listado de empresas; owner puede listar miembros, cambiar roles member/manager y quitar membresías no-owner; las empresas existentes antes de la migración reciben una excepción explícita | Parcial; sin invitaciones ni transferencia de ownership; límites de miembros, sucursales y cajas requieren endpoints/recursos adicionales |
| Subscriptions/Billing | Catálogo ARS administrable con snapshot contractual, contratación pendiente, historial de intentos, lifecycle y cancelación con Mercado Pago; webhook firmado/idempotente y consulta autoritativa de pago; enforcement central de catalog/inventory/POS/reportes y límite de productos | Parcial; no hay reconciliación programada, cambio de plan ni automatización de vencimientos/gracia; sin sandbox verificado |
| PlatformAdministration | Autorización separada en `platform_admins`, gestión de planes, consulta de suscripciones/pagos, métricas y auditoría de modificaciones de planes | Parcial; sin consola de incidentes, conciliación/reintentos operativos ni auditoría completa de todas las acciones |
| Catalog | Categorías y productos tenant-scoped; SKU/código de barras únicos por empresa; precio e impuesto validados | MVP implementado para alta/listado; sin importación/exportación |
| Inventory | Saldos por producto/empresa, libro paginado de movimientos y ajustes idempotentes | MVP implementado; sin sucursales/depósitos ni compras/recepciones |
| CashManagement | Una caja abierta por empresa; apertura, cierre y diferencia de arqueo | MVP implementado; caja ligada a empresa, no a terminal/sucursal |
| Sales/POS | Venta en efectivo, descuento de stock, idempotencia, ticket, anulación y devolución parcial/total trazable | MVP implementado; sin otros medios de pago |
| Modelos compartidos | `User` y `Category` en `app/Models` | Fuera de los límites funcionales; no mover masivamente sin migración coordinada |
| Framework | Rutas, proveedores Laravel, cache, sesiones y colas | Infraestructura del monolito |
| Frontend Identity/Tenancy | Login, registro con empresa inicial, refresh automático, selector de empresa y gestión de membresías | Parcial; sesión en memoria, sin recuperación de cuenta ni verificación de email |
| Frontend Subscriptions | Landing, planes/precios, selección mensual/anual, registro con plan seleccionado y estado/pagos de la empresa | Implementado contra API; requiere credenciales y verificación sandbox de Mercado Pago |
| Frontend PlatformAdministration | Panel con métricas de suscripciones/pagos y CRUD de planes | Parcial; vistas y API protegidas, sin acciones de conciliación/reintentos ni soporte operativo |
| Frontend Catalog | Categorías y productos con validación de API y presentación de estados vacíos/errores | MVP implementado |
| Frontend Inventory | Saldos, ajuste de stock y consulta de movimientos recientes | MVP implementado |
| Frontend Sales/POS | Búsqueda por nombre/SKU, lector HID configurable (Enter/Tab/manual), carrito, caja, venta, historial, anulación y devolución autorizadas | MVP implementado; interfaz en tema rojo/blanco/azul oscuro |
| Reporting | Resúmenes por rango de fechas de ventas, inventario (productos/saldos y movimientos) y caja (sesiones, ventas, devoluciones y arqueos) | Reportes tenant-scoped de ventas, inventario y caja implementados; pendiente compras y optimización medida |

Siguen pendientes la aplicación centralizada de entitlements y límites, la gestión
operativa completa de suscripciones/pagos, compras, sucursales/depósitos, pagos no
efectivo, reporte de compras y recuperación/verificación de cuentas.

## Estructura objetivo del backend

```text
backend/app/Modules/
  Identity/
    Domain/ (credenciales, políticas de sesión y puertos)
    Application/ (login, refresh, logout, perfil)
    Infrastructure/ (JWT, persistencia de sesión, notificaciones)
    Presentation/ (routes/controllers/requests/resources)
  Tenancy/
    Domain/ (pendiente de separar de persistencia)
    Application/ (crear empresa)
    Infrastructure/Persistence/Eloquent/ (Company)
    Presentation/ (CompanyController, middleware de membresía, requests)
  AccessControl/
  Subscription/
  Organization/ (sucursales, depósitos, terminales, cajas)
  Catalog/
  Parties/ (clientes y proveedores)
  Inventory/
  Purchasing/
  Sales/
  CashManagement/
  Reporting/
  Audit/
  PlatformAdministration/
```

Los subdirectorios se crean cuando existen casos de uso reales, no para llenar una
plantilla. Los modelos Eloquent se ubicarán en infraestructura cuando se extraiga un
módulo; la migración debe preservar los contratos existentes y ejecutarse de forma
incremental.

## Módulos objetivo y dependencias permitidas

| Módulo | Depende de | No debe depender de |
|---|---|---|
| Identity | infraestructura compartida | Catálogo, ventas |
| Tenancy | Identity | módulos comerciales |
| AccessControl | Identity, Tenancy | ventas/inventario |
| Subscription | Tenancy | datos comerciales detallados |
| Organization | Tenancy, AccessControl | ventas |
| Catalog | Tenancy, AccessControl | Inventory, Sales |
| Parties | Tenancy, AccessControl | compras/ventas |
| Inventory | Tenancy, Organization, Catalog | presentación de compras/ventas |
| Purchasing | Tenancy, Organization, Catalog, Parties, Inventory | implementación interna de POS |
| CashManagement | Tenancy, Organization, AccessControl | Catálogo |
| Sales/POS | Tenancy, Organization, Catalog, Inventory, CashManagement | tablas privadas de otros módulos |
| Reporting | contratos de consulta/eventos de dominios | escrituras o lógica duplicada |
| Audit | Tenancy, Identity | cargas sensibles o secretos |
| PlatformAdministration | Identity, Subscription, Tenancy | acceso comercial indiscriminado |

El contexto de empresa se recibe en `X-Company-ID` como selector no confiable y se
resuelve contra las membresías del usuario autenticado en cada petición. El repositorio
de categorías filtra por ese contexto. Las dependencias se expresan mediante interfaces/casos de uso o eventos internos
cuando sea útil. No se permite que un módulo manipule directamente tablas ajenas.
La suscripción habilita funcionalidades y límites; nunca concede permisos de
administración de tenant.

## Frontend objetivo

```text
frontend/src/
  app/ (bootstrap, router, guards)
  modules/{identity,tenancy,subscription,catalog,inventory,purchasing,pos,...}/
    components/ views/ composables/ services/ types/ validations/ stores/
  shared/
    api/ ui/ composables/ types/ utilities/
```

El estado local queda en componentes/composables; Pinia se reserva a sesiones y
estado compartido con ciclo de vida transversal. La autorización efectiva siempre
se valida en backend.
