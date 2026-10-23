# Resolución de problemas

| Síntoma | Diagnóstico inicial |
|---|---|
| Login falla con configuración JWT | Confirmar que `JWT_SECRET` exista y tenga al menos 32 caracteres; nunca imprimirlo |
| API devuelve 401 | Confirmar access token vigente y que no se haya revocado |
| Categorías devuelven 400 | Enviar `X-Company-ID` de una empresa de la que el usuario sea miembro |
| Categorías devuelven 404 | Verificar membership desde `GET /api/v1/companies`; no intentar IDs de otras empresas |
| Migración no conecta a DB | Revisar `DB_CONNECTION` y variables de conexión sin exponer password |
| Frontend no resuelve API | Backend local debe escuchar en puerto 8000 según proxy de Vite |
| Build reporta chunk grande | Revisar carga Vuetify/iconos y división dinámica; el warning actual no es fallo de build |

Para incidentes de acceso cruzado o datos corruptos, preservar evidencia y seguir el
procedimiento de respuesta a incidentes; no copiar tokens/PII a tickets.
