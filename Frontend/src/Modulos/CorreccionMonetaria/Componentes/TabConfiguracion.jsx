import React, { useState, useEffect } from 'react';
import Swal from 'sweetalert2';
import { api } from '../../../Configuracion/api';

const MESES = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

const ROLES_CM = [
    { value: 'ACTIVO_NO_MONETARIO',   label: 'Activo No Monetario',     color: 'blue' },
    { value: 'DEPRECIACION_ACUMULADA', label: 'Depreciación Acumulada', color: 'orange' },
    { value: 'INVENTARIO',             label: 'Existencias / Inventario', color: 'green' },
    { value: 'PATRIMONIO_CAPITAL',     label: 'Patrimonio / Capital',    color: 'purple' },
    { value: 'PASIVO_NO_MONETARIO',    label: 'Pasivo No Monetario',     color: 'red' },
];

const badgeRol = (rol) => {
    const found = ROLES_CM.find(r => r.value === rol);
    const colors = {
        blue:   'bg-blue-100 text-blue-700 border-blue-200',
        orange: 'bg-orange-100 text-orange-700 border-orange-200',
        green:  'bg-emerald-100 text-emerald-700 border-emerald-200',
        purple: 'bg-violet-100 text-violet-700 border-violet-200',
        red:    'bg-rose-100 text-rose-700 border-rose-200',
    };
    return (
        <span className={`text-[10px] px-2 py-0.5 rounded border font-black uppercase tracking-wide ${colors[found?.color] || 'bg-slate-100 text-slate-600 border-slate-200'}`}>
            {found?.label || rol}
        </span>
    );
};

const TabConfiguracion = ({ config, onConfigChange }) => {
    const [cuentas, setCuentas]       = useState([]);
    const [loadingCuentas, setLoadingCuentas] = useState(true);
    const [guardandoConfig, setGuardandoConfig] = useState(false);
    const [formConfig, setFormConfig] = useState(null);
    const [nuevaCuenta, setNuevaCuenta] = useState({ cuenta_codigo: '', rol_cm: '' });
    const [agregando, setAgregando]   = useState(false);

    useEffect(() => {
        if (config) {
            setFormConfig({ ...config });
        }
    }, [config]);

    useEffect(() => {
        cargarCuentas();
    }, []);

    const cargarCuentas = async () => {
        setLoadingCuentas(true);
        try {
            const res = await api.get('/correccion-monetaria/cuentas');
            if (res.success) setCuentas(res.data);
        } catch (_) {
        } finally {
            setLoadingCuentas(false);
        }
    };

    const guardarConfig = async () => {
        setGuardandoConfig(true);
        try {
            const res = await api.put('/correccion-monetaria/configuracion', formConfig);
            if (res.success) {
                onConfigChange?.();
                Swal.fire({ icon: 'success', title: 'Configuración guardada', timer: 1500, showConfirmButton: false });
            }
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: err?.message || 'No se pudo guardar.', buttonsStyling: false, customClass: { confirmButton: 'bg-slate-900 text-white font-bold py-2 px-6 rounded-lg' } });
        } finally {
            setGuardandoConfig(false);
        }
    };

    const toggleCuenta = async (cuentaCodigo, aplica) => {
        try {
            await api.put('/correccion-monetaria/cuentas', {
                cuentas: [{ cuenta_codigo: cuentaCodigo, aplica }],
            });
            setCuentas(prev => prev.map(c => c.cuenta_codigo === cuentaCodigo ? { ...c, aplica } : c));
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: err?.message || 'No se pudo actualizar.', buttonsStyling: false, customClass: { confirmButton: 'bg-slate-900 text-white font-bold py-2 px-6 rounded-lg' } });
        }
    };

    const agregarCuenta = async () => {
        if (!nuevaCuenta.cuenta_codigo || !nuevaCuenta.rol_cm) return;
        setAgregando(true);
        try {
            const res = await api.post('/correccion-monetaria/cuentas', nuevaCuenta);
            if (res.success) {
                setNuevaCuenta({ cuenta_codigo: '', rol_cm: '' });
                await cargarCuentas();
            }
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: err?.message || 'No se pudo agregar la cuenta.', buttonsStyling: false, customClass: { confirmButton: 'bg-slate-900 text-white font-bold py-2 px-6 rounded-lg' } });
        } finally {
            setAgregando(false);
        }
    };

    const cuentasPorRol = (rol) => cuentas.filter(c => c.rol_cm === rol);

    if (!formConfig) {
        return <div className="flex items-center justify-center py-20 text-slate-400"><i className="fas fa-spinner fa-spin text-2xl mr-3"></i></div>;
    }

    return (
        <div className="space-y-8">
            <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div className="bg-slate-50 px-6 py-4 border-b border-slate-200">
                    <h3 className="font-black text-slate-800">Configuración General</h3>
                    <p className="text-xs text-slate-500 mt-0.5">Define cómo se ejecuta la corrección monetaria para esta empresa.</p>
                </div>
                <div className="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label className="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Aplica CM</label>
                        <div className="flex gap-3">
                            {[{ val: true, label: 'Sí' }, { val: false, label: 'No (14D8)' }].map(opt => (
                                <button
                                    key={String(opt.val)}
                                    onClick={() => setFormConfig(f => ({ ...f, aplica_cm: opt.val }))}
                                    className={`flex-1 py-2.5 rounded-xl font-bold text-sm border transition-all ${
                                        formConfig.aplica_cm === opt.val
                                            ? 'bg-violet-600 text-white border-violet-600 shadow-md shadow-violet-200'
                                            : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'
                                    }`}
                                >
                                    {opt.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div>
                        <label className="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Modalidad</label>
                        <div className="flex gap-3">
                            {[{ val: 'mensual', label: 'Mensual' }, { val: 'anual', label: 'Anual' }].map(opt => (
                                <button
                                    key={opt.val}
                                    onClick={() => setFormConfig(f => ({ ...f, modalidad: opt.val }))}
                                    className={`flex-1 py-2.5 rounded-xl font-bold text-sm border transition-all ${
                                        formConfig.modalidad === opt.val
                                            ? 'bg-violet-600 text-white border-violet-600 shadow-md shadow-violet-200'
                                            : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'
                                    }`}
                                >
                                    {opt.label}
                                </button>
                            ))}
                        </div>
                        {formConfig.modalidad === 'mensual' && (
                            <p className="text-xs text-violet-600 mt-1.5">
                                <i className="fas fa-info-circle mr-1"></i>
                                Permite ejecutar CM en cualquier mes. El simulador funciona siempre.
                            </p>
                        )}
                    </div>

                    {formConfig.modalidad === 'anual' && (
                        <div>
                            <label className="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Mes de Cierre Anual</label>
                            <select
                                value={formConfig.mes_cierre}
                                onChange={e => setFormConfig(f => ({ ...f, mes_cierre: parseInt(e.target.value) }))}
                                className="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-bold text-slate-700 bg-white focus:ring-2 focus:ring-violet-500 outline-none"
                            >
                                {MESES.map((m, i) => (
                                    <option key={i + 1} value={i + 1}>{m}</option>
                                ))}
                            </select>
                        </div>
                    )}
                </div>

                <div className="px-6 pb-6">
                    <h4 className="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-3">Cuentas de Resultado CM</h4>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        {[
                            { key: 'cuenta_activos_codigo',     label: 'Resultado Activos (Ingreso)' },
                            { key: 'cuenta_existencias_codigo', label: 'Resultado Existencias (Ingreso)' },
                            { key: 'cuenta_depreciacion_codigo',label: 'Resultado Depreciación (Gasto)' },
                            { key: 'cuenta_patrimonio_codigo',  label: 'Resultado Patrimonio (Gasto)' },
                            { key: 'cuenta_pasivos_codigo',     label: 'Resultado Pasivos (Gasto)' },
                        ].map(field => (
                            <div key={field.key}>
                                <label className="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">{field.label}</label>
                                <input
                                    type="text"
                                    value={formConfig[field.key] || ''}
                                    onChange={e => setFormConfig(f => ({ ...f, [field.key]: e.target.value }))}
                                    className="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-violet-500 outline-none"
                                />
                            </div>
                        ))}
                    </div>
                </div>

                <div className="px-6 pb-6 flex justify-end">
                    <button
                        onClick={guardarConfig}
                        disabled={guardandoConfig}
                        className="bg-violet-600 hover:bg-violet-700 text-white font-black px-6 py-2.5 rounded-xl shadow-lg shadow-violet-200 transition-all disabled:opacity-60"
                    >
                        {guardandoConfig ? <><i className="fas fa-spinner fa-spin mr-2"></i>Guardando...</> : 'Guardar Configuración'}
                    </button>
                </div>
            </div>

            <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div className="bg-slate-50 px-6 py-4 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center gap-3">
                    <div>
                        <h3 className="font-black text-slate-800">Cuentas Participantes en CM</h3>
                        <p className="text-xs text-slate-500 mt-0.5">Activá o desactivá cada cuenta del plan que entra en el cálculo.</p>
                    </div>
                </div>

                {loadingCuentas ? (
                    <div className="flex items-center justify-center py-12 text-slate-400"><i className="fas fa-spinner fa-spin text-xl mr-2"></i>Cargando...</div>
                ) : (
                    <div className="divide-y divide-slate-100">
                        {ROLES_CM.map(rol => {
                            const cuentasRol = cuentasPorRol(rol.value);
                            if (cuentasRol.length === 0) return null;
                            return (
                                <div key={rol.value} className="px-6 py-4">
                                    <div className="flex items-center gap-2 mb-3">
                                        {badgeRol(rol.value)}
                                        <span className="text-xs text-slate-400">{cuentasRol.filter(c => c.aplica).length}/{cuentasRol.length} activas</span>
                                    </div>
                                    <div className="space-y-2">
                                        {cuentasRol.map(cuenta => (
                                            <div key={cuenta.cuenta_codigo} className={`flex items-center justify-between p-3 rounded-xl border transition-all ${cuenta.aplica ? 'bg-slate-50 border-slate-200' : 'bg-white border-dashed border-slate-200 opacity-60'}`}>
                                                <div className="flex items-center gap-3">
                                                    <button
                                                        onClick={() => toggleCuenta(cuenta.cuenta_codigo, !cuenta.aplica)}
                                                        className={`relative inline-flex h-5 w-9 items-center rounded-full transition-colors ${cuenta.aplica ? 'bg-violet-600' : 'bg-slate-200'}`}
                                                    >
                                                        <span className={`inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform shadow-sm ${cuenta.aplica ? 'translate-x-4' : 'translate-x-1'}`} />
                                                    </button>
                                                    <span className="font-mono text-xs text-slate-500">{cuenta.cuenta_codigo}</span>
                                                    <span className="font-bold text-sm text-slate-800">{cuenta.nombre_cuenta}</span>
                                                </div>
                                                {cuenta.factor_override !== null && (
                                                    <span className="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded font-bold border border-amber-200">
                                                        Override: {cuenta.factor_override}%
                                                    </span>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}

                <div className="px-6 pb-6 pt-2 border-t border-slate-100">
                    <p className="text-xs font-black text-slate-500 uppercase tracking-widest mb-3">Agregar Cuenta</p>
                    <div className="flex flex-col sm:flex-row gap-3">
                        <input
                            type="text"
                            value={nuevaCuenta.cuenta_codigo}
                            onChange={e => setNuevaCuenta(n => ({ ...n, cuenta_codigo: e.target.value.toUpperCase() }))}
                            placeholder="Código (ej: 112005)"
                            className="flex-1 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-mono focus:ring-2 focus:ring-violet-500 outline-none"
                        />
                        <select
                            value={nuevaCuenta.rol_cm}
                            onChange={e => setNuevaCuenta(n => ({ ...n, rol_cm: e.target.value }))}
                            className="flex-1 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-bold text-slate-700 bg-white focus:ring-2 focus:ring-violet-500 outline-none"
                        >
                            <option value="">Seleccionar rol...</option>
                            {ROLES_CM.map(r => <option key={r.value} value={r.value}>{r.label}</option>)}
                        </select>
                        <button
                            onClick={agregarCuenta}
                            disabled={agregando || !nuevaCuenta.cuenta_codigo || !nuevaCuenta.rol_cm}
                            className="bg-slate-900 hover:bg-slate-700 text-white font-bold px-5 py-2.5 rounded-xl transition-colors disabled:opacity-40"
                        >
                            {agregando ? <i className="fas fa-spinner fa-spin"></i> : 'Agregar'}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default TabConfiguracion;
