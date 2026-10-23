# Arquitectura actual y objetivo

## Alcance de este documento

Esta es una arquitectura inicial basada en el repositorio inspeccionado. Distingue el
código que existe hoy del diseño objetivo; las entidades, rutas y flujos futuros no
se presentan como funcionalidades implementadas. La plataforma seguirá siendo un
monolito modular y usará una base de datos compartida con aislamiento lógico por
empresa.

## Diagnóstico del estado actual

- El repositorio contiene dos aplicaciones: `backend/` (Laravel) y `frontend/`
  (Vue/Vite independientes).
- El backend ya implementa inicio de sesión JWT, refresh rotativo con revocación por
  familia de sesión, consulta del usuario autenticado y categorías tenant-scoped.
- Hay módulos `Identity`, `Tenancy` y `Catalog`, pero los modelos Eloquent residen
  fuera de los módulos salvo `Company`. `Catalog` separa controller, requests,
  casos de uso y persistencia; sus contratos aún dependen de Eloquent y del
  paginador de Laravel.
- El esquema actual contiene usuarios, empresas, membresías y categorías además de
  tablas técnicas de Laravel. No hay sucursales, permisos granulares, planes,
  suscripciones, inventario, ventas, compras, cajas ni auditoría persistente.
- La SPA tiene login, refresh automático ante 401, selector/alta de empresas y
  listado/alta de categorías. Los tokens y la empresa activa se conservan en Pinia
  en memoria; una recarga requiere volver a iniciar sesión.
- El build del frontend fallaba por un import no utilizado en `App.vue`; se eliminó.
  Se configuró Vitest y se añadieron pruebas del store tenant y del servicio de catálogo.
- Se eliminó un valor de contraseña MySQL codificado como fallback de configuración.
  La contraseña ahora debe provenir de la configuración del entorno.

## Stack observado

Versiones instaladas/validadas en esta revisión:

| Componente | Versión observada | Estado respecto del objetivo |
|---|---:|---|
| PHP | 8.3.6 | Compatible con PHP 8.2+ |
| Laravel | 12.70.0 | Compatible |
| `firebase/php-jwt` | 7.2.1 | JWT HS256 ya está implementado; no se reemplazó |
| PHPUnit | 11.5.57 | Suite actual pasa |
| Node.js | 22.15.0 | Runtime local |
| Vue | 3.5.44 | Compatible |
| TypeScript | 6.0.3 | Configurado con `vue-tsc`; el build identifica errores |
| Vue Router | 4.6.4 | Compatible con Vue 3 |
| Pinia | 4.1.0 | Instalado; peer devtools presente |
| Vuetify | 3.13.7 | Alineado con objetivo |
| Vite frontend | 8.3.4 | Instalado |
| Axios | 1.20.0 | Instalado |
| Vitest | 4.1.12 | Configurado; compatible con Vite 8 |
| Vue Test Utils | 2.4.6 | Configurado |

No se inspeccionó el archivo `.env` local ni se consultó una base de datos externa.
PHPUnit fuerza SQLite en memoria. El ejemplo de entorno usa SQLite, mientras que la
configuración de Laravel conserva MySQL como conexión predeterminada si no se
configura `DB_CONNECTION`.

## Arquitectura objetivo

```text
Frontend Vue 3
  módulos por funcionalidad -> Router -> Pinia (estado compartido necesario)
  servicios Axios -> API REST versionada /api/v1

Backend Laravel 12 (monolito modular)
  Presentation (Routes, Controllers, Form Requests, Resources, Policies)
       -> Application (Use cases, Commands, Queries, DTOs)
            -> Domain (reglas, entidades, value objects, puertos)
                 <- Infrastructure (Eloquent, adapters, proveedores externos)
  módulos comunicados por contratos explícitos; evitar acceso cruzado a tablas/modelos

Persistencia relacional configurada por entorno
  company_id y restricciones/indexación por empresa en los agregados multiempresa
```

El dominio no debe importar Laravel ni Eloquent. La capa de aplicación coordina
casos de uso; infraestructura implementa puertos específicos; controllers y
componentes no contienen reglas comerciales. Las escrituras comerciales futuras
deben definir su límite transaccional, idempotencia y estrategia de concurrencia
antes de exponerse por API.

## Decisiones y supuestos

1. **Conservar el monolito Laravel y la SPA separada.** Ya existen y permiten
   avanzar por módulos sin introducir servicios distribuidos.
2. **Conservar la base configurada por entorno.** No se migra el motor. Los tests
   permanecen aislados en SQLite en memoria.
3. **API REST versionada.** La superficie observada ya se encuentra bajo `/api/v1`.
   Los contratos actuales se describen en `../api/openapi.yaml`.
4. **JWT existente, no criptografía propia.** Se conserva `firebase/php-jwt`. Los
   refresh tokens opacos se almacenan como hashes SHA-256, rotan por uso y la
   detección de reuso revoca su familia; el access token está ligado a esa familia.
   El frontend mantiene ambos tokens solo en memoria; antes de producción se debe
   evaluar el almacenamiento del refresh token en cookie HttpOnly/Secure y sus
   controles CSRF.
5. **Aislamiento por empresa implementado para categorías.** `X-Company-ID` es solo
   un selector no confiable; el middleware valida membresía del usuario y el
   repositorio restringe las consultas/escrituras de categorías a esa empresa.
   Cada recurso futuro requiere aplicar y probar la misma frontera.
6. **No fijar todavía un esquema de auditoría de negocio.** La persistencia de
   auditoría debe tener claves y relaciones consistentes con empresa/actor; se
   define junto con el modelo de tenant. La correlación de peticiones y los logs
   técnicos se tratan como base transversal, sin registrar secretos.
7. **Stack de UI alineado.** Vuetify se actualizó de v4 a v3 y Vue Router de v5 a
   v4 después de verificar peer dependencies con Vue 3.5/Vite 8. Se añadieron
   Vitest 4, Vue Test Utils y jsdom; la versión 4.0 inicial de Vitest tenía avisos
   de seguridad, por lo que se fijó 4.1.12 y `npm audit` quedó limpio.
8. **Sin certificación implícita.** Los documentos de calidad y seguridad describen
   prácticas y brechas; no constituyen certificación ISO 9001 o ISO/IEC 27001.

## Límites y riesgos principales

| Riesgo | Evidencia actual | Tratamiento previsto |
|---|---|---|
| Acceso entre empresas | Empresas/membresías y categorías aisladas; aún no se ha extendido a recursos futuros | Aplicar contexto tenant, autorización y pruebas negativas en cada módulo antes de exponer sus datos |
| Credenciales de base de datos | Existía un fallback codificado en `config/database.php` | Corregido; secretos solo desde variables/gestor de secretos |
| Exposición de refresh token a XSS | Refresh token permanece en memoria JavaScript y se envía en el body | Migrar a cookie HttpOnly/Secure con política CSRF evaluada antes de producción |
| Bundle frontend | Build válido, pero chunk principal supera 500 kB | Medir y dividir carga de rutas/iconos antes de optimizar |
| Límites hexagonales débiles | El puerto de Catalog devuelve modelos Eloquent y `LengthAwarePaginator` | Refactor gradual al implementar dominio; no propagar tipos de infraestructura |
| Operaciones comerciales ausentes | Sin compras, ventas, inventario ni caja | Implementar secuencialmente con transacciones, idempotencia y concurrencia |
| Contratos y calidad incompletos | OpenAPI inicial y tests frontend de tenancy/categorías | Ampliar trazabilidad y pruebas con cada módulo; faltan gates automáticos completos |
| Auditoría no persistida | No hay tabla/servicio de auditoría | Diseñar con tenant/actor; no crear tablas placeholder sin integridad referencial |

## Evidencia de esta etapa

- Suite backend: 21 tests pasaron (65 assertions), incluidos roles de catálogo,
  login, rotación, replay, expiración y logout aislado por familia.
- Suite frontend: 8 tests pasaron en 3 archivos, incluido refresh y reintento HTTP.
- `vue-tsc -b && vite build` pasó; el bundle JavaScript principal mide 700.10 kB
  minificado y Vite mantiene la advertencia de chunk superior a 500 kB.
- `composer audit` y `npm audit --audit-level=low`: sin advisories.
- OpenAPI YAML parsea con Symfony YAML; no se ejecutó validación completa de esquema.
- Migraciones ejecutadas por `RefreshDatabase` sobre SQLite en memoria; no se
  ejecutaron contra la base configurada fuera del entorno de test.
- Ver el plan incremental en `IMPLEMENTATION_PLAN.md`, los módulos en `MODULES.md`
  y el modelo de datos inicial en `DER.md`.
