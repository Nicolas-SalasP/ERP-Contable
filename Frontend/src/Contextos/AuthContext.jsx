import React, { createContext, useContext, useState, useEffect } from 'react';
import { api } from '../Configuracion/api';

const AuthContext = createContext(null);

export const AuthProvider = ({ children }) => {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const storedUser = localStorage.getItem('erp_user');
        const token = localStorage.getItem('erp_token');

        if (token && storedUser) {
            try {
                setUser(JSON.parse(storedUser));
            } catch (e) {
                console.error("Error al parsear usuario local", e);
                localStorage.removeItem('erp_user');
            }
        }
        setLoading(false);
    }, []);

    const login = async (email, password) => {
        try {
            const response = await api.auth.login({ email, password });
            setUser(response.user);
            return { success: true };
        } catch (error) {
            return { 
                success: false, 
                message: error.message, 
                code: error.code 
            };
        }
    };

    const logout = () => {
        api.auth.logout();
        setUser(null);
    };

    return (
        <AuthContext.Provider value={{ user, login, logout, isAuthenticated: !!user, loading }}>
            {!loading && children}
        </AuthContext.Provider>
    );
};

export const useAuth = () => useContext(AuthContext);