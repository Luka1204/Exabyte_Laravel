# Estrategia inicial de pruebas

## Estado comprobado

- Backend: PHPUnit 11.5.57 y `RefreshDatabase`; pruebas actuales de ejemplo, JWT y
  API de categorías.
- Frontend: Vitest y Vue Test Utils no están instalados/configurados. No se reportan
  pruebas frontend hasta que existan.
- Los tests backend fuerzan SQLite `:memory:` y no ejercitan la base local ni la
  configuración de producción.

## Comandos del repositorio

Desde el root:

```powershell
composer --working-dir=backend test
npm --prefix frontend run build
```

Al añadir pruebas frontend, documentar y usar el script de test definido en
`frontend/package.json`; no considerar el build como sustituto de pruebas.

## Pirámide y prioridades

1. **Dominio**: reglas puras y objetos de valor, sin Laravel/Eloquent.
2. **Aplicación**: casos de uso, límites transaccionales, idempotencia.
3. **Persistencia**: repositorios, restricciones/índices y comportamiento de locks.
4. **API**: Form Requests, Resources, status/error contract, autenticación,
   policies y paginación.
5. **Frontend**: formularios, estados de carga/error, routing/permisos y servicios
   API con stubs de transporte controlados.
6. **Integración crítica**: ventas-stock-pagos-caja, compras-recepción-stock,
   aislamiento multiempresa y reintentos.

## Evidencias mínimas por cambio

- Asociar cada requisito con caso de uso y test reproducible.
- Probar los resultados permitidos y denegados, no solo respuestas exitosas.
- Probar invariantes financieras con decimales exactos, reintentos/idempotencia y
  transacciones antes de cerrar ventas/compras.
- Probar el tenant en el servidor: consultas, relaciones, escrituras, reportes,
  colas y exportaciones; incluir intento deliberado de acceder a otro tenant.
- Mantener datos de test no sensibles y fixtures explícitos.
- Registrar el comando ejecutado y su resultado real en el informe de etapa; no
  presentar cobertura ni auditorías que no se midieron.

## Puertas de calidad

Una etapa no se cierra si falla el build, las pruebas de regresión, las migraciones
en el motor configurado o los tests de acceso denegado correspondientes. Una
validación satisfactoria en SQLite no demuestra compatibilidad con MySQL/PostgreSQL;
ejecutar validación en el motor objetivo antes de liberar.
