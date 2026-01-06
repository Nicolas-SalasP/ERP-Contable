import React, { useState, useEffect } from 'react';
import GenericModal from '../../../Components/GenericModal';

// --- HELPERS ---
const formatCurrency = (value) => {
    if (!value && value !== 0) return '';
    return new Intl.NumberFormat('es-CL').format(value.toString().replace(/\D/g, ''));
};

const cleanNumber = (value) => {
    if (!value) return 0;
    return parseInt(value.toString().replace(/\D/g, '')) || 0;
};

// --- MODAL DE AUDITORÍA (Local - Para Paso 3) ---
const IvaWarningModal = ({ isOpen, onClose, onConfirm, calculado, ingresado }) => {
    const [motivo, setMotivo] = useState('');
    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 p-4 animate-fade-in">
            <div className="bg-white rounded-lg shadow-2xl p-6 max-w-md w-full border-t-4 border-yellow-500">
                <h3 className="text-lg font-bold text-gray-900 mb-2">⚠️ Diferencia de Impuesto Detectada</h3>
                <p className="text-sm text-gray-600 mb-4">
                    El IVA ingresado difiere del cálculo teórico (19%).
                </p>
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
                <textarea
                    className="w-full border rounded p-2 text-sm focus:ring-yellow-500 focus:border-yellow-500 outline-none"
                    rows="2"
                    placeholder="Ej: Impuesto específico carne, redondeo..."
                    value={motivo}
                    onChange={(e) => setMotivo(e.target.value)}
                ></textarea>
                <div className="flex justify-end space-x-3 mt-6">
                    <button onClick={onClose} className="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded text-sm">Cancelar</button>
                    <button onClick={() => onConfirm(motivo)} className="px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded text-sm font-bold shadow">
                        Confirmar
                    </button>
                </div>
            </div>
        </div>
    );
};

// --- COMPONENTE PRINCIPAL ---
const InvoiceWizard = () => {
    const [currentStep, setCurrentStep] = useState(1);
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [checkingDuplicate, setCheckingDuplicate] = useState(false); // Nuevo estado de carga
    
    // Modales
    const [showIvaModal, setShowIvaModal] = useState(false);
    const [successData, setSuccessData] = useState(null);
    const [duplicateWarning, setDuplicateWarning] = useState(false); // Nuevo Modal

    const [formData, setFormData] = useState({
        autorizadoPor: '',
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

    // Validaciones
    const fechaInvalida = formData.fechaVencimiento && (formData.fechaVencimiento < formData.fechaEmision);
    const brutoNum = parseInt(formData.montoBruto || 0);
    const ivaNum = parseInt(formData.montoIva || 0);
    const ivaInvalido = formData.tieneIva && (ivaNum >= brutoNum);

    // 1. Fetch Proveedor
    useEffect(() => {
        const codigo = formData.proveedorCodigo;
        if (codigo.length >= 6) {
            setLoading(true);
            fetch(`http://localhost/ERP-Contable/Backend/Public/api/proveedores/${codigo}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const p = data.data;
                        setFormData(prev => ({
                            ...prev,
                            proveedorId: p.id,
                            proveedorNombre: p.razonSocial,
                            rut: p.rut || 'Extranjero',
                            pais: p.pais,
                            moneda: p.moneda,
                            tieneIva: p.pais === 'Chile'
                        }));
                    }
                })
                .finally(() => setLoading(false));
        }
    }, [formData.proveedorCodigo]);

    // 2. Fetch Cuentas
    useEffect(() => {
        if (currentStep === 2 && formData.proveedorId) {
            fetch(`http://localhost/ERP-Contable/Backend/Public/api/cuentas-bancarias/proveedor/${formData.proveedorId}`)
                .then(res => res.json())
                .then(data => data.success && setCuentasDisponibles(data.data));
        }
    }, [currentStep, formData.proveedorId]);

    // 3. Calculo Inicial IVA
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
    }, [currentStep, formData.tieneIva]);


    // Handlers
    const handleChange = (e) => setFormData(prev => ({ ...prev, [e.target.name]: e.target.value }));

    const handleMontoChange = (e) => {
        const val = e.target.value.replace(/\D/g, '');
        setFormData(prev => ({ ...prev, montoBruto: val, montoVisual: formatCurrency(val) }));
    };

    const handleIvaManualChange = (e) => {
        const rawIva = cleanNumber(e.target.value);
        const bruto = parseInt(formData.montoBruto || 0);
        const nuevoNeto = bruto - rawIva;

        setFormData(prev => ({
            ...prev,
            montoIva: rawIva,
            montoIvaVisual: formatCurrency(rawIva),
            montoNeto: nuevoNeto
        }));
    };

    // --- NUEVA LÓGICA: Validar duplicado antes de avanzar ---
    const handleNextStep = async () => {
        // Si estamos en el paso 1, verificamos duplicado
        if (currentStep === 1) {
            if (!formData.proveedorId || !formData.numeroFactura) {
                alert("Complete los datos obligatorios");
                return;
            }

            setCheckingDuplicate(true);
            try {
                // Llamamos al nuevo endpoint ligero
                const res = await fetch(`http://localhost/ERP-Contable/Backend/Public/api/facturas/check?proveedor_id=${formData.proveedorId}&numero_factura=${formData.numeroFactura}`);
                const data = await res.json();

                if (data.exists) {
                    // Si existe, mostramos el modal de advertencia y NO avanzamos
                    setDuplicateWarning(true);
                } else {
                    // Si no existe, avanzamos
                    setCurrentStep(prev => prev + 1);
                }
            } catch (error) {
                alert("Error al validar factura. Revise su conexión.");
            } finally {
                setCheckingDuplicate(false);
            }
        } else {
            // Si no es paso 1, avanzamos normal
            setCurrentStep(prev => prev + 1);
        }
    };

    const handlePreSave = () => {
        const bruto = parseInt(formData.montoBruto || 0);
        const ivaTeorico = formData.tieneIva ? (bruto - Math.round(bruto / 1.19)) : 0;
        const ivaReal = parseInt(formData.montoIva || 0);

        if (formData.tieneIva && Math.abs(ivaReal - ivaTeorico) > 5) {
            setShowIvaModal(true);
        } else {
            finalSave('');
        }
    };

    const finalSave = (motivo) => {
        setShowIvaModal(false);
        setSaving(true);

        const payload = { ...formData, motivoCorreccion: motivo };

        fetch('http://localhost/ERP-Contable/Backend/Public/api/facturas', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    setSuccessData({
                        id: data.id,
                        codigo: data.codigo_sistema
                    });
                } else {
                    // Fallback por si acaso falla la validación del paso 1
                    alert('❌ Error: ' + data.message);
                }
            })
            .catch(() => alert('Error de Conexión'))
            .finally(() => setSaving(false));
    };

    const prevStep = () => setCurrentStep(p => p - 1);

    const handleSuccessClose = () => {
        setSuccessData(null);
        window.location.reload();
    };

    return (
        <div className="max-w-5xl mx-auto bg-white rounded-lg shadow-xl overflow-hidden mt-6 border border-gray-200 font-sans">

            {/* Modal de Advertencia IVA (Local) */}
            <IvaWarningModal
                isOpen={showIvaModal}
                onClose={() => setShowIvaModal(false)}
                onConfirm={finalSave}
                calculado={formData.tieneIva ? (parseInt(formData.montoBruto) - Math.round(parseInt(formData.montoBruto) / 1.19)) : 0}
                ingresado={formData.montoIva}
            />

            {/* NUEVO: Modal de Factura Duplicada (Usando GenericModal) */}
            <GenericModal 
                isOpen={duplicateWarning}
                onClose={() => setDuplicateWarning(false)}
                type="warning"
                title="⚠️ Factura Duplicada"
                message={
                    <span>
                        La factura <b>{formData.numeroFactura}</b> ya está registrada para este proveedor. 
                        <br/><br/>
                        Si está intentando registrar una corrección, agregue un punto al final del número (Ej: <b>{formData.numeroFactura}.</b>).
                    </span>
                }
                confirmText="Entendido"
            />

            {/* Modal de Éxito */}
            <GenericModal
                isOpen={!!successData}
                type="success"
                title="¡Factura Contabilizada Exitosamente!"
                confirmText="Entendido, Salir"
                onConfirm={handleSuccessClose}
                onClose={handleSuccessClose}
                message={
                    successData ? (
                        <div className="text-center space-y-4 mt-4">
                            <p>El documento ha sido registrado en los libros contables.</p>
                            <div className="bg-gray-100 p-4 rounded-lg border border-gray-200">
                                <p className="text-xs text-gray-500 uppercase tracking-wide">Código de Sistema (Smart ID)</p>
                                <p className="text-3xl font-mono font-bold text-slate-800 tracking-wider mt-1">{successData.codigo}</p>
                            </div>
                            <p className="text-sm text-gray-400">ID Interno BD: #{successData.id}</p>
                        </div>
                    ) : null
                }
            />

            {/* Header */}
            <div className="bg-slate-900 p-6 flex flex-col md:flex-row justify-between items-center text-white gap-4">
                <div>
                    <h2 className="text-xl font-bold tracking-wide">Registro de Factura</h2>
                    <p className="text-slate-400 text-xs mt-1">Paso {currentStep} de 3</p>
                </div>
                <div className="flex space-x-2">
                    {[1, 2, 3].map(s => <div key={s} className={`w-3 h-3 rounded-full transition-colors duration-300 ${currentStep >= s ? 'bg-emerald-400' : 'bg-slate-700'}`} />)}
                </div>
            </div>

            <div className="p-6 md:p-8 min-h-[450px]">

                {/* PASO 1 */}
                {currentStep === 1 && (
                    <div className="grid grid-cols-1 md:grid-cols-12 gap-6 animate-fade-in-up">
                        <div className="md:col-span-12">
                            <label className="text-xs font-bold text-gray-500 uppercase mb-1 block">Autorizado Por</label>
                            <input
                                type="text"
                                name="autorizadoPor"
                                value={formData.autorizadoPor}
                                onChange={handleChange}
                                className="w-full border border-gray-300 rounded p-2 focus:ring-2 focus:ring-emerald-500 outline-none"
                            />
                        </div>

                        <div className="md:col-span-4 space-y-4">
                            <label className="text-xs font-bold text-gray-500 uppercase block">Código Proveedor</label>
                            <input
                                type="text"
                                name="proveedorCodigo"
                                value={formData.proveedorCodigo}
                                onChange={(e) => { if (e.target.value.length <= 6) handleChange(e) }}
                                className="text-3xl font-mono font-bold w-full border-b-2 border-gray-200 focus:border-emerald-500 outline-none py-2"
                                placeholder="710000"
                                autoFocus
                            />

                            <div className={`p-4 rounded-lg border text-sm transition-all ${formData.proveedorId ? 'bg-blue-50 border-blue-200 shadow-sm' : 'bg-gray-50 border-gray-200 border-dashed'}`}>
                                {loading ? <p className="text-blue-500 font-bold">Buscando...</p> : (
                                    formData.proveedorId ? (
                                        <div>
                                            <p className="font-bold text-blue-900 text-lg">{formData.proveedorNombre}</p>
                                            <p className="text-gray-600 font-mono text-xs">RUT: {formData.rut}</p>
                                            <div className="flex gap-2 mt-3">
                                                <span className="px-2 py-1 bg-white border border-blue-100 rounded text-xs font-bold text-blue-700">{formData.pais}</span>
                                                <span className="px-2 py-1 bg-emerald-100 text-emerald-800 rounded text-xs font-bold">{formData.moneda}</span>
                                            </div>
                                        </div>
                                    ) : <p className="text-gray-400 italic">Esperando código...</p>
                                )}
                            </div>
                        </div>

                        <div className="md:col-span-8 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">N° Factura</label>
                                <input type="text" name="numeroFactura" value={formData.numeroFactura} onChange={handleChange} className="w-full border rounded p-2 focus:ring-2 focus:ring-blue-500 outline-none" />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Fecha Emisión</label>
                                <input type="date" name="fechaEmision" value={formData.fechaEmision} onChange={handleChange} className="w-full border rounded p-2 focus:ring-2 focus:ring-blue-500 outline-none" />
                            </div>

                            <div className="md:col-span-2">
                                <label className="block text-sm font-medium text-gray-700 mb-1">Monto Bruto Total</label>
                                <div className="relative w-full">
                                    <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 font-bold text-lg pointer-events-none select-none">
                                        $
                                    </span>
                                    <input
                                        type="text"
                                        value={formData.montoVisual}
                                        onChange={handleMontoChange}
                                        className="w-full border border-gray-300 rounded p-2 pl-8 text-3xl font-bold text-gray-800 tracking-wide focus:ring-2 focus:ring-emerald-500 outline-none"
                                        placeholder="0"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* PASO 2 */}
                {currentStep === 2 && (
                    <div className="max-w-2xl mx-auto animate-fade-in-up">
                        <div className="mb-6">
                            <label className="block font-bold text-gray-700 mb-2">Fecha Vencimiento</label>
                            <input
                                type="date"
                                name="fechaVencimiento"
                                value={formData.fechaVencimiento}
                                onChange={handleChange}
                                className={`w-full border rounded p-3 text-lg outline-none focus:ring-2 ${fechaInvalida ? 'border-red-500 bg-red-50 ring-red-200' : 'border-gray-300 focus:ring-emerald-500'}`}
                            />
                            {fechaInvalida && <p className="text-red-600 text-sm font-bold mt-2">⚠️ Fecha inválida.</p>}
                        </div>

                        <label className="block font-bold text-gray-700 mb-3">Seleccionar Cuenta de Destino</label>
                        <div className="space-y-3">
                            {cuentasDisponibles.length > 0 ? cuentasDisponibles.map(cta => (
                                <div key={cta.id} onClick={() => setFormData(p => ({ ...p, cuentaBancariaId: cta.id }))}
                                    className={`p-4 border rounded-lg cursor-pointer flex justify-between items-center ${formData.cuentaBancariaId === cta.id ? 'bg-emerald-50 border-emerald-500 ring-1 ring-emerald-500' : 'bg-white border-gray-200'}`}>
                                    <div><p className="font-bold text-gray-800">{cta.banco}</p><p className="text-xs text-gray-500">{cta.numero_cuenta}</p></div>
                                    <span className="text-xs bg-gray-100 px-2 py-1 rounded font-bold text-gray-600">{cta.pais_iso}</span>
                                </div>
                            )) : <p className="text-gray-400 text-center italic border-2 border-dashed p-4 rounded">Sin cuentas.</p>}
                        </div>
                    </div>
                )}

                {/* PASO 3 */}
                {currentStep === 3 && (
                    <div className="animate-fade-in-up">
                        <div className="flex justify-center mb-8">
                            <label className="inline-flex items-center space-x-3 cursor-pointer bg-slate-50 border border-slate-200 px-6 py-3 rounded-full hover:bg-slate-100">
                                <input type="checkbox" checked={formData.tieneIva} onChange={(e) => setFormData(p => ({ ...p, tieneIva: e.target.checked }))} className="form-checkbox h-5 w-5 text-emerald-600 rounded" />
                                <span className="text-sm font-bold text-slate-700">Documento Afecto a IVA</span>
                            </label>
                        </div>

                        <div className="max-w-4xl mx-auto overflow-hidden border border-gray-200 rounded-xl shadow-sm">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-slate-50 text-xs font-bold text-gray-500 uppercase">
                                    <tr>
                                        <th className="px-6 py-4 text-left">Cuenta</th>
                                        <th className="px-6 py-4 text-right text-emerald-700">Debe</th>
                                        <th className="px-6 py-4 text-right text-red-700">Haber</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200 text-sm">
                                    <tr className="hover:bg-gray-50">
                                        <td className="px-6 py-4"><span className="font-bold text-gray-800 block">Facturas por Pagar</span></td>
                                        <td className="px-6 py-4 text-right text-gray-300">-</td>
                                        <td className="px-6 py-4 text-right font-bold text-gray-900">{formData.montoVisual}</td>
                                    </tr>

                                    {formData.tieneIva && (
                                        <tr className={`${ivaInvalido ? 'bg-red-50' : 'bg-blue-50/50 hover:bg-blue-50'}`}>
                                            <td className="px-6 py-4">
                                                <span className="font-bold text-blue-800 block">IVA Crédito Fiscal</span>
                                                {ivaInvalido && <span className="text-xs font-bold text-red-600">⚠️ Error</span>}
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                <div className="relative inline-block w-36">
                                                    <span className="absolute left-2 top-2 text-blue-400 font-bold">$</span>
                                                    <input
                                                        type="text"
                                                        value={formData.montoIvaVisual}
                                                        onChange={handleIvaManualChange}
                                                        className={`w-full text-right font-bold text-blue-700 border rounded p-1 pl-5 outline-none focus:ring-2 ${ivaInvalido ? 'border-red-500 ring-red-200' : 'border-blue-200 focus:ring-blue-500'}`}
                                                    />
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 text-right text-gray-300">-</td>
                                        </tr>
                                    )}

                                    <tr className="hover:bg-gray-50">
                                        <td className="px-6 py-4"><span className="font-bold text-gray-800 block">Gasto / Activo</span></td>
                                        <td className="px-6 py-4 text-right font-bold text-gray-900">{formatCurrency(formData.montoNeto)}</td>
                                        <td className="px-6 py-4 text-right text-gray-300">-</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

            </div>

            <div className="bg-gray-50 px-8 py-5 flex justify-between border-t border-gray-200 gap-4">
                <button onClick={prevStep} disabled={currentStep === 1} className="px-5 py-2.5 border border-gray-300 rounded-lg bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-50">Atrás</button>
                {currentStep < 3 ? (
                    // CAMBIO AQUÍ: Llamamos a handleNextStep en lugar de nextStep directamente
                    <button 
                        onClick={handleNextStep} 
                        disabled={!formData.montoBruto || !formData.proveedorId || (currentStep === 2 && (!formData.fechaVencimiento || fechaInvalida)) || checkingDuplicate} 
                        className="px-8 py-2.5 bg-slate-900 text-white rounded-lg font-bold shadow-lg hover:bg-slate-800 disabled:opacity-50"
                    >
                        {checkingDuplicate ? 'Verificando...' : 'Siguiente'}
                    </button>
                ) : (
                    <button onClick={handlePreSave} disabled={saving || ivaInvalido} className="px-8 py-2.5 bg-emerald-600 text-white rounded-lg font-bold shadow-lg hover:bg-emerald-700 disabled:opacity-50">
                        {saving ? 'Procesando...' : 'Confirmar'}
                    </button>
                )}
            </div>
        </div>
    );
};

export default InvoiceWizard;