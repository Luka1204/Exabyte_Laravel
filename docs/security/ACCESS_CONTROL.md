# Control de acceso

## Estado implementado

- JWT autentica al usuario y valida firma/expiración/versión de token.
- `GET /api/v1/companies` lista las memberships del usuario autenticado.
- La creación de empresa crea membership `owner` en la misma transacción.
- `X-Company-ID` se trata como entrada no confiable. El middleware verifica
  membership antes de continuar; una empresa ajena devuelve 404.
- Las consultas/escrituras actuales de categorías filtran por `company_id`, que
  procede del middleware y no de datos de categoría enviados por el cliente.
- Las rutas de login tienen un throttle de 5 solicitudes/minuto.

## Brechas y reglas

- No hay RBAC/permissions granular, invitaciones, administración de miembros ni
  administrador global.
- La regla actual permite a cualquier miembro modificar/consultar categorías; no
  reutilizarla automáticamente para acciones comerciales sensibles.
- Todo endpoint futuro debe probar usuario no autenticado, miembro sin permiso,
  empresa ajena, recurso ajeno dentro del tenant y tenant faltante.
- No aceptar `tenant_id/company_id` en DTOs del cliente para determinar propiedad.
- Revisar sesiones administrativas, exportaciones, reportes, colas y tareas cron.
- El frontend oculta/expone UI por conveniencia solamente; nunca autoriza.
