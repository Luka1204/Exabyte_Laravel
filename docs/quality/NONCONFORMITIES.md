# No conformidades y defectos

## Registro

No se registran auditorías ni no conformidades formales ficticias. Los hallazgos
técnicos observados y corregidos durante implementación se consignan en commits,
tests y el informe de trabajo; deben migrarse al sistema de incidentes/issues que
defina el equipo si requieren seguimiento formal.

## Procedimiento

Para cada defecto/no conformidad real, registrar:

- referencia, fecha y versión/entorno;
- requisito afectado, síntoma y evidencia reproducible;
- impacto/riesgo y contención inmediata;
- causa analizada (no asumir causa sin evidencia);
- corrección, acción correctiva/preventiva, responsable y vencimiento;
- test de regresión y evidencia de cierre;
- revisión de efectividad y recurrencia.

Los incidentes de seguridad siguen además el proceso de
[`INCIDENT_RESPONSE.md`](../security/INCIDENT_RESPONSE.md). No almacenar secretos,
tokens o datos personales innecesarios en el registro.
