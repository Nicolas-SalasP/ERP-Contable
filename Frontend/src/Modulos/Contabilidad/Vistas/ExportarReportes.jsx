import React, { useState, useEffect, useCallback } from 'react';
import Select from 'react-select';
import { Mail, Send } from 'lucide-react';
import { api } from '../../../Configuracion/api';
import { logger } from '../../../Configuracion/logger';
import { TablaSkeleton } from '../../../Componentes/Skeleton';
import { EstadoVacio } from '../../../Componentes/EstadoVacio';
import { useToast } from '../../../Contextos/ToastContext';
import { useAuth } from '../../../Contextos/AuthContext';
import AyudaModulo from '../../../Componentes/AyudaModulo';

const ESTADO_ESTILOS = {
    PENDIENTE: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
    PROCESANDO: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    ENVIADO: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    ERROR: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
};

const EN_CURSO = ['PENDIENTE', 'PROCESANDO'];

const ExportarReportes = () => {
    const { toast } = useToast();
    const { user } = useAuth();

    const [tipoReporte, setTipoReporte] = useState('libro_diario');
    const [fechaInicio, setFechaInicio] = useState('');
    const [fechaFin, setFechaFin] = useState('');
    const [filtro, setFiltro] = useState(1);
    const [cuentaSel, setCuentaSel] = useState(null);
    const [cuentas, setCuentas] = useState([]);
    const [email, setEmail] = useState('');
    const [enviando, setEnviando] = useState(false);

    const [historial, setHistorial] = useState([]);
    const [cargandoHistorial, setCargandoHistorial] = useState(false);

    useEffect(() => {
        if (user?.email) setEmail(user.email);
    }, [user]);

    useEffect(() => {
        api.get('/banco/cuentas-imputables')
            .then(res => {
                if (res.success) {
                    setCuentas(res.data.map(c => ({ value: c.codigo, label: `[${c.codigo}] ${c.nombre} (${c.tipo})` })));
                }
            })
            .catch(err => logger.error('Error cargando plan de cuentas', err));
    }, []);

    const cargarHistorial = useCallback(async () => {
        setCargandoHistorial(true);
        try {
            const res = await api.get('/contabilidad/reportes/exportar');
            if (res.success) setHistorial(res.data);
        } catch (err) {
            logger.error('Error cargando historial de exportaciones', err);
        } finally {
            setCargandoHistorial(false);
        }
    }, []);

    useEffect(() => {
        cargarHistorial();
    }, [cargarHistorial]);

    // Mientras haya alguna solicitud PENDIENTE/PROCESANDO, refresca cada 15s para reflejar cuando quede ENVIADO/ERROR.
    useEffect(() => {
        const hayEnCurso = historial.some(h => EN_CURSO.includes(h.estado));
        if (!hayEnCurso) return undefined;
        const intervalo = setInterval(cargarHistorial, 15000);
        return () => clearInterval(intervalo);
    }, [historial, cargarHistorial]);

    const enviarSolicitud = async (e) => {
        e.preventDefault();

        if (!fechaInicio || !fechaFin) {
            return toast('Selecciona la fecha de inicio y de fin.', 'warning');
        }
        if (tipoReporte === 'libro_mayor' && !cuentaSel) {
            return toast('Selecciona la cuenta contable para el Libro Mayor.', 'warning');
        }

        setEnviando(true);
        try {
            const res = await api.post('/contabilidad/reportes/exportar', {
                tipo_reporte: tipoReporte,
                fecha_inicio: fechaInicio,
                fecha_fin: fechaFin,
                filtro: Number(filtro),
                cuenta_contable: tipoReporte === 'libro_mayor' ? cuentaSel.value : undefined,
                email: email || undefined,
            });

            if (res.success) {
                toast(res.message || 'Tu reporte se está generando, te llegará por correo en unos minutos.', 'success');
                cargarHistorial();
            } else {
                toast(res.message || 'No se pudo solicitar el reporte.', 'error');
            }
        } catch (err) {
            logger.error('Error solicitando exportación de reporte', err);
            toast(err.message || 'No se pudo conectar con el servidor.', 'error');
        } finally {
            setEnviando(false);
        }
    };

    return (
        <div className="max-w-4xl mx-auto p-6 font-sans text-slate-800 dark:text-slate-200">
            <div className="mb-6 flex items-start justify-between gap-4">
                <div>
                    <h1 className="text-3xl font-bold text-slate-900 dark:text-slate-100">
                        Exportar Reportes por Correo
                    </h1>
                    <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Libro Diario y Libro Mayor sin el límite de 1 año de la pantalla en vivo — pide hasta 10 años y te llega el Excel al correo.
                    </p>
                </div>
                <AyudaModulo
                    modulo="exportar-reportes"
                    titulo="Exportar Reportes por Correo"
                    descripcion="Solicita un Libro Diario o Libro Mayor para cualquier rango de fechas (hasta 10 años). El reporte se genera en segundo plano y se envía como Excel adjunto al correo indicado, sin bloquear tu sesión."
                />
            </div>

            <form onSubmit={enviarSolicitud} className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 mb-8 space-y-4">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label htmlFor="reporte-tipo" className="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">Tipo de Reporte</label>
                        <select
                            id="reporte-tipo"
                            value={tipoReporte}
                            onChange={(e) => setTipoReporte(e.target.value)}
                            className="w-full border border-slate-300 dark:border-slate-600 dark:bg-slate-900 rounded-lg px-3 py-2 text-sm"
                        >
                            <option value="libro_diario">Libro Diario</option>
                            <option value="libro_mayor">Libro Mayor</option>
                        </select>
                    </div>

                    {tipoReporte === 'libro_mayor' && (
                        <div>
                            <label htmlFor="reporte-cuenta-contable" className="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">Cuenta Contable</label>
                            <Select
                                inputId="reporte-cuenta-contable"
                                options={cuentas}
                                value={cuentaSel}
                                onChange={setCuentaSel}
                                placeholder="Busca la cuenta..."
                                classNamePrefix="react-select"
                            />
                        </div>
                    )}

                    <div>
                        <label htmlFor="reporte-fecha-inicio" className="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">Fecha Inicio</label>
                        <input
                            id="reporte-fecha-inicio"
                            type="date"
                            value={fechaInicio}
                            onChange={(e) => setFechaInicio(e.target.value)}
                            className="w-full border border-slate-300 dark:border-slate-600 dark:bg-slate-900 rounded-lg px-3 py-2 text-sm"
                        />
                    </div>

                    <div>
                        <label htmlFor="reporte-fecha-fin" className="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">Fecha Fin</label>
                        <input
                            id="reporte-fecha-fin"
                            type="date"
                            value={fechaFin}
                            onChange={(e) => setFechaFin(e.target.value)}
                            className="w-full border border-slate-300 dark:border-slate-600 dark:bg-slate-900 rounded-lg px-3 py-2 text-sm"
                        />
                    </div>

                    <div>
                        <label htmlFor="reporte-filtro-estado" className="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">Filtro de Estado</label>
                        <select
                            id="reporte-filtro-estado"
                            value={filtro}
                            onChange={(e) => setFiltro(e.target.value)}
                            className="w-full border border-slate-300 dark:border-slate-600 dark:bg-slate-900 rounded-lg px-3 py-2 text-sm"
                        >
                            <option value={1}>Válidos (excluye anulados)</option>
                            <option value={2}>Solo anulados/internos</option>
                            <option value={0}>Historial completo</option>
                        </select>
                    </div>

                    <div>
                        <label htmlFor="reporte-email" className="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">Enviar a</label>
                        <div className="relative">
                            <Mail className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                            <input
                                id="reporte-email"
                                type="email"
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                                placeholder="correo@destino.cl"
                                className="w-full border border-slate-300 dark:border-slate-600 dark:bg-slate-900 rounded-lg pl-9 pr-3 py-2 text-sm"
                            />
                        </div>
                    </div>
                </div>

                <button
                    type="submit"
                    disabled={enviando}
                    className="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-bold rounded-lg bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white transition-colors"
                >
                    <Send className="w-4 h-4" />
                    {enviando ? 'Solicitando...' : 'Solicitar Reporte por Correo'}
                </button>
            </form>

            <h2 className="text-lg font-bold text-slate-800 dark:text-slate-200 mb-3">Historial de Solicitudes</h2>
            <div className="bg-white dark:bg-slate-800 rounded-lg shadow border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full text-left border-collapse">
                        <thead>
                            <tr className="text-[10px] uppercase font-bold bg-slate-100 dark:bg-slate-700 border-b border-slate-200 dark:border-slate-600">
                                <th className="px-3 py-2 text-slate-500 dark:text-slate-400">Tipo</th>
                                <th className="px-3 py-2 text-slate-500 dark:text-slate-400">Rango</th>
                                <th className="px-3 py-2 text-slate-500 dark:text-slate-400">Destino</th>
                                <th className="px-3 py-2 text-slate-500 dark:text-slate-400">Estado</th>
                                <th className="px-3 py-2 text-slate-500 dark:text-slate-400">Solicitado</th>
                            </tr>
                        </thead>
                        <tbody className="text-xs divide-y divide-slate-100 dark:divide-slate-700">
                            {cargandoHistorial ? (
                                <TablaSkeleton filas={3} columnas={5} />
                            ) : historial.length === 0 ? (
                                <EstadoVacio mensaje="Todavía no has solicitado ningún reporte por correo." />
                            ) : (
                                historial.map((h) => (
                                    <tr key={h.id} className="hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                                        <td className="px-3 py-2 font-bold text-slate-700 dark:text-slate-300">
                                            {h.tipo_reporte === 'libro_mayor' ? 'Libro Mayor' : 'Libro Diario'}
                                        </td>
                                        <td className="px-3 py-2 text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                            {h.fecha_inicio} → {h.fecha_fin}
                                        </td>
                                        <td className="px-3 py-2 text-slate-600 dark:text-slate-400">{h.email_destino}</td>
                                        <td className="px-3 py-2">
                                            <span className={`inline-block px-2 py-0.5 rounded-full text-[10px] font-bold uppercase ${ESTADO_ESTILOS[h.estado] || ESTADO_ESTILOS.PENDIENTE}`}>
                                                {h.estado}
                                            </span>
                                            {h.estado === 'ERROR' && h.error_mensaje && (
                                                <p className="text-[10px] text-red-500 mt-0.5">{h.error_mensaje}</p>
                                            )}
                                        </td>
                                        <td className="px-3 py-2 text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                            {h.created_at ? new Date(h.created_at).toLocaleString('es-CL') : '-'}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
};

export default ExportarReportes;
