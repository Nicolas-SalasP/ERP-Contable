import React, { useState } from 'react';
import { useAuth } from '../../Contextos/AuthContext';
import { useNavigate, useSearchParams, Link } from 'react-router-dom';
import { AlertCircle, Mail, Lock, Eye, EyeOff, Loader2 } from 'lucide-react';

const Login = () => {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [showPassword, setShowPassword] = useState(false);
    const [rememberMe, setRememberMe] = useState(false);
    const [error, setError] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const { login } = useAuth();
    const navigate = useNavigate();
    const [searchParams] = useSearchParams();
    const redirectTo = searchParams.get('redirect') || '/';

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        setIsSubmitting(true);

        const result = await login(email, password, rememberMe);

        if (result.success) {
            navigate(redirectTo, { replace: true });
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

                <div className="w-full md:w-1/2 p-8 md:p-12">
                    <div className="text-center mb-10">
                        <h3 className="text-3xl font-bold text-slate-800 mb-2">Bienvenido</h3>
                        <p className="text-slate-500">Ingresa tus credenciales para acceder</p>
                    </div>

                    {error && (
                        <div className="mb-6 bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded text-sm flex items-center shadow-sm animate-pulse-short">
                            <AlertCircle size={20} strokeWidth={1.75} className="mr-2 flex-shrink-0" />
                            {error}
                        </div>
                    )}

                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div>
                            <label className="block text-sm font-bold text-slate-700 mb-2 ml-1">Correo Electrónico</label>
                            <div className="relative">
                                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <Mail size={20} strokeWidth={1.75} className="text-slate-400" />
                                </div>
                                <input
                                    type="email"
                                    className={`w-full  pl-10 pr-4 py-3 rounded-lg border ${error ? 'border-red-300 focus:ring-red-200' : 'border-slate-300 focus:ring-blue-200'} focus:border-blue-500 focus:ring-4 outline-none transition-all text-slate-700`}
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
                                    <Lock size={20} strokeWidth={1.75} className="text-slate-400" />
                                </div>
                                <input
                                    type={showPassword ? "text" : "password"}
                                    className={`w-full pr-12 py-3 rounded-lg border ${error ? 'border-red-300 focus:ring-red-200' : 'border-slate-300 focus:ring-blue-200'} focus:border-blue-500 focus:ring-4 outline-none transition-all text-slate-700`}
                                    style={{ paddingLeft: '3rem', height: '3rem' }}
                                    placeholder="••••••••"
                                    value={password}
                                    onChange={(e) => setPassword(e.target.value)}
                                    required
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowPassword(!showPassword)}
                                    className="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 focus:outline-none"
                                >
                                    {showPassword ? (
                                        <EyeOff size={20} strokeWidth={1.75} />
                                    ) : (
                                        <Eye size={20} strokeWidth={1.75} />
                                    )}
                                </button>
                            </div>
                        </div>

                        <div className="flex items-center justify-between text-sm">
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
                                    <Loader2 size={20} strokeWidth={1.75} className="animate-spin -ml-1 mr-3 text-white" />
                                    Validando...
                                </div>
                            ) : 'Ingresar al Sistema'}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    );
};

export default Login;