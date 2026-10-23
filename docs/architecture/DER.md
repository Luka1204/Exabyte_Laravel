# DER inicial de Exabyte

## Alcance y evidencia

Este diagrama refleja las migraciones presentes en `backend/database/migrations/`.
Incluye tablas de aplicación y tablas técnicas explícitas de cache/colas. No agrega
entidades de SaaS o comercio que todavía no existen. La tabla interna `migrations`
administrada por Laravel se omite del DER funcional.

### Entidades implementadas

- **users**: `id` PK; `name`; `email` UNIQUE; `email_verified_at` nullable;
  `password`; `remember_token`; `token_version` unsigned default 0; timestamps.
- **categories**: `id` PK; `company_id` nullable FK to `companies.id` (restrict on
  delete); `name` (máximo 120); `description` nullable; timestamps. Unique composite
  `(company_id, name)`. La nulabilidad conserva filas preexistentes sin asignarles
  una empresa no verificada; esas filas no aparecen en consultas tenant-scoped.
- **companies**: `id` PK; `name` (máximo 160); timestamps.
- **company_memberships**: `id` PK; `company_id` FK a `companies.id`; `user_id` FK
  a `users.id`; `role`; timestamps. UNIQUE `(company_id, user_id)` e índice
  `(user_id, company_id)`. Ambas FKs restringen el borrado.
- **refresh_tokens**: `id` PK; `user_id` FK a `users.id` (cascade delete);
  `family_id` UUID indexado; `token_hash` CHAR(64) UNIQUE; `token_version`;
  `expires_at` indexado; `revoked_at` nullable; timestamps. El token plano no se
  persiste.
- **password_reset_tokens**: `email` PK; `token`; `created_at` nullable.
- **sessions**: `id` PK; `user_id` nullable e indexado; `ip_address` nullable;
  `user_agent` nullable; `payload`; `last_activity` indexado. No hay FK declarada
  desde `sessions.user_id` a `users.id`.
- **cache**: `key` PK; `value`; `expiration` indexado.
- **cache_locks**: `key` PK; `owner`; `expiration` indexado.
- **jobs**: `id` PK; `queue` indexado; `payload`; `attempts`; `reserved_at`;
  `available_at`; `created_at`.
- **job_batches**: `id` PK; `name`; contadores; `failed_job_ids`; `options` nullable;
  fechas epoch.
- **failed_jobs**: `id` PK; `uuid` UNIQUE; `connection`; `queue`; `payload`;
  `exception`; `failed_at`.

Las tablas cache y jobs no tienen relaciones con entidades de negocio.
`sessions.user_id` es un identificador indexado, no una relación referencial
garantizada por la base. Categorías históricas tienen `company_id = NULL` hasta que
se apruebe un proceso explícito para asignar su propiedad.

## Mermaid

El archivo editable es [`DER.mmd`](./DER.mmd). Se intentó renderizar con Mermaid
CLI local, pero la instalación temporal falló por falta de espacio en disco
(`ENOSPC`). No se generó un SVG artesanal. Generar `DER.svg` desde esta fuente
queda pendiente hasta disponer de renderer y espacio.

## Restricciones y decisiones

- `users.email`, `cache.key`, `cache_locks.key`,
  `job_batches.id` son claves únicas/primarias según sus migraciones.
- `failed_jobs.uuid` tiene índice único.
- `company_memberships(company_id, user_id)` y `categories(company_id, name)` son
  únicas. La unicidad de categorías permite nombres repetidos entre empresas.
- Las categorías legacy con `company_id = NULL` no son accesibles desde el API
  tenant-scoped; no se asignan a la primera empresa que se cree.
- No existe borrado lógico configurado en los modelos/migraciones actuales.
- No hay columnas de empresa, sucursal, actor de auditoría o movimientos comerciales.
- Los timestamps explícitos son `created_at`/`updated_at` donde se usa `$table->timestamps()`.

## Sincronización

Al cambiar una migración o el modelo persistente:

1. Revisar migración y modelo Eloquent afectados.
2. Actualizar `DER.mmd` y este inventario, con columnas, índices y relaciones verificables.
3. Renderizar el SVG desde `DER.mmd` con una versión fijada de Mermaid CLI cuando
   la herramienta se incorpore al proceso.
4. Ejecutar migraciones y pruebas de repositorio/API; no documentar una FK que solo
   exista como convención en código.
