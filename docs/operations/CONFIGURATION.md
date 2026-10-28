# Configuración inicial

## Variables sensibles

No guardar `.env`, claves, contraseñas ni tokens en el repositorio. Copiar
`backend/.env.example` a `.env` para desarrollo local y asignar secretos por entorno
o mediante el gestor de secretos de la plataforma.

`JWT_SECRET` debe contener al menos 32 caracteres; generar un valor aleatorio de
256 bits para cada entorno, por ejemplo:

```powershell
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

No reutilizar valores entre desarrollo, pruebas y producción. No registrar el valor
ni incluirlo en capturas/logs. `JWT_TTL` se expresa en segundos.

## Mercado Pago

Configurar `MERCADOPAGO_ACCESS_TOKEN`, `MERCADOPAGO_PUBLIC_KEY`,
`MERCADOPAGO_WEBHOOK_SECRET`, `MERCADOPAGO_ENVIRONMENT`,
`MERCADOPAGO_WEBHOOK_URL` y `MERCADOPAGO_RETURN_URL` en el entorno de ejecución,
usando credenciales de prueba y `sandbox` para validar. No guardar credenciales en
`config/services.php` ni en el frontend. El checkout alojado de suscripciones usa
el access token en el backend; no requiere exponer la public key al navegador.
En sandbox, el campo `payer_email` enviado a Mercado Pago normalmente es el email
de la cuenta que se registró en la aplicación, independientemente de la sesión
abierta en Mercado Pago. Para usar un usuario comprador de prueba distinto, se
puede configurar `MERCADOPAGO_SANDBOX_PAYER_EMAIL` con el email exacto de ese
usuario; esta opción solo se aplica con `MERCADOPAGO_ENVIRONMENT=sandbox` y no
modifica el email de la cuenta ni se usa en producción.
El prefijo del access token no permite distinguir de forma fiable credenciales de
prueba y producción: Mercado Pago indica que las credenciales de prueba también
pueden comenzar con `APP_USR`. Selecciona el entorno en el panel de Mercado Pago
antes de copiar las claves y configura `MERCADOPAGO_ENVIRONMENT` de acuerdo con
ese entorno; el backend no infiere el entorno a partir del prefijo.
La URL de webhook debe ser HTTPS y coincidir con la registrada en Mercado Pago.
Después de cambiar variables en producción, reconstruir la caché de configuración
de forma controlada y comprobar el flujo con una suscripción sandbox.
Si el checkout devuelve 502, revisar el log `Mercado Pago API request failed`:
`http_status` y `provider_error_codes` identifican rechazos HTTP del proveedor;
`operation` y `transport_error_category` permiten distinguir errores de transporte
(DNS, TLS, conexión o timeout) sin registrar el mensaje original ni credenciales.

## Base de datos

- `DB_CONNECTION` elige la conexión; `.env.example` usa SQLite para desarrollo.
- Para MySQL/MariaDB, configurar `DB_HOST`, `DB_PORT`, `DB_DATABASE`,
  `DB_USERNAME` y `DB_PASSWORD` explícitamente.
- La configuración no provee una contraseña por defecto. Un entorno sin
  `DB_PASSWORD` no debe depender de un valor codificado en el repositorio.
- PHPUnit usa SQLite en memoria según `backend/phpunit.xml`; esto no sustituye una
  ejecución de migraciones y pruebas en el motor de producción.

## Producción

Establecer `APP_ENV=production`, `APP_DEBUG=false`, HTTPS y orígenes CORS explícitos
cuando se configure CORS. Proveer claves mediante variables seguras, limitar
privilegios de DB y comprobar que logs/backups no incluyan secretos. La lista es
una guía inicial, no una validación de despliegue.
