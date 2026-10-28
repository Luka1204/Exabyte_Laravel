# Guía inicial de la API

## Base y autenticación

- Base local mediante Vite: `/api/v1`.
- La autenticación actual usa `Authorization: Bearer <access_token>`.
- `POST /api/v1/auth/login` emite access y refresh tokens. `POST /api/v1/auth/refresh`
  rota el refresh token (un solo uso); reutilizar uno revocado invalida la familia
  completa de sesión.
- El cliente mantiene ambos tokens en Pinia en memoria y los rota automáticamente
  ante un 401. La recarga de página termina la sesión; no se persisten tokens en
  almacenamiento web.
- La definición OpenAPI de las rutas actuales está en [`openapi.yaml`](./openapi.yaml).

## Endpoints existentes

| Método | Ruta | Autenticación | Descripción |
|---|---|---|---|
| POST | `/api/v1/auth/login` | No | Validación de credenciales y emisión JWT; throttle 5/min |
| POST | `/api/v1/auth/refresh` | No | Rotación de refresh token; throttle 10/min |
| GET | `/api/v1/auth/me` | Sí | Devuelve id, nombre y correo |
| POST | `/api/v1/auth/logout` | Sí | Revoca la familia de la sesión actual |
| GET | `/api/v1/companies` | Sí | Empresas en las que el usuario tiene membresía |
| POST | `/api/v1/companies` | Sí | Crea empresa y asigna al actor como owner; responde 201 |
| PATCH | `/api/v1/companies/{company}` | Sí + owner | Edita nombre o activa/desactiva la empresa |
| GET | `/api/v1/companies/{company}/members` | Sí + owner | Lista miembros de la empresa |
| PATCH | `/api/v1/companies/{company}/members/{member}` | Sí + owner | Cambia rol o activa/reactiva una membresía |
| DELETE | `/api/v1/companies/{company}/members/{member}` | Sí + owner | Da de baja lógicamente una membresía no-owner; responde 204 |
| GET | `/api/v1/categories` | Sí + empresa activa | Listado paginado para cualquier miembro; `per_page` 1–100, default 15 |
| POST | `/api/v1/categories` | Sí + empresa activa + rol | Alta tenant-scoped para owner/manager; responde 201 |
| PUT | `/api/v1/categories/{category}` | Sí + empresa activa + rol | Edita una categoría de la empresa |
| PATCH | `/api/v1/categories/{category}/status` | Sí + empresa activa + rol | Activa o da de baja una categoría |
| GET/POST | `/api/v1/products` | Sí + empresa activa | Lista o crea productos de la empresa |
| PUT | `/api/v1/products/{product}` | Sí + empresa activa + rol | Edita un producto |
| PATCH | `/api/v1/products/{product}/status` | Sí + empresa activa + rol | Activa o da de baja un producto |
| GET | `/api/v1/activity` | Sí + empresa activa + owner/manager | Historial paginado de acciones de la empresa |

La empresa activa se envía como `X-Company-ID`, pero el valor no es una prueba de
autorización: el middleware verifica la membresía del usuario para cada petición.
Una empresa no disponible responde 404; falta el header responde 400. Las rutas
actuales de catálogo se aíslan por empresa. Cualquier miembro puede listar; solo
owner y manager pueden crear, editar o cambiar estados (member recibe 403). Las
bajas de productos, categorías, empresas y membresías conservan el registro y
permiten reactivación; las ventas, devoluciones, caja, pagos y movimientos de stock
son históricos y se corrigen con operaciones compensatorias. Al crear una empresa,
el creador recibe membership `owner`; las invitaciones para incorporar usuarios
nuevos aún no están implementadas. La membresía owner no se puede cambiar ni
desactivar mediante estos endpoints.

El historial `/activity` registra actor, ruta/entidad, acción, referencia y nombres
de campos enviados, sin conservar valores de solicitudes. Solo owner y manager
pueden consultarlo. El inventario mantiene además su libro de movimientos.

## Errores actuales

- Validación Laravel: HTTP 422 y errores por campo.
- Bearer ausente, inválido, vencido o revocado: HTTP 401.
- La falta de rol para crear categorías responde HTTP 403.
- Los errores de login se representan hoy como validación (HTTP 422).

Este contrato inicial debe evolucionar a un formato de error API común y estable
antes de ampliar la superficie.
