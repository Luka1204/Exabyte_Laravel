# Gestión de cambios

## Flujo requerido

1. Todo cambio identifica necesidad, alcance, impacto, requisitos y riesgos.
2. Los cambios de esquema identifican migración hacia delante, rollback,
   compatibilidad con datos existentes y ventana operativa.
3. Cambios de contrato identifican consumidores y actualizan OpenAPI.
4. Cambios de permisos/tenant incluyen pruebas negativas de acceso.
5. Un revisor distinto del autor valida reglas, seguridad, pruebas y documentación.
6. La integración requiere build, pruebas relevantes y revisión de dependencias.
7. La liberación referencia cambios y evidencia; la reversión debe tener criterio.
8. Incidentes/defectos generan corrección, causa y acción preventiva cuando aplique.

## Evidencias mínimas

- Issue/requisito y criterios de aceptación.
- Diff revisado; migraciones reversibles o limitación documentada.
- Resultado real de tests, build, análisis de dependencias y smoke test.
- Contrato/documentos actualizados.
- Riesgos residuales, plan de rollback y responsable de release.

No se afirma que exista actualmente un flujo formal de aprobación, firma digital,
CI obligatorio ni segregación de funciones organizacional.
