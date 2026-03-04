import React, { useState } from 'react';
import { useAuth } from '../../Contextos/AuthContext';
import { useNavigate, Link } from 'react-router-dom';

const Login = () => {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    
    // 1. ESTADO PARA EL CHECKBOX (Es crucial tener esto)
    const [rememberMe, setRememberMe] = useState(false); 

    const [error, setError] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    
    const { login } = useAuth();
    const navigate = useNavigate();

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        setIsSubmitting(true);
        
        // 2. ENVIAR 'rememberMe' COMO TERCER PARÁMETRO
        const result = await login(email, password, rememberMe);
        
        if (result.success) {
            navigate('/'); 
        } else {
            const errorMap = {
                'PLAN_VENCIDO': '⏳ Su suscripción ha vencido. Contacte a administración.',
                'CUENTA_SUSPENDIDA': '⛔ Cuenta suspendida por seguridad.',
                'CREDENCIALES_INCORRECTAS': '🔒 Credenciales incorrectas.',
                'ERROR_RED': '📡 No hay conexión con el servidor.'
            };
            setError(errorMap[result.code] || result.message || 'Error al iniciar sesión');
            setIsSubmitting(false);
        }
    };

    return (
        <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 p-4">
            
            <div className="max-w-4xl w-full bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col md:flex-row animate-fade-in-up">
                
                {/* SECCIÓN IZQUIERDA: IMAGEN / BRANDING */}
                <div className="w-full md:w-1/2 bg-blue-600 p-12 flex flex-col justify-between text-white relative overflow-hidden">
                    <div className="absolute top-0 left-0 w-40 h-40 bg-white opacity-10 rounded-full -translate-x-10 -translate-y-10"></div>
                    <div className="absolute bottom-0 right-0 w-60 h-60 bg-white opacity-10 rounded-full translate-x-20 translate-y-20"></div>

                    <div className="relative z-10">
                        <div className="flex items-center gap-3 mb-8">
                            <div className="bg-white p-2 rounded-lg bg-opacity-20 backdrop-blur-sm">
                                <svg className="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                            </div>
                            <h1 className="text-2xl font-bold tracking-wider">ERP CONTABLE</h1>
                        </div>
                        <h2 className="text-4xl font-extrabold mb-4 leading-tight">Control total de tu negocio.</h2>
                        <p className="text-blue-100 text-lg opacity-90">Gestiona facturas, proveedores y contabilidad en un solo lugar, seguro y rápido.</p>
                    </div>
                    
                    <div className="relative z-10 mt-8 text-sm text-blue-200">
                        © 2026 ERP System. Todos los derechos reservados.
                    </div>
                </div>

                {/* SECCIÓN DERECHA: FORMULARIO */}
                <div className="w-full md:w-1/2 p-8 md:p-12">
                    <div className="text-center mb-10">
                        <h3 className="text-3xl font-bold text-slate-800 mb-2">Bienvenido</h3>
                        <p className="text-slate-500">Ingresa tus credenciales para acceder</p>
                    </div>

                    {error && (
                        <div className="mb-6 bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded text-sm flex items-center shadow-sm animate-pulse-short">
                            <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            {error}
                        </div>
                    )}

                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div>
                            <label className="block text-sm font-bold text-slate-700 mb-2 ml-1">Correo Electrónico</label>
                            <div className="relative">
                                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg className="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path></svg>
                                </div>
                                <input
                                    type="email"
                                    className={`w-full pl-10 pr-4 py-3 rounded-lg border ${error ? 'border-red-300 focus:ring-red-200' : 'border-slate-300 focus:ring-blue-200'} focus:border-blue-500 focus:ring-4 outline-none transition-all text-slate-700`}
                                    placeholder="ejemplo@empresa.com"
                                    value={email}
                                    onChange={(e) => setEmail(e.target.value)}
                                    required
                                />
                            </div>
                        </div>

                        <div>
                            <label className="block text-sm font-bold text-slate-700 mb-2 ml-1">Contraseña</label>
                            <div className="relative">
                                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg className="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                </div>
                                <input
                                    type="password"
                                    className={`w-full pl-10 pr-4 py-3 rounded-lg border ${error ? 'border-red-300 focus:ring-red-200' : 'border-slate-300 focus:ring-blue-200'} focus:border-blue-500 focus:ring-4 outline-none transition-all text-slate-700`}
                                    placeholder="••••••••"
                                    value={password}
                                    onChange={(e) => setPassword(e.target.value)}
                                    required
                                />
                            </div>
                        </div>

                        <div className="flex items-center justify-between text-sm">
                            {/* 3. CHECKBOX CONECTADO AL ESTADO */}
                            <label className="flex items-center text-slate-600 cursor-pointer select-none">
                                <input 
                                    type="checkbox" 
                                    className="mr-2 rounded border-slate-300 text-blue-600 focus:ring-blue-500" 
                                    checked={rememberMe}
                                    onChange={(e) => setRememberMe(e.target.checked)}
                                />
                                Recordarme
                            </label>
                            
                            <Link to="/recuperar" className="text-blue-600 hover:text-blue-800 font-bold hover:underline">
                                ¿Olvidaste tu contraseña?
                            </Link>
                        </div>

                        <button
                            type="submit"
                            disabled={isSubmitting}
                            className={`w-full py-3 px-4 rounded-lg text-white font-bold text-lg shadow-md transition-all transform hover:scale-[1.02] active:scale-[0.98] 
                            ${isSubmitting ? 'bg-slate-400 cursor-not-allowed' : 'bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 shadow-blue-500/30'}`}
                        >
                            {isSubmitting ? (
                                <div className="flex items-center justify-center">
                                    <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Validando...
                                </div>
                            ) : 'Ingresar al Sistema'}
                        </button>
                    </form>

                    <div className="mt-8 text-center text-sm text-slate-500">
                        ¿No tienes una cuenta?{' '}
                        <Link to="/registro" className="text-blue-600 font-bold cursor-pointer hover:underline">
                            Regístrate aquí
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default Login;