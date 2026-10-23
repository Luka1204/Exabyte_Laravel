# Diagramas de secuencia

## Flujos implementados actualmente

Los diagramas siguientes describen únicamente flujos implementados. El login
entrega un access token JWT y un refresh token opaco; el refresh rota el token y el
reuso revoca su familia. El logout revoca solo la sesión actual. No hay checkout ni
persistencia de auditoría.

### Inicio de sesión y selección de empresa

```mermaid
sequenceDiagram
    actor User as Usuario
    participant SPA as Vue SPA
    participant API as Laravel API /api/v1
    participant DB as Base de datos
    participant JWT as JwtTokenService

    User->>SPA: Envía email y contraseña
    SPA->>API: POST /auth/login
    API->>API: throttle 5 por minuto + validación
    API->>DB: Busca usuario por email
    DB-->>API: Usuario o inexistente
    API->>API: Verifica hash de contraseña
    API->>JWT: Crea familia y refresh token aleatorio (persiste solo hash)
    API->>JWT: Emite access JWT HS256 con sid de familia
    API-->>SPA: access_token, refresh_token, expiraciones, user
    SPA->>SPA: Mantiene sesión en Pinia en memoria
    SPA->>API: GET /companies con JWT
    API->>DB: Consulta membresías del usuario
    DB-->>API: Empresas y roles de membresía
    API-->>SPA: Empresas disponibles
    User->>SPA: Selecciona empresa
    SPA->>SPA: Guarda la empresa activa en memoria
```

### Refresh rotativo y logout

```mermaid
sequenceDiagram
    participant SPA as Vue SPA
    participant API as Laravel API
    participant Session as RefreshSession
    participant DB as Base de datos

    SPA->>API: POST /auth/refresh con refresh_token actual
    API->>Session: Busca hash y bloquea fila en transacción
    Session->>DB: Revoca token anterior e inserta el siguiente de la familia
    DB-->>Session: Confirmación
    Session-->>API: Nuevo access JWT y refresh token
    API-->>SPA: Par rotado
    SPA->>API: POST /auth/logout con access JWT
    API->>DB: Revoca tokens activos de la familia sid
    DB-->>API: Confirmación
    API-->>SPA: Sesión revocada
```

### Consulta de categorías y creación

```mermaid
sequenceDiagram
    actor User as Usuario
    participant SPA as Vue SPA
    participant API as Laravel API
    participant Auth as AuthenticateJwt
    participant UseCase as Caso de uso Catalog
    participant Repo as EloquentCategoryRepository
    participant DB as Base de datos

    User->>SPA: Abre categorías o envía el formulario
    SPA->>API: GET /categories o POST /categories + JWT y X-Company-ID
    API->>Auth: Valida firma, expiración, token_version y familia activa
    Auth-->>API: Usuario autenticado
    API->>DB: Verifica membresía a la empresa seleccionada
    DB-->>API: Membresía válida o ausente
    API->>API: Rechaza no miembros con 404
    API->>UseCase: Lista o crea categoría con company_id validado
    UseCase->>Repo: Pagina / comprueba duplicado y persiste por empresa
    Repo->>DB: Consulta / INSERT
    DB-->>Repo: Resultado persistido
    Repo-->>UseCase: Resultado
    UseCase-->>API: Resultado del caso de uso
    API-->>SPA: JSON paginado o 201
```

## Flujos requeridos, aún no implementados

No se dibujan como si fueran comportamiento real:

1. Escaneo y confirmación de venta.
2. Recepción de compra e inventario.
3. Creación/actualización de suscripción.
4. Devolución de venta.

Se agregarán diagramas de esos flujos cuando existan endpoints, casos de uso,
transacciones e invariantes verificables.
