# Gastos del Exterior: Asimetría IVA vs. Renta

**Fecha de análisis**: 2026-06-26
**Ámbito**: `preCalculoRenta()` y `simularF29()` en `ImpuestosService.php`
**Decisión**: Mantener comportamiento actual — no cambiar `preCalculoRenta()`

---

## Resumen ejecutivo

Las facturas de compra registradas con `es_documento_exterior=true` deben tratarse de forma **distinta** en IVA (F29) y en Impuesto a la Renta (F22). El ERP lo hace correctamente.

| Impuesto | Función | ¿Incluye exterior? | Base legal |
|---|---|---|---|
| IVA (F29) | `simularF29()` | ❌ NO | Art. 23 N°1 DL 825 — sin DIN/DUS no hay IVA crédito |
| Renta (F22) | `preCalculoRenta()` | ✅ SÍ | Art. 31 LIR — gastos necesarios para producir la renta |

La inconsistencia aparente entre ambas funciones es **intencional y correcta**.

---

## Marco legal

### IVA — DL 825, Art. 23 N°1

Las importaciones de bienes o servicios del exterior NO generan IVA crédito fiscal a menos que exista un Documento de Ingreso Nacional (DIN) o Declaración de Ingreso tramitada ante el SII/Aduana. Las facturas emitidas por proveedores extranjeros no son documentos tributarios válidos para el crédito fiscal en Chile.

→ `simularF29()` excluye `es_documento_exterior=true` del cálculo de IVA crédito: **correcto**.

### Impuesto a la Renta — LIR Art. 31

Los gastos necesarios para producir la renta son deducibles, incluyendo los incurridos en el extranjero, siempre que:

1. Sean necesarios para producir la renta chilena.
2. Estén acreditados o justificados fehacientemente ante el SII (contrato, factura del proveedor extranjero, remesa al exterior documentada, declaración de renta extranjera si aplica).
3. No correspondan a gastos rechazados por Art. 31 N°12 inc. 4 (paraísos fiscales, pagos a entidades sin actividad real).

**Circular SII N°53/2020** confirma que gastos en el exterior son deducibles bajo Art. 31 cuando se acreditan en forma fehaciente. La restricción aplica a documentación insuficiente, no a la naturaleza exterior del gasto.

→ `preCalculoRenta()` incluye `es_documento_exterior=true` en `totalCostosGastos`: **correcto bajo Art. 31**.

---

## Riesgo residual — documentación

`preCalculoRenta()` suma `monto_neto` de **todas** las facturas del exterior sin verificar si tienen documentación fehaciente (Art. 31). El ERP asume que si el usuario la registró, existe respaldo. 

**Consecuencia si no hay documentación**: el pre-cálculo subestima el impuesto a pagar — el SII podría rechazar el gasto en una fiscalización.

**Mitigación**: El pre-cálculo es una estimación orientativa (`preCalculoRenta` ≠ F22 definitivo). El contador debe revisar que cada factura del exterior tenga:
- Contrato de prestación de servicios o invoice del proveedor
- Remesa al exterior (transferencia Swift o equivalente)
- Retención de impuesto adicional Art. 59 LIR aplicada si corresponde (servicios intangibles, regalías, intereses)

---

## Impuesto adicional — Art. 59 LIR (punto conexo)

Si el pago al exterior corresponde a:
- Regalías o licencias de software → retención 30% (Art. 59 N°1)
- Servicios prestados en el extranjero → retención 35% (Art. 59 N°2) con posible exención por convenio doble tributación
- Intereses → retención 4% o 35% según fuente

El ERP actualmente **no calcula ni registra** la retención Art. 59 en las facturas del exterior. Esto es un gap funcional separado, no un bug de `preCalculoRenta()`.

---

## Conclusión para el código

```php
// ImpuestosService.php — comportamiento CORRECTO, no cambiar

// simularF29(): excluye exterior — correcto (DL 825, sin IVA crédito sin DIN)
->where('es_documento_exterior', false)

// preCalculoRenta(): incluye exterior — correcto (Art. 31 LIR, gasto deducible)
// sin filtro es_documento_exterior ← INTENCIONAL
```

**Acción requerida**: ninguna en el código. Agregar aviso en la UI del pre-cálculo de renta indicando que las facturas del exterior deben tener documentación Art. 31 para ser deducibles.

---

## Referencias

- Ley de la Renta (DL 824), Art. 31 — Gastos necesarios
- Ley de la Renta (DL 824), Art. 59 — Impuesto adicional a remesas al exterior
- DL 825, Art. 23 N°1 — Crédito fiscal IVA, requisitos
- Circular SII N°53/2020 — Gastos incurridos en el exterior
- Resolución Ex. SII N°6080/2000 — Acreditación de gastos en el exterior
