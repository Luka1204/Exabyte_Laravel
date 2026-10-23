# Módulos y dependencias

## Estado implementado

| Módulo/capa | Responsabilidad actual | Estado |
|---|---|---|
| Identity | Login JWT, refresh opaco rotativo, detección de reuso y revocación por sesión | Parcial; sin registro, recuperación, membresías ni roles |
| Tenancy | Alta/listado de empresas; owner puede listar miembros, cambiar roles member/manager y quitar membresías no-owner | Parcial; no hay invitaciones/altas de usuarios ni transferencia de ownership |
| Catalog | Listado paginado y alta de categorías con validación, repositorio Eloquent y policy por rol | Parcial; scope por empresa y policy para categorías implementados; productos y códigos de barras pendientes |
| Modelos compartidos | `User` y `Category` en `app/Models` | Fuera de los límites funcionales; no mover masivamente sin migración coordinada |
| Framework | Rutas, proveedores Laravel, cache, sesiones y colas | Infraestructura del monolito |
| Frontend Identity | Login, refresh automático y estado de sesión en Pinia | Parcial; estado solo en memoria, sin recuperación de cuenta |
| Frontend Catalog | Pantalla y servicios de categorías | Parcial; consume API real |

No existen aún autorización granular por rol/permisos, suscripciones, inventario o
ventas.

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
