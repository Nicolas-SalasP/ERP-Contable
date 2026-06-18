import React from 'react';
import { ImageIcon, Camera, Building2, Check } from 'lucide-react';
import BotonAccion from '../../../Componentes/BotonAccion';
const PerfilEmpresaGeneral = ({
    formData,
    imagenMostrada,
    saving,
    onChange,
    onRutChange,
    onTelefonoChange,
    onSeleccionarLogo,
    onSubmit,
}) => {
    return (
        <div className="p-6 md:p-8 grid grid-cols-1 lg:grid-cols-3 gap-8 animate-fade-in">
            <div className="lg:col-span-1 flex flex-col items-center space-y-4">
                <div className="w-56 h-56 border-2 border-dashed border-slate-300 rounded-2xl flex items-center justify-center bg-slate-50 overflow-hidden relative group transition-colors hover:border-blue-400">
                    {imagenMostrada ? (
                        <img src={imagenMostrada} alt="Logo Empresa" className="w-full h-full object-contain p-4" />
                    ) : (
                        <div className="text-center text-slate-400">
                            <ImageIcon size={48} strokeWidth={1.75} className="mx-auto mb-2 text-slate-300" />
                            <p className="text-sm font-bold">Sin Logo</p>
                        </div>
                    )}
                    <label className="absolute inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center text-white opacity-0 group-hover:opacity-100 transition-all cursor-pointer">
                        <span className="text-sm font-bold flex items-center gap-2">
                            <Camera size={20} strokeWidth={1.75} />
                            Cambiar Imagen
                        </span>
                        <input type="file" className="hidden" accept="image/*" onChange={onSeleccionarLogo} />
                    </label>
                </div>
                <p className="text-xs text-slate-400 text-center font-medium">
                    Formato: PNG o JPG. <br />Se utilizará en tus cotizaciones y documentos.
                </p>
            </div>

            <form onSubmit={onSubmit} className="lg:col-span-2 space-y-5">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label className="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5">
                            RUT Empresa
                        </label>
                        <input
                            name="rut"
                            value={formData.rut}
                            onChange={onRutChange}
                            placeholder="76.123.456-K"
                            className="w-full border border-slate-200 rounded-lg p-2.5 bg-slate-50 font-mono text-sm outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                    <div>
                        <label className="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5">
                            Razón Social
                        </label>
                        <input
                            name="razon_social"
                            value={formData.razon_social}
                            onChange={onChange}
                            className="w-full border border-slate-200 rounded-lg p-2.5 font-bold text-sm outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                </div>

                <div className="bg-blue-50 p-5 border border-blue-100 rounded-xl">
                    <label className="block text-[10px] font-black text-blue-600 uppercase tracking-widest mb-2 flex items-center gap-1.5">
                        <Building2 size={14} strokeWidth={1.75} />
                        Régimen Tributario (SII)
                    </label>
                    <select
                        name="regimen_tributario"
                        value={formData.regimen_tributario}
                        onChange={onChange}
                        className="w-full border border-blue-200 rounded-lg p-2.5 bg-white text-sm font-bold text-slate-700 focus:ring-2 focus:ring-blue-500 outline-none cursor-pointer"
                    >
                        <option value="14_D3">Régimen Pro Pyme General (14 D N° 3)</option>
                        <option value="14_D8">Régimen Pro Pyme Transparente (14 D N° 8)</option>
                        <option value="14_A">Régimen General Semi Integrado (14 A)</option>
                    </select>
                    <p className="text-[11px] text-blue-500 font-medium mt-2">
                        Define si el ERP calculará la Operación Renta mediante Flujo de Caja o Devengado.
                    </p>
                </div>

                <div>
                    <label className="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5">
                        Dirección Comercial
                    </label>
                    <input
                        name="direccion"
                        value={formData.direccion}
                        onChange={onChange}
                        className="w-full border border-slate-200 rounded-lg p-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Calle, Número, Comuna..."
                    />
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label className="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5">
                            Email Contacto
                        </label>
                        <input
                            name="email"
                            type="email"
                            value={formData.email}
                            onChange={onChange}
                            className="w-full border border-slate-200 rounded-lg p-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                    <div>
                        <label className="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5">
                            Teléfono
                        </label>
                        <input
                            name="telefono"
                            value={formData.telefono}
                            onChange={onTelefonoChange}
                            placeholder="+56 9 1234 5678"
                            className="w-full border border-slate-200 rounded-lg p-2.5 text-sm font-mono outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                </div>

                <div className="pt-2">
                    <label className="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">
                        Color Corporativo (Cotizaciones)
                    </label>
                    <div className="flex items-center gap-3">
                        <input
                            type="color"
                            name="color_primario"
                            value={formData.color_primario}
                            onChange={onChange}
                            className="w-10 h-10 rounded-lg cursor-pointer border-0 p-0 shadow-sm"
                        />
                        <input
                            type="text"
                            name="color_primario"
                            value={formData.color_primario}
                            onChange={onChange}
                            className="border border-slate-200 rounded-lg px-3 py-2 text-sm font-mono uppercase w-28 focus:ring-2 focus:ring-blue-500 outline-none"
                            maxLength={7}
                        />
                    </div>
                </div>

                <div className="pt-4 border-t border-slate-100 flex justify-end">
                    <BotonAccion
                        type="submit"
                        cargando={saving}
                        color="slate"
                        tamano="lg"
                        textoCargando="Guardando..."
                        icono={
                            <Check size={16} strokeWidth={1.75} />
                        }
                    >
                        Guardar Cambios
                    </BotonAccion>
                </div>
            </form>
        </div>
    );
};

export default PerfilEmpresaGeneral;
