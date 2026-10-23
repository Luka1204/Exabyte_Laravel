# Instalación local

## Requisitos observados

- PHP 8.2 o superior y Composer.
- Node.js compatible con el Vite instalado; ver `frontend/package.json`.
- Motor relacional configurado en `backend/.env` o SQLite local.

## Pasos

1. Instalar backend: `composer install --working-dir=backend`.
2. Copiar `backend/.env.example` a `backend/.env`; configurar `APP_KEY`,
   `JWT_SECRET` y conexión de DB. Ver [`CONFIGURATION.md`](./CONFIGURATION.md).
3. Generar clave de app desde `backend/`: `php artisan key:generate`.
4. Ejecutar migraciones de desarrollo: `php artisan migrate`.
5. Instalar frontend: `npm --prefix frontend ci`.
6. Ejecutar backend desde `backend/`: `php artisan serve`.
7. En otra terminal, ejecutar `npm --prefix frontend run dev`.

El frontend usa proxy Vite a `http://localhost:8000`. No se incluyen credenciales de
administración por defecto ni se debe ejecutar un seeder de demostración en producción.
