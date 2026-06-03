import React, { useState } from 'react';
import AyudaModulo from '../../Componentes/AyudaModulo';
import { api } from '../../Configuracion/api';
import { logger } from '../../Configuracion/logger';
import { validarIdentificador, formatearIdentificador } from '../../Utilidades/identificadores';
import Swal from 'sweetalert2';

const validarRutChile = (valor) => validarIdentificador(valor, 'CL');
const formatearRutChile = (numero) => formatearIdentificador(numero, 'CL');

const formatearTelefonoChile = (valor) => {
    let limpio = valor.replace(/[^\d+]/g, '');
    if (limpio.startsWith('9')) limpio = '+56' + limpio;
    if (limpio.startsWith('569')) limpio = '+' + limpio;
    if (limpio.startsWith('56') && !limpio.startsWith('569')) limpio = '+' + limpio;
    return limpio.slice(0, 12);
};

const PASOS = [
    { n: 1, label: 'Empresa', titulo: 'Datos de tu empresa', subtitulo: 'Identifícala ante el SII para empezar.' },
    { n: 2, label: 'Contacto', titulo: '¿Cómo te contactamos?', subtitulo: 'Dirección y teléfono. Puedes completarlo más tarde.' },
    { n: 3, label: 'Tributario', titulo: 'Régimen tributario', subtitulo: 'Define cómo tu empresa declara sus impuestos.' },
];

const inputBase = 'w-full bg-slate-900/60 border text-white rounded-2xl px-4 py-3.5 outline-none focus:ring-2 transition-all placeholder:text-slate-600';
const inputOk = 'border-slate-700/80 focus:border-emerald-500/50 focus:ring-emerald-500/30';
const inputErr = 'border-rose-500/70 focus:border-rose-500 focus:ring-rose-500/30';
const labelCls = 'text-[11px] font-bold text-emerald-400/90 uppercase tracking-widest mb-2 block';
const errorCls = 'text-rose-400 text-xs mt-2 font-semibold flex items-center gap-1.5';

const CrearEmpresa = () => {
    const [paso, setPaso] = useState(1);
    const [loading, setLoading] = useState(false);
    const [verificandoRut, setVerificandoRut] = useState(false);
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

    const handleBlurRut = async () => {
        const rut = formData.empresa_rut;
        if (!rut) return;

        if (!validarRutChile(rut)) {
            setErrores(prev => ({ ...prev, empresa_rut: 'RUT inválido. Revisa el dígito verificador.' }));
            return;
        }

        setVerificandoRut(true);
        try {
            const res = await api.get(`/empresas/verificar-rut?rut=${rut}`);
            // El backend es la fuente autoritativa de la validación del dígito verificador.
            if (res.valido === false) {
                setErrores(prev => ({ ...prev, empresa_rut: 'El RUT no es válido: el dígito verificador no corresponde.' }));
            } else if (res.existe) {
                setErrores(prev => ({ ...prev, empresa_rut: 'Este RUT ya está registrado en el ERP.' }));
            }
        } catch (error) {
            logger.error("Error al verificar RUT");
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

            if (errores.empresa_rut) nuevosErrores.empresa_rut = errores.empresa_rut;
        }

        if (paso === 2) {
            if (formData.telefono && formData.telefono.length < 12) {
                nuevosErrores.telefono = 'El teléfono debe tener formato +569XXXXXXXX';
            }
        }

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
                const storage = localStorage.getItem('erp_token') ? localStorage : sessionStorage;
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

    const metaPaso = PASOS[paso - 1];

    return (
        <div className="min-h-screen relative overflow-hidden bg-slate-950 flex flex-col justify-center items-center p-4 sm:p-6">
            {/* Fondo: blobs de color difuminados */}
            <div className="absolute top-[-15%] left-[-10%] w-[55%] h-[55%] bg-emerald-600/20 blur-[130px] rounded-full animate-pulse"></div>
            <div className="absolute bottom-[-15%] right-[-10%] w-[55%] h-[55%] bg-sky-700/20 blur-[130px] rounded-full animate-pulse" style={{ animationDelay: '2.5s' }}></div>
            <div className="absolute top-[30%] right-[20%] w-[25%] h-[25%] bg-teal-500/10 blur-[100px] rounded-full"></div>

            {/* Marca */}
            <div className="flex items-center gap-2.5 mb-7 relative z-10">
                <div className="flex items-center justify-center w-9 h-9 rounded-xl bg-gradient-to-br from-emerald-400 to-teal-500 shadow-lg shadow-emerald-500/30">
                    <i className="fas fa-cloud text-white text-base"></i>
                </div>
                <span className="text-white font-black text-lg tracking-tight">Tenri <span className="text-emerald-400">ERP</span> Cloud</span>
            </div>

            <div className="max-w-lg w-full bg-white/[0.06] backdrop-blur-2xl rounded-[2rem] shadow-2xl shadow-black/40 border border-white/10 overflow-hidden relative z-10">

                {/* Encabezado: bienvenida + stepper */}
                <div className="px-8 sm:px-10 pt-9 pb-7 text-center border-b border-white/10 bg-gradient-to-b from-white/[0.04] to-transparent">
                    <div className="flex items-center justify-center gap-2 mb-1">
                        <h1 className="text-2xl sm:text-3xl font-black tracking-tight text-white">Bienvenido</h1>
                        <span className="text-2xl sm:text-3xl">👋</span>
                        <AyudaModulo moduloId="crearEmpresa" />
                    </div>
                    <p className="text-slate-400 text-sm">Configura tu empresa en 3 pasos rápidos.</p>

                    {/* Stepper */}
                    <div className="flex items-start justify-center mt-7">
                        {PASOS.map((p, i) => (
                            <div key={p.n} className="flex items-center">
                                <div className="flex flex-col items-center w-[68px]">
                                    <div className={`flex items-center justify-center w-9 h-9 rounded-full text-xs font-black transition-all duration-500 ${
                                        paso > p.n
                                            ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-500/30'
                                            : paso === p.n
                                                ? 'bg-white text-slate-900 ring-4 ring-emerald-500/30'
                                                : 'bg-white/10 text-slate-400'
                                    }`}>
                                        {paso > p.n ? <i className="fas fa-check"></i> : p.n}
                                    </div>
                                    <span className={`text-[9px] font-bold uppercase tracking-wider mt-2 ${paso >= p.n ? 'text-slate-200' : 'text-slate-500'}`}>{p.label}</span>
                                </div>
                                {i < PASOS.length - 1 && (
                                    <div className={`h-0.5 w-6 sm:w-9 rounded-full mt-[18px] transition-all duration-500 ${paso > p.n ? 'bg-emerald-500' : 'bg-white/10'}`}></div>
                                )}
                            </div>
                        ))}
                    </div>
                </div>

                {/* Contenido del paso */}
                <div className="px-8 sm:px-10 py-8">
                    <div className="mb-6">
                        <h2 className="text-lg font-black text-white">{metaPaso.titulo}</h2>
                        <p className="text-slate-400 text-sm mt-0.5">{metaPaso.subtitulo}</p>
                    </div>

                    {/* PASO 1 */}
                    {paso === 1 && (
                        <div className="space-y-5 animate-in fade-in slide-in-from-bottom-4 duration-500">
                            <div className="relative">
                                <label className={labelCls}>RUT Empresa *</label>
                                <input
                                    type="text" name="empresa_rut" value={formData.empresa_rut}
                                    onChange={handleChange} onBlur={handleBlurRut}
                                    placeholder="76.000.000-0"
                                    className={`${inputBase} ${errores.empresa_rut ? inputErr : inputOk} font-mono`}
                                />
                                {verificandoRut && <span className="absolute right-4 top-[42px] text-[10px] text-emerald-400 animate-pulse font-bold">Validando…</span>}
                                {errores.empresa_rut && <p className={errorCls}><i className="fas fa-exclamation-circle"></i> {errores.empresa_rut}</p>}
                            </div>
                            <div>
                                <label className={labelCls}>Razón Social *</label>
                                <input
                                    type="text" name="empresa_razon_social" value={formData.empresa_razon_social}
                                    onChange={handleChange} placeholder="Nombre legal de la empresa"
                                    className={`${inputBase} ${errores.empresa_razon_social ? inputErr : inputOk} font-semibold`}
                                />
                                {errores.empresa_razon_social && <p className={errorCls}><i className="fas fa-exclamation-circle"></i> {errores.empresa_razon_social}</p>}
                            </div>
                            <div>
                                <label className={labelCls}>Giro Comercial</label>
                                <input type="text" name="giro" value={formData.giro} onChange={handleChange} placeholder="Ej: Venta de Software" className={`${inputBase} ${inputOk}`} />
                            </div>
                        </div>
                    )}

                    {/* PASO 2 */}
                    {paso === 2 && (
                        <div className="space-y-5 animate-in fade-in slide-in-from-right-4 duration-500">
                            <div>
                                <label className={labelCls}>Dirección Principal</label>
                                <input type="text" name="direccion" value={formData.direccion} onChange={handleChange} placeholder="Ej: Av. Providencia 1234" className={`${inputBase} ${inputOk}`} />
                            </div>
                            <div>
                                <label className={labelCls}>Teléfono (WhatsApp)</label>
                                <input
                                    type="text" name="telefono" value={formData.telefono}
                                    onChange={handleChange} placeholder="+56912345678"
                                    className={`${inputBase} ${errores.telefono ? inputErr : inputOk} font-mono`}
                                />
                                {errores.telefono ? (
                                    <p className={errorCls}><i className="fas fa-exclamation-circle"></i> {errores.telefono}</p>
                                ) : (
                                    <p className="text-slate-500 text-[11px] mt-2">Solo números. Ej: si digitas 937094271 se formatea a +56937094271.</p>
                                )}
                            </div>
                        </div>
                    )}

                    {/* PASO 3 */}
                    {paso === 3 && (
                        <div className="space-y-5 animate-in fade-in slide-in-from-right-4 duration-500">
                            <div>
                                <label className={labelCls}>Régimen Tributario</label>
                                <div className="relative">
                                    <select
                                        name="regimen_tributario"
                                        value={formData.regimen_tributario}
                                        onChange={handleChange}
                                        className={`${inputBase} ${inputOk} appearance-none cursor-pointer pr-10`}
                                    >
                                        <option className="bg-slate-900" value="14_D3">Pro Pyme General (14 D3)</option>
                                        <option className="bg-slate-900" value="14_D8">Pro Pyme Transparente (14 D8)</option>
                                        <option className="bg-slate-900" value="14_A">Régimen General (14 A)</option>
                                    </select>
                                    <i className="fas fa-chevron-down text-slate-500 text-xs absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                                </div>
                            </div>
                            <div className="flex items-start gap-3 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 p-4">
                                <i className="fas fa-circle-check text-emerald-400 mt-0.5"></i>
                                <p className="text-slate-300 text-xs leading-relaxed">¡Todo listo! Al finalizar crearemos tu empresa y entrarás directo a tu panel.</p>
                            </div>
                        </div>
                    )}

                    {/* Acciones */}
                    <div className="flex gap-3 pt-7">
                        {paso > 1 && (
                            <button onClick={() => setPaso(paso - 1)} className="px-6 py-3.5 bg-white/5 hover:bg-white/10 text-white font-bold rounded-2xl transition-all border border-white/10 flex items-center gap-2">
                                <i className="fas fa-chevron-left text-[10px]"></i> Atrás
                            </button>
                        )}

                        {paso < 3 ? (
                            <button
                                onClick={avanzarPaso}
                                disabled={verificandoRut}
                                className="flex-1 bg-emerald-500 hover:bg-emerald-400 text-white font-black py-3.5 rounded-2xl shadow-lg shadow-emerald-500/20 transition-all flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Siguiente <i className="fas fa-chevron-right text-[10px]"></i>
                            </button>
                        ) : (
                            <button
                                onClick={finalizarOnboarding}
                                disabled={loading}
                                className="flex-1 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-white font-black py-3.5 rounded-2xl shadow-xl shadow-emerald-500/25 transition-all flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {loading ? (
                                    <><i className="fas fa-circle-notch animate-spin"></i> Creando empresa…</>
                                ) : (
                                    <>Finalizar y entrar <i className="fas fa-arrow-right text-xs"></i></>
                                )}
                            </button>
                        )}
                    </div>
                </div>
            </div>

            {/* Pie */}
            <div className="mt-7 flex items-center gap-2 text-slate-500 text-[11px] font-semibold relative z-10">
                <i className="fas fa-lock text-[10px]"></i>
                <span>Conexión segura · Tenri ERP Cloud</span>
            </div>
        </div>
    );
};

export default CrearEmpresa;
