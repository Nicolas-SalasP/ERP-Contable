import React, { useState } from 'react';
import { api } from '../../Configuracion/api';
import Swal from 'sweetalert2';

// =============================================================================
// --- UTILIDADES DE VALIDACIÓN Y FORMATEO ---
// =============================================================================

const validarRutChile = (valor) => {
    const limpio = valor.replace(/[^0-9kK]/g, '').toUpperCase();
    if (limpio.length < 2) return false;
    const cuerpo = limpio.slice(0, -1);
    const dv = limpio.slice(-1);
    let suma = 0, multiplo = 2;
    for (let i = cuerpo.length - 1; i >= 0; i--) {
        suma += multiplo * cuerpo.charAt(i);
        multiplo = (multiplo + 1 === 8) ? 2 : multiplo + 1;
    }
    const res = 11 - (suma % 11);
    let dvCalc = res === 11 ? '0' : res === 10 ? 'K' : res.toString();
    return dvCalc === dv;
};

const formatearRutChile = (numero) => {
    const limpio = numero.replace(/[^0-9kK]/g, '').toUpperCase();
    if (!limpio) return '';
    if (limpio.length <= 1) return limpio;
    const cuerpo = limpio.slice(0, -1);
    const dv = limpio.slice(-1);
    return `${cuerpo.replace(/\B(?=(\d{3})+(?!\d))/g, ".")}-${dv}`;
};

// Formatea el teléfono para WhatsApp (+569XXXXXXXX) eliminando espacios
const formatearTelefonoChile = (valor) => {
    let limpio = valor.replace(/[^\d+]/g, ''); // Solo deja números y el '+'
    
    // Autocompletar si el usuario empieza solo con '9' o '569'
    if (limpio.startsWith('9')) limpio = '+56' + limpio;
    if (limpio.startsWith('569')) limpio = '+' + limpio;
    if (limpio.startsWith('56') && !limpio.startsWith('569')) limpio = '+' + limpio;

    // Limitar a 12 caracteres (+569 + 8 dígitos)
    return limpio.slice(0, 12);
};

// =============================================================================
// --- COMPONENTE PRINCIPAL ---
// =============================================================================

const CrearEmpresa = () => {
    const [paso, setPaso] = useState(1);
    const [loading, setLoading] = useState(false);
    const [verificandoRut, setVerificandoRut] = useState(false);
    
    // Estado para manejar los errores en línea (UX)
    const [errores, setErrores] = useState({});

    const [formData, setFormData] = useState({
        empresa_rut: '',
        empresa_razon_social: '',
        giro: '',
        direccion: '',
        telefono: '',
        regimen_tributario: '14_D3'
    });

    const handleChange = (e) => {
        const { name, value } = e.target;
        
        // Limpiamos el error de este campo apenas el usuario empiece a escribir
        if (errores[name]) {
            setErrores({ ...errores, [name]: null });
        }

        if (name === 'empresa_rut') {
            setFormData({ ...formData, [name]: formatearRutChile(value) });
        } else if (name === 'telefono') {
            setFormData({ ...formData, [name]: formatearTelefonoChile(value) });
        } else {
            setFormData({ ...formData, [name]: value });
        }
    };

    // Validación Silenciosa al salir del input del RUT
    const handleBlurRut = async () => {
        const rut = formData.empresa_rut;
        if (!rut) return;

        if (!validarRutChile(rut)) {
            setErrores(prev => ({ ...prev, empresa_rut: 'RUT inválido. Verifica la informacion ingresada.' }));
            return;
        }

        setVerificandoRut(true);
        try {
            const res = await api.get(`/empresas/verificar-rut?rut=${rut}`);
            if (res.existe) {
                setErrores(prev => ({ ...prev, empresa_rut: 'Este RUT ya está registrado en el ERP.' }));
            }
        } catch (error) {
            console.error("Error al verificar RUT");
        } finally {
            setVerificandoRut(false);
        }
    };

    const avanzarPaso = () => {
        let nuevosErrores = {};

        if (paso === 1) {
            if (!formData.empresa_rut) nuevosErrores.empresa_rut = 'El RUT es obligatorio.';
            else if (!validarRutChile(formData.empresa_rut)) nuevosErrores.empresa_rut = 'Ingrese un RUT válido.';
            
            if (!formData.empresa_razon_social) nuevosErrores.empresa_razon_social = 'La razón social es obligatoria.';
            
            // Si el RUT ya tiene error por duplicado desde el onBlur, lo mantenemos
            if (errores.empresa_rut) nuevosErrores.empresa_rut = errores.empresa_rut;
        }

        if (paso === 2) {
            if (formData.telefono && formData.telefono.length < 12) {
                nuevosErrores.telefono = 'El teléfono debe tener formato +569XXXXXXXX';
            }
        }

        // Si hay errores, los mostramos en los inputs y detenemos el avance
        if (Object.keys(nuevosErrores).length > 0) {
            setErrores(nuevosErrores);
            return;
        }

        setPaso(paso + 1);
    };

    const finalizarOnboarding = async () => {
        setLoading(true);
        try {
            const res = await api.post('/empresas/onboarding', formData);

            if (res.success) {
                const storage = !!localStorage.getItem('erp_token') ? localStorage : sessionStorage;
                storage.setItem('erp_token', res.token);
                storage.setItem('erp_user', JSON.stringify(res.user));

                await Swal.fire({
                    icon: 'success',
                    title: '¡Bienvenido!',
                    text: 'Empresa configurada exitosamente.',
                    timer: 2000,
                    showConfirmButton: false
                });

                window.location.href = '/'; 
            }
        } catch (error) {
            Swal.fire({ 
                icon: 'error', 
                title: 'Error de Configuración',
                text: error.message || 'No se pudo completar el registro.', 
                confirmButtonColor: '#0f172a' 
            });
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="min-h-screen relative overflow-hidden bg-slate-950 flex flex-col justify-center items-center p-4">
            <div className="absolute top-[-10%] left-[-10%] w-[50%] h-[50%] bg-emerald-600/20 blur-[120px] rounded-full animate-pulse"></div>
            <div className="absolute bottom-[-10%] right-[-10%] w-[50%] h-[50%] bg-blue-900/20 blur-[120px] rounded-full animate-pulse" style={{ animationDelay: '3s' }}></div>
            
            <div className="max-w-xl w-full bg-white/5 backdrop-blur-2xl rounded-[2.5rem] shadow-2xl border border-white/10 overflow-hidden relative z-10">
                
                <div className="p-10 text-center border-b border-white/10 bg-white/5">
                    <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-emerald-500 shadow-lg shadow-emerald-500/40 mb-6 animate-bounce-slow">
                        <i className="fas fa-building text-2xl text-white"></i>
                    </div>
                    <h1 className="text-3xl font-black tracking-tight text-white mb-1 uppercase">Bienvenida</h1>
                    <p className="text-slate-400 text-xs font-bold tracking-[0.2em]">PASO {paso} DE 3</p>
                    
                    <div className="w-full bg-slate-800/50 h-1.5 rounded-full mt-6 overflow-hidden">
                        <div className="bg-gradient-to-r from-emerald-500 to-teal-400 h-full transition-all duration-700" style={{ width: `${(paso / 3) * 100}%` }}></div>
                    </div>
                </div>

                <div className="p-10">
                    <div className="space-y-8">
                        
                        {/* PASO 1 */}
                        {paso === 1 && (
                            <div className="space-y-5 animate-in fade-in slide-in-from-bottom-4 duration-500">
                                <div className="relative">
                                    <label className="text-[10px] font-black text-emerald-500 uppercase tracking-widest mb-2 block">RUT Empresa *</label>
                                    <input 
                                        type="text" name="empresa_rut" value={formData.empresa_rut} 
                                        onChange={handleChange} onBlur={handleBlurRut}
                                        placeholder="76.000.000-0"
                                        className={`w-full bg-slate-900/50 border ${errores.empresa_rut ? 'border-rose-500 focus:ring-rose-500/50' : 'border-slate-700 focus:ring-emerald-500/50'} text-white rounded-2xl p-4 outline-none focus:ring-2 transition-all font-mono`} 
                                    />
                                    {verificandoRut && <span className="absolute right-4 top-12 text-[10px] text-emerald-500 animate-pulse font-bold">Validando...</span>}
                                    {errores.empresa_rut && <p className="text-rose-400 text-xs mt-2 font-bold flex items-center gap-1"><i className="fas fa-exclamation-circle"></i> {errores.empresa_rut}</p>}
                                </div>
                                <div>
                                    <label className="text-[10px] font-black text-emerald-500 uppercase tracking-widest mb-2 block">Razón Social *</label>
                                    <input 
                                        type="text" name="empresa_razon_social" value={formData.empresa_razon_social} 
                                        onChange={handleChange} placeholder="Nombre Legal" 
                                        className={`w-full bg-slate-900/50 border ${errores.empresa_razon_social ? 'border-rose-500 focus:ring-rose-500/50' : 'border-slate-700 focus:ring-emerald-500/50'} text-white rounded-2xl p-4 outline-none focus:ring-2 transition-all font-bold`} 
                                    />
                                    {errores.empresa_razon_social && <p className="text-rose-400 text-xs mt-2 font-bold flex items-center gap-1"><i className="fas fa-exclamation-circle"></i> {errores.empresa_razon_social}</p>}
                                </div>
                                <div>
                                    <label className="text-[10px] font-black text-emerald-500 uppercase tracking-widest mb-2 block">Giro Comercial</label>
                                    <input type="text" name="giro" value={formData.giro} onChange={handleChange} placeholder="Ej: Venta de Software" className="w-full bg-slate-900/50 border border-slate-700 text-white rounded-2xl p-4 outline-none focus:ring-2 focus:ring-emerald-500/50 transition-all" />
                                </div>
                            </div>
                        )}

                        {/* PASO 2 */}
                        {paso === 2 && (
                            <div className="space-y-5 animate-in fade-in slide-in-from-right-4 duration-500">
                                <div>
                                    <label className="text-[10px] font-black text-emerald-500 uppercase tracking-widest mb-2 block">Dirección Principal</label>
                                    <input type="text" name="direccion" value={formData.direccion} onChange={handleChange} placeholder="Ej: Av. Providencia 1234" className="w-full bg-slate-900/50 border border-slate-700 text-white rounded-2xl p-4 outline-none focus:ring-2 focus:ring-emerald-500/50 transition-all" />
                                </div>
                                <div>
                                    <label className="text-[10px] font-black text-emerald-500 uppercase tracking-widest mb-2 block">Teléfono (WhatsApp)</label>
                                    <input 
                                        type="text" name="telefono" value={formData.telefono} 
                                        onChange={handleChange} placeholder="+56912345678" 
                                        className={`w-full bg-slate-900/50 border ${errores.telefono ? 'border-rose-500 focus:ring-rose-500/50' : 'border-slate-700 focus:ring-emerald-500/50'} text-white rounded-2xl p-4 outline-none focus:ring-2 transition-all font-mono`} 
                                    />
                                    {errores.telefono ? (
                                        <p className="text-rose-400 text-xs mt-2 font-bold flex items-center gap-1"><i className="fas fa-exclamation-circle"></i> {errores.telefono}</p>
                                    ) : (
                                        <p className="text-slate-500 text-[10px] mt-2">Solo números. Ej: Si digita 937094271 se formateará a +56937094271 automáticamente.</p>
                                    )}
                                </div>
                            </div>
                        )}

                        {/* PASO 3 */}
                        {paso === 3 && (
                            <div className="space-y-5 animate-in fade-in slide-in-from-right-4 duration-500">
                                <div>
                                    <label className="text-[10px] font-black text-emerald-500 uppercase tracking-widest mb-2 block">Régimen Tributario</label>
                                    <select 
                                        name="regimen_tributario" 
                                        value={formData.regimen_tributario} 
                                        onChange={handleChange} 
                                        className="w-full bg-slate-900 border border-slate-700 text-white rounded-2xl p-4 outline-none focus:ring-2 focus:ring-emerald-500 transition-all appearance-none cursor-pointer"
                                    >
                                        <option value="14_D3">Pro Pyme General (14 D3)</option>
                                        <option value="14_D8">Pro Pyme Transparente (14 D8)</option>
                                        <option value="14_A">Régimen General (14 A)</option>
                                    </select>
                                </div>
                            </div>
                        )}

                        <div className="flex gap-4 pt-6">
                            {paso > 1 && (
                                <button onClick={() => setPaso(paso - 1)} className="px-8 py-4 bg-white/5 hover:bg-white/10 text-white font-bold rounded-2xl transition-all border border-white/10">
                                    ATRÁS
                                </button>
                            )}
                            
                            {paso < 3 ? (
                                <button 
                                    onClick={avanzarPaso} 
                                    disabled={verificandoRut}
                                    className="flex-1 bg-emerald-500 hover:bg-emerald-400 text-white font-black py-4 rounded-2xl shadow-lg shadow-emerald-500/20 transition-all flex items-center justify-center gap-2 disabled:opacity-50"
                                >
                                    SIGUIENTE <i className="fas fa-chevron-right text-[10px]"></i>
                                </button>
                            ) : (
                                <button 
                                    onClick={finalizarOnboarding} 
                                    disabled={loading} 
                                    className="flex-1 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-white font-black py-4 rounded-2xl shadow-xl shadow-emerald-500/20 transition-all flex items-center justify-center gap-2 disabled:opacity-50"
                                >
                                    {loading ? 'Sincronizando...' : 'FINALIZAR Y ENTRAR'}
                                </button>
                            )}
                        </div>
                    </div>
                </div>
            </div>
            
            <div className="mt-8 text-slate-600 text-[10px] font-black uppercase tracking-[0.3em] animate-pulse">
                AtlasWeb Identity S2S
            </div>
        </div>
    );
};

export default CrearEmpresa;