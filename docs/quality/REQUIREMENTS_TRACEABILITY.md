# Trazabilidad inicial de requisitos

Esta tabla cubre únicamente el código existente y los fundamentos implementados
en esta etapa. No afirma cobertura de requisitos de negocio todavía inexistentes.

| Requisito/resultado | Componente | Evidencia automatizada/documental | Estado |
|---|---|---|---|
| Login con credenciales y access/refresh token | `Identity/AuthController`, `RefreshSession`, `JwtTokenService` | `RefreshSessionApiTest::test_login_returns_a_refresh_token_but_only_stores_its_hash` | Implementado; refresh hash, no token plano, persistido |
| Rotación y reuso de refresh token | `Identity/RefreshSession` | `test_refresh_rotation_rejects_reuse_and_revokes_the_session_family` | Implementado y probado |
| Expiración de refresh token | `Identity/RefreshSession` | `test_expired_refresh_token_cannot_be_rotated` | Implementado y probado |
| Revocación de una sola sesión | `AuthController::logout`, familia `sid` | `test_logout_revokes_only_the_current_session` | Implementado y probado; tokens legacy sin `sid` aún revocan globalmente |
| Refresh automático ante 401 | Pinia auth store e interceptor Axios | `http.test.ts`; `authStore.test.ts` | Implementado y probado |
| Middleware JWT protege API | `AuthenticateJwt`, rutas `/api/v1` | `test_categories_require_authentication`, `test_revoked_token_is_rejected` | Implementado |
| Creación autenticada de categoría | `Catalog/CategoryController`, use case, repositorio | `test_authenticated_user_can_create_category` | Implementado bajo empresa activa |
| Rechazar categoría duplicada | `CreateCategory` y constraint único por empresa | `test_duplicate_category_is_rejected` | Implementado por tenant |
| Paginación por defecto de 15 | `CategoryController@index` | `test_categories_use_fifteen_items_per_page_by_default` | Corregido y probado |
| Build del frontend con type-check | Vue/Vite/TypeScript | `npm run build` desde `frontend/` | Pasa; advertencia de chunk |
| No codificar contraseña DB de fallback | `config/database.php` | Inspección de configuración; `DB_PASSWORD` requiere entorno | Corregido |
| Crear empresa con membership owner | `Tenancy/CompanyController`, `CreateCompany` | `test_authenticated_user_can_create_a_company_as_owner` | Implementado parcialmente |
| Owner administra roles y membresías existentes | `Tenancy/CompanyMemberController`, `RequireCompanyOwner`, use cases | `CompanyMemberApiTest` (7 casos: listado scoped, cambio/remoción, no-owner, aislamiento y owner protegido) | Implementado parcialmente; no hay invitaciones/alta de usuarios |
| Categorías aisladas entre empresas | Middleware de membership + repositorio scoped | `test_categories_are_isolated_between_companies`, `test_non_member_cannot_access_another_company_categories` | Implementado para categorías |
| Lectura de categorías por membresía y creación según rol | `CategoryPolicy`, `CategoryController` | `test_company_member_can_read_categories_but_cannot_create_them`, `test_company_manager_can_create_categories` | Implementado: owner/manager escriben; member solo lee |
| No confiar en `company_id` del cliente | Controller extrae contexto del middleware y repositorio lo aplica | `test_client_cannot_override_category_company_id` | Implementado para categorías |
| Aislamiento para otros módulos | Módulos comerciales aún no creados | Pruebas por agregado pendientes | No implementado |
| Compra/venta/inventario/suscripciones | Módulos no creados | Plan incremental y aceptación en `IMPLEMENTATION_PLAN.md` | No implementado |

Por cada nuevo requisito, agregar identificador, módulo/caso de uso, criterio
verificable, prueba y referencia a evidencia real antes de marcarlo como completo.
