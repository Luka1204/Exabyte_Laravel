# Evaluación inicial de riesgos

## Método

Evaluación cualitativa preliminar de código/configuración observados, no auditoría
formal. Impacto: Bajo / Medio / Alto / Crítico. Probabilidad: Baja / Media / Alta,
estimada según exposición y controles observados. Nivel resultante: juicio
cualitativo, no cálculo actuarial. Responsable se expresa como función por asignar.
Revisar y validar con responsables de negocio/operación antes de aceptar riesgo.

| Activo | Amenaza | Vulnerabilidad observada | Impacto | Probabilidad inicial | Nivel | Tratamiento | Responsable | Estado |
|---|---|---|---|---|---|---|---|---|
| Datos comerciales multiempresa | Acceso/alteración entre tenants | Solo categorías tienen filtro de tenant; otros dominios aún no existen | Crítico | Media | Alto | Exigir contexto/membership en cada agregado, constraints y tests negativos antes de producción | Tech lead + seguridad | En tratamiento |
| Categorías | IDOR / lectura/escritura cruzada | Riesgo mitigado mediante middleware de membresía y `company_id`; cobertura limitada a este recurso | Alto | Baja en rutas probadas | Medio residual | Mantener pruebas de no-miembro, spoofing y aislamiento en regresión | Responsable Catalog | Parcial |
| Sesiones y cuentas | Robo/reutilización de token | Se agregó refresh rotativo con hash persistido y revocación de familia ante reuso; el token cliente permanece en memoria JS | Alto | Media | Alto residual | Evaluar cookie HttpOnly/Secure y CSRF; mantener rate limits y pruebas de reuso/expiración | Responsable Identity | En tratamiento |
| Credenciales de DB | Acceso no autorizado a persistencia | Se retiró password fallback; seguridad real de env/secret manager no verificada | Crítico | Desconocida | Alto residual | Rotar secretos expuestos, gestionar por entorno, mínimo privilegio y validar despliegues | Operaciones | Pendiente |
| Integridad financiera | Duplicación/pérdida de venta o stock | POS/inventario aún no implementados | Crítico | Media al implementar sin controles | Alto previo | Diseñar transacciones, lock/concurrencia e idempotencia y probar fallos/reintentos | Responsable comercial/backend | Pendiente |
| Evidencia y detección | Repudio y filtración por logs | Auditoría persistente y política de retención ausentes | Alto | Media | Alto | Diseñar eventos mínimos tenant/actor, control de acceso, integridad y redacción | Seguridad/operaciones | Pendiente |
| Disponibilidad/recuperación | Pérdida de datos | No hay evidencia de backup restaurado ni RPO/RTO acordado | Alto | Desconocida | Alto residual | Definir RPO/RTO, backup cifrado, prueba de restauración y runbook | Operaciones | Pendiente |

Riesgos residuales no se aceptan automáticamente por aparecer en este documento.
No se han hecho pruebas de intrusión, restauración ni auditoría formal.
