# Convenciones del proyecto

Reglas permanentes del repositorio. (También replicadas en `CLAUDE.md`, que es
un archivo local ignorado por git; este documento es la copia **versionada**.)

## Documentación (SIEMPRE)
- **Toda la documentación va en `docs/`.** No solo `.md`: si se consulta o usa un
  **PDF, Word, Excel u otro binario**, se **adjunta el archivo** en `docs/`
  (idealmente bajo una subcarpeta `fuentes/` del tema), no solo el enlace.
- Si el entorno bloquea la descarga binaria (egress allowlist), dejar igualmente
  un índice de fuentes (`FUENTES.md`) con las URLs y cómo habilitarlas.
- Las decisiones de diseño, auditorías y planes se documentan como artefacto
  versionado en `docs/` antes o junto con la implementación.

## Git y commits
- Commits **siempre** desde `Nicolas-SalasP <nicolas.salas.contacto@gmail.com>`.
  **Sin** co-author y **sin** atribución a asistentes en ningún artefacto.
- Estilo de mensaje: `Tipo (alcance): Descripción` con cuerpo descriptivo.
- Trabajar en la rama **`NSalas-dev`**. **Nunca** push a `main`. **No** crear
  ramas nuevas sin permiso explícito.
- Todo cambio debe **pasar CI**. El frontend usa `pnpm install --frozen-lockfile`:
  al agregar/cambiar una dependencia, **regenerar `pnpm-lock.yaml`** en el mismo commit.

## Cumplimiento de protección de datos
Plan vigente: `docs/auditoria/CUMPLIMIENTO-LEY-21719-PLAN.md`. Fuentes en
`docs/auditoria/fuentes/`. RAT, DPIA y mapeo de controles en `docs/auditoria/`.
