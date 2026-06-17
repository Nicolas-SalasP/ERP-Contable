import React, { useState, useEffect, useCallback } from 'react';
import { propietariosApi } from '../Servicios/propietariosApi';

const inputCls =
    'w-full px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none';

const pctFmt = (v) => (v !== null && v !== undefined ? `${Number(v).toFixed(2)}%` : '—');

const PropietariosEmpresa = () => {
    const [lista, setLista]         = useState([]);
    const [cargando, setCargando]   = useState(false);
    const [enviando, setEnviando]   = useState(false);
    const [mensaje, setMensaje]     = useState(null);

    // Formulario agregar
    const [rut, setRut]             = useState('');
    const [nombre, setNombre]       = useState('');
    const [porcentaje, setPorcentaje] = useState('');

    // Edición en línea
    const [editandoId, setEditandoId] = useState(null);
    const [editNombre, setEditNombre] = useState('');
    const [editPorcentaje, setEditPorcentaje] = useState('');

    const mostrarMensaje = (tipo, texto) => {
        setMensaje({ tipo, texto });
        setTimeout(() => setMensaje(null), 5000);
    };

    const cargarLista = useCallback(async () => {
        setCargando(true);
        try {
            const resp = await propietariosApi.listar();
            const items = Array.isArray(resp) ? resp : (resp?.data ?? []);
            setLista(items);
        } catch (_) {
            // el interceptor global muestra el toast
        } finally {
            setCargando(false);
        }
    }, []);

    useEffect(() => {
        cargarLista();
    }, [cargarLista]);

    const limpiarForm = () => {
        setRut('');
        setNombre('');
        setPorcentaje('');
    };

    const handleAgregar = async (e) => {
        e.preventDefault();
        if (!rut || !nombre || !porcentaje) {
            mostrarMensaje('error', 'Completa todos los campos requeridos.');
            return;
        }
        setEnviando(true);
        try {
            await propietariosApi.crear({
                rut,
                nombre,
                porcentaje_participacion: parseFloat(porcentaje),
            });
            mostrarMensaje('exito', 'Propietario agregado correctamente.');
            limpiarForm();
            await cargarLista();
        } catch (err) {
            const msg = err?.response?.data?.mensaje ?? err?.response?.data?.message ?? 'Error al agregar el propietario.';
            mostrarMensaje('error', msg);
        } finally {
            setEnviando(false);
        }
    };

    const iniciarEdicion = (p) => {
        setEditandoId(p.id);
        setEditNombre(p.nombre);
        setEditPorcentaje(String(p.porcentaje_participacion));
    };

    const cancelarEdicion = () => {
        setEditandoId(null);
        setEditNombre('');
        setEditPorcentaje('');
    };

    const handleGuardarEdicion = async (id) => {
        setEnviando(true);
        try {
            await propietariosApi.actualizar(id, {
                nombre: editNombre,
                porcentaje_participacion: parseFloat(editPorcentaje),
            });
            mostrarMensaje('exito', 'Propietario actualizado.');
            cancelarEdicion();
            await cargarLista();
        } catch (err) {
            const msg = err?.response?.data?.mensaje ?? err?.response?.data?.message ?? 'Error al actualizar.';
            mostrarMensaje('error', msg);
        } finally {
            setEnviando(false);
        }
    };

    const handleEliminar = async (id, nombreProp) => {
        if (!window.confirm(`¿Eliminar al propietario "${nombreProp}"?`)) return;
        try {
            await propietariosApi.eliminar(id);
            mostrarMensaje('exito', 'Propietario eliminado.');
            await cargarLista();
        } catch (err) {
            const msg = err?.response?.data?.mensaje ?? err?.response?.data?.message ?? 'Error al eliminar.';
            mostrarMensaje('error', msg);
        }
    };

    const sumaParticipacion = lista.reduce((acc, p) => acc + Number(p.porcentaje_participacion ?? 0), 0);
    const sumaOk = Math.abs(sumaParticipacion - 100) < 0.01;

    return (
        <div className="max-w-4xl mx-auto p-6 md:p-8">
            <header className="mb-6">
                <h1 className="text-2xl md:text-3xl font-black text-slate-900 flex items-center gap-3">
                    <i className="fas fa-users text-emerald-600" />
                    Propietarios de la Empresa
                </h1>
                <p className="text-sm text-slate-500 mt-1">
                    Registro de socios o propietarios con su porcentaje de participación. Requerido para DJ 1947 (Propyme Transparente).
                </p>
            </header>

            {mensaje && (
                <div className={`mb-4 px-4 py-3 rounded-lg text-sm font-medium flex items-center gap-2 ${
                    mensaje.tipo === 'exito'
                        ? 'bg-emerald-50 text-emerald-800 border border-emerald-200'
                        : 'bg-red-50 text-red-800 border border-red-200'
                }`}>
                    <i className={mensaje.tipo === 'exito' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle'} />
                    {mensaje.texto}
                </div>
            )}

            {/* Formulario agregar */}
            <div className="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 mb-6">
                <h2 className="text-sm font-bold text-slate-700 mb-4 uppercase tracking-wide">Agregar propietario</h2>
                <form onSubmit={handleAgregar}>
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                        <div>
                            <label className="block text-xs font-semibold text-slate-600 mb-1">
                                RUT <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                value={rut}
                                onChange={(e) => setRut(e.target.value)}
                                placeholder="12345678-5"
                                maxLength={12}
                                className={inputCls}
                                required
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-semibold text-slate-600 mb-1">
                                Nombre <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                value={nombre}
                                onChange={(e) => setNombre(e.target.value)}
                                placeholder="Juan Pérez Silva"
                                maxLength={255}
                                className={inputCls}
                                required
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-semibold text-slate-600 mb-1">
                                % Participación <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="number"
                                value={porcentaje}
                                onChange={(e) => setPorcentaje(e.target.value)}
                                placeholder="50.00"
                                min="0.01"
                                max="100"
                                step="0.01"
                                className={inputCls}
                                required
                            />
                        </div>
                    </div>
                    <button
                        type="submit"
                        disabled={enviando}
                        className="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm disabled:opacity-50"
                    >
                        {enviando
                            ? <><i className="fas fa-spinner fa-spin" /> Guardando...</>
                            : <><i className="fas fa-plus" /> Agregar propietario</>
                        }
                    </button>
                </form>
            </div>

            {/* Tabla propietarios */}
            <div className="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div className="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                    <h2 className="font-bold text-slate-900">Propietarios registrados</h2>
                    <button
                        onClick={cargarLista}
                        className="text-xs text-emerald-600 hover:text-emerald-800 font-semibold flex items-center gap-1"
                    >
                        <i className="fas fa-sync-alt" /> Actualizar
                    </button>
                </div>

                {cargando ? (
                    <div className="py-10 text-center text-slate-400 text-sm">
                        <i className="fas fa-spinner fa-spin mr-2" />Cargando...
                    </div>
                ) : lista.length === 0 ? (
                    <div className="py-10 text-center text-slate-400 text-sm">
                        Sin propietarios registrados. Usa el formulario de arriba para agregar.
                    </div>
                ) : (
                    <>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-slate-50 text-slate-600 text-xs uppercase">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-semibold">RUT</th>
                                        <th className="px-4 py-3 text-left font-semibold">Nombre</th>
                                        <th className="px-4 py-3 text-right font-semibold">% Participación</th>
                                        <th className="px-4 py-3 text-center font-semibold">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {lista.map((p) => (
                                        <tr key={p.id} className="hover:bg-slate-50">
                                            <td className="px-4 py-3 font-mono text-slate-700">{p.rut}</td>
                                            <td className="px-4 py-3 text-slate-900">
                                                {editandoId === p.id ? (
                                                    <input
                                                        type="text"
                                                        value={editNombre}
                                                        onChange={(e) => setEditNombre(e.target.value)}
                                                        className={inputCls}
                                                        maxLength={255}
                                                    />
                                                ) : p.nombre}
                                            </td>
                                            <td className="px-4 py-3 text-right font-mono text-slate-900">
                                                {editandoId === p.id ? (
                                                    <input
                                                        type="number"
                                                        value={editPorcentaje}
                                                        onChange={(e) => setEditPorcentaje(e.target.value)}
                                                        className={inputCls + ' text-right'}
                                                        min="0.01"
                                                        max="100"
                                                        step="0.01"
                                                    />
                                                ) : pctFmt(p.porcentaje_participacion)}
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                {editandoId === p.id ? (
                                                    <div className="flex items-center justify-center gap-2">
                                                        <button
                                                            onClick={() => handleGuardarEdicion(p.id)}
                                                            disabled={enviando}
                                                            title="Guardar cambios"
                                                            className="inline-flex items-center justify-center w-8 h-8 rounded-lg text-emerald-600 hover:bg-emerald-50 disabled:opacity-50"
                                                        >
                                                            <i className="fas fa-check text-xs" />
                                                        </button>
                                                        <button
                                                            onClick={cancelarEdicion}
                                                            title="Cancelar"
                                                            className="inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-500 hover:bg-slate-100"
                                                        >
                                                            <i className="fas fa-times text-xs" />
                                                        </button>
                                                    </div>
                                                ) : (
                                                    <div className="flex items-center justify-center gap-2">
                                                        <button
                                                            onClick={() => iniciarEdicion(p)}
                                                            title="Editar"
                                                            className="inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-600 hover:bg-slate-100"
                                                        >
                                                            <i className="fas fa-pencil-alt text-xs" />
                                                        </button>
                                                        <button
                                                            onClick={() => handleEliminar(p.id, p.nombre)}
                                                            title="Eliminar"
                                                            className="inline-flex items-center justify-center w-8 h-8 rounded-lg text-red-500 hover:bg-red-50 hover:text-red-700"
                                                        >
                                                            <i className="fas fa-trash-alt text-xs" />
                                                        </button>
                                                    </div>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot>
                                    <tr className={`border-t-2 ${sumaOk ? 'border-emerald-300 bg-emerald-50' : 'border-red-300 bg-red-50'}`}>
                                        <td colSpan={2} className={`px-4 py-3 text-sm font-bold ${sumaOk ? 'text-emerald-800' : 'text-red-800'}`}>
                                            {sumaOk
                                                ? <><i className="fas fa-check-circle mr-1" /> Participación total verificada</>
                                                : <><i className="fas fa-exclamation-triangle mr-1" /> La suma de participaciones no es 100%. Corrija antes de generar la DJ 1947.</>
                                            }
                                        </td>
                                        <td className={`px-4 py-3 text-right font-mono font-bold text-base ${sumaOk ? 'text-emerald-900' : 'text-red-900'}`}>
                                            {sumaParticipacion.toFixed(2)}%
                                        </td>
                                        <td />
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </>
                )}
            </div>
        </div>
    );
};

export default PropietariosEmpresa;
