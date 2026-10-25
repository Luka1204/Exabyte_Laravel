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

### Datos de demostración

En `local` y `testing`, `php artisan migrate --seed` también ejecuta:

- `PlanCatalogSeeder`: tres planes activos con importes **ilustrativos**, solo para
  que el catálogo público tenga contenido al desarrollar. No son precios aprobados
  para clientes.
- `DemoCommerceSeeder`: categoría y productos de muestra, saldos y libro de
  movimientos, una caja cerrada, una venta y una devolución.

Los seeders operativos usan claves estables e inserción idempotente; volver a
ejecutarlos no reinicia saldos ni sobrescribe operaciones existentes. También
registran una excepción de acceso local para la empresa de muestra.

No se crean suscripciones, pagos, eventos de Mercado Pago ni usuarios Superadmin
ficticios: esos registros serían engañosos y podrían confundirse con contratos,
cobros o permisos reales. Tampoco se siembran refresh tokens, sesiones, trabajos,
cache ni tokens de recuperación, porque son datos temporales que deben generarse
durante el uso de la aplicación. Para producción se requiere crear planes aprobados
desde Superadmin y conceder ese permiso con el comando documentado abajo; `db:seed`
está bloqueado fuera de `local` y `testing`.

## Validación

```powershell
php artisan test
```

## Mercado Pago (sandbox)

La integración usa suscripciones recurrentes alojadas de Mercado Pago; Exabyte no
recibe ni almacena datos de tarjetas. Para probar el flujo, configurá credenciales
de prueba y la URL HTTPS pública del webhook en `.env`, usando los nombres ficticios
documentados en `.env.example`. Registrá esa misma URL y el secreto de firma en la
configuración de notificaciones de la aplicación de Mercado Pago. No copies
credenciales de producción a entornos locales o de prueba.

Las notificaciones se guardan y se procesan mediante la cola. En desarrollo, ejecutá
un worker además de la API:

```powershell
php artisan queue:work --tries=3
```

La URL de retorno del navegador no confirma cobros. Una suscripción solo se activa
después de validar la firma y consultar en Mercado Pago el estado, importe y moneda
del pago autorizado. Las pruebas automatizadas usan datos controlados; no generan
cobros reales. La configuración por sí sola no sustituye una prueba de extremo a
extremo con una cuenta sandbox.

## Concesión de Superadmin

Superadmin es independiente de los roles de empresa. Creá primero una cuenta por el
flujo de alta de cuentas o por el procedimiento seguro de identidad de la instalación,
y luego otorgá el permiso mediante un comando ejecutado por un operador autorizado:

```powershell
php artisan platform-admin:set admin@example.com
```

El comando solo acepta una cuenta existente y deja un evento de auditoría. Para
revocar el acceso:

```powershell
php artisan platform-admin:set admin@example.com --revoke
```

No hay credenciales Superadmin predefinidas. El usuario de demostración del seeder
es únicamente para los entornos `local` y `testing`; no uses su configuración ni
ejecutes `php artisan migrate --seed` con credenciales de demostración en producción.
Restringí el acceso al host y a las credenciales del operador que ejecuta el comando.

## Estado de preparación comercial

La administración de planes, la contratación inicial y el enforcement de
funcionalidades de catálogo/inventario/POS/reportes más el límite de productos están
implementados. Los límites de usuarios/sucursales/cajas, la reconciliación programada,
los controles de recuperación y la validación sandbox/proveedor aún son pendientes.
Las empresas existentes durante la migración reciben una excepción no expirable para
evitar un bloqueo retroactivo; requieren una migración comercial explícita. No habilites
cobros ni clientes reales hasta completar y documentar
esas verificaciones, configurar HTTPS, worker, monitoreo y backups, y aprobar
legalmente términos y privacidad.
