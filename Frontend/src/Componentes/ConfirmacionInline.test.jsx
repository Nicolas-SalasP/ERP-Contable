import React from 'react';
import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, screen, fireEvent, cleanup, act } from '@testing-library/react';
import { BotonEliminar, useConfirmacion } from './ConfirmacionInline';

afterEach(cleanup);

describe('BotonEliminar — estado inicial', () => {
    it('renderiza el label inicial por defecto "Eliminar"', () => {
        render(<BotonEliminar onConfirmar={vi.fn()} />);
        expect(screen.getByText('Eliminar')).toBeTruthy();
    });

    it('renderiza label inicial personalizado cuando se pasa labelInicial', () => {
        render(<BotonEliminar onConfirmar={vi.fn()} labelInicial="Borrar registro" />);
        expect(screen.getByText('Borrar registro')).toBeTruthy();
    });

    it('el boton inicial esta deshabilitado cuando disabled=true', () => {
        render(<BotonEliminar onConfirmar={vi.fn()} disabled={true} />);
        const boton = screen.getByRole('button');
        expect(boton.disabled).toBe(true);
    });

    it('acepta className adicional en el boton inicial', () => {
        const { container } = render(
            <BotonEliminar onConfirmar={vi.fn()} className="mi-clase" />
        );
        expect(container.querySelector('button').className).toContain('mi-clase');
    });
});

describe('BotonEliminar — flujo de confirmacion', () => {
    it('click en el boton inicial muestra el estado de confirmacion con el texto de confirmacion', () => {
        render(<BotonEliminar onConfirmar={vi.fn()} />);
        fireEvent.click(screen.getByRole('button'));
        expect(screen.getByText('¿Confirmar?')).toBeTruthy();
    });

    it('muestra labelConfirmar personalizado tras primer click', () => {
        render(<BotonEliminar onConfirmar={vi.fn()} labelConfirmar="¿Seguro?" />);
        fireEvent.click(screen.getByRole('button'));
        expect(screen.getByText('¿Seguro?')).toBeTruthy();
    });

    it('click en "Sí" invoca onConfirmar', async () => {
        const onConfirmar = vi.fn().mockResolvedValue(undefined);
        render(<BotonEliminar onConfirmar={onConfirmar} />);
        fireEvent.click(screen.getByRole('button'));
        await act(async () => { fireEvent.click(screen.getByText('Sí')); });
        expect(onConfirmar).toHaveBeenCalledTimes(1);
    });

    it('click en "Sí" vuelve al estado inicial tras ejecutar onConfirmar', async () => {
        const onConfirmar = vi.fn().mockResolvedValue(undefined);
        render(<BotonEliminar onConfirmar={onConfirmar} />);
        fireEvent.click(screen.getByRole('button'));
        await act(async () => { fireEvent.click(screen.getByText('Sí')); });
        expect(screen.getByText('Eliminar')).toBeTruthy();
    });

    it('click en "No" vuelve al estado inicial sin llamar onConfirmar', () => {
        const onConfirmar = vi.fn();
        render(<BotonEliminar onConfirmar={onConfirmar} />);
        fireEvent.click(screen.getByRole('button'));
        fireEvent.click(screen.getByText('No'));
        expect(onConfirmar).not.toHaveBeenCalled();
        expect(screen.getByText('Eliminar')).toBeTruthy();
    });
});

// Hook de confirmacion inline por ID

const ConsumidorConfirmacion = ({ id, onEjecutar }) => {
    const { pedir, cancelar, esPendiente, confirmar } = useConfirmacion();
    return (
        <div>
            <span data-testid="pendiente">{String(esPendiente(id))}</span>
            <button onClick={() => pedir(id)}>pedir</button>
            <button onClick={() => cancelar()}>cancelar</button>
            <button onClick={() => confirmar(onEjecutar)}>confirmar</button>
        </div>
    );
};

describe('useConfirmacion', () => {
    it('esPendiente retorna false en el estado inicial', () => {
        render(<ConsumidorConfirmacion id="fila-1" onEjecutar={vi.fn()} />);
        expect(screen.getByTestId('pendiente').textContent).toBe('false');
    });

    it('pedir(id) hace que esPendiente(id) sea true', () => {
        render(<ConsumidorConfirmacion id="fila-1" onEjecutar={vi.fn()} />);
        fireEvent.click(screen.getByText('pedir'));
        expect(screen.getByTestId('pendiente').textContent).toBe('true');
    });

    it('cancelar() devuelve esPendiente a false', () => {
        render(<ConsumidorConfirmacion id="fila-1" onEjecutar={vi.fn()} />);
        fireEvent.click(screen.getByText('pedir'));
        fireEvent.click(screen.getByText('cancelar'));
        expect(screen.getByTestId('pendiente').textContent).toBe('false');
    });

    it('confirmar() invoca la funcion y restablece el estado', async () => {
        const fn = vi.fn().mockResolvedValue(undefined);
        render(<ConsumidorConfirmacion id="fila-1" onEjecutar={fn} />);
        fireEvent.click(screen.getByText('pedir'));
        await act(async () => { fireEvent.click(screen.getByText('confirmar')); });
        expect(fn).toHaveBeenCalledTimes(1);
        expect(screen.getByTestId('pendiente').textContent).toBe('false');
    });
});
