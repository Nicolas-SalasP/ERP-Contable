import React, { useEffect, useState } from 'react';
import Swal from 'sweetalert2';
import { validarIdentificador, formatearIdentificador } from '../../../Utilidades/identificadores';

const VALOR_INICIAL = {
    giro_emisor: '',
    codigo_actividad_sii: '',
    comuna: '',
    ciudad: '',
    resolucion_sii_numero: '',
    resolucion_sii_fecha: '',
    ambiente_sii: 'certificacion',
    email_intercambio_sii: '',
    rut_representante_legal: '',
};

const desdeBackend = (configuracion) => {
    if (!configuracion) return { ...VALOR_INICIAL };
    return {
        giro_emisor: configuracion.giro_emisor ?? '',
        codigo_actividad_sii: configuracion.codigo_actividad_sii ?? '',
        comuna: configuracion.comuna ?? '',
        ciudad: configuracion.ciudad ?? '',
        resolucion_sii_numero: configuracion.resolucion_sii_numero ?? '',
        resolucion_sii_fecha: (configuracion.resolucion_sii_fecha ?? '').slice(0, 10),
        ambiente_sii: configuracion.ambiente_sii ?? 'certificacion',
        email_intercambio_sii: configuracion.email_intercambio_sii ?? '',
        rut_representante_legal: configuracion.rut_representante_legal ?? '',
    };
};

const haciaBackend = (formData) => ({
    giro_emisor: formData.giro_emisor || null,
    codigo_actividad_sii: formData.codigo_actividad_sii === '' ? null : Number(formData.codigo_actividad_sii),
    comuna: formData.comuna || null,
    ciudad: formData.ciudad || null,
    resolucion_sii_numero: formData.resolucion_sii_numero === '' ? null : Number(formData.resolucion_sii_numero),
    resolucion_sii_fecha: formData.resolucion_sii_fecha || null,
    ambiente_sii: formData.ambiente_sii,
    email_intercambio_sii: formData.email_intercambio_sii || null,
    rut_representante_legal: formData.rut_representante_legal || null,
});

const FormularioConfiguracionSii = ({ configuracion, onSubmit, guardando }) => {
    const [formData, setFormData] = useState(() => desdeBackend(configuracion));
    const [errores, setErrores] = useState({});

    useEffect(() => {
        setFormData(desdeBackend(configuracion));
    }, [configuracion]);

    const handleChange = (e) => {
        const { name, value } = e.target;
        if (errores[name]) setErrores((prev) => ({ ...prev, [name]: null }));

        if (name === 'rut_representante_legal') {
            setFormData((prev) => ({ ...prev, [name]: formatearIdentificador(value, 'CL') }));
            return;
        }

        setFormData((prev) => ({ ...prev, [name]: value }));
    };

    const validar = () => {
        const nuevosErrores = {};

        if (!formData.ambiente_sii) {
            nuevosErrores.ambiente_sii = 'Debe seleccionar un ambiente.';
        } else if (!['certificacion', 'produccion'].includes(formData.ambiente_sii)) {
            nuevosErrores.ambiente_sii = 'Ambiente invalido.';
        }

        if (formData.email_intercambio_sii) {
            const reEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!reEmail.test(formData.email_intercambio_sii)) {
                nuevosErrores.email_intercambio_sii = 'Formato de email invalido.';
            }
        }

        if (formData.rut_representante_legal) {
            if (!validarIdentificador(formData.rut_representante_legal, 'CL')) {
                nuevosErrores.rut_representante_legal = 'RUT chileno invalido (DV incorrecto).';
            }
        }

        if (formData.codigo_actividad_sii !== '' && Number(formData.codigo_actividad_sii) < 1) {
            nuevosErrores.codigo_actividad_sii = 'El codigo de actividad debe ser positivo.';
        }

        setErrores(nuevosErrores);
        return Object.keys(nuevosErrores).length === 0;
    };

    const confirmarProduccionSiAplica = async () => {
        const eraCert = (configuracion?.ambiente_sii ?? 'certificacion') !== 'produccion';
        const ahoraProd = formData.ambiente_sii === 'produccion';
        if (eraCert && ahoraProd) {
            const { isConfirmed } = await Swal.fire({
                icon: 'warning',
                title: 'Cambio a PRODUCCION',
                html: 'Estas a punto de cambiar el ambiente a <b>produccion</b>. A partir de este momento, los DTE emitidos tendran <b>valor tributario real</b> ante el SII.<br/><br/>Confirmas el cambio?',
                showCancelButton: true,
                confirmButtonText: 'Si, activar produccion',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc2626',
            });
            return isConfirmed;
        }
        return true;
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!validar()) return;
        if (!(await confirmarProduccionSiAplica())) return;
        onSubmit(haciaBackend(formData));
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-8 animate-fade-in" data-testid="form-sii-configuracion">
            {/* Seccion: Datos de emision */}
            <section className="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <h3 className="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                    <i className="fas fa-building text-emerald-600" /> Datos de Emision
                </h3>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label htmlFor="giro_emisor">Giro / Actividad</label>
                        <input id="giro_emisor" name="giro_emisor" type="text" maxLength={80} value={formData.giro_emisor} onChange={handleChange} />
                    </div>
                    <div>
                        <label htmlFor="codigo_actividad_sii">Codigo Actividad SII</label>
                        <input id="codigo_actividad_sii" name="codigo_actividad_sii" type="number" min={1} value={formData.codigo_actividad_sii} onChange={handleChange} />
                        {errores.codigo_actividad_sii && <p className="text-xs text-rose-600 mt-1">{errores.codigo_actividad_sii}</p>}
                    </div>
                    <div>
                        <label htmlFor="comuna">Comuna</label>
                        <input id="comuna" name="comuna" type="text" maxLength={20} value={formData.comuna} onChange={handleChange} />
                    </div>
                    <div>
                        <label htmlFor="ciudad">Ciudad</label>
                        <input id="ciudad" name="ciudad" type="text" maxLength={20} value={formData.ciudad} onChange={handleChange} />
                    </div>
                    <div className="md:col-span-2">
                        <label htmlFor="rut_representante_legal">RUT Representante Legal</label>
                        <input id="rut_representante_legal" name="rut_representante_legal" type="text" placeholder="12.345.678-5" value={formData.rut_representante_legal} onChange={handleChange} />
                        {errores.rut_representante_legal && <p className="text-xs text-rose-600 mt-1">{errores.rut_representante_legal}</p>}
                    </div>
                </div>
            </section>

            {/* Seccion: Resolucion SII */}
            <section className="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <h3 className="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                    <i className="fas fa-stamp text-blue-600" /> Resolucion SII
                </h3>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label htmlFor="resolucion_sii_numero">Numero de Resolucion</label>
                        <input id="resolucion_sii_numero" name="resolucion_sii_numero" type="number" min={0} value={formData.resolucion_sii_numero} onChange={handleChange} />
                    </div>
                    <div>
                        <label htmlFor="resolucion_sii_fecha">Fecha de Resolucion</label>
                        <input id="resolucion_sii_fecha" name="resolucion_sii_fecha" type="date" value={formData.resolucion_sii_fecha} onChange={handleChange} />
                    </div>
                </div>
            </section>

            {/* Seccion: Configuracion operacional */}
            <section className="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <h3 className="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                    <i className="fas fa-sliders text-amber-600" /> Configuracion Operacional
                </h3>
                <div className="space-y-5">
                    <div>
                        <label htmlFor="ambiente_sii">Ambiente SII <span className="text-rose-500">*</span></label>
                        <select id="ambiente_sii" name="ambiente_sii" value={formData.ambiente_sii} onChange={handleChange} aria-required="true">
                            <option value="certificacion">Certificacion (pruebas)</option>
                            <option value="produccion">Produccion (DTE reales)</option>
                        </select>
                        {formData.ambiente_sii === 'produccion' && (
                            <p className="text-xs text-amber-700 mt-2 bg-amber-50 border border-amber-200 rounded-md p-2">
                                <i className="fas fa-exclamation-triangle mr-1" />
                                En produccion los DTE tendran valor tributario real ante el SII.
                            </p>
                        )}
                        {errores.ambiente_sii && <p className="text-xs text-rose-600 mt-1">{errores.ambiente_sii}</p>}
                    </div>
                    <div>
                        <label htmlFor="email_intercambio_sii">Email de Intercambio SII</label>
                        <input id="email_intercambio_sii" name="email_intercambio_sii" type="email" maxLength={80} placeholder="intercambio@empresa.cl" value={formData.email_intercambio_sii} onChange={handleChange} />
                        {errores.email_intercambio_sii && <p className="text-xs text-rose-600 mt-1">{errores.email_intercambio_sii}</p>}
                    </div>
                </div>
            </section>

            <div className="flex justify-end">
                <button
                    type="submit"
                    disabled={guardando}
                    className="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:bg-slate-400 disabled:cursor-not-allowed text-white font-bold rounded-lg shadow-sm transition-colors flex items-center gap-2"
                >
                    {guardando ? (<><i className="fas fa-spinner fa-spin" /> Guardando...</>) : (<><i className="fas fa-save" /> Guardar Configuracion</>)}
                </button>
            </div>
        </form>
    );
};

export default FormularioConfiguracionSii;
