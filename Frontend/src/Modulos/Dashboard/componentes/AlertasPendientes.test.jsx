import React from 'react';
import { describe, it, expect, afterEach } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';
import AlertasPendientes from './AlertasPendientes';

afterEach(() => {
    cleanup();
});

describe('AlertasPendientes — estado vacío', () => {
    it('no renderiza nada cuando alertas es null', () => {
        const { container } = render(<AlertasPendientes alertas={null} />);
        expect(container.firstChild).toBeNull();
    });

    it('no renderiza nada cuando alertas es array vacío', () => {
        const { container } = render(<AlertasPendientes alertas={[]} />);
        expect(container.firstChild).toBeNull();
    });

    it('no renderiza nada cuando alertas es undefined', () => {
        const { container } = render(<AlertasPendientes />);
        expect(container.firstChild).toBeNull();
    });
});

describe('AlertasPendientes — con datos', () => {
    const alertasEjemplo = [
        {
            titulo: 'DJ 1887 vence pronto',
            descripcion: 'Plazo el 15 del mes',
            urgencia: 'alta',
            tipo: 'dj',
        },
        {
            titulo: 'F29 pendiente',
            descripcion: 'Revisar declaración',
            urgencia: 'media',
            tipo: 'f29',
        },
    ];

    it('muestra el título "Recordatorios pendientes" cuando hay alertas', () => {
        render(<AlertasPendientes alertas={alertasEjemplo} />);
        expect(screen.getByText('Recordatorios pendientes')).toBeDefined();
    });

    it('muestra el contador con la cantidad de alertas', () => {
        render(<AlertasPendientes alertas={alertasEjemplo} />);
        expect(screen.getByText('2')).toBeDefined();
    });

    it('renderiza el título y descripción de cada alerta', () => {
        render(<AlertasPendientes alertas={alertasEjemplo} />);
        expect(screen.getByText('DJ 1887 vence pronto')).toBeDefined();
        expect(screen.getByText('Plazo el 15 del mes')).toBeDefined();
        expect(screen.getByText('F29 pendiente')).toBeDefined();
        expect(screen.getByText('Revisar declaración')).toBeDefined();
    });

    it('muestra el badge de urgencia en cada alerta', () => {
        render(<AlertasPendientes alertas={alertasEjemplo} />);
        expect(screen.getByText('alta')).toBeDefined();
        expect(screen.getByText('media')).toBeDefined();
    });

    it('usa urgencia "baja" por defecto cuando no se especifica', () => {
        const alerta = [{ titulo: 'Sin urgencia', descripcion: 'desc' }];
        render(<AlertasPendientes alertas={alerta} />);
        expect(screen.getByText('baja')).toBeDefined();
    });

    it('renderiza correctamente con una sola alerta', () => {
        const alerta = [
            {
                titulo: 'Alerta única',
                descripcion: 'Solo una',
                urgencia: 'baja',
                tipo: 'rrhh',
            },
        ];
        render(<AlertasPendientes alertas={alerta} />);
        expect(screen.getByText('Alerta única')).toBeDefined();
        expect(screen.getByText('1')).toBeDefined();
    });
});
