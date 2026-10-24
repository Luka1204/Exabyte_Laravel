# Plan incremental de implementación

Este plan parte del código existente y evita presentar módulos no implementados
como entregados. Cada etapa se cierra con evidencia reproducible y documentación
actualizada.

## Fase 0 — Diagnóstico y diseño

- [x] Inspeccionar manifiestos, rutas, modelos, migraciones, módulos, frontend y tests.
- [x] Documentar estado actual, arquitectura objetivo, dependencias y riesgos.
- [x] Crear DER y diagrama de clases iniciales basados en el esquema/código existente.
- [x] Especificar el contrato REST existente en OpenAPI.
- [ ] Generar SVG de los Mermaid al disponer de un renderer compatible.

**Criterios de aceptación:** modelos actuales diferenciados de los propuestos; cada
relación del DER tiene evidencia de migración; dependencias y riesgos sin afirmar
controles no verificados.

## Fase 1 — Fundamentos

- [x] Corregir errores detectados en build y paginación; añadir regresión.
- [x] Eliminar el fallback codificado de la contraseña MySQL.
- [x] Documentar arquitectura modular, contrato OpenAPI y estrategia de pruebas.
- [ ] Definir formato de errores API y correlation ID.
- [ ] Establecer convención completa de logging sensible y revisión automática.
- [x] Configurar runner de pruebas frontend; añadir casos de tenant/store y headers.
- [ ] Implementar auditoría persistente con tenant/actor, retención y pruebas de integridad.

**Criterios de aceptación:** build frontend y suite PHPUnit pasan; migraciones
ejecutables en la DB configurada y SQLite de test; contratos API versionados;
errores sin detalles internos; logs sin credenciales/tokens; auditoría vinculable
al tenant y actor, con pruebas de integridad y aislamiento.

## Fase 2 — Identidad y multiempresa

- [x] Definir empresas, membresías iniciales y tenant activo autorizado en rutas de catálogo.
- [x] Completar refresh JWT con rotación, hash persistido, detección de reuso y revocación por familia.
- [x] Seleccionar empresa en frontend y enviar selector validado en llamadas de catálogo.
- [x] Añadir policy de categorías: cualquier miembro puede listar; owner/manager pueden crear.
- [ ] Extender roles/permisos y policies a todos los recursos y operaciones.
- [x] Permitir al owner listar miembros, cambiar roles member/manager y quitar membresías no-owner.
- [x] Integrar la administración de membresías en la vista de empresas y probar el cliente API.
- [ ] Implementar invitaciones/altas de nuevos miembros y transferencia controlada de ownership.
- [ ] Agregar invitaciones, perfil, recuperación de contraseña y rate limits.
- [x] Incorporar pruebas negativas de acceso entre empresas y spoofing de company_id para categorías.
- [ ] Completar registro, recuperación de contraseña y administración de sesiones.
- [ ] Añadir roles/permisos granulares, invitaciones y administración completa de miembros.

**Criterios de aceptación:** cada operación tenant resuelve empresa desde contexto
autenticado, verifica membresía en servidor y rechaza IDOR/acceso cruzado; pruebas
de tokens expirados/revocados y permisos denegados.

**Migración de datos:** las categorías previas conservan `company_id = NULL` y se
excluyen del API tenant-scoped. No se asignarán automáticamente a una empresa hasta
que el propietario de los datos autorice y valide el mapeo.

## Fase 3 — SaaS y membresías comerciales

- [ ] Modelar Plan, Entitlement, Subscription, períodos, estados e historial.
- [ ] Aplicar límites de uso mediante políticas/servicios, nunca en UI.
- [ ] Incorporar administración de plataforma separada de administración de empresa.
- [ ] Diseñar adaptador de pagos/webhooks verificables; sin cobros simulados.

**Criterios de aceptación:** límites probados por estado/plan; cambios auditables;
sin privilegios administrativos derivados de la suscripción; pasarela marcada
pendiente hasta su integración real.

## Fase 4 — Catálogo y partes comerciales

- [x] Añadir scope tenant a categorías; filas existentes permanecen sin asignación.
- [x] Implementar productos, categorías, SKU, códigos de barras, precios e impuestos para el flujo MVP.
- [ ] Implementar clientes y proveedores con reglas de identificación/duplicidad.
- [ ] Incorporar importación/exportación controlada y pruebas de autorización.

**Criterios de aceptación:** unicidad de SKU/código definida por tenant; precios
validados en servidor; consultas y exportaciones filtradas por empresa.

## Fase 5 — Compras e inventario

- [ ] Modelar sucursales y depósitos; el MVP actual mantiene stock por empresa y producto.
- [ ] Implementar compras/recepciones parciales; fuera del alcance del corte MVP actual.
- [x] Implementar saldos, libro de movimientos, ajustes idempotentes y control transaccional de concurrencia.
- [x] Implementar restitución de stock trazable al anular ventas dentro de la caja abierta.
- [x] Implementar devoluciones parciales/totales con restitución de stock y reintegro conciliado en caja.

**Criterios de aceptación:** recepción/reintento no duplica movimientos; movimientos
son la fuente de trazabilidad; pruebas de transacción, tenant y concurrencia.

## Fase 6 — POS, ventas y caja

- [x] Implementar apertura/cierre de caja, arqueo y conciliación de efectivo para una caja por empresa.
- [x] Implementar búsqueda por nombre/SKU y lectura HID con sufijo configurable por estación.
- [x] Permitir configurar el sufijo del lector HID por estación (Enter, Tab o ingreso manual).
- [x] Confirmar venta, detalle, pago en efectivo y stock dentro de una operación consistente.
- [x] Añadir idempotencia, tickets internos y anulación autorizada con restitución de stock/efectivo.

**Criterios de aceptación:** el servidor recalcula totales; venta nunca se confirma
sin persistir inventario; doble envío es idempotente; devolución respeta cantidades;
pruebas de lector con entrada de teclado y de caja cerrada.

## Fase 7 — Reportes

- [x] Añadir resumen agregado de ventas/devoluciones/anulaciones con filtro temporal y permiso tenant.
- [x] Añadir reportes de inventario y caja con filtro temporal y aislamiento por empresa.
- [ ] Añadir reporte de compras cuando se implemente el flujo de compras/recepciones.
- [ ] Medir consultas y agregar índices justificados; prevenir N+1.

**Criterios de aceptación:** cifras derivadas de registros autorizados, filtros
tenant, sin datos demo, y resultados contrastados con casos conocidos. Los reportes
actuales de inventario y caja están aislados por empresa; aún no existe dimensión
de sucursal/depósito ni reporte de compras.

## Fase 8 — Seguridad y calidad

- [ ] Mantener evaluación de riesgos, aplicabilidad/evidencias y controles.
- [ ] Añadir pruebas de seguridad, regresión, aislamiento y consistencia.
- [ ] Revisar dependencias, permisos, retención de auditoría y restauración de backups.
- [ ] Completar trazabilidad requisito-caso de uso-prueba-evidencia.

**Criterios de aceptación:** controles declarados implementados solo con evidencia;
incidentes, riesgos residuales y brechas documentados; no afirmar certificación.

## Fase 9 — Preparación de producción

- [ ] Verificar secretos/HTTPS/CORS, rate limits, monitoreo y configuración segura.
- [ ] Ejecutar restauración de backup en entorno de prueba.
- [ ] Validar rendimiento medido, procedimientos operativos y checklist de release.

**Criterios de aceptación:** pruebas de humo/integración aprobadas, rollback y
recuperación ensayados, umbrales de liberación acordados y documentación vigente.
