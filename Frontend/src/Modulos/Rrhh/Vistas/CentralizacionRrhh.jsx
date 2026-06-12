import React, { useCallback, useEffect, useState } from 'react';
import Swal from 'sweetalert2';
import EstadoCarga from '../../../Componentes/EstadoCarga';
import AyudaModulo from '../../../Componentes/AyudaModulo';
import { usePermisos } from '../../../Contextos/Permisos';
import { api } from '../../../Configuracion/api';
import BuscadorCuentaContable from '../../Contabilidad/Componentes/BuscadorCuentaContable';
import rrhhApi from '../Servicios/rrhhApi';
import { MESES, nombreMes } from '../Utilidades/formato';

const inputCls = 'w-full px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none';
const anioActual = new Date().getFullYear();
const ANIOS = Array.from({ length: 6 }, (_, i) => anioActual - i);

const LABELS = {
    GASTO_REMUNERACIONES: 'Gasto: Remuneraciones',
    GASTO_LEYES_SOCIALES: 'Gasto: Leyes sociales (empleador)',
    GASTO_PROVISION_VACACIONES: 'Gasto: Provisión vacaciones',
    PASIVO_LIQUIDO_PAGAR: 'Pasivo: Líquido por pagar',
    PASIVO_RETENCIONES_PREVISIONALES: 'Pasivo: Retenciones previsionales (AFP+Salud+AFC)',
    PASIVO_IMPUESTO_UNICO: 'Pasivo: Impuesto único retenido',
    PASIVO_LEYES_SOCIALES: 'Pasivo: Leyes sociales por pagar',
    PASIVO_DESCUENTOS_VOLUNTARIOS: 'Pasivo: Descuentos voluntarios',
    PASIVO_PROVISION_VACACIONES: 'Pasivo: Provisión vacaciones por pagar',
};

const CentralizacionRrhh = () => {
    const { tienePermiso } = usePermisos();
    const puedeEditar = tienePermiso('rrhh.parametros.ver') && tienePermiso('rrhh.parametros.editar');
    const puedeProcesar = tienePermiso('rrhh.remuneraciones.procesar');

    const [mapeos, setMapeos] = useState([]);
    const [requeridos, setRequeridos] = useState([]);
    const [todos, setTodos] = useState([]);
    const [cargando, setCargando] = useState(true);
    const [planCuentas, setPlanCuentas] = useState([]);
    const [manual, setManual] = useState({}); // fallback tipo -> codigo (si no se pudo cargar el plan)
    const [periodo, setPeriodo] = useState({ anio: anioActual, mes: new Date().getMonth() + 1 });
    const [centralizando, setCentralizando] = useState(false);

    const cargar = useCallback(async () => {
        setCargando(true);
        try {
            const resp = await rrhhApi.mapeoContable.listar();
            setMapeos(resp?.data ?? []);
            setRequeridos(resp?.tipos_requeridos ?? []);
            setTodos(resp?.todos_los_tipos ?? Object.keys(LABELS));
        } catch (_) { /* toast */ } finally {
            setCargando(false);
        }
    }, []);

    useEffect(() => { cargar(); }, [cargar]);

    // Plan de cuentas para el buscador. Si el usuario no tiene acceso a contabilidad,
    // queda vacío y se usa el ingreso manual de código como respaldo.
    useEffect(() => {
        (async () => {
            try {
                const res = await api.get('/contabilidad/plan-cuentas', { silent: true });
                if (res?.success) {
                    setPlanCuentas((res.data ?? []).filter((c) => c.imputable && c.activo !== false));
                }
            } catch (_) { /* sin plan: se usa el respaldo manual */ }
        })();
    }, []);

    const codigoDe = (tipo) => mapeos.find((m) => m.tipo_cuenta === tipo)?.cuenta_contable_codigo ?? '';

    const guardarMapeo = async (tipo, codigo) => {
        const limpio = (codigo ?? '').trim();
        if (!limpio || limpio === codigoDe(tipo)) return;
        try {
            await rrhhApi.mapeoContable.guardar({ tipo_cuenta: tipo, cuenta_contable_codigo: limpio });
            setManual((m) => { const n = { ...m }; delete n[tipo]; return n; });
            await Swal.fire({ icon: 'success', title: 'Cuenta asignada', toast: true, position: 'top-end', showConfirmButton: false, timer: 1500 });
            cargar();
        } catch (_) { /* toast */ }
    };

    const eliminarMapeo = async (tipo) => {
        try {
            await rrhhApi.mapeoContable.eliminar(tipo);
            await Swal.fire({ icon: 'success', title: 'Mapeo eliminado', toast: true, position: 'top-end', showConfirmButton: false, timer: 1500 });
            cargar();
        } catch (_) { /* toast */ }
    };

    const centralizar = async () => {
        const r = await Swal.fire({
            icon: 'question',
            title: `¿Centralizar ${nombreMes(periodo.mes)} ${periodo.anio}?`,
            text: 'Genera el asiento contable mensual con todas las liquidaciones EMITIDAS del período.',
            showCancelButton: true, confirmButtonText: 'Centralizar', confirmButtonColor: '#059669', cancelButtonText: 'Cancelar',
        });
        if (!r.isConfirmed) return;
        setCentralizando(true);
        try {
            const resp = await rrhhApi.centralizacion.ejecutar(periodo.anio, periodo.mes);
            await Swal.fire({
                icon: 'success', title: 'Centralización exitosa',
                text: resp?.message || `Asiento generado: ${resp?.data?.numero_comprobante ?? ''}`,
                confirmButtonColor: '#0f172a',
            });
        } catch (_) { /* toast */ } finally {
            setCentralizando(false);
        }
    };

    const faltantes = requeridos.filter((t) => !codigoDe(t));
    const hayPlan = planCuentas.length > 0;

    return (
        <div className="max-w-5xl mx-auto p-6 md:p-8">
            <header className="mb-6">
                <div className="flex items-center gap-3">
                    <h1 className="text-2xl md:text-3xl font-black text-slate-900 flex items-center gap-3">
                        <i className="fas fa-book-bookmark text-emerald-600" />
                        Centralización Contable
                    </h1>
                    <AyudaModulo moduloId="centralizacionRrhh" size={28} />
                </div>
                <p className="text-sm text-slate-500 mt-1">
                    Genera el asiento mensual de remuneraciones (partida doble) desde las liquidaciones emitidas.
                </p>
            </header>

            <EstadoCarga cargando={cargando} mensajeCargando="Cargando configuración..." color="emerald" tamano="compacto">
                {/* Ejecutar centralización */}
                <div className="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 mb-6">
                    <h3 className="font-bold text-slate-900 mb-3 flex items-center gap-2"><i className="fas fa-play-circle text-emerald-600" /> Ejecutar centralización</h3>
                    {faltantes.length > 0 && (
                        <div className="bg-amber-50 border border-amber-200 rounded-lg p-3 text-amber-800 text-sm mb-3">
                            <i className="fas fa-triangle-exclamation mr-2" />
                            Faltan {faltantes.length} cuenta(s) obligatoria(s) en el mapeo. Configúralas abajo antes de centralizar.
                        </div>
                    )}
                    <div className="flex flex-wrap items-end gap-3">
                        <div>
                            <label className="block text-xs font-semibold text-slate-600 mb-1">Año</label>
                            <select value={periodo.anio} onChange={(e) => setPeriodo((p) => ({ ...p, anio: Number(e.target.value) }))} className={inputCls}>
                                {ANIOS.map((a) => <option key={a} value={a}>{a}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-semibold text-slate-600 mb-1">Mes</label>
                            <select value={periodo.mes} onChange={(e) => setPeriodo((p) => ({ ...p, mes: Number(e.target.value) }))} className={inputCls}>
                                {MESES.map((m) => <option key={m.valor} value={m.valor}>{m.label}</option>)}
                            </select>
                        </div>
                        {puedeProcesar && (
                            <button onClick={centralizar} disabled={centralizando || faltantes.length > 0}
                                className="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm disabled:opacity-50">
                                {centralizando ? <i className="fas fa-spinner fa-spin" /> : <i className="fas fa-bolt" />} Centralizar período
                            </button>
                        )}
                    </div>
                </div>

                {/* Mapeo de cuentas */}
                <div className="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div className="px-5 py-4 border-b border-slate-200">
                        <h3 className="font-bold text-slate-900 flex items-center gap-2"><i className="fas fa-sitemap text-emerald-600" /> Mapeo contable</h3>
                        <p className="text-xs text-slate-500 mt-1">Asocia cada categoría RRHH a una cuenta imputable de tu Plan de Cuentas.</p>
                    </div>
                    <div className="divide-y divide-slate-100">
                        {(todos.length ? todos : Object.keys(LABELS)).map((tipo) => {
                            const esRequerido = requeridos.includes(tipo);
                            const codigoActual = codigoDe(tipo);
                            return (
                                <div key={tipo} className="flex flex-wrap items-center gap-3 px-5 py-3">
                                    <div className="flex-1 min-w-full sm:min-w-[240px]">
                                        <span className="text-sm font-medium text-slate-800">{LABELS[tipo] || tipo}</span>
                                        {esRequerido && <span className="ml-2 text-[10px] font-bold uppercase text-red-500">obligatorio</span>}
                                    </div>

                                    {!puedeEditar ? (
                                        <span className="font-mono text-sm text-slate-700">{codigoActual || '—'}</span>
                                    ) : hayPlan ? (
                                        <div className="flex items-center gap-2 w-full sm:w-80">
                                            <div className="flex-1">
                                                <BuscadorCuentaContable
                                                    cuentas={planCuentas}
                                                    valor={codigoActual}
                                                    onChange={(codigo) => guardarMapeo(tipo, codigo)}
                                                />
                                            </div>
                                            {codigoActual && (
                                                <button onClick={() => eliminarMapeo(tipo)}
                                                    className="px-3 py-2 rounded-lg border border-slate-300 text-slate-500 hover:text-red-600 text-xs" aria-label="Quitar cuenta">
                                                    <i className="fas fa-trash" />
                                                </button>
                                            )}
                                        </div>
                                    ) : (
                                        // Respaldo: ingreso manual del código si no se pudo cargar el Plan de Cuentas.
                                        <div className="flex items-center gap-2">
                                            <input
                                                value={tipo in manual ? manual[tipo] : codigoActual}
                                                onChange={(e) => setManual((m) => ({ ...m, [tipo]: e.target.value }))}
                                                onKeyDown={(e) => { if (e.key === 'Enter') guardarMapeo(tipo, manual[tipo]); }}
                                                placeholder="Código cuenta"
                                                className="w-36 px-3 py-1.5 rounded-lg border border-slate-300 text-sm font-mono focus:ring-2 focus:ring-emerald-500 outline-none"
                                            />
                                            <button onClick={() => guardarMapeo(tipo, manual[tipo])} disabled={!(tipo in manual)}
                                                className="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold disabled:opacity-40" aria-label="Guardar mapeo">
                                                <i className="fas fa-check" />
                                            </button>
                                            {codigoActual && (
                                                <button onClick={() => eliminarMapeo(tipo)}
                                                    className="px-3 py-1.5 rounded-lg border border-slate-300 text-slate-500 hover:text-red-600 text-xs" aria-label="Quitar cuenta">
                                                    <i className="fas fa-trash" />
                                                </button>
                                            )}
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </div>
            </EstadoCarga>
        </div>
    );
};

export default CentralizacionRrhh;
