import React, { useState, useEffect } from 'react';
import AyudaModulo from '../../../Componentes/AyudaModulo';
import BotonAccion from '../../../Componentes/BotonAccion';
import ModalGenerico from '../../../Componentes/ModalGenerico';
import BuscadorCuentaContable from './BuscadorCuentaContable';
import IvaWarningModal from './IvaWarningModal';
import RegistroFacturaPaso1 from './RegistroFacturaPaso1';
import RegistroFacturaPaso2 from './RegistroFacturaPaso2';
import RegistroFacturaPaso3 from './RegistroFacturaPaso3';
import { useRegistroFactura } from '../Hooks/useRegistroFactura';
import { api } from '../../../Configuracion/api';
import { logger } from '../../../Configuracion/logger';
const formatCurrency = (value) => {
    if (!value && value !== 0) return '';
    return new Intl.NumberFormat('es-CL').format(value.toString().replace(/\D/g, ''));
};

const cleanNumber = (value) => {
    if (!value) return 0;
    return parseInt(value.toString().replace(/\D/g, '')) || 0;
};

const RegistroFactura = () => {
    const [currentStep, setCurrentStep] = useState(1);
    const [saving, setSaving] = useState(false);
    const [checkingDuplicate, setCheckingDuplicate] = useState(false);

    const [showIvaModal, setShowIvaModal] = useState(false);
    const [duplicateWarning, setDuplicateWarning] = useState(false);
    const [successData, setSuccessData] = useState(null);

    const [formData, setFormData] = useState({
        proveedorCodigo: '',
        proveedorId: null,
        proveedorNombre: '',
        rut: '',
        pais: '',
        moneda: 'CLP',
        tipoDocumento: 'FACTURA',
        numeroFactura: '',
        fechaEmision: new Date().toISOString().split('T')[0],
        fechaContable: new Date().toISOString().split('T')[0],
        montoBruto: '',
        montoVisual: '',
        fechaVencimiento: '',
        cuentaBancariaId: null,
        tieneIva: true,
        montoNeto: 0,
        montoIva: 0,
        montoIvaVisual: '',
        motivoCorreccion: '',
        cuentaDestino: '',
        cuentaIva: '353350',
        cuentaProveedor: '352105'
    });

    const {
        listaProveedores,
        cuentasDisponibles,
        loading,
        busqueda, setBusqueda,
        sugerencias,
        mostrarSugerencias, setMostrarSugerencias,
        searchRef,
        handleBusquedaChange,
    } = useRegistroFactura({
        currentStep,
        proveedorId: formData.proveedorId,
    });

    const fechaInvalida = formData.fechaVencimiento && (formData.fechaVencimiento < formData.fechaEmision);
    const brutoNum = parseInt(formData.montoBruto || 0);
    const ivaNum = parseInt(formData.montoIva || 0);
    const ivaInvalido = formData.tieneIva && (ivaNum >= brutoNum);

    // Recalculo de IVA cuando se llega al paso 3 o cambian valores relevantes
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
            if (!formData.proveedorId || !formData.numeroFactura || !formData.montoBruto || !formData.fechaContable) {
                alert("Por favor complete todos los campos obligatorios.");
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
                logger.error(error);
                alert("Error validando factura. Verifique conexión.");
            } finally {
                setCheckingDuplicate(false);
            }

        } else {
            setCurrentStep(prev => prev + 1);
        }
    };

    const handlePreSave = () => {
        if (!formData.cuentaDestino) return alert("Debe seleccionar una cuenta de Destino (Gasto/Activo)");
        if (!formData.cuentaProveedor) return alert("Debe seleccionar una cuenta de Proveedor (Pasivo)");
        if (formData.tieneIva && !formData.cuentaIva) return alert("Debe seleccionar una cuenta de IVA");

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
        const payload = {
            ...formData,
            motivoCorreccion: motivo,
            tipo_documento: formData.tipoDocumento,
            numero_factura: formData.numeroFactura
        };

        api.post('/facturas', payload)
            .then(data => {
                if (data.success) {
                    const facturaGuardada = data.data || data;

                    setSuccessData({
                        id: facturaGuardada.id,
                        codigo: facturaGuardada.comprobante_contable || facturaGuardada.codigo_interno || 'N/A'
                    });
                } else {
                    logger.error("Errores:", data.errors);
                    const errorMsgs = data.errors ? Object.values(data.errors).flat().join('\n') : data.message;
                    alert('❌ Error de Validación:\n' + errorMsgs);
                }
            })
            .catch(error => {
                const msj = error.response?.data?.message || error.message;
                const errs = error.response?.data?.errors ? Object.values(error.response.data.errors).flat().join('\n') : '';
                alert('Error crítico:\n' + msj + '\n' + errs);
            })
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
                message={successData ? (
                    <div className="text-center mt-4">
                        <div className="bg-slate-100 p-4 rounded-lg inline-block">
                            <p className="text-xs text-slate-500 uppercase font-bold tracking-wider">Se ha generado el siguiente comprobante contable:</p>
                            <p className="text-3xl font-mono font-bold text-slate-800">{successData.codigo}</p>
                        </div>
                    </div>
                ) : null}
            />

            <div className="bg-slate-900 rounded-t-xl px-8 py-6 flex flex-col md:flex-row justify-between items-center text-white">
                <div>
                    <div className="flex items-center gap-3">
                        <h2 className="text-2xl font-bold tracking-tight">Registro de Factura</h2>
                        <AyudaModulo moduloId="registroFactura" />
                    </div>
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
                    <RegistroFacturaPaso1
                        formData={formData}
                        busqueda={busqueda}
                        sugerencias={sugerencias}
                        mostrarSugerencias={mostrarSugerencias}
                        searchRef={searchRef}
                        onBusquedaChange={handleBusquedaChange}
                        onMostrarSugerencias={setMostrarSugerencias}
                        onSeleccionarProveedor={seleccionarProveedor}
                        onLimpiarProveedor={limpiarProveedor}
                        onChange={handleChange}
                        onMontoChange={handleMontoChange}
                    />
                )}

                {currentStep === 2 && (
                    <RegistroFacturaPaso2
                        formData={formData}
                        fechaInvalida={fechaInvalida}
                        cuentasDisponibles={cuentasDisponibles}
                        onChange={handleChange}
                        onSeleccionarCuenta={(id) => setFormData(p => ({ ...p, cuentaBancariaId: id }))}
                    />
                )}

                {currentStep === 3 && (
                    <RegistroFacturaPaso3
                        formData={formData}
                        ivaInvalido={ivaInvalido}
                        onTieneIvaChange={(checked) => setFormData(p => ({ ...p, tieneIva: checked }))}
                        onCuentaDestinoChange={(codigo) => setFormData(prev => ({ ...prev, cuentaDestino: codigo }))}
                        onCuentaIvaChange={(codigo) => setFormData(prev => ({ ...prev, cuentaIva: codigo }))}
                        onCuentaProveedorChange={(codigo) => setFormData(prev => ({ ...prev, cuentaProveedor: codigo }))}
                        onIvaManualChange={handleIvaManualChange}
                    />
                )}
            </div>

            <div className="bg-slate-50 rounded-b-xl relative z-10 p-4 md:px-8 md:py-6 flex flex-col md:flex-row justify-between border-t border-slate-200 gap-3">
                <button
                    onClick={prevStep}
                    disabled={currentStep === 1}
                    className="w-full md:w-auto px-6 py-3 border border-slate-300 rounded-xl bg-white text-slate-700 font-semibold hover:bg-slate-100 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
                >
                    Atrás
                </button>

                {currentStep < 3 ? (
                    <BotonAccion
                        onClick={handleNextStep}
                        cargando={checkingDuplicate}
                        disabled={
                            !formData.montoBruto ||
                            !formData.proveedorId ||
                            !formData.fechaContable ||
                            (currentStep === 2 && (!formData.fechaVencimiento || fechaInvalida))
                        }
                        color="slate"
                        tamano="lg"
                        textoCargando="Verificando"
                        icono={<i className="fas fa-arrow-right"></i>}
                        className="w-full md:w-auto min-w-[160px]"
                    >
                        Siguiente
                    </BotonAccion>
                ) : (
                    <BotonAccion
                        onClick={handlePreSave}
                        cargando={saving}
                        disabled={ivaInvalido}
                        color="blue"
                        tamano="lg"
                        textoCargando="Guardando..."
                        icono={<i className="fas fa-check"></i>}
                        className="w-full md:w-auto min-w-[160px]"
                    >
                        Confirmar y Guardar
                    </BotonAccion>
                )}
            </div>
        </div>
    );
};

export default RegistroFactura;