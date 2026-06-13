# Registro de Actividades de Tratamiento (RAT)

**Obligación:** Ley 21.719 (registro de actividades de tratamiento).
**Responsable del tratamiento:** Tenri SpA (operador del ERP) / la empresa cliente según rol.
**Última actualización:** 2026-06-13. **Estado:** vivo (se actualiza con cada cambio de modelo de datos).

> Este registro inventaría **qué datos personales** trata el ERP, **con qué finalidad**,
> **bajo qué base de licitud**, **quién accede**, **cuánto se conservan** y **qué medidas
> de seguridad** los protegen. Es el documento base exigido por la Ley 21.719 y la
> evidencia primaria para una auditoría ISO 27701.

## Roles

- **Responsable del tratamiento:** la empresa cliente (decide finalidades de RRHH, ventas, etc.).
- **Encargado del tratamiento:** Tenri SpA (opera la plataforma por cuenta del responsable).
  Requiere **contrato de encargo** (DPA) entre ambas partes — pendiente, ver plan §4.
- **Delegado de Protección de Datos (DPO):** por designar (acto organizativo).

## Actividades de tratamiento

### A1 — Gestión de personal y remuneraciones (RRHH) 🔴 alto riesgo
| Campo | Detalle |
|---|---|
| **Categorías de titulares** | Trabajadores, cargas familiares (incl. menores), ex-trabajadores. |
| **Datos tratados** | Identificación (RUT, nombres, apellidos, fecha nac., sexo, nacionalidad, estado civil), contacto (email, teléfono, dirección), datos laborales (cargo, contrato, sueldo), **datos sensibles de salud** (tipo de salud, Isapre/Fonasa, plan), previsión (AFP), datos bancarios (banco, cuenta — cifrada). Cargas familiares: RUT, nombre, fecha nac., parentesco. |
| **Finalidad** | Ejecución del contrato de trabajo, cálculo y pago de remuneraciones, cumplimiento de obligaciones previsionales/tributarias (Previred, libro de remuneraciones, finiquitos). |
| **Base de licitud** | Ejecución de contrato + obligación legal (laboral/previsional/tributaria). Datos de salud: obligación legal de cotización + **consentimiento** donde aplique. |
| **Tablas** | `empleados`, `cargas_familiares`, `contratos`, `liquidaciones`, `liquidacion_detalles`. |
| **Acceso** | Permisos `rrhh.empleados.*`, `rrhh.remuneraciones.*`; aislamiento `empresa_id`. |
| **Conservación** | Retención legal laboral/tributaria (~5-6 años desde término). No se elimina antes; al vencer → bloqueo/anonimización. |
| **Seguridad** | Cuenta bancaria cifrada (AES) + `$hidden`. **Pendiente (Fase 2):** cifrar RUT, nombres, fecha nac., contacto, sueldo con CipherSweet. **Pendiente (Fase 3):** auditoría de acceso. |

### A2 — Gestión comercial: clientes 🟡 medio
| Campo | Detalle |
|---|---|
| **Titulares** | Personas de contacto de clientes (y clientes persona natural). |
| **Datos** | RUT, razón social/nombre, contacto (nombre, email, teléfono), dirección. |
| **Finalidad** | Facturación, gestión de cobranza, emisión de DTE al SII. |
| **Base de licitud** | Ejecución de contrato + obligación legal tributaria. |
| **Tablas** | `clientes`. **Acceso:** `clientes.ver`/`ventas.ver`, `clientes.crear`. |
| **Conservación** | Retención tributaria (~6 años). |
| **Seguridad** | **Pendiente (Fase 2):** cifrar RUT y contacto. |

### A3 — Gestión comercial: proveedores 🟡 medio
| Campo | Detalle |
|---|---|
| **Titulares** | Personas de contacto de proveedores. |
| **Datos** | RUT, nombre de contacto, email; cuentas bancarias de proveedor (banco, **número de cuenta — hoy en texto plano** 🔴). |
| **Finalidad** | Pago a proveedores, registro de compras, DTE. |
| **Base de licitud** | Ejecución de contrato + obligación legal. |
| **Tablas** | `proveedores`, `cuentas_bancarias_proveedores`. |
| **Conservación** | Retención tributaria (~6 años). |
| **Seguridad** | **Crítico (Fase 1):** cifrar `numero_cuenta`. **Fase 2:** cifrar RUT/contacto. |

### A4 — Usuarios del sistema 🟡 medio
| Campo | Detalle |
|---|---|
| **Titulares** | Usuarios internos del ERP. |
| **Datos** | Nombre, email, contraseña (bcrypt), último acceso, rol. |
| **Finalidad** | Autenticación, control de acceso, trazabilidad. |
| **Base de licitud** | Ejecución de contrato (relación laboral/servicio) + interés legítimo (seguridad). |
| **Tablas** | `usuarios`. **Seguridad:** contraseña hasheada; `ultimo_acceso` con throttle. |

### A5 — Datos de la empresa y cuentas bancarias propias 🟠 alto
| Campo | Detalle |
|---|---|
| **Datos** | RUT empresa, email, teléfono, dirección; cuentas bancarias propias (titular, RUT titular, **número de cuenta — hoy en texto plano** 🔴). |
| **Finalidad** | Operación contable, conciliación bancaria, pagos. |
| **Tablas** | `empresas`, `cuentas_bancarias_empresa`. |
| **Seguridad** | **Crítico (Fase 1):** cifrar `numero_cuenta`. |

## Resumen de pendientes de seguridad (por fase del plan)
- **Fase 1 (crítico):** cifrar `cuentas_bancarias_empresa.numero_cuenta` y `cuentas_bancarias_proveedores.numero_cuenta`; `SESSION_ENCRYPT=true`; máscara de RUT en UI.
- **Fase 2:** cifrado CipherSweet de RUT, nombres, fecha nac., contacto, sueldo + blind index.
- **Fase 3:** auditoría de acceso/cambio a PII.
- **Fase 4-5:** consentimiento y derechos ARCO+.
