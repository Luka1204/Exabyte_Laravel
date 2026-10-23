# Riesgos técnicos y de seguridad iniciales

Registro preliminar derivado de inspección estática y comandos ejecutados. No es una
certificación, auditoría formal ISO/IEC 27001 ni evaluación completa de amenazas.
La probabilidad/impacto formal y el tratamiento deben acordarse con responsables
del producto/operación; no se asignan puntuaciones sin metodología ni evidencia.

| Activo/superficie | Amenaza o brecha observada | Estado | Tratamiento requerido |
|---|---|---|---|
| Credenciales de base | Había un valor de contraseña codificado como fallback MySQL | Corregido en este cambio; usar entorno/gestor de secretos | Revisar todos los entornos y rotar cualquier secreto real que hubiera sido expuesto |
| Datos de empresas | Membresías implementadas y categorías scoped; otros recursos todavía no existen | Parcial | Aplicar resolución/validación del tenant, policies, claves y pruebas cruzadas a cada agregado |
| Sesiones | Refresh rota y revoca familias, pero el refresh token vive en memoria JavaScript | Parcial | Evaluar cookie HttpOnly/Secure, CSRF, expiración y pruebas; no persistir tokens en almacenamiento web |
| Categorías | Middleware comprueba membership y repositorio filtra `company_id` | Implementado y probado con pruebas negativas | Mantener pruebas de no-miembro, spoofing y aislamiento en cada cambio |
| Dependencias de UI | Vuetify 3 y Vue Router 4 se alinearon al stack objetivo; el árbol instalado versionado puede requerir reinstalación | Corregido en manifests y lockfile | Ejecutar instalación reproducible desde lockfile en el entorno de desarrollo |
| Logging/auditoría | No hay auditoría persistente de negocio; revisar redacción de logs | Pendiente | Definir eventos, minimización, retención, actor/tenant y controles de acceso |
| Recuperación | No hay evidencia de backups restaurados | No verificado | Definir RPO/RTO y ensayar restauración en entorno aislado |
| Frontend | Build no pasaba por import sin uso; token solo vive en memoria | Import corregido; sesión no persistente | Build y pruebas; decidir almacenamiento seguro al completar identidad |

Los análisis ejecutados en esta etapa no reportaron advisories en Composer ni
vulnerabilidades en `npm audit`. Esto no demuestra ausencia de vulnerabilidades en
código, configuración, infraestructura o runtime.
