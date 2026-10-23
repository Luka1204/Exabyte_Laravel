# Exabyte API

API REST de Exabyte construida con Laravel 12.

## Preparar un entorno local

1. Copiá `.env.example` como `.env` y configurá la conexión a la base de datos local
   que uses para el proyecto. No compartas ni subas ese archivo.
2. Generá las claves requeridas por Laravel y el servicio JWT:

   ```powershell
   php artisan key:generate
   ```

   Configurá `JWT_SECRET` en `.env` con un secreto aleatorio propio antes de iniciar
   la API.
3. Configurá las variables del usuario de demostración en `.env`:

   ```dotenv
   ERP_INITIAL_ADMIN_NAME="Exabyte Demo"
   ERP_INITIAL_ADMIN_EMAIL=demo@exabyte.local
   ERP_INITIAL_ADMIN_PASSWORD="reemplazar-por-una-clave-unica-de-16-o-mas-caracteres"
   ERP_INITIAL_COMPANY_NAME="Exabyte Demo"
   ```

   La contraseña no tiene un valor por defecto y no debe guardarse en el repositorio.
   El seeder inicial solo se puede ejecutar en los entornos `local` y `testing`.
4. Aplicá las migraciones y creá la cuenta inicial junto con su empresa y rol owner:

   ```powershell
   php artisan migrate --seed
   ```

   Para volver a crear o actualizar solo esa cuenta de desarrollo:

   ```powershell
   php artisan db:seed --class=AdminUserSeeder
   ```

La cuenta predeterminada es `demo@exabyte.local`; usá la contraseña definida en
`ERP_INITIAL_ADMIN_PASSWORD` para iniciar sesión en el frontend. El seeder es
idempotente: crea o actualiza esa cuenta y garantiza su membresía owner en la empresa
configurada.

## Validación

```powershell
php artisan test
```
