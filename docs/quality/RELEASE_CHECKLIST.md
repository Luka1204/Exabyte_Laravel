# Checklist de liberación

## Antes de aprobar

- [ ] Requisitos y criterios de aceptación trazables a casos de uso y evidencia.
- [ ] Revisión de código completada y hallazgos bloqueantes resueltos.
- [ ] Suite backend y pruebas de frontend aplicables aprobadas.
- [ ] `vue-tsc`/build, lint configurado y auditoría de dependencias ejecutados.
- [ ] Migraciones verificadas en el motor configurado y con datos compatibles.
- [ ] Aislamiento tenant validado para endpoints, jobs, reportes y exportaciones.
- [ ] Transacciones, idempotencia, concurrencia financiera y permisos probados.
- [ ] OpenAPI, DER, diagramas, riesgos y runbooks sincronizados.
- [ ] Secretos fuera del repo; debug deshabilitado; HTTPS/CORS/rate limits revisados.
- [ ] Backup reciente disponible y restauración probada según RPO/RTO acordados.
- [ ] Alertas, monitoreo, soporte, rollback y responsable de guardia definidos.

## Decisión

Estado: **plantilla; no es evidencia de una liberación**. Registrar versión, entorno,
ejecutor, fecha, evidencias y aceptación explícita antes de marcar cada punto.
