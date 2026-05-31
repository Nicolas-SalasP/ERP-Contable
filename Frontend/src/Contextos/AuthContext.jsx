import React, { createContext, useContext, useState } from 'react';
import { api, markTokenIssued } from '../Configuracion/api';

const AuthContext = createContext(null);

export const AuthProvider = ({ children }) => {
    const [user, setUser] = useState(() => {
        const storedUser = localStorage.getItem('erp_user') || sessionStorage.getItem('erp_user');
        return storedUser ? JSON.parse(storedUser) : null;
    });

    const [loading, setLoading] = useState(false);

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
                storage.removeItem.bind(otherStorage)('erp_token');
                otherStorage.removeItem('erp_token');
                otherStorage.removeItem('erp_user');
                markTokenIssued();

                try {
                    const meResponse = await api.get('/auth/me');
                    if (meResponse && meResponse.id) {
                        usuarioRecibido = meResponse;
                    }
                } catch (_) {}

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
        <AuthContext.Provider value={{ user, login, logout, isAuthenticated: !!user, loading }}>
            {children}
        </AuthContext.Provider>
    );
};

export const useAuth = () => useContext(AuthContext);