import React, { createContext, useContext, useState } from 'react';
import { api } from '../Configuracion/api';

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
                const usuarioRecibido = response.user;

                setUser(usuarioRecibido);
                
                if (remember) {
                    localStorage.setItem('erp_token', tokenRecibido);
                    localStorage.setItem('erp_user', JSON.stringify(usuarioRecibido));
                    sessionStorage.removeItem('erp_token');
                    sessionStorage.removeItem('erp_user');
                } else {
                    sessionStorage.setItem('erp_token', tokenRecibido);
                    sessionStorage.setItem('erp_user', JSON.stringify(usuarioRecibido));
                    localStorage.removeItem('erp_token');
                    localStorage.removeItem('erp_user');
                }

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

    const logout = () => {
        localStorage.removeItem('erp_token');
        localStorage.removeItem('erp_user');
        sessionStorage.removeItem('erp_token');
        sessionStorage.removeItem('erp_user');

        setUser(null);
    };

    return (
        <AuthContext.Provider value={{ user, login, logout, isAuthenticated: !!user, loading }}>
            {children}
        </AuthContext.Provider>
    );
};

export const useAuth = () => useContext(AuthContext);