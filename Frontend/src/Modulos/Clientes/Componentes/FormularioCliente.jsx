import React, { useState, useEffect } from 'react';
import { api } from '../../../Configuracion/api';
// Importamos las utilidades de validación y formateo existentes
import { formatearIdentificador, validarIdentificador } from '../../../Utilidades/identificadores';

const FormularioCliente = ({ clienteInicial, onSuccess, onCancel }) => {
    const [formData, setFormData] = useState({
        rut: '',
        razon_social: '',
        contacto_nombre: '',
        contacto_email: '',
        contacto_telefono: '',
        direccion: '',
        email: '',
        pais_iso: 'CL' // Valor por defecto
    });
    const [idError, setIdError] = useState(false);

    useEffect(() => {
        if (clienteInicial) {
            setFormData({
                ...clienteInicial,
                pais_iso: clienteInicial.pais_iso || 'CL'
            });
        }
    }, [clienteInicial]);

    // Maneja el formateo y validación del RUT/Identificador
    const handleIdChange = (e) => {
        const val = e.target.value;
        const pais = formData.pais_iso;
        
        // Aplica el formateo (ej: puntos y guion en Chile)
        const formatted = formatearIdentificador(val, pais);
        setFormData(prev => ({ ...prev, rut: formatted }));

        // Valida el dígito verificador y formato si hay longitud suficiente
        const cleanVal = formatted.replace(/[^0-9kK]/g, '');
        if (cleanVal.length > 4) {
            const isValid = validarIdentificador(formatted, pais);
            setIdError(!isValid);
        } else {
            setIdError(false);
        }
    };

    const handleChange = (e) => {
        setFormData({ ...formData, [e.target.name]: e.target.value });
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        
        if (idError) {
            alert("El identificador fiscal ingresado no es válido.");
            return;
        }

        try {
            let res;
            if (clienteInicial) {
                res = await api.put(`/clientes/${clienteInicial.id}`, formData);
            } else {
                res = await api.post('/clientes', formData);
            }
            if (res.success) onSuccess();
        } catch (error) {
            alert(error.message || "Error al procesar la solicitud");
        }
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            <section className="space-y-4">
                <h4 className="text-xs font-bold text-slate-400 uppercase tracking-wider border-b pb-2">Identificación Legal</h4>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="space-y-1">
                        <label className="text-xs font-bold text-slate-600 ml-1 flex justify-between">
                            <span>RUT / ID Fiscal</span>
                            {idError && <span className="text-red-500 animate-pulse text-[10px]">INVÁLIDO</span>}
                        </label>
                        <input 
                            name="rut" 
                            value={formData.rut} 
                            onChange={handleIdChange} 
                            required 
                            className={`w-full border rounded p-2.5 font-mono outline-none transition-all ${
                                idError ? 'border-red-500 bg-red-50' : 'border-gray-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100'
                            }`}
                            placeholder="Ingrese número..."
                        />
                    </div>
                    <div className="space-y-1">
                        <label className="text-xs font-bold text-slate-600 ml-1">Razón Social</label>
                        <input 
                            name="razon_social" 
                            value={formData.razon_social} 
                            onChange={handleChange} 
                            required 
                            className="w-full border border-gray-300 rounded p-2.5 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 outline-none transition-all" 
                            placeholder="Nombre de la empresa"
                        />
                    </div>
                </div>
            </section>

            <section className="space-y-4">
                <h4 className="text-xs font-bold text-slate-400 uppercase tracking-wider border-b pb-2">Personal de Contacto</h4>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="col-span-2 space-y-1">
                        <label className="text-xs font-bold text-slate-600 ml-1">Nombre Completo</label>
                        <input name="contacto_nombre" value={formData.contacto_nombre} onChange={handleChange} className="w-full border border-gray-300 rounded p-2.5 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" />
                    </div>
                    <div className="space-y-1">
                        <label className="text-xs font-bold text-slate-600 ml-1">Email Contacto</label>
                        <input type="email" name="contacto_email" value={formData.contacto_email} onChange={handleChange} className="w-full border border-gray-300 rounded p-2.5 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" />
                    </div>
                    <div className="space-y-1">
                        <label className="text-xs font-bold text-slate-600 ml-1">Teléfono</label>
                        <input name="contacto_telefono" value={formData.contacto_telefono} onChange={handleChange} className="w-full border border-gray-300 rounded p-2.5 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" />
                    </div>
                </div>
            </section>

            <section className="space-y-4">
                <h4 className="text-xs font-bold text-slate-400 uppercase tracking-wider border-b pb-2">Logística y Envío</h4>
                <div className="space-y-1">
                    <label className="text-xs font-bold text-slate-600 ml-1">Dirección Comercial</label>
                    <input name="direccion" value={formData.direccion} onChange={handleChange} className="w-full border border-gray-300 rounded p-2.5 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" />
                </div>
                <div className="space-y-1">
                    <label className="text-xs font-bold text-slate-600 ml-1">Email Facturación / Cobranza</label>
                    <input type="email" name="email" value={formData.email} onChange={handleChange} className="w-full border border-gray-300 rounded p-2.5 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" />
                </div>
            </section>

            <div className="flex justify-end gap-3 pt-4 border-t">
                <button type="button" onClick={onCancel} className="px-4 py-2 text-slate-500 hover:text-slate-700 font-bold transition-colors">Cancelar</button>
                <button 
                    type="submit" 
                    disabled={idError}
                    className={`px-8 py-2 rounded-lg font-bold shadow transition-all ${
                        idError ? 'bg-slate-300 cursor-not-allowed' : 'bg-emerald-600 text-white hover:bg-emerald-700 active:scale-95'
                    }`}
                >
                    {clienteInicial ? 'Guardar Cambios' : 'Registrar Cliente'}
                </button>
            </div>
        </form>
    );
};

export default FormularioCliente;