# Desarrollo seguro

## Controles actuales

- Autenticación de contraseñas con hash del framework y JWT mediante
  `firebase/php-jwt`; no se implementa criptografía propia.
- Validación con Form Requests para categorías y empresa.
- Queries parametrizadas vía Eloquent; scope tenant de categorías verificado por
  pruebas API negativas.
- Se eliminó un valor de contraseña MySQL codificado como fallback.
- Composer audit y npm audit se ejecutaron en esta etapa y no reportaron advisories;
  es una observación puntual, no monitoreo continuo.

## Requisitos para cada cambio

1. Validar entradas y autorizar servidor antes de cargar/modificar recursos.
2. Evitar asignación masiva de campos de propiedad/privilegios.
3. Mantener secretos fuera de código/config versionada y revisar logs.
4. Usar transacciones, restricciones e idempotencia para operaciones comerciales.
5. Tratar estados como máquina de transiciones y evitar mutación histórica destructiva.
6. Añadir tests de abuso, límites y tenant además de casos exitosos.
7. Revisar dependencias directas/transitivas, licencia y advisories antes de release.
8. Proteger integraciones/webhooks contra falsificación, replay y filtración.
9. Revisar cambios de seguridad por un responsable independiente cuando sea posible.

## Brechas

No se ejecutaron SAST/DAST, revisión de penetración, validación OWASP ASVS completa,
revisión de CORS/HTTPS en producción ni fuzzing. Las versiones de acceso/refresh
requieren diseño adicional antes de producción.
