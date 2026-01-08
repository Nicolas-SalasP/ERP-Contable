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

    const [items, setItems] = useState([{ productoNombre: '', cantidad: 1, precioUnitario: 0 }]);
    const [fecha, setFecha] = useState(new Date().toISOString().split('T')[0]);
    const [validez, setValidez] = useState(15);
    const [esAfecta, setEsAfecta] = useState(true);

    const [modal, setModal] = useState({
        show: false,
        title: '',
        message: '',
        type: 'info',
        idGenerado: null
    });

    useEffect(() => {
        const fetchClientes = async () => {
            try {
                const res = await api.get('/clientes');
                if (res.success) setClientes(res.data);
            } catch (error) {
                console.error("Error cargando clientes:", error);
            }
        };
        fetchClientes();
    }, []);

    const clientesFiltrados = clientes.filter(c =>
        c.razon_social.toLowerCase().includes(busquedaCliente.toLowerCase()) ||
        c.rut.toLowerCase().includes(busquedaCliente.toLowerCase())
    );

    const handleItemChange = (index, name, value) => {
        const nuevosItems = [...items];
        nuevosItems[index][name] = value;
        setItems(nuevosItems);
    };

    const eliminarItem = (index) => {
        if (items.length > 1) {
            setItems(items.filter((_, i) => i !== index));
        }
    };

    const agregarItem = () => {
        setItems([...items, { productoNombre: '', cantidad: 1, precioUnitario: 0 }]);
    };

    const calcularTotal = () => {
        return items.reduce((acc, item) => acc + (Number(item.cantidad) * Number(item.precioUnitario)), 0);
    };

    const descargarPDFBackend = (id) => {
        const baseURL = api.defaults?.baseURL || api.getUri?.() || 'http://localhost/ERP-Contable/Backend/Public/api';
        const url = `${baseURL}/cotizaciones/pdf/${id}`;
        window.open(url, '_blank');
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!clienteSeleccionado) {
            setModal({ show: true, title: 'Atención', message: 'Debe seleccionar un cliente de la lista.', type: 'warning' });
            return;
        }

        try {
            const res = await api.post('/cotizaciones', {
                clienteId: clienteSeleccionado.id,
                nombreCliente: clienteSeleccionado.razon_social,
                fechaEmision: fecha,
                validezDias: validez,
                esAfecta: esAfecta ? 1 : 0,
                items: items
            });

            if (res.success) {
                setModal({
                    show: true,
                    title: '¡Cotización Generada!',
                    message: `La cotización #${res.id} se ha registrado con éxito.`,
                    type: 'success',
                    idGenerado: res.id
                });
            }
        } catch (error) {
            setModal({ show: true, title: 'Error', message: 'No se pudo guardar la cotización.', type: 'danger' });
        }
    };

    const seleccionarCliente = (cliente) => {
        setClienteSeleccionado(cliente);
        setBusquedaCliente(`${cliente.rut} - ${cliente.razon_social}`);
        setMostrarDropdown(false);
    };

    const cerrarModal = () => {
        setModal({ ...modal, show: false });
        if (modal.type === 'success') navigate('/cotizaciones');
    };

    const descargarPDF = (id) => {
        descargarPDFBackend(id);
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
                <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
                    <div className="md:col-span-2 relative">
                        <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Cliente</label>
                        <input
                            type="text"
                            className="w-full border border-slate-200 p-3 rounded-lg outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all"
                            placeholder="Buscar por RUT o Razón Social..."
                            value={busquedaCliente}
                            onChange={(e) => {
                                setBusquedaCliente(e.target.value);
                                setMostrarDropdown(true);
                                setClienteSeleccionado(null);
                            }}
                            onFocus={() => setMostrarDropdown(true)}
                        />

                        {mostrarDropdown && busquedaCliente.length > 0 && (
                            <div className="absolute z-10 w-full bg-white border border-slate-200 mt-1 rounded-lg shadow-xl max-h-60 overflow-y-auto">
                                {clientesFiltrados.length > 0 ? (
                                    clientesFiltrados.map(c => (
                                        <div
                                            key={c.id}
                                            className="p-3 hover:bg-slate-50 cursor-pointer border-b last:border-0"
                                            onClick={() => seleccionarCliente(c)}
                                        >
                                            <div className="font-bold text-slate-800 text-sm">{c.razon_social}</div>
                                            <div className="text-xs text-slate-500">{c.rut}</div>
                                        </div>
                                    ))
                                ) : (
                                    <div className="p-3 text-sm text-slate-400">No se encontraron clientes.</div>
                                )}
                            </div>
                        )}
                    </div>

                    <div>
                        <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Fecha Emisión</label>
                        <input 
                            type="date" 
                            value={fecha} 
                            className="w-full border border-slate-200 p-3 rounded-lg outline-none focus:ring-2 focus:ring-emerald-500/20" 
                            onChange={(e) => setFecha(e.target.value)} 
                            required 
                        />
                    </div>

                    <div>
                        <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Validez (Días)</label>
                        <input 
                            type="number" 
                            min="1"
                            value={validez} 
                            className="w-full border border-slate-200 p-3 rounded-lg outline-none focus:ring-2 focus:ring-emerald-500/20" 
                            onChange={(e) => setValidez(e.target.value)} 
                            required 
                        />
                    </div>
                </div>

                <div className="flex items-center gap-6 mb-8 bg-slate-50 p-4 rounded-lg border border-slate-100">
                    <span className="text-sm font-bold text-slate-600 uppercase">Configuración de Impuestos:</span>
                    <div className="flex items-center gap-4">
                        <label className="flex items-center gap-2 cursor-pointer">
                            <input 
                                type="radio" 
                                name="afecta" 
                                checked={esAfecta === true} 
                                onChange={() => setEsAfecta(true)}
                                className="text-emerald-600 focus:ring-emerald-500"
                            />
                            <span className="text-sm text-slate-700">Afecta (Con IVA)</span>
                        </label>
                        <label className="flex items-center gap-2 cursor-pointer">
                            <input 
                                type="radio" 
                                name="afecta" 
                                checked={esAfecta === false} 
                                onChange={() => setEsAfecta(false)}
                                className="text-emerald-600 focus:ring-emerald-500"
                            />
                            <span className="text-sm text-slate-700">Exento (Sin IVA)</span>
                        </label>
                    </div>
                </div>

                <div className="mb-8">
                    <div className="flex justify-between items-end mb-4 border-b border-slate-100 pb-2">
                        <h3 className="text-sm font-bold text-slate-700 uppercase tracking-widest">Detalle de la Cotización</h3>
                        <button type="button" onClick={agregarItem} className="text-emerald-600 text-xs font-bold hover:bg-emerald-50 px-3 py-1 rounded-lg transition-colors">+ AGREGAR LÍNEA</button>
                    </div>
                    <div className="space-y-3">
                        {items.map((item, index) => (
                            <FilaItemCotizacion key={index} index={index} item={item} onChange={handleItemChange} onRemove={eliminarItem} />
                        ))}
                    </div>
                </div>

                <div className="flex flex-col md:flex-row justify-between items-center pt-8 border-t border-slate-100">
                    <div className="mb-4 md:mb-0">
                        <button type="button" onClick={() => navigate('/cotizaciones')} className="text-slate-400 font-bold text-sm hover:text-slate-600">← Volver al listado</button>
                    </div>
                    <div className="flex items-center gap-10">
                        <div className="text-right">
                            <p className="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Total {esAfecta ? 'Bruto (Inc. IVA)' : 'Neto'}</p>
                            <p className="text-4xl font-black text-slate-900">${calcularTotal().toLocaleString('es-CL')}</p>
                        </div>
                        <button type="submit" className="bg-slate-900 text-white px-10 py-4 rounded-xl font-bold shadow-xl hover:bg-slate-800 active:scale-95 transition-all">Generar Cotización</button>
                    </div>
                </div>
            </form>

            <ModalGenerico
                isOpen={modal.show}
                onClose={cerrarModal}
                title={modal.title}
                message={modal.message}
                type={modal.type}
            >
                <div className="p-4 text-center">
                    <p className="mb-6 text-slate-600">{modal.message}</p>
                    <div className="flex flex-col gap-3">
                        {modal.type === 'success' && (
                            <button
                                onClick={() => descargarPDF(modal.idGenerado)}
                                className="w-full py-3 rounded-lg font-bold bg-slate-100 text-slate-800 hover:bg-slate-200 transition-colors flex items-center justify-center gap-2 border border-slate-200"
                            >
                                📥 Descargar Ahora
                            </button>
                        )}
                        <button onClick={cerrarModal} className={`w-full py-3 rounded-lg font-bold text-white ${modal.type === 'danger' ? 'bg-red-600' : 'bg-emerald-600'}`}>
                            {modal.type === 'success' ? 'Ir al Listado' : 'Aceptar'}
                        </button>
                    </div>
                </div>
            </ModalGenerico>
        </div>
    );
};

export default CrearCotizacion;