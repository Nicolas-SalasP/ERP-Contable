import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, cleanup } from '@testing-library/react';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import Login from './Login';
import { AuthProvider } from '../../Contextos/AuthContext';
import { api } from '../../Configuracion/api';
import { cleanTestEnv } from '../../test-utils';

vi.mock('../../Configuracion/api', () => ({
    api: { auth: { login: vi.fn() }, get: vi.fn() },
    markTokenIssued: vi.fn(),
}));

const renderLogin = () =>
    render(
        <AuthProvider>
            <MemoryRouter initialEntries={['/login']}>
                <Routes>
                    <Route path="/login" element={<Login />} />
                    <Route path="/" element={<div>PANEL PRINCIPAL</div>} />
                </Routes>
            </MemoryRouter>
        </AuthProvider>
    );

const enviarFormulario = (email = 'ana@empresa.cl', password = 'secreta123') => {
    fireEvent.change(screen.getByPlaceholderText('ejemplo@empresa.com'), { target: { value: email } });
    fireEvent.change(screen.getByPlaceholderText('••••••••'), { target: { value: password } });
    fireEvent.click(screen.getByRole('button', { name: /Ingresar al Sistema/i }));
};

describe('Login', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        cleanTestEnv();
    });
    afterEach(cleanup);

    it('login exitoso guarda el token y redirige al panel', async () => {
        api.auth.login.mockResolvedValue({ token: 'tok-123', user: { id: 1, nombre: 'Ana', empresa_id: 5, permisos: [] } });
        api.get.mockResolvedValue({ id: 1, nombre: 'Ana', empresa_id: 5, permisos: [] });

        renderLogin();
        enviarFormulario();

        expect(await screen.findByText('PANEL PRINCIPAL')).toBeDefined();
        expect(sessionStorage.getItem('erp_token')).toBe('tok-123');
        expect(api.auth.login).toHaveBeenCalledWith({ email: 'ana@empresa.cl', password: 'secreta123' });
    });

    it('credenciales incorrectas muestra el mensaje de error y no redirige', async () => {
        api.auth.login.mockRejectedValue({ status: 401, code: 'NO_AUTORIZADO', message: 'Credenciales incorrectas.' });

        renderLogin();
        enviarFormulario('mala@empresa.cl', 'erronea');

        expect(await screen.findByText(/Credenciales incorrectas/i)).toBeDefined();
        expect(screen.queryByText('PANEL PRINCIPAL')).toBeNull();
        expect(sessionStorage.getItem('erp_token')).toBeNull();
    });

    it('empresa suspendida (403) muestra el mensaje especifico', async () => {
        api.auth.login.mockRejectedValue({ status: 403, code: 'PROHIBIDO', message: 'La empresa se encuentra suspendida. Contacte al administrador.' });

        renderLogin();
        enviarFormulario();

        expect(await screen.findByText(/La empresa se encuentra suspendida/i)).toBeDefined();
        expect(screen.queryByText('PANEL PRINCIPAL')).toBeNull();
    });

    it('usuario bloqueado (403) muestra el mensaje especifico', async () => {
        api.auth.login.mockRejectedValue({ status: 403, code: 'PROHIBIDO', message: 'Usuario bloqueado temporalmente.' });

        renderLogin();
        enviarFormulario();

        expect(await screen.findByText(/Usuario bloqueado temporalmente/i)).toBeDefined();
        expect(screen.queryByText('PANEL PRINCIPAL')).toBeNull();
    });

    it('error de red muestra el mensaje amistoso mapeado por el formulario', async () => {
        api.auth.login.mockRejectedValue({ status: 0, code: 'ERROR_RED', message: 'Sin conexion con el servidor. Revisa tu internet.' });

        renderLogin();
        enviarFormulario();

        expect(await screen.findByText(/No hay conexión con el servidor/i)).toBeDefined();
    });
});
