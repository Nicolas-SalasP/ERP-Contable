import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '../../Configuracion/api';
import FilaItemCotizacion from './Componentes/FilaItemCotizacion';
import ModalGenerico from '../../Componentes/ModalGenerico';

const CrearCotizacion = () => {
    const navigate = useNavigate();

    const [clientes, setClientes] = useState([]);
    const [clienteSeleccionado, setClienteSeleccionado] = useState(null);
    const [busquedaCliente, setBusquedaCliente] = useState('');
    const [mostrarDropdown, setMostrarDropdown] = useState(false);

    const [items, setItems] = useState([{ productoNombre: '', descripcion: '', cantidad: 1, precioUnitario: 0 }]);
    const [fecha, setFecha] = useState(new Date().toISOString().split('T')[0]);
    const [validez, setValidez] = useState(15);
    const [esAfecta, setEsAfecta] = useState(true);
    const [ivaPorcentaje, setIvaPorcentaje] = useState(19);

    const [modal, setModal] = useState({ show: false, title: '', message: '', type: 'info', idGenerado: null });

    useEffect(() => {
        const fetchClientes = async () => {
            try {
                const res = await api.get('/clientes');
                if (res.success) setClientes(res.data);
            } catch (error) { console.error("Error cargando clientes:", error); }
        };
        fetchClientes();
    }, []);

    // BUSCADOR MEJORADO: Filtra por RUT y Razón Social
    const clientesFiltrados = clientes.filter(c =>
        c.razon_social.toLowerCase().includes(busquedaCliente.toLowerCase()) ||
        c.rut.toLowerCase().includes(busquedaCliente.toLowerCase())
    );

    const handleItemChange = (index, name, value) => {
        const nuevosItems = [...items];
        // Convertimos a número si es cantidad o precio para evitar errores de cálculo
        nuevosItems[index][name] = (name === 'cantidad' || name === 'precioUnitario') ? Number(value) : value;
        setItems(nuevosItems);
    };

    const agregarItem = () => {
        setItems([...items, { productoNombre: '', descripcion: '', cantidad: 1, precioUnitario: 0 }]);
    };

    const eliminarItem = (index) => {
        if (items.length > 1) setItems(items.filter((_, i) => i !== index));
    };

    const neto = items.reduce((acc, item) => acc + (Number(item.cantidad) * Number(item.precioUnitario)), 0);
    const montoIva = esAfecta ? Math.round(neto * (Number(ivaPorcentaje) / 100)) : 0;
    const total = neto + montoIva;

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!clienteSeleccionado) {
            setModal({ show: true, title: 'Atención', message: 'Debe seleccionar un cliente de la lista.', type: 'warning' });
            return;
        }

        try {
            const res = await api.post('/cotizaciones', {
                clienteId: clienteSeleccionado.id,
                fechaEmision: fecha,
                validezDias: validez,
                esAfecta: esAfecta ? 1 : 0,
                porcentajeIva: ivaPorcentaje,
                items: items
            });

            if (res.success) {
                setModal({
                    show: true,
                    title: '¡Cotización Generada!',
                    message: `La cotización #${res.data.id} se ha registrado con éxito.`,
                    type: 'success',
                    idGenerado: res.data.id
                });
            }
        } catch (error) {
            setModal({ show: true, title: 'Error', message: 'No se pudo guardar la cotización.', type: 'danger' });
        }
    };

    const seleccionarCliente = (c) => {
        setClienteSeleccionado(c);
        setBusquedaCliente(`${c.rut} - ${c.razon_social}`);
        setMostrarDropdown(false);
    };

    return (
        <div className="max-w-6xl mx-auto space-y-6">
            <div className="flex justify-between items-center">
                <div>
                    <h1 className="text-3xl font-bold text-slate-900">Nueva Cotización</h1>
                    <p className="text-slate-500 text-sm">Vincule un cliente y detalle los productos o servicios</p>
                </div>
            </div>

            <form onSubmit={handleSubmit} className="bg-white shadow-sm rounded-xl p-8 border border-slate-200">
                <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div className="md:col-span-2 relative">
                        <label className="block text-xs font-bold text-slate-500 uppercase mb-2">Cliente</label>
                        <input
                            type="text"
                            className="w-full border border-slate-200 p-3 rounded-lg outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all"
                            placeholder="Buscar por RUT o Razón Social..."
                            value={busquedaCliente}
                            onChange={(e) => { setBusquedaCliente(e.target.value); setMostrarDropdown(true); }}
                            onFocus={() => setMostrarDropdown(true)}
                        />
                        {mostrarDropdown && busquedaCliente.length > 0 && (
                            <div className="absolute z-50 w-full bg-white border border-slate-200 mt-1 rounded-lg shadow-2xl max-h-60 overflow-y-auto">
                                {clientesFiltrados.length > 0 ? (
                                    clientesFiltrados.map(c => (
                                        <div key={c.id} className="p-3 hover:bg-emerald-50 cursor-pointer border-b last:border-0 transition-colors" onClick={() => seleccionarCliente(c)}>
                                            <div className="font-bold text-slate-800 text-sm">{c.razon_social}</div>
                                            <div className="text-xs text-slate-500">{c.rut}</div>
                                        </div>
                                    ))
                                ) : (
                                    <div className="p-4 text-sm text-slate-400 italic">No se encontraron resultados...</div>
                                )}
                            </div>
                        )}
                    </div>
                    <div>
                        <label className="block text-xs font-bold text-slate-500 uppercase mb-2">Fecha Emisión</label>
                        <input type="date" value={fecha} className="w-full border border-slate-200 p-3 rounded-lg outline-none focus:ring-2 focus:ring-emerald-500/20" onChange={(e) => setFecha(e.target.value)} />
                    </div>
                    <div>
                        <label className="block text-xs font-bold text-slate-500 uppercase mb-2">Días Validez</label>
                        <input type="number" value={validez} className="w-full border border-slate-200 p-3 rounded-lg outline-none focus:ring-2 focus:ring-emerald-500/20" onChange={(e) => setValidez(e.target.value)} />
                    </div>
                </div>

                <div className="flex flex-col md:flex-row items-center justify-between gap-6 mb-8 bg-slate-50 p-4 rounded-lg border border-slate-100">
                    <div className="flex items-center gap-6">
                        <span className="text-sm font-bold text-slate-600 uppercase">Impuestos:</span>
                        <div className="flex items-center gap-4">
                            <label className="flex items-center gap-2 cursor-pointer">
                                <input type="radio" checked={esAfecta} onChange={() => setEsAfecta(true)} className="w-4 h-4 text-emerald-600 focus:ring-emerald-500" />
                                <span className="text-sm text-slate-700 font-medium">Afecta (Con IVA)</span>
                            </label>
                            <label className="flex items-center gap-2 cursor-pointer">
                                <input type="radio" checked={!esAfecta} onChange={() => setEsAfecta(false)} className="w-4 h-4 text-emerald-600 focus:ring-emerald-500" />
                                <span className="text-sm text-slate-700 font-medium">Exento (Sin IVA)</span>
                            </label>
                        </div>
                    </div>
                    {esAfecta && (
                        <div className="flex items-center gap-3 bg-white p-2 px-4 border border-slate-200 rounded-lg shadow-sm">
                            <span className="text-xs font-bold text-slate-500 uppercase">Tasa de IVA:</span>
                            <div className="flex items-center">
                                <input type="number" value={ivaPorcentaje} onChange={(e) => setIvaPorcentaje(e.target.value)} className="w-12 text-center font-bold text-emerald-600 outline-none" onFocus={(e) => e.target.select()} />
                                <span className="font-bold text-emerald-600">%</span>
                            </div>
                        </div>
                    )}
                </div>

                <div className="mb-8">
                    <div className="flex justify-between items-center mb-6 border-b border-slate-100 pb-2">
                        <h3 className="text-sm font-bold text-slate-700 uppercase tracking-widest">Detalle de Cotización</h3>
                        <button type="button" onClick={agregarItem} className="bg-emerald-50 text-emerald-600 text-xs font-bold px-4 py-2 rounded-lg hover:bg-emerald-100 transition-all">+ AGREGAR LÍNEA</button>
                    </div>
                    <div className="space-y-6">
                        {items.map((item, index) => (
                            <FilaItemCotizacion key={index} index={index} item={item} onChange={handleItemChange} onRemove={eliminarItem} />
                        ))}
                    </div>
                </div>

                <div className="flex flex-col md:flex-row justify-between items-end pt-8 border-t border-slate-100">
                    <button type="button" onClick={() => navigate('/cotizaciones')} className="text-slate-400 font-bold text-sm mb-4 md:mb-0 hover:text-slate-600 transition-colors">← Volver al listado</button>
                    <div className="flex items-center gap-12">
                        <div className="text-right space-y-1">
                            <div className="text-slate-400 text-xs font-bold uppercase tracking-tighter">Neto: ${neto.toLocaleString('es-CL')}</div>
                            {esAfecta && <div className="text-slate-400 text-xs font-bold uppercase tracking-tighter">IVA ({ivaPorcentaje}%): ${montoIva.toLocaleString('es-CL')}</div>}
                            <div className="text-emerald-600 text-[10px] font-bold uppercase mt-3 tracking-widest">Total Final Cotización</div>
                            <div className="text-5xl font-black text-slate-900 leading-none">${total.toLocaleString('es-CL')}</div>
                        </div>
                        <button type="submit" className="bg-slate-900 text-white px-12 py-5 rounded-2xl font-bold shadow-2xl hover:bg-slate-800 transition-all active:scale-95">Generar Cotización</button>
                    </div>
                </div>
            </form>

            <ModalGenerico isOpen={modal.show} onClose={() => { setModal({ ...modal, show: false }); if (modal.type === 'success') navigate('/cotizaciones'); }} title={modal.title} message={modal.message} type={modal.type}>
                {modal.type === 'success' && (
                    <div className="p-4 text-center">
                        <button onClick={() => window.open(`${api.defaults.baseURL}/cotizaciones/pdf/${modal.idGenerado}`, '_blank')} className="w-full py-4 rounded-xl font-bold bg-slate-100 text-slate-800 mb-3 border border-slate-200 hover:bg-slate-200 transition-all flex items-center justify-center gap-2">📥 Descargar PDF Ahora</button>
                        <button onClick={() => navigate('/cotizaciones')} className="w-full py-4 rounded-xl font-bold bg-emerald-600 text-white hover:bg-emerald-700 transition-all">Finalizar y Salir</button>
                    </div>
                )}
            </ModalGenerico>
        </div>
    );
};

export default CrearCotizacion;