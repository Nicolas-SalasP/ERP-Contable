import React, { useEffect, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { useAuth } from '../../Contextos/AuthContext';
import { api } from '../../Configuracion/api';

const SsoCallback = () => {
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();
    const { login: setSession } = useAuth();
    const [error, setError] = useState(null);

    useEffect(() => {
        const ssoToken = searchParams.get('sso_token');

        if (!ssoToken) {
            setError('Token SSO no encontrado en la URL.');
            return;
        }

        const doTokenLogin = async () => {
            try {
                const response = await api.auth.tokenLogin(ssoToken);

                if (!response.success || !response.token) {
                    setError(response.message || 'Token inválido o expirado.');
                    return;
                }

                sessionStorage.setItem('erp_token', response.token);
                sessionStorage.setItem('erp_user', JSON.stringify(response.user));

                navigate('/dashboard', { replace: true });
            } catch (err) {
                setError('No se pudo completar el acceso automático. Por favor ingresa con tu email y contraseña.');
            }
        };

        doTokenLogin();
    }, []);

    if (error) {
        return (
            <div className="min-h-screen flex items-center justify-center bg-gray-50">
                <div className="bg-white p-8 rounded-2xl shadow-sm border border-red-100 max-w-md w-full text-center">
                    <p className="text-red-600 font-semibold mb-4">{error}</p>
                    <button
                        onClick={() => navigate('/login', { replace: true })}
                        className="text-sm text-gray-500 underline hover:text-gray-700"
                    >
                        Ir al login
                    </button>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen flex items-center justify-center bg-gray-50">
            <div className="text-center space-y-3">
                <div className="w-10 h-10 border-4 border-tenri-600 border-t-transparent rounded-full animate-spin mx-auto" />
                <p className="text-gray-600 font-medium text-sm">Iniciando sesión automáticamente...</p>
            </div>
        </div>
    );
};

export default SsoCallback;
