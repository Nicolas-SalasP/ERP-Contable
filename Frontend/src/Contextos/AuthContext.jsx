import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import { api, markTokenIssued } from '../Configuracion/api';

const AuthContext = createContext(null);

// Storage activo de la sesion (el que contiene el token).
const getSessionStorage = () => {
    if (typeof window === 'undefined') return null;
    if (localStorage.getItem('erp_token')) return localStorage;
    if (sessionStorage.getItem('erp_token')) return sessionStorage;
    return null;
};

const REFRESCO_SESION_MS = 3 * 60 * 1000; // 3 minutos

export const AuthProvider = ({ children }) => {
    const [user, setUser] = useState(() => {
        try {
            const storedUser = localStorage.getItem('erp_user') || sessionStorage.getItem('erp_user');
            return storedUser ? JSON.parse(storedUser) : null;
        } catch {
            ['erp_token', 'erp_user', 'token', 'erp_token_issued_at'].forEach((k) => {
                localStorage.removeItem(k);
                sessionStorage.removeItem(k);
            });
            return null;
        }
    });

    const [loading, setLoading] = useState(false);

    // Re-sincroniza el usuario (incluidos sus permisos) contra el backend para que
    // los cambios de rol/permisos surtan efecto sin necesidad de re-login.
    const refrescarSesion = useCallback(async () => {
        const storage = getSessionStorage();
        if (!storage) return;
        try {
            const me = await api.get('/auth/me');
            if (me && me.id) {
                setUser(me);
                storage.setItem('erp_user', JSON.stringify(me));
            }
        } catch (_) {
            // Un 401 lo maneja globalmente api.js (cierra sesion). Errores de red se ignoran.
        }
    }, []);

    // Refresco periodico y al recuperar el foco de la pestaña.
    useEffect(() => {
        if (!user?.id) return;
        refrescarSesion();
        const intervalo = setInterval(refrescarSesion, REFRESCO_SESION_MS);
        const onFocus = () => refrescarSesion();
        window.addEventListener('focus', onFocus);
        return () => {
            clearInterval(intervalo);
            window.removeEventListener('focus', onFocus);
        };
    }, [user?.id, refrescarSesion]);

    // Si otra pestaña cierra sesion (erp_token pasa a null), invalidamos el
    // usuario en memoria de inmediato, sin esperar la recarga del navegador.
    useEffect(() => {
        const onStorage = (e) => {
            if (e.key === 'erp_token' && e.newValue === null) {
                setUser(null);
            }
        };
        window.addEventListener('storage', onStorage);
        return () => window.removeEventListener('storage', onStorage);
    }, []);

    const login = async (email, password, remember = false) => {
        setLoading(true);
        try {
            const response = await api.auth.login({ email, password });

            if (response.token) {
                const tokenRecibido = response.token;
                let usuarioRecibido = response.user;

                const storage = remember ? localStorage : sessionStorage;
                const otherStorage = remember ? sessionStorage : localStorage;

                storage.setItem('erp_token', tokenRecibido);
                // Limpia el otro storage por completo (incluido issued_at) para no
                // dejar restos de una sesion anterior con distinto "Recordarme".
                otherStorage.removeItem('erp_token');
                otherStorage.removeItem('erp_user');
                otherStorage.removeItem('erp_token_issued_at');
                markTokenIssued();

                try {
                    const meResponse = await api.get('/auth/me');
                    if (meResponse && meResponse.id) {
                        usuarioRecibido = meResponse;
                    }
                } catch (_) {}

                if (!usuarioRecibido || !usuarioRecibido.id) {
                    storage.removeItem('erp_token');
                    return { success: false, message: 'No se recibió información del usuario.', code: 'USER_MISSING' };
                }

                setUser(usuarioRecibido);
                storage.setItem('erp_user', JSON.stringify(usuarioRecibido));

                return { success: true };
            } else {
                return {
                    success: false,
                    message: response.message || 'Error',
                    code: response.code
                };
            }

        } catch (error) {
            return {
                success: false,
                message: error.message,
                code: error.code
            };
        } finally {
            setLoading(false);
        }
    };

    const logout = async () => {
        try {
            await api.auth.logout({ redirect: false });
        } finally {
            localStorage.removeItem('erp_token');
            localStorage.removeItem('erp_user');
            localStorage.removeItem('erp_token_issued_at');
            sessionStorage.removeItem('erp_token');
            sessionStorage.removeItem('erp_user');
            sessionStorage.removeItem('erp_token_issued_at');

            setUser(null);

            if (typeof window !== 'undefined') {
                window.location.href = '/login';
            }
        }
    };

    return (
        <AuthContext.Provider value={{ user, login, logout, isAuthenticated: !!user, loading, refrescarSesion }}>
            {children}
        </AuthContext.Provider>
    );
};

export const useAuth = () => useContext(AuthContext);