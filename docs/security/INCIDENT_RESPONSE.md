# Respuesta a incidentes de seguridad

## Flujo

1. **Detectar y reportar:** preservar hora, sistema, tenant afectado y síntoma sin
   copiar credenciales/token o cargas completas.
2. **Triage:** responsable de seguridad/operaciones clasifica confidencialidad,
   integridad, disponibilidad, alcance y obligaciones de notificación.
3. **Contener:** revocar credenciales/sesiones, limitar rutas o aislar componente;
   preservar evidencia y mantener trazabilidad de acciones.
4. **Erradicar y recuperar:** corregir causa, verificar artefacto, restaurar datos
   cuando corresponda y validar integridad antes de habilitar servicio.
5. **Comunicar:** notificar dirección, clientes/proveedores/autoridades según
   contratos y asesoramiento legal; no improvisar declaraciones regulatorias.
6. **Aprender:** registrar causa evidenciada, impacto, tiempos, acciones y tests de
   regresión; revisar riesgos y controles.

## Preparación y brechas

El propietario de guardia, canales de escalamiento, SLA, requisitos legales,
retención de evidencia y ejercicios de tabletop deben ser definidos por la
organización. Este procedimiento aún no ha sido ensayado.
