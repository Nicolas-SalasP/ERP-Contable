import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { api } from '../../../Configuracion/api';
import EstadoCarga from '../../../Componentes/EstadoCarga';
import Swal from 'sweetalert2';

const VisorAsientoCompleto = () => {
    const { id } = useParams();
    const navigate = useNavigate();

    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [vistaActiva, setVistaActiva] = useState(1);

    useEffect(() => {
        const cargarAsiento = async () => {
            try {
                const response = await api.get(`/facturas/${id}/asiento`);
                if (response.success) {
                    setData(response.data);
                } else {
                    setError('No se pudo encontrar la información contable.');
                }
            } catch (err) {
                setError(err.response?.data?.message || err.message || 'El asiento no existe o no pudo ser cargado.');
            } finally {
                setLoading(false);
            }
        };
        cargarAsiento();
    }, [id]);

    if (loading) {
        return (
            <EstadoCarga
                cargando={true}
                mensajeCargando="Cargando información del asiento..."
                tamano="compacto"
                color="indigo"
            />
        );
    }

    if (error) {
        return (
            <div className="p-10 flex flex-col items-center justify-center min-h-[50vh]">
                <div className="bg-red-50 text-red-600 p-4 rounded border border-red-200 mb-4 shadow-sm">{error}</div>
                <button onClick={() => navigate(-1)} className="text-blue-600 hover:text-blue-800 font-bold hover:underline">
                    ← Volver al Historial
                </button>
            </div>
        );
    }

    if (!data) {
        return null;
    }

    const { cabecera, detalles } = data;
    const totalDebe = detalles.reduce((acc, fila) => acc + Number(fila.debe), 0);
    const totalHaber = detalles.reduce((acc, fila) => acc + Number(fila.haber), 0);

    const formatoFechaHora = (fechaString) => {
        if (!fechaString) return 'N/A';
        const date = new Date(fechaString);
        return date.toLocaleString('es-CL', { dateStyle: 'medium', timeStyle: 'short' });
    };

    const formatoFechaCorta = (fechaString) => {
        if (!fechaString) return 'N/A';
        // Forzamos la zona horaria UTC para que no reste un día por el cambio de horario local
        const date = new Date(fechaString);
        return date.toLocaleDateString('es-CL', { timeZone: 'UTC' });
    };

    const manejarReclasificacion = () => {
        navigate(`/contabilidad/factura/${id}/reclasificar`);
    };

    return (
        <div className="p-6 bg-slate-50 min-h-screen animate-fade-in-up">
            <div className="bg-white p-5 rounded-t-lg shadow-sm border border-slate-200 mb-4 flex justify-between items-start">
                <div>
                    <h1 className="text-2xl font-bold text-slate-800 mb-1">
                        Comprobante Contable #{cabecera.numero_comprobante || cabecera.id}
                    </h1>
                    <p className="text-slate-500 text-sm font-medium">
                        Fecha: {formatoFechaCorta(cabecera.fecha)} | Estado: <span className="text-emerald-600">{cabecera.estado}</span>
                    </p>
                    <p className="text-slate-700 mt-2 text-sm">{cabecera.glosa}</p>
                </div>

                {/* Contenedor derecho: Botones y Select */}
                <div className="flex flex-col items-end gap-3">
                    <div className="flex gap-2">
                        <button onClick={manejarReclasificacion} className="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white text-sm font-bold rounded shadow transition-colors">
                            Reclasificar
                        </button>
                        <button onClick={() => navigate("/facturas/historial")} className="px-4 py-2 bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 text-sm font-bold rounded shadow-sm transition-colors">
                            Volver
                        </button>
                    </div>

                    {/* Select de Vistas */}
                    <select
                        value={vistaActiva}
                        onChange={(e) => setVistaActiva(Number(e.target.value))}
                        className="w-48 px-3 py-2 bg-slate-50 border border-slate-300 rounded text-sm font-bold text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all cursor-pointer"
                    >
                        <option value={1}>Vista 1 (Básica)</option>
                        <option value={2}>Vista 2 (Extendida)</option>
                        <option value={3}>Vista 3 (Monedas)</option>
                    </select>
                </div>
            </div>

            {/* Tabla Estilo Excel (Compacta) */}
            <div className="bg-white rounded-lg shadow-sm border border-slate-200 overflow-x-auto">
                <table className="w-full border-collapse text-sm whitespace-nowrap">
                    <thead className="bg-slate-100 text-slate-700">
                        <tr>
                            <th className="border-b border-r border-slate-300 px-3 py-2 text-left font-bold">CUENTA</th>
                            {vistaActiva >= 2 && (
                                <th className="border-b border-r border-slate-300 px-3 py-2 text-left font-bold">NOMBRE CUENTA</th>
                            )}
                            <th className="border-b border-r border-slate-300 px-3 py-2 text-left font-bold w-full">DESCRIPCIÓN</th>

                            {vistaActiva === 3 && (
                                <>
                                    <th className="border-b border-r border-slate-300 px-3 py-2 text-center font-bold bg-amber-50">MONEDA</th>
                                    <th className="border-b border-r border-slate-300 px-3 py-2 text-right font-bold bg-amber-50">T/C</th>
                                </>
                            )}

                            <th className="border-b border-r border-slate-300 px-3 py-2 text-right font-bold">DEBE</th>
                            <th className="border-b border-slate-300 px-3 py-2 text-right font-bold">HABER</th>
                        </tr>
                    </thead>
                    <tbody>
                        {detalles.map((fila, index) => (
                            <tr key={index} className="hover:bg-blue-50 transition-colors">
                                <td className="border-b border-r border-slate-200 px-3 py-1.5 font-mono text-slate-600">
                                    {fila.cuenta_contable}
                                </td>
                                {vistaActiva >= 2 && (
                                    <td className="border-b border-r border-slate-200 px-3 py-1.5 text-slate-700">
                                        {fila.cuenta_nombre}
                                    </td>
                                )}
                                <td className="border-b border-r border-slate-200 px-3 py-1.5 text-slate-700 truncate max-w-md">
                                    {fila.glosa_detalle || cabecera.glosa}
                                </td>

                                {vistaActiva === 3 && (
                                    <>
                                        <td className="border-b border-r border-slate-200 px-3 py-1.5 text-center text-slate-500 bg-amber-50/30">CLP</td>
                                        <td className="border-b border-r border-slate-200 px-3 py-1.5 text-right text-slate-500 bg-amber-50/30">1.00</td>
                                    </>
                                )}

                                <td className="border-b border-r border-slate-200 px-3 py-1.5 text-right font-medium text-slate-800">
                                    {Number(fila.debe).toLocaleString('es-CL')}
                                </td>
                                <td className="border-b border-slate-200 px-3 py-1.5 text-right font-medium text-slate-800">
                                    {Number(fila.haber).toLocaleString('es-CL')}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                    <tfoot className="bg-slate-50 font-bold border-t-2 border-slate-400">
                        <tr>
                            <td
                                colSpan={vistaActiva === 1 ? 2 : (vistaActiva === 2 ? 3 : 5)}
                                className="px-3 py-2 text-right border-r border-slate-300 text-slate-600"
                            >
                                TOTALES:
                            </td>
                            <td className="px-3 py-2 text-right border-r border-slate-300 text-blue-700">
                                {totalDebe.toLocaleString('es-CL')}
                            </td>
                            <td className="px-3 py-2 text-right text-blue-700">
                                {totalHaber.toLocaleString('es-CL')}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {/* Footer: Auditoría */}
            <div className="mt-6 flex justify-between items-end text-xs text-slate-500">
                <p>Módulo: <span className="uppercase font-semibold">{cabecera.origen_modulo}</span></p>
                <div className="text-right bg-slate-200/50 p-2 rounded border border-slate-200">
                    Última modificación por: <strong>{cabecera.usuario ? cabecera.usuario.nombre : `Usuario ID ${cabecera.usuario_id || 'Sistema'}`}</strong><br />
                    {formatoFechaHora(cabecera.updated_at || cabecera.created_at)}
                </div>
            </div>
        </div>
    );
};

export default VisorAsientoCompleto;