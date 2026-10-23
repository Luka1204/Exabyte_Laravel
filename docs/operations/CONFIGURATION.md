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
