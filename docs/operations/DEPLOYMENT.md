# Despliegue

Este documento es un checklist de diseño; no se ha creado infraestructura ni
ejecutado despliegue. No utilizar Docker.

## Requisitos previos a producción

- Elegir y probar el motor relacional soportado por la configuración existente.
- Proveer secretos fuera del repositorio; `APP_DEBUG=false`; TLS en todo tráfico.
- Configurar hosts/orígenes CORS permitidos, cookies/sesiones y proxies confiables.
- Ejecutar Composer/NPM con lockfiles y build frontend reproducible.
- Ensayar migraciones/rollback y carga de datos históricos en staging.
- Configurar queue/scheduler, monitoreo, alertas, backups y restauración.
- Verificar límites, permisos, aislamiento tenant y continuidad antes de release.

No hay CI/CD, manifiestos ni requisitos de plataforma implementados en esta etapa.
