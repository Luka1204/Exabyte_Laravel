# Backup y recuperación

## Política a definir

El entorno productivo debe definir responsable, datos incluidos, frecuencia,
cifrado, ubicación separada, retención, protección frente a borrado/ransomware,
RPO, RTO y controles de acceso. Secretos y datos personales requieren tratamiento
acorde a su clasificación y a obligaciones contractuales/legales.

## Recuperación

1. Abrir incidente/cambio de recuperación y aprobar alcance.
2. Seleccionar backup íntegro y verificar identidad/fecha/checksum.
3. Restaurar en entorno aislado sin exponer datos a usuarios.
4. Ejecutar migraciones en orden aprobado, comprobar integridad referencial y
   reconciliar conteos/totales de negocio.
5. Validar flujos autenticados/tenant y consistencia de ventas-stock antes del
   cutover.
6. Registrar duración, resultado, desviaciones y eliminación segura de copias
   temporales.

No se declara que Exabyte tenga backups productivos configurados ni que una
restauración se haya probado. El proceso debe adaptarse al motor configurado; los
tests actuales en SQLite en memoria no verifican esta capacidad.
