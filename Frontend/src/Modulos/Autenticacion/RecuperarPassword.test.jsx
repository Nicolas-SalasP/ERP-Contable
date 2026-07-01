import React from 'react';
import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, screen, fireEvent, cleanup, waitFor } from '@testing-library/react';

vi.mock('react-router-dom', () => ({
    Link: ({ children, to }) => <a href={to}>{children}</a>,
    useNavigate: () => vi.fn(),
}));

vi.mock('../../Configuracion/api', () => ({
    api: { post: vi.fn() },
}));

vi.mock('sweetalert2', () => ({
    default: { fire: vi.fn().mockResolvedValue({ isConfirmed: true }) },
}));

vi.mock('../../Configuracion/logger', () => ({
    logger: { error: vi.fn(), warn: vi.fn(), info: vi.fn() },
}));

import RecuperarPassword from './RecuperarPassword';
import { api } from '../../Configuracion/api';
import Swal from 'sweetalert2';

afterEach(cleanup);

// Helpers: la label no tiene htmlFor, usamos placeholder/role

describe('RecuperarPassword', () => {
    it('renderiza el título "Recuperar Cuenta"', () => {
        render(<RecuperarPassword />);
        expect(screen.getByText('Recuperar Cuenta')).toBeTruthy();
    });

    it('muestra el botón "Enviar Código" en paso 1', () => {
        render(<RecuperarPassword />);
        expect(screen.getByRole('button', { name: /Enviar Código/i })).toBeTruthy();
    });

    it('enlace "Volver al Login" está presente', () => {
        render(<RecuperarPassword />);
        expect(screen.getByText('Volver al Login')).toBeTruthy();
    });

    it('instrucción inicial orienta al usuario sobre el correo', () => {
        render(<RecuperarPassword />);
        expect(screen.getByText(/Ingresa tu correo para recibir un código/i)).toBeTruthy();
    });

    it('al enviar el formulario paso 1 llama api.post con email', async () => {
        api.post.mockResolvedValueOnce({ success: true });
        render(<RecuperarPassword />);

        const inputs = document.querySelectorAll('input[type="email"]');
        expect(inputs.length).toBeGreaterThan(0);
        fireEvent.change(inputs[0], { target: { value: 'test@ejemplo.cl' } });
        fireEvent.submit(inputs[0].closest('form'));

        await waitFor(() => {
            expect(api.post).toHaveBeenCalledWith('/auth/recuperar', { email: 'test@ejemplo.cl' });
        });
    });

    it('después de enviar paso 1 exitoso muestra campo de código', async () => {
        api.post.mockResolvedValueOnce({ success: true });
        render(<RecuperarPassword />);

        const inputs = document.querySelectorAll('input[type="email"]');
        fireEvent.change(inputs[0], { target: { value: 'test@ejemplo.cl' } });
        fireEvent.submit(inputs[0].closest('form'));

        await waitFor(() => {
            expect(screen.getByText(/Código de 6 dígitos/i)).toBeTruthy();
        });
    });

    it('muestra Swal de info al enviar código correctamente', async () => {
        api.post.mockResolvedValueOnce({ success: true });
        render(<RecuperarPassword />);

        const inputs = document.querySelectorAll('input[type="email"]');
        fireEvent.change(inputs[0], { target: { value: 'test@ejemplo.cl' } });
        fireEvent.submit(inputs[0].closest('form'));

        await waitFor(() => {
            expect(Swal.fire).toHaveBeenCalledWith(expect.objectContaining({ icon: 'info' }));
        });
    });

    it('muestra error Swal si api.post falla en paso 1', async () => {
        api.post.mockRejectedValueOnce(new Error('Error de red'));
        render(<RecuperarPassword />);

        const inputs = document.querySelectorAll('input[type="email"]');
        fireEvent.change(inputs[0], { target: { value: 'test@ejemplo.cl' } });
        fireEvent.submit(inputs[0].closest('form'));

        await waitFor(() => {
            expect(Swal.fire).toHaveBeenCalledWith('Error', 'Hubo un problema de conexión', 'error');
        });
    });

    it('paso 2 muestra campos de código y nueva contraseña', async () => {
        api.post.mockResolvedValueOnce({ success: true });
        render(<RecuperarPassword />);

        const emailInputs = document.querySelectorAll('input[type="email"]');
        fireEvent.change(emailInputs[0], { target: { value: 'test@ejemplo.cl' } });
        fireEvent.submit(emailInputs[0].closest('form'));

        await waitFor(() => {
            expect(screen.getByText(/Nueva Contraseña/i)).toBeTruthy();
            expect(screen.getByText(/Confirmar Contraseña/i)).toBeTruthy();
        });
    });

    it('paso 2 muestra el botón "Cambiar Contraseña"', async () => {
        api.post.mockResolvedValueOnce({ success: true });
        render(<RecuperarPassword />);

        const emailInputs = document.querySelectorAll('input[type="email"]');
        fireEvent.change(emailInputs[0], { target: { value: 'test@ejemplo.cl' } });
        fireEvent.submit(emailInputs[0].closest('form'));

        await waitFor(() => {
            expect(screen.getByRole('button', { name: /Cambiar Contraseña/i })).toBeTruthy();
        });
    });

    it('paso 2 muestra error si contraseñas no coinciden', async () => {
        api.post.mockResolvedValueOnce({ success: true });
        render(<RecuperarPassword />);

        const emailInputs = document.querySelectorAll('input[type="email"]');
        fireEvent.change(emailInputs[0], { target: { value: 'test@ejemplo.cl' } });
        fireEvent.submit(emailInputs[0].closest('form'));

        await waitFor(() => screen.getByText(/Código de 6 dígitos/i));

        // En paso 2: input texto (código), 2x password
        const allInputs = document.querySelectorAll('input');
        const codigoInput = Array.from(allInputs).find(i => i.type === 'text');
        const passInputs = document.querySelectorAll('input[type="password"]');

        if (codigoInput) fireEvent.change(codigoInput, { target: { value: '123456' } });
        if (passInputs[0]) fireEvent.change(passInputs[0], { target: { value: 'clave1' } });
        if (passInputs[1]) fireEvent.change(passInputs[1], { target: { value: 'clave2distinta' } });

        if (codigoInput) fireEvent.submit(codigoInput.closest('form'));

        await waitFor(() => {
            expect(Swal.fire).toHaveBeenCalledWith('Error', 'Las contraseñas no coinciden', 'error');
        });
    });
});
