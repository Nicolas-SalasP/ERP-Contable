import React, { useState, useEffect, useCallback } from 'react';
import AyudaModulo from '../../../Componentes/AyudaModulo.jsx';
import { honorarios } from '../Servicios/comercialApi';
import { TablaSkeleton } from '../../../Componentes/Skeleton';
import { EstadoVacio } from '../../../Componentes/EstadoVacio';
import { BotonEliminar } from '../../../Componentes/ConfirmacionInline';

const TASAS = { 2024: 13.75, 2025: 14.50, 2026: 15.25, 2027: 16.25, 2028: 17.00 };

const clpFmt = new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' });

const inputCls =
    'w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none';

const mesActual = new Date().getMonth() + 1;
const anioActual = new Date().getFullYear();

const MESES = [
    { valor: 1,  nombre: 'Enero' },
    { valor: 2,  nombre: 'Febrero' },
    { valor: 3,  nombre: 'Marzo' },
    { valor: 4,  nombre: 'Abril' },
    { valor: 5,  nombre: 'Mayo' },
    { valor: 6,  nombre: 'Junio' },
    { valor: 7,  nombre: 'Julio' },
    { valor: 8,  nombre: 'Agosto' },
    { valor: 9,  nombre: 'Septiembre' },
    { valor: 10, nombre: 'Octubre' },
    { valor: 11, nombre: 'Noviembre' },
    { valor: 12, nombre: 'Diciembre' },
];

const ANIOS_FILTRO = Array.from({ length: 5 }, (_, i) => anioActual - i);

const HonorariosRecibidos = () => {
    const [lista, setLista]           = useState([]);
    const [cargando, setCargando]     = useState(false);
    const [enviando, setEnviando]     = useState(false);
    const [mensaje, setMensaje]       = useState(null);

    // Filtros
    const [filtromes, setFiltroMes]   = useState(mesActual);
    const [filtroAnio, setFiltroAnio] = useState(anioActual);

    // Formulario
    const [rut, setRut]               = useState('');
    const [nombre, setNombre]         = useState('');
    const [noBoleta, setNoBoleta]     = useState('');
    const [monto, setMonto]           = useState('');
    const [fecha, setFecha]           = useState('');

    // Cálculo en vivo
    const anioFecha = fecha ? new Date(fecha).getFullYear() : null;
    const tasa      = TASAS[anioFecha] ?? null;
    const montoParsed = monto ? parseInt(monto, 10) : 0;
    const retencion  = tasa && montoParsed ? Math.round(montoParsed * tasa / 100) : null;
    const liquido    = retencion !== null ? montoParsed - retencion : null;

    const mostrarMensaje = (tipo, texto) => {
        setMensaje({ tipo, texto });
        setTimeout(() => setMensaje(null), 5000);
    };

    const cargarLista = useCallback(async () => {
        setCargando(true);
        try {
            const resp = await honorarios.listar({ mes: filtromes, anio: filtroAnio });
            const items = Array.isArray(resp) ? resp : (resp?.data ?? []);
            setLista(items);
        } catch (_) {
            // el interceptor global muestra el toast
        } finally {
            setCargando(false);
        }
    }, [filtromes, filtroAnio]);

    useEffect(() => {
        cargarLista();
    }, [cargarLista]);

    const limpiarForm = () => {
        setRut('');
        setNombre('');
        setNoBoleta('');
        setMonto('');
        setFecha('');
    };

    const handleRegistrar = async (e) => {
        e.preventDefault();
        if (!rut || !nombre || !monto || !fecha) {
            mostrarMensaje('error', 'Completa los campos obligatorios: RUT, nombre, monto y fecha.');
            return;
        }
        setEnviando(true);
        try {
            await honorarios.registrar({
                rut_prestador:    rut,
                nombre_prestador: nombre,
                numero_boleta:    noBoleta || null,
                monto_bruto:      parseInt(monto, 10),
                fecha,
            });
            mostrarMensaje('exito', 'Honorario registrado correctamente.');
            limpiarForm();
            await cargarLista();
        } catch (err) {
            const msg = err?.response?.data?.mensaje ?? err?.response?.data?.message ?? 'Error al registrar el honorario.';
            mostrarMensaje('error', msg);
        } finally {
            setEnviando(false);
        }
    };

    const eliminarHonorario = async (id) => {
        try {
            await honorarios.eliminar(id);
            mostrarMensaje('exito', 'Honorario eliminado.');
            await cargarLista();
        } catch (err) {
            const msg = err?.response?.data?.mensaje ?? err?.response?.data?.message ?? 'Error al eliminar.';
            mostrarMensaje('error', msg);
        }
    };

    return (
        <div className="max-w-5xl mx-auto p-6 md:p-8">
            <header className="mb-6">
                <h1 className="text-2xl md:text-3xl font-black text-slate-900 dark:text-slate-100 flex items-center gap-3">
                    <i className="fas fa-receipt text-emerald-600" />
                    Honorarios Recibidos
                    <AyudaModulo moduloId="honorariosRecibidos" />
                </h1>
                <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Boletas de honorarios recibidas con retención segunda categoría (art. 74 N°2 LIR)
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

            {/* Formulario de registro */}
            <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 p-5 mb-6">
                <h2 className="text-sm font-bold text-slate-700 dark:text-slate-300 mb-4 uppercase tracking-wide">Registrar honorario</h2>
                <form onSubmit={handleRegistrar}>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
                        <div>
                            <label className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">
                                RUT prestador <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                value={rut}
                                onChange={(e) => setRut(e.target.value)}
                                placeholder="12345678-5"
                                className={inputCls}
                                required
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">
                                Nombre prestador <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                value={nombre}
                                onChange={(e) => setNombre(e.target.value)}
                                placeholder="Juan Pérez Silva"
                                className={inputCls}
                                required
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">
                                N° boleta (opcional)
                            </label>
                            <input
                                type="text"
                                value={noBoleta}
                                onChange={(e) => setNoBoleta(e.target.value)}
                                placeholder="000123"
                                className={inputCls}
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">
                                Monto bruto (CLP) <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="number"
                                value={monto}
                                onChange={(e) => setMonto(e.target.value)}
                                placeholder="500000"
                                min="1"
                                className={inputCls}
                                required
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">
                                Fecha <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="date"
                                value={fecha}
                                onChange={(e) => setFecha(e.target.value)}
                                className={inputCls}
                                required
                            />
                        </div>
                    </div>

                    {/* Cálculo en vivo */}
                    {montoParsed > 0 && fecha && (
                        <div className="mb-4 p-3 bg-slate-50 dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-700 text-sm">
                            {tasa !== null ? (
                                <div className="space-y-1">
                                    <div className="text-slate-700 dark:text-slate-300">
                                        <span className="font-semibold">Retención ({tasa}%):</span>{' '}
                                        <span className="font-mono">{retencion !== null ? clpFmt.format(retencion) : '—'}</span>
                                    </div>
                                    <div className="text-slate-700">
                                        <span className="font-semibold">Líquido:</span>{' '}
                                        <span className="font-mono">{liquido !== null ? clpFmt.format(liquido) : '—'}</span>
                                    </div>
                                </div>
                            ) : (
                                <p className="text-amber-700">
                                    <i className="fas fa-exclamation-triangle mr-1" />
                                    Tasa de retención no configurada para el año {anioFecha}.
                                </p>
                            )}
                        </div>
                    )}

                    <button
                        type="submit"
                        disabled={enviando}
                        className="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm disabled:opacity-50"
                    >
                        {enviando
                            ? <><i className="fas fa-spinner fa-spin" /> Registrando...</>
                            : <><i className="fas fa-plus" /> Registrar</>
                        }
                    </button>
                </form>
            </div>

            {/* Filtros de período */}
            <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 p-4 mb-6">
                <h2 className="text-sm font-bold text-slate-700 dark:text-slate-300 mb-3 uppercase tracking-wide">Filtrar período</h2>
                <div className="flex flex-wrap gap-3">
                    <div>
                        <label className="block text-xs font-semibold text-slate-600 mb-1">Mes</label>
                        <select
                            value={filtromes}
                            onChange={(e) => setFiltroMes(Number(e.target.value))}
                            className={inputCls + ' w-auto'}
                        >
                            {MESES.map((m) => (
                                <option key={m.valor} value={m.valor}>{m.nombre}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-slate-600 mb-1">Año</label>
                        <select
                            value={filtroAnio}
                            onChange={(e) => setFiltroAnio(Number(e.target.value))}
                            className={inputCls + ' w-auto'}
                        >
                            {ANIOS_FILTRO.map((a) => (
                                <option key={a} value={a}>{a}</option>
                            ))}
                        </select>
                    </div>
                </div>
            </div>

            {/* Tabla */}
            <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div className="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                    <h2 className="font-bold text-slate-900 dark:text-slate-100">Honorarios del período</h2>
                    <button
                        onClick={cargarLista}
                        className="text-xs text-emerald-600 hover:text-emerald-800 font-semibold flex items-center gap-1"
                    >
                        <i className="fas fa-sync-alt" /> Actualizar
                    </button>
                </div>

                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="sticky top-0 z-10 bg-slate-50 dark:bg-slate-900 text-slate-600 dark:text-slate-400 text-xs uppercase">
                            <tr>
                                <th className="px-4 py-3 text-left font-semibold">Fecha</th>
                                <th className="px-4 py-3 text-left font-semibold">RUT</th>
                                <th className="px-4 py-3 text-left font-semibold">Nombre</th>
                                <th className="px-4 py-3 text-left font-semibold">N° Boleta</th>
                                <th className="px-4 py-3 text-right font-semibold">Bruto</th>
                                <th className="px-4 py-3 text-right font-semibold">Tasa%</th>
                                <th className="px-4 py-3 text-right font-semibold">Retención</th>
                                <th className="px-4 py-3 text-right font-semibold">Líquido</th>
                                <th className="px-4 py-3 text-center font-semibold">Eliminar</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-700">
                            {cargando ? (
                                <TablaSkeleton filas={6} columnas={9} />
                            ) : lista.length === 0 ? (
                                <EstadoVacio mensaje="Sin boletas de honorarios." />
                            ) : lista.map((h) => {
                                const anioH    = h.fecha ? new Date(h.fecha).getFullYear() : null;
                                const tasaH    = TASAS[anioH] ?? h.tasa ?? null;
                                const brutoH   = h.monto_bruto ?? 0;
                                const retH     = h.monto_retencion ?? (tasaH ? Math.round(brutoH * tasaH / 100) : null);
                                const liqH     = h.monto_liquido  ?? (retH !== null ? brutoH - retH : null);
                                return (
                                    <tr key={h.id} className="hover:bg-slate-50 dark:hover:bg-slate-700">
                                        <td className="px-4 py-3 text-slate-700 dark:text-slate-300">
                                            {h.fecha ? new Date(h.fecha).toLocaleDateString('es-CL') : '—'}
                                        </td>
                                        <td className="px-4 py-3 font-mono text-slate-700 dark:text-slate-300">{h.rut_prestador ?? '—'}</td>
                                        <td className="px-4 py-3 text-slate-900 dark:text-slate-100">{h.nombre_prestador ?? '—'}</td>
                                        <td className="px-4 py-3 text-slate-600 dark:text-slate-400">{h.numero_boleta ?? '—'}</td>
                                        <td className="px-4 py-3 text-right font-mono text-slate-900 dark:text-slate-100">
                                            {clpFmt.format(brutoH)}
                                        </td>
                                        <td className="px-4 py-3 text-right text-slate-700 dark:text-slate-300">
                                            {tasaH !== null ? `${tasaH}%` : '—'}
                                        </td>
                                        <td className="px-4 py-3 text-right font-mono text-red-700">
                                            {retH !== null ? clpFmt.format(retH) : '—'}
                                        </td>
                                        <td className="px-4 py-3 text-right font-mono text-emerald-700">
                                            {liqH !== null ? clpFmt.format(liqH) : '—'}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            <BotonEliminar onConfirmar={() => eliminarHonorario(h.id)} />
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
};

export default HonorariosRecibidos;
