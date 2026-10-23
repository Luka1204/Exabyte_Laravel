# Gestión de calidad del software

## Propósito y alcance

Proceso inicial para el desarrollo y operación del monolito Exabyte. Orienta la
gestión de requisitos, verificación y mejora según principios aplicables de
ISO 9001:2015; no demuestra conformidad ni certificación del sistema de gestión.

## Responsabilidades

- **Product owner:** define prioridad, reglas comerciales y criterios de aceptación.
- **Responsable técnico:** mantiene arquitectura, revisa cambios y evidencia de gates.
- **Desarrollador:** implementa, añade pruebas y actualiza contratos/documentación.
- **Operación:** valida configuración, backups, restauración y liberaciones.
- **Seguridad:** revisa riesgos, acceso, incidentes y controles aplicables.

Los roles podrán ser asignados a personas por la organización; este repositorio no
registra nombramientos formales.

## Ciclo de trabajo

1. Registrar requisito con criterio verificable y dependencias.
2. Vincular requisito a módulo, caso de uso, contrato API y prueba.
3. Analizar riesgo/cambio de esquema/compatibilidad antes de implementar.
4. Implementar dentro de límites de módulo y revisar código.
5. Ejecutar pruebas focalizadas, suite aplicable, build y migraciones.
6. Registrar resultados reales y defectos, incluidos gates fallidos.
7. Actualizar documentación, DER/OpenAPI y controles afectados.
8. Liberar solo con checklist aprobado y recuperación definida.
9. Revisar incidentes y acciones correctivas para mejora continua.

## Indicadores

Medir por versión/periodo definido; no hay resultados históricos declarados todavía.

| Indicador | Fórmula | Fuente |
|---|---|---|
| Aceptación verificada | criterios aprobados / criterios aplicables × 100 | requisitos y evidencia de test |
| Pruebas críticas aprobadas | pruebas críticas aprobadas / ejecutadas × 100 | informes del runner |
| Defectos escapados | defectos confirmados post-release / releases del periodo | registro de no conformidades |
| Incidentes post-release | incidentes atribuibles por severidad y release | registro de incidentes |
| Documentación al día | cambios con docs/contracts sincronizados / cambios que los afectan × 100 | revisión de PR |

No llenar métricas con datos simulados ni presentar porcentajes hasta medirlos.

## Control documental

Mantener propietario, fecha/cambio, ubicación de fuente y estado; cambios de
documentación se revisan junto con el código. La evidencia se conserva con el
proceso de CI/gestión de cambios que la organización configure.
