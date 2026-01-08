import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { api } from '../../Configuracion/api';
import Swal from 'sweetalert2';

const RecuperarPassword = () => {
    const navigate = useNavigate();
    const [step, setStep] = useState(1); // 1: Pedir Email, 2: Poner Código y Nuevo Pass
    const [loading, setLoading] = useState(false);
    
    const [email, setEmail] = useState('');
    const [codigo, setCodigo] = useState('');
    const [password, setPassword] = useState('');
    const [confirmPassword, setConfirmPassword] = useState('');

    // PASO 1: Enviar correo
    const handleSolicitarCodigo = async (e) => {
        e.preventDefault();
        setLoading(true);
        try {
            await api.post('/auth/recuperar', { email });
            // Pasamos al paso 2 independiente del resultado (por seguridad)
            // Pero avisamos al usuario
            Swal.fire({
                icon: 'info',
                title: 'Revisa tu correo',
                text: 'Si el correo está registrado, recibirás un código de 6 dígitos.',
            });
            setStep(2);
        } catch (error) {
            console.error(error);
            Swal.fire('Error', 'Hubo un problema de conexión', 'error');
        } finally {
            setLoading(false);
        }
    };

    // PASO 2: Resetear
    const handleResetear = async (e) => {
        e.preventDefault();
        if (password !== confirmPassword) {
            Swal.fire('Error', 'Las contraseñas no coinciden', 'error');
            return;
        }

        setLoading(true);
        try {
            const res = await api.post('/auth/resetear', { email, codigo, password });
            if (res.success) {
                await Swal.fire('¡Éxito!', 'Tu contraseña ha sido actualizada.', 'success');
                navigate('/login');
            } else {
                Swal.fire('Error', res.message || 'Código inválido', 'error');
            }
        } catch (error) {
            Swal.fire('Error', error.message || 'Error al restablecer', 'error');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 p-4">
            <div className="max-w-md w-full bg-white rounded-2xl shadow-2xl p-8 animate-fade-in-up">
                
                <div className="text-center mb-8">
                    <h2 className="text-2xl font-bold text-slate-800">Recuperar Cuenta</h2>
                    <p className="text-slate-500 text-sm mt-2">
                        {step === 1 ? 'Ingresa tu correo para recibir un código.' : 'Ingresa el código recibido y tu nueva clave.'}
                    </p>
                </div>

                {step === 1 ? (
                    <form onSubmit={handleSolicitarCodigo} className="space-y-6">
                        <div>
                            <label className="block text-sm font-bold text-slate-700 mb-2">Correo Electrónico</label>
                            <input 
                                type="email" 
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                                className="w-full border border-gray-300 rounded-lg p-3 outline-none focus:ring-2 focus:ring-emerald-500"
                                required
                            />
                        </div>
                        <button 
                            type="submit" 
                            disabled={loading}
                            className="w-full py-3 bg-emerald-600 text-white font-bold rounded-lg hover:bg-emerald-700 transition-colors disabled:opacity-50"
                        >
                            {loading ? 'Enviando...' : 'Enviar Código'}
                        </button>
                    </form>
                ) : (
                    <form onSubmit={handleResetear} className="space-y-4">
                        <div className="bg-blue-50 text-blue-800 p-3 rounded text-sm mb-4">
                            Enviamos un código a: <strong>{email}</strong>
                        </div>
                        <div>
                            <label className="block text-sm font-bold text-slate-700 mb-1">Código de 6 dígitos</label>
                            <input 
                                type="text" 
                                value={codigo}
                                onChange={(e) => setCodigo(e.target.value)}
                                className="w-full border border-gray-300 rounded-lg p-3 outline-none focus:ring-2 focus:ring-emerald-500 font-mono text-center tracking-widest text-lg"
                                maxLength={6}
                                placeholder="000000"
                                required
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-bold text-slate-700 mb-1">Nueva Contraseña</label>
                            <input 
                                type="password" 
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                                className="w-full border border-gray-300 rounded-lg p-3 outline-none focus:ring-2 focus:ring-emerald-500"
                                required
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-bold text-slate-700 mb-1">Confirmar Contraseña</label>
                            <input 
                                type="password" 
                                value={confirmPassword}
                                onChange={(e) => setConfirmPassword(e.target.value)}
                                className="w-full border border-gray-300 rounded-lg p-3 outline-none focus:ring-2 focus:ring-emerald-500"
                                required
                            />
                        </div>
                        <button 
                            type="submit" 
                            disabled={loading}
                            className="w-full py-3 bg-emerald-600 text-white font-bold rounded-lg hover:bg-emerald-700 transition-colors disabled:opacity-50 mt-4"
                        >
                            {loading ? 'Actualizando...' : 'Cambiar Contraseña'}
                        </button>
                    </form>
                )}

                <div className="mt-6 text-center">
                    <Link to="/login" className="text-sm font-bold text-slate-400 hover:text-slate-600">
                        Volver al Login
                    </Link>
                </div>
            </div>
        </div>
    );
};

export default RecuperarPassword;