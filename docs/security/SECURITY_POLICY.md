# Política de seguridad inicial

## Alcance

Código, datos, dependencias y operación de las aplicaciones Laravel y Vue de
Exabyte. Cubre los datos de usuarios, empresas, membresías y, cuando se incorporen,
información comercial y de transacciones. La infraestructura de hosting, pasarela
de pagos y obligaciones regulatorias quedan fuera hasta que se identifiquen
responsables/proveedores.

## Principios

- Denegar por defecto; autorizar en servidor por usuario, membresía, empresa,
  recurso y acción.
- Minimizar datos recogidos, retenidos y expuestos.
- Separar roles de plataforma y de empresa.
- No almacenar secretos/tokens en el repositorio ni registrar cargas sensibles.
- Proteger cambios comerciales con transacciones, idempotencia y auditoría.
- Evaluar riesgos antes de exponer nuevos datos, integraciones o permisos.
- Reportar y tratar incidentes con evidencia, contención y recuperación.

## Estado y gobierno

La política es una base de trabajo, no una aprobación formal de dirección. Los
responsables nombrados, clasificación contractual, requisitos regulatorios y
aceptación de riesgos residuales siguen pendientes. Revisar al cambiar alcance,
proveedores, amenazas o arquitectura y como mínimo anualmente.

El estado de controles y brechas se mantiene en `STATEMENT_OF_APPLICABILITY.md`;
riesgos iniciales en `RISK_ASSESSMENT.md`.
