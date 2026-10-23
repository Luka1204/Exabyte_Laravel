# Declaración de aplicabilidad inicial

Evaluación preliminar tomando como referencia ISO/IEC 27001:2022 Anexo A y
OWASP ASVS. La aplicabilidad debe ser aprobada tras definir alcance, riesgos,
proveedores y requisitos regulatorios. “Parcial” significa evidencia limitada en
repositorio, no cumplimiento total. No se declara certificación.

| Control de referencia | Aplicabilidad | Justificación | Estado observado | Evidencia / brecha |
|---|---|---|---|---|
| A.5.15 Access control | Sí | API de empresa y datos multiempresa | Parcial | Membership comprobado en categorías; sin RBAC completo |
| A.5.16 Identity management | Sí | Usuarios y JWT existentes | Parcial | Login, refresh rotativo y revocación por sesión; sin registro ni recuperación |
| A.5.17 Authentication information | Sí | Contraseñas, secreto JWT, DB credentials | Parcial | Hash Laravel y secret por env; gestión productiva no verificada |
| A.5.18 Access rights | Sí | Roles y memberships requeridos | Parcial | Rol owner al crear empresa; cambios/revisión de accesos pendientes |
| A.5.23 Information security for use of cloud services | Sí al desplegar | SaaS en infraestructura/proveedores | No verificado | Proveedor/arquitectura cloud no definido |
| A.5.24–A.5.28 Incident management | Sí | Servicio SaaS requiere respuesta y evidencia | Documentado inicialmente | Runbook preliminar; responsables y ejercicios pendientes |
| A.5.29–A.5.30 Continuity and ICT readiness | Sí | Operación comercial depende de disponibilidad/datos | No verificado | Backup/restore y continuidad sin prueba |
| A.5.31 Legal/regulatory requirements | Sí | Clientes, impuestos, pagos y Argentina | Pendiente | Alcance legal/fiscal no definido |
| A.5.34 Privacy and protection of PII | Sí | Datos de cuentas/empresas/clientes | Parcial | Minimización como principio; evaluación legal/retención pendiente |
| A.5.37 Documented operating procedures | Sí | Releases, backup, incidentes | Parcial | Documentos iniciales; ejecución real no probada |
| A.8.2 Privileged access rights | Sí | Administración global/empresa | Pendiente | Admin plataforma y elevación controlada no implementados |
| A.8.3 Information access restriction | Sí | Aislamiento tenant/recurso | Parcial | Categorías filtradas y tests negativos |
| A.8.5 Secure authentication | Sí | API JWT y rate limiting | Parcial | Throttle login/refresh, rotación y detección de reuso; refresh token aún accesible al runtime JavaScript |
| A.8.8 Management of technical vulnerabilities | Sí | Dependencias PHP/npm | Parcial | Auditoría Composer/npm de esta etapa; proceso continuo no configurado |
| A.8.9 Configuration management | Sí | DB, JWT, CORS, producción | Parcial | Password fallback eliminado; despliegue/CORS no verificados |
| A.8.13 Information backup | Sí | Datos financieros futuros | No verificado | Sin evidencia de restauración |
| A.8.15 Logging | Sí | Seguridad y trazabilidad | Parcial | Logging framework; no hay auditoría comercial ni revisión de redacción |
| A.8.16 Monitoring activities | Sí | Detección de abuso/incidentes | No verificado | Métricas/alertas operativas no configuradas |
| A.8.24 Use of cryptography | Sí | JWT, HTTPS, backup y secretos | Parcial | JWT library; TLS/cifrado de backups en hosting no verificados |
| A.8.25–A.8.29 Secure development and testing | Sí | Desarrollo de API, SPA y operaciones financieras | Parcial | Tests API y frontend; revisión formal, SAST y pipeline pendientes |

Los controles organizacionales y técnicos no citados requieren análisis de alcance
antes de declarar no aplicabilidad. Este registro no sustituye la Statement of
Applicability aprobada dentro de un SGSI.
