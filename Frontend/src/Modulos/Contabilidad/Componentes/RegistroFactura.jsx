import React, { useState, useEffect, useRef } from 'react';
import ModalGenerico from '../../../Componentes/ModalGenerico';
import { api } from '../../../Configuracion/api';

const formatCurrency = (value) => {
    if (!value && value !== 0) return '';
    return new Intl.NumberFormat('es-CL').format(value.toString().replace(/\D/g, ''));
};

const cleanNumber = (value) => {
    if (!value) return 0;
    return parseInt(value.toString().replace(/\D/g, '')) || 0;
};

const IvaWarningModal = ({ isOpen, onClose, onConfirm, calculado, ingresado }) => {
    const [motivo, setMotivo] = useState('');
    if (!isOpen) return null;
    return (
        <div className="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 p-4 animate-fade-in">
            <div className="bg-white rounded-lg shadow-2xl p-6 max-w-md w-full border-t-4 border-yellow-500">
                <h3 className="text-lg font-bold text-gray-900 mb-2">⚠️ Diferencia de Impuesto Detectada</h3>
                <p className="text-sm text-gray-600 mb-4">El IVA ingresado difiere del cálculo teórico (19%).</p>
                <div className="bg-gray-50 p-3 rounded mb-4 text-sm grid grid-cols-2 gap-4">
                    <div>
                        <span className="block text-gray-500 text-xs">Calculado (19%)</span>
                        <span className="font-bold text-gray-500 text-lg">${formatCurrency(calculado)}</span>
                    </div>
                    <div>
                        <span className="block text-gray-900 text-xs">Ingresado (Real)</span>
                        <span className="font-bold text-emerald-600 text-lg">${formatCurrency(ingresado)}</span>
                    </div>
                </div>
                <label className="block text-sm font-bold text-gray-700 mb-1">Motivo (Para Auditoría)</label>
                <textarea className="w-full border rounded p-2 text-sm focus:ring-yellow-500 outline-none" rows="2" placeholder="Ej: Impuesto específico..." value={motivo} onChange={(e) => setMotivo(e.target.value)}></textarea>
                <div className="flex justify-end space-x-3 mt-6">
                    <button onClick={onClose} className="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded text-sm">Cancelar</button>
                    <button onClick={() => onConfirm(motivo)} className="px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded text-sm font-bold shadow">Confirmar</button>
                </div>
            </div>
        </div>
    );
};

const RegistroFactura = () => {
    const [currentStep, setCurrentStep] = useState(1);
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [checkingDuplicate, setCheckingDuplicate] = useState(false);

    const [showIvaModal, setShowIvaModal] = useState(false);
    const [duplicateWarning, setDuplicateWarning] = useState(false);
    const [successData, setSuccessData] = useState(null);

    const [listaProveedores, setListaProveedores] = useState([]);
    const [busqueda, setBusqueda] = useState('');
    const [sugerencias, setSugerencias] = useState([]);
    const [mostrarSugerencias, setMostrarSugerencias] = useState(false);
    const searchRef = useRef(null);

    const [formData, setFormData] = useState({
        proveedorCodigo: '',
        proveedorId: null,
        proveedorNombre: '',
        rut: '',
        pais: '',
        moneda: 'CLP',
        numeroFactura: '',
        fechaEmision: new Date().toISOString().split('T')[0],
        montoBruto: '',
        montoVisual: '',
        fechaVencimiento: '',
        cuentaBancariaId: null,
        tieneIva: true,
        montoNeto: 0,
        montoIva: 0,
        montoIvaVisual: '',
        motivoCorreccion: ''
    });

    const [cuentasDisponibles, setCuentasDisponibles] = useState([]);

    const fechaInvalida = formData.fechaVencimiento && (formData.fechaVencimiento < formData.fechaEmision);
    const brutoNum = parseInt(formData.montoBruto || 0);
    const ivaNum = parseInt(formData.montoIva || 0);
    const ivaInvalido = formData.tieneIva && (ivaNum >= brutoNum);

    useEffect(() => {
        setLoading(true);
        api.get('/proveedores')
            .then(res => {
                if (res.success) setListaProveedores(res.data);
            })
            .catch(err => console.error("Error cargando proveedores:", err))
            .finally(() => setLoading(false));

        const handleClickOutside = (event) => {
            if (searchRef.current && !searchRef.current.contains(event.target)) {
                setMostrarSugerencias(false);
            }
        };
        document.addEventListener("mousedown", handleClickOutside);
        return () => document.removeEventListener("mousedown", handleClickOutside);
    }, []);

    useEffect(() => {
        if (currentStep === 2 && formData.proveedorId) {
            api.get(`/cuentas-bancarias/proveedor/${formData.proveedorId}`)
                .then(data => {
                    if (data.success) setCuentasDisponibles(data.data);
                })
                .catch(err => console.error(err));
        }
    }, [currentStep, formData.proveedorId]);

    useEffect(() => {
        if (currentStep === 3) {
            const bruto = parseInt(formData.montoBruto || 0);
            let neto = bruto;
            let iva = 0;

            if (formData.tieneIva) {
                neto = Math.round(bruto / 1.19);
                iva = bruto - neto;
            }

            setFormData(prev => ({
                ...prev,
                montoNeto: neto,
                montoIva: iva,
                montoIvaVisual: formatCurrency(iva)
            }));
        }
    }, [currentStep, formData.tieneIva, formData.montoBruto]);

    const handleChange = (e) => setFormData(prev => ({ ...prev, [e.target.name]: e.target.value }));

    const handleMontoChange = (e) => {
        const val = e.target.value.replace(/\D/g, '');
        setFormData(prev => ({ ...prev, montoBruto: val, montoVisual: formatCurrency(val) }));
    };

    const handleIvaManualChange = (e) => {
        const rawIva = cleanNumber(e.target.value);
        const bruto = parseInt(formData.montoBruto || 0);
        const nuevoNeto = bruto - rawIva;
        setFormData(prev => ({ ...prev, montoIva: rawIva, montoIvaVisual: formatCurrency(rawIva), montoNeto: nuevoNeto }));
    };

    const handleBusquedaChange = (e) => {
        const termino = e.target.value;
        setBusqueda(termino);
        if (termino.length > 0) {
            const matches = listaProveedores.filter(p =>
                p.razon_social.toLowerCase().includes(termino.toLowerCase()) ||
                (p.rut && p.rut.toLowerCase().includes(termino.toLowerCase())) ||
                p.codigo_interno.toString().includes(termino)
            );
            setSugerencias(matches);
            setMostrarSugerencias(true);
        } else {
            setSugerencias([]);
            setMostrarSugerencias(false);
        }
    };

    const seleccionarProveedor = (p) => {
        setFormData(prev => ({
            ...prev,
            proveedorId: p.id,
            proveedorCodigo: p.codigo_interno,
            proveedorNombre: p.razon_social,
            rut: p.rut || 'N/A',
            pais: p.pais_iso === 'CL' ? 'Chile' : 'Extranjero',
            moneda: p.moneda_defecto || 'CLP',
            tieneIva: p.pais_iso === 'CL'
        }));
        setBusqueda('');
        setSugerencias([]);
        setMostrarSugerencias(false);
    };

    const limpiarProveedor = () => {
        setFormData(prev => ({ ...prev, proveedorId: null, proveedorNombre: '', rut: '' }));
        setBusqueda('');
    };

    const handleNextStep = async () => {
        if (currentStep === 1) {
            if (!formData.proveedorId || !formData.numeroFactura || !formData.montoBruto) {
                alert("Por favor complete los campos obligatorios.");
                return;
            }

            setCheckingDuplicate(true);
            try {
                const url = `/facturas/check?proveedor_id=${formData.proveedorId}&numero_factura=${formData.numeroFactura}`;
                const data = await api.get(url);
                if (data.exists) {
                    setDuplicateWarning(true);
                } else {
                    setCurrentStep(prev => prev + 1);
                }
            } catch (error) {
                console.error(error);
                alert("Error validando factura. Verifique conexión.");
            } finally {
                setCheckingDuplicate(false);
            }

        } else {
            setCurrentStep(prev => prev + 1);
        }
    };

    const handlePreSave = () => {
        const bruto = parseInt(formData.montoBruto || 0);
        const ivaTeorico = formData.tieneIva ? (bruto - Math.round(bruto / 1.19)) : 0;
        const ivaReal = parseInt(formData.montoIva || 0);

        if (formData.tieneIva && Math.abs(ivaReal - ivaTeorico) > 10) {
            setShowIvaModal(true);
        } else {
            finalSave('');
        }
    };

    const finalSave = (motivo) => {
        setShowIvaModal(false);
        setSaving(true);
        const payload = { ...formData, motivoCorreccion: motivo };

        api.post('/facturas', payload)
            .then(data => {
                if (data.success) {
                    setSuccessData({ id: data.id, codigo: data.codigo || 'N/A' });
                } else {
                    alert('❌ Error al guardar: ' + data.message);
                }
            })
            .catch(error => alert('Error crítico: ' + error.message))
            .finally(() => setSaving(false));
    };

    const prevStep = () => setCurrentStep(p => p - 1);
    const handleSuccessClose = () => { setSuccessData(null); window.location.reload(); };

    return (
        <div className="max-w-6xl mx-auto bg-white rounded-xl shadow-xl overflow-hidden mt-8 border border-slate-200 font-sans">

            <IvaWarningModal
                isOpen={showIvaModal}
                onClose={() => setShowIvaModal(false)}
                onConfirm={finalSave}
                calculado={formData.tieneIva ? (parseInt(formData.montoBruto) - Math.round(parseInt(formData.montoBruto) / 1.19)) : 0}
                ingresado={formData.montoIva}
            />

            <ModalGenerico
                isOpen={duplicateWarning}
                onClose={() => setDuplicateWarning(false)}
                type="warning"
                title="Factura Duplicada"
                message={<span>El documento <b>{formData.numeroFactura}</b> ya existe para este proveedor.<br />Agregue un punto (.) al final si es una corrección.</span>}
                confirmText="Entendido"
            />

            <ModalGenerico
                isOpen={!!successData}
                type="success"
                title="Registro Exitoso"
                confirmText="Nueva Factura"
                onConfirm={handleSuccessClose}
                onClose={handleSuccessClose}
                message={successData ? <div className="text-center mt-4"><div className="bg-slate-100 p-4 rounded-lg inline-block"><p className="text-xs text-slate-500 uppercase font-bold tracking-wider">ID Interno</p><p className="text-3xl font-mono font-bold text-slate-800">{successData.codigo}</p></div></div> : null}
            />

            <div className="bg-slate-900 px-8 py-6 flex flex-col md:flex-row justify-between items-center text-white">
                <div>
                    <h2 className="text-2xl font-bold tracking-tight">Registro de Factura</h2>
                    <p className="text-slate-400 text-sm mt-1">Gestión de Compras e Inventario</p>
                </div>
                <div className="flex items-center gap-3 mt-4 md:mt-0">
                    <div className={`flex flex-col items-center ${currentStep >= 1 ? 'text-blue-400' : 'text-slate-600'}`}>
                        <div className={`w-8 h-8 rounded-full flex items-center justify-center font-bold mb-1 transition-colors ${currentStep >= 1 ? 'bg-blue-600 text-white' : 'bg-slate-700'}`}>1</div>
                        <span className="text-[10px] uppercase font-bold tracking-wider">Datos</span>
                    </div>
                    <div className="w-10 h-0.5 bg-slate-700"></div>
                    <div className={`flex flex-col items-center ${currentStep >= 2 ? 'text-blue-400' : 'text-slate-600'}`}>
                        <div className={`w-8 h-8 rounded-full flex items-center justify-center font-bold mb-1 transition-colors ${currentStep >= 2 ? 'bg-blue-600 text-white' : 'bg-slate-700'}`}>2</div>
                        <span className="text-[10px] uppercase font-bold tracking-wider">Pagos</span>
                    </div>
                    <div className="w-10 h-0.5 bg-slate-700"></div>
                    <div className={`flex flex-col items-center ${currentStep >= 3 ? 'text-blue-400' : 'text-slate-600'}`}>
                        <div className={`w-8 h-8 rounded-full flex items-center justify-center font-bold mb-1 transition-colors ${currentStep >= 3 ? 'bg-blue-600 text-white' : 'bg-slate-700'}`}>3</div>
                        <span className="text-[10px] uppercase font-bold tracking-wider">Contable</span>
                    </div>
                </div>
            </div>

            <div className="p-4 md:p-8 min-h-[500px]">

                {currentStep === 1 && (
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 animate-fade-in-up">
                        <div className="flex flex-col gap-4">
                            <label className="text-sm font-bold text-slate-700 uppercase tracking-wide">Proveedor</label>

                            {!formData.proveedorId ? (
                                <div className="relative z-20" ref={searchRef}>
                                    <div className="relative group">
                                        <span className="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400 group-focus-within:text-blue-500 transition-colors">
                                            <i className="fas fa-search"></i>
                                        </span>
                                        <input
                                            type="text"
                                            placeholder="Buscar RUT, Razón Social..."
                                            value={busqueda}
                                            onChange={handleBusquedaChange}
                                            onFocus={() => { if (busqueda) setMostrarSugerencias(true); }}
                                            className="w-full pl-11 pr-4 py-4 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all shadow-sm text-lg"
                                            autoFocus
                                        />
                                    </div>

                                    {mostrarSugerencias && (
                                        <div className="absolute w-full bg-white border border-slate-200 mt-2 rounded-xl shadow-2xl max-h-80 overflow-y-auto z-50">
                                            {sugerencias.length > 0 ? sugerencias.map(p => (
                                                <div
                                                    key={p.id}
                                                    onClick={() => seleccionarProveedor(p)}
                                                    className="p-4 hover:bg-blue-50 cursor-pointer border-b last:border-0 border-slate-100 transition-colors group"
                                                >
                                                    <p className="font-bold text-slate-800 group-hover:text-blue-700">{p.razon_social}</p>
                                                    <div className="flex justify-between items-center mt-1">
                                                        <span className="text-sm text-slate-500 font-mono">{p.rut}</span>
                                                        <span className="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded group-hover:bg-blue-100 group-hover:text-blue-700 transition-colors">#{p.codigo_interno}</span>
                                                    </div>
                                                </div>
                                            )) : (
                                                <div className="p-6 text-center text-slate-400">
                                                    No se encontraron resultados para "{busqueda}"
                                                </div>
                                            )}
                                        </div>
                                    )}
                                    <p className="text-xs text-slate-400 mt-2 ml-1">
                                        * Escriba para buscar en la base de datos de proveedores.
                                    </p>
                                </div>
                            ) : (
                                <div className="bg-blue-50 border border-blue-200 rounded-xl p-6 relative group hover:shadow-md transition-all">
                                    <div className="absolute top-0 left-0 w-1.5 h-full bg-blue-500 rounded-l-xl"></div>
                                    <div className="flex justify-between items-start">
                                        <div>
                                            <span className="bg-blue-200 text-blue-800 text-[10px] font-bold px-2 py-1 rounded uppercase tracking-wide">
                                                Proveedor Seleccionado
                                            </span>
                                            <h3 className="font-bold text-slate-800 text-xl mt-2">{formData.proveedorNombre}</h3>
                                            <p className="text-slate-600 font-mono text-sm mt-1">{formData.rut}</p>
                                            <div className="flex gap-2 mt-4">
                                                <span className="px-3 py-1 bg-white border border-blue-100 rounded-md text-xs font-bold text-slate-600 shadow-sm">{formData.pais}</span>
                                                <span className="px-3 py-1 bg-emerald-100 text-emerald-800 border border-emerald-200 rounded-md text-xs font-bold shadow-sm">{formData.moneda}</span>
                                            </div>
                                        </div>
                                        <button
                                            onClick={limpiarProveedor}
                                            className="text-slate-400 hover:text-red-500 hover:bg-white p-2 rounded-full transition-all shadow-sm"
                                            title="Cambiar proveedor"
                                        >
                                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        </button>
                                    </div>
                                </div>
                            )}
                        </div>

                        <div className="flex flex-col gap-6">
                            <div className="grid grid-cols-2 gap-6">
                                <div>
                                    <label className="block text-sm font-bold text-slate-700 mb-2 uppercase tracking-wide">N° Factura</label>
                                    <input
                                        type="text"
                                        name="numeroFactura"
                                        value={formData.numeroFactura}
                                        onChange={handleChange}
                                        placeholder="Ej: 123456"
                                        className="w-full border border-slate-300 rounded-lg py-3 px-4 focus:ring-2 focus:ring-blue-500 outline-none transition-all font-semibold text-slate-700"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-bold text-slate-700 mb-2 uppercase tracking-wide">Fecha Emisión</label>
                                    <input
                                        type="date"
                                        name="fechaEmision"
                                        value={formData.fechaEmision}
                                        onChange={handleChange}
                                        className="w-full border border-slate-300 rounded-lg py-3 px-4 focus:ring-2 focus:ring-blue-500 outline-none transition-all text-slate-600 font-medium"
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="block text-sm font-bold text-slate-700 mb-2 uppercase tracking-wide">Monto Bruto Total</label>
                                <div className="relative rounded-lg shadow-sm">
                                    <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4">
                                        <span className="text-slate-400 font-bold text-xl">$</span>
                                    </div>
                                    <input
                                        type="text"
                                        name="monto_total"
                                        value={formData.montoVisual}
                                        onChange={handleMontoChange}
                                        placeholder="0"
                                        className="block w-full rounded-lg border border-slate-300 py-4 pl-10 pr-12 text-slate-800 placeholder:text-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-2xl font-bold outline-none tracking-wide"
                                    />
                                    <div className="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-4">
                                        <span className="text-slate-400 text-sm font-bold">CLP</span>
                                    </div>
                                </div>
                                <p className="text-xs text-slate-400 mt-2 text-right">Ingresa el valor total con IVA incluido.</p>
                            </div>
                        </div>
                    </div>
                )}

                {currentStep === 2 && (
                    <div className="max-w-3xl mx-auto animate-fade-in-up">
                        <div className="mb-8 p-6 bg-slate-50 rounded-xl border border-slate-100">
                            <label className="block font-bold text-slate-700 mb-2">Fecha Vencimiento Factura</label>
                            <input
                                type="date"
                                name="fechaVencimiento"
                                value={formData.fechaVencimiento}
                                onChange={handleChange}
                                className={`w-full border rounded-lg p-3 text-lg outline-none focus:ring-2 ${fechaInvalida ? 'border-red-500 bg-red-50 ring-red-200' : 'border-slate-300 focus:ring-blue-500'}`}
                            />
                            {fechaInvalida && <p className="text-red-600 text-sm font-bold mt-2"><i className="fas fa-exclamation-triangle mr-1"></i> La fecha de vencimiento no puede ser anterior a la emisión.</p>}
                        </div>

                        <h3 className="font-bold text-slate-800 text-lg mb-4 pl-1">Seleccionar Cuenta de Pago (Destino)</h3>
                        <div className="space-y-3">
                            {cuentasDisponibles.length > 0 ? cuentasDisponibles.map(cta => (
                                <div
                                    key={cta.id}
                                    onClick={() => setFormData(p => ({ ...p, cuentaBancariaId: cta.id }))}
                                    className={`p-5 border rounded-xl cursor-pointer flex justify-between items-center transition-all ${formData.cuentaBancariaId === cta.id ? 'bg-blue-50 border-blue-500 ring-1 ring-blue-500 shadow-md' : 'bg-white border-slate-200 hover:bg-slate-50 hover:border-slate-300'}`}
                                >
                                    <div className="flex items-center gap-4">
                                        <div className={`w-10 h-10 rounded-full flex items-center justify-center ${formData.cuentaBancariaId === cta.id ? 'bg-blue-200 text-blue-700' : 'bg-slate-100 text-slate-400'}`}>
                                            <i className="fas fa-university"></i>
                                        </div>
                                        <div>
                                            <p className="font-bold text-slate-800 text-lg">{cta.banco}</p>
                                            <p className="text-sm text-slate-500 font-mono tracking-wide">{cta.numero_cuenta}</p>
                                        </div>
                                    </div>
                                    <span className="text-xs bg-white border border-slate-200 px-3 py-1 rounded-full font-bold text-slate-500">{cta.tipo_cuenta || 'Vista/Corriente'}</span>
                                </div>
                            )) : (
                                <div className="text-slate-400 text-center italic border-2 border-dashed border-slate-200 p-8 rounded-xl bg-slate-50">
                                    <i className="fas fa-inbox text-3xl mb-2 opacity-50"></i>
                                    <p>Este proveedor no tiene cuentas registradas.</p>
                                </div>
                            )}
                        </div>
                    </div>
                )}

                {currentStep === 3 && (
                    <div className="animate-fade-in-up">
                        <div className="flex justify-center mb-6 md:mb-10">
                            <label className={`flex w-full md:w-auto justify-center items-center space-x-3 cursor-pointer px-6 py-3 rounded-full border transition-all select-none ${formData.tieneIva ? 'bg-blue-50 border-blue-200' : 'bg-slate-50 border-slate-200 opacity-75'}`}>
                                <input type="checkbox" checked={formData.tieneIva} onChange={(e) => setFormData(p => ({ ...p, tieneIva: e.target.checked }))} className="form-checkbox h-5 w-5 text-blue-600 rounded focus:ring-blue-500" />
                                <span className="text-sm font-bold text-slate-700">Documento Afecto a IVA (19%)</span>
                            </label>
                        </div>

                        <div className="max-w-4xl mx-auto overflow-hidden border border-slate-200 rounded-xl shadow-lg bg-white">
                            <div className="bg-slate-50 px-6 py-4 border-b border-slate-200 flex flex-col md:flex-row justify-between items-start md:items-center">
                                <h3 className="font-bold text-slate-700">Previsualización de Asiento</h3>
                                <span className="text-xs font-mono text-slate-400 mt-1 md:mt-0">AUTOMÁTICO</span>
                            </div>

                            <div className="flex flex-col">
                                <div className="hidden md:flex bg-white text-xs font-bold text-slate-500 uppercase tracking-wider py-3 px-6 border-b border-slate-100">
                                    <div className="w-1/2">Cuenta Contable</div>
                                    <div className="w-1/4 text-right text-emerald-600">Debe</div>
                                    <div className="w-1/4 text-right text-red-600">Haber</div>
                                </div>

                                <div className="flex flex-col md:flex-row border-b border-slate-100 p-5 md:py-4 md:px-6 gap-4 md:gap-0 items-start md:items-center hover:bg-slate-50 transition">
                                    <div className="w-full md:w-1/2">
                                        <span className="font-bold text-slate-800 block text-base">Proveedores por Pagar</span>
                                        <span className="text-xs text-slate-400">Pasivo Circulante</span>
                                    </div>
                                    <div className="w-full md:w-1/2 flex flex-col md:flex-row gap-2 md:gap-0">
                                        <div className="w-full md:w-1/2 flex justify-between md:block text-right md:pr-6">
                                            <span className="md:hidden text-xs font-bold text-gray-400 uppercase">Debe:</span>
                                            <span className="text-slate-300">-</span>
                                        </div>
                                        <div className="w-full md:w-1/2 flex justify-between md:block text-right">
                                            <span className="md:hidden text-xs font-bold text-gray-400 uppercase">Haber:</span>
                                            <span className="font-bold text-slate-900 text-lg bg-red-50/30 px-2 rounded">{formData.montoVisual}</span>
                                        </div>
                                    </div>
                                </div>

                                {formData.tieneIva && (
                                    <div className={`flex flex-col md:flex-row border-b border-slate-100 p-5 md:py-4 md:px-6 gap-4 md:gap-0 items-start md:items-center transition ${ivaInvalido ? 'bg-red-50' : 'hover:bg-blue-50/30'}`}>
                                        <div className="w-full md:w-1/2">
                                            <span className="font-bold text-blue-800 block text-base">IVA Crédito Fiscal</span>
                                            {ivaInvalido && <span className="text-xs font-bold text-red-600 bg-red-100 px-2 py-0.5 rounded mt-1 inline-block">⚠️ Monto Inválido</span>}
                                        </div>
                                        <div className="w-full md:w-1/2 flex flex-col md:flex-row gap-3 md:gap-0 items-center">
                                            <div className="w-full md:w-1/2 flex justify-between md:justify-end items-center md:pr-6">
                                                <span className="md:hidden text-xs font-bold text-blue-400 uppercase mr-4">Debe:</span>
                                                <div className="relative w-full md:w-full">
                                                    <span className="absolute left-3 top-2 text-blue-400 font-bold text-sm">$</span>
                                                    <input
                                                        type="text"
                                                        value={formData.montoIvaVisual}
                                                        onChange={handleIvaManualChange}
                                                        className={`w-full text-right font-bold text-blue-700 bg-white border rounded-md py-1.5 pl-6 pr-2 outline-none focus:ring-2 shadow-sm ${ivaInvalido ? 'border-red-500 ring-red-200' : 'border-blue-200 focus:ring-blue-500'}`}
                                                    />
                                                </div>
                                            </div>
                                            <div className="w-full md:w-1/2 flex justify-between md:block text-right">
                                                <span className="md:hidden text-xs font-bold text-gray-400 uppercase">Haber:</span>
                                                <span className="text-slate-300">-</span>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                <div className="flex flex-col md:flex-row p-5 md:py-4 md:px-6 gap-4 md:gap-0 items-start md:items-center hover:bg-slate-50 transition">
                                    <div className="w-full md:w-1/2">
                                        <span className="font-bold text-slate-800 block text-base">Gasto / Activo (Neto)</span>
                                        <span className="text-xs text-slate-400">Resultado / Inventario</span>
                                    </div>
                                    <div className="w-full md:w-1/2 flex flex-col md:flex-row gap-2 md:gap-0">
                                        <div className="w-full md:w-1/2 flex justify-between md:block text-right md:pr-6">
                                            <span className="md:hidden text-xs font-bold text-gray-400 uppercase">Debe:</span>
                                            <span className="font-bold text-slate-900 text-lg bg-emerald-50/30 px-2 rounded">{formatCurrency(formData.montoNeto)}</span>
                                        </div>
                                        <div className="w-full md:w-1/2 flex justify-between md:block text-right">
                                            <span className="md:hidden text-xs font-bold text-gray-400 uppercase">Haber:</span>
                                            <span className="text-slate-300">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            <div className="bg-slate-50 p-4 md:px-8 md:py-6 flex flex-col md:flex-row justify-between border-t border-slate-200 gap-3">
                <button
                    onClick={prevStep}
                    disabled={currentStep === 1}
                    className="w-full md:w-auto px-6 py-3 border border-slate-300 rounded-xl bg-white text-slate-700 font-semibold hover:bg-slate-100 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
                >
                    Atrás
                </button>

                {currentStep < 3 ? (
                    <button
                        onClick={handleNextStep}
                        disabled={
                            !formData.montoBruto ||
                            !formData.proveedorId ||
                            (currentStep === 2 && (!formData.fechaVencimiento || fechaInvalida)) ||
                            checkingDuplicate
                        }
                        className="w-full md:w-auto px-10 py-3 bg-slate-900 text-white rounded-xl font-bold shadow-lg hover:bg-slate-800 hover:shadow-xl disabled:opacity-50 disabled:cursor-not-allowed transition-all flex items-center justify-center gap-3 min-w-[160px]"
                    >
                        {checkingDuplicate ? (
                            <><i className="fas fa-circle-notch fa-spin"></i> Verificando</>
                        ) : (
                            <>Siguiente <i className="fas fa-arrow-right"></i></>
                        )}
                    </button>
                ) : (
                    <button
                        onClick={handlePreSave}
                        disabled={saving || ivaInvalido}
                        className="w-full md:w-auto px-10 py-3 bg-blue-600 text-white rounded-xl font-bold shadow-lg shadow-blue-500/30 hover:bg-blue-700 hover:shadow-blue-600/40 disabled:opacity-50 disabled:cursor-not-allowed transition-all flex items-center justify-center gap-3 min-w-[160px]"
                    >
                        {saving ? (
                            <><i className="fas fa-circle-notch fa-spin"></i> Guardando...</>
                        ) : (
                            <>Confirmar y Guardar <i className="fas fa-check"></i></>
                        )}
                    </button>
                )}
            </div>
        </div>
    );
};

export default RegistroFactura;