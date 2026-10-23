# Contrato OpenAPI

La especificación [`openapi.yaml`](./openapi.yaml) describe las cinco rutas REST
implementadas actualmente bajo `/api/v1`. Está limitada deliberadamente a la
superficie observada: no documenta endpoints de productos, tenants, ventas,
suscripciones ni pagos que aún no existen.

## Actualización

Al cambiar una ruta, Form Request, Resource o respuesta:

1. Actualizar ruta/controlador y pruebas de contrato.
2. Actualizar `openapi.yaml` en el mismo cambio con status, validación,
   autenticación y esquema de respuesta verificados.
3. Validar sintaxis YAML y compatibilidad OpenAPI 3.1 con el validador elegido
   por el proyecto.
4. Verificar que los endpoints protegidos sigan denegando tokens ausentes,
   inválidos, vencidos o revocados.

La sintaxis YAML se validó con Symfony YAML en esta etapa. No se ejecutó un
validador de esquema OpenAPI.
