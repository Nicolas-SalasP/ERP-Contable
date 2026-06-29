import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, waitFor, cleanup } from '@testing-library/react';
import React from 'react';
import TablaAmortizacion from './TablaAmortizacion';

vi.mock('../../../Configuracion/api', () => ({
    api: { get: vi.fn() },
}));

import { api } from '../../../Configuracion/api';

const resumenBase = {
    valor_adquisicion: 1200000,
    valor_residual: 0,
    depreciacion_acumulada_real: 600000,
    porcentaje_depreciado: 50,
    valor_libro_actual: 600000,
};

const filasBase = [
    {
        numero_mes: 1,
        periodo: '2025-01',
        cuota: 100000,
        depreciacion_acumulada: 100000,
        valor_libro: 1100000,
        ya_ejecutado: true,
    },
    {
        numero_mes: 2,
        periodo: '2025-02',
        cuota: 100000,
        depreciacion_acumulada: 200000,
        valor_libro: 1000000,
        ya_ejecutado: false,
    },
];

const respuestaOk = {
    success: true,
    data: { resumen: resumenBase, filas: filasBase },
};

describe('TablaAmortizacion', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    afterEach(() => {
        cleanup();
    });

    it('no renderiza nada cuando no hay activoId', () => {
        const { container } = render(
            <TablaAmortizacion activoId={null} activoNombre="Test" onCerrar={() => {}} />
        );
        expect(container.firstChild).toBeNull();
    });

    it('muestra skeleton/spinner mientras carga', async () => {
        // Promesa que nunca resuelve para que el estado quede en cargando
        api.get.mockReturnValue(new Promise(() => {}));

        render(
            <TablaAmortizacion activoId={1} activoNombre="Computador" onCerrar={() => {}} />
        );

        expect(screen.getByText('Cargando tabla de amortización...')).toBeDefined();
    });

    it('renderiza la tabla con filas cuando la API responde OK', async () => {
        api.get.mockResolvedValue(respuestaOk);

        render(
            <TablaAmortizacion activoId={1} activoNombre="Computador" onCerrar={() => {}} />
        );

        await waitFor(() => {
            expect(screen.getByText('2025-01')).toBeDefined();
            expect(screen.getByText('2025-02')).toBeDefined();
        });

        // Encabezados de tabla presentes
        expect(screen.getByText('N° Mes')).toBeDefined();
        expect(screen.getByText('Período')).toBeDefined();
        expect(screen.getByText('Estado')).toBeDefined();
    });

    it('muestra tarjetas de resumen con valores correctos', async () => {
        api.get.mockResolvedValue(respuestaOk);

        render(
            <TablaAmortizacion activoId={1} activoNombre="Computador" onCerrar={() => {}} />
        );

        await waitFor(() => {
            expect(screen.getByText('Valor Adquisición')).toBeDefined();
            expect(screen.getByText('Valor Residual')).toBeDefined();
            // "Dep. Acumulada" aparece en la tarjeta resumen Y en el encabezado de columna
            expect(screen.getAllByText('Dep. Acumulada').length).toBeGreaterThanOrEqual(1);
            expect(screen.getByText('Valor Libro Actual')).toBeDefined();
        });

        // El porcentaje depreciado
        expect(screen.getByText('50%')).toBeDefined();
    });

    it('filas ejecutadas muestran etiqueta "Ejecutado"', async () => {
        api.get.mockResolvedValue(respuestaOk);

        render(
            <TablaAmortizacion activoId={1} activoNombre="Computador" onCerrar={() => {}} />
        );

        await waitFor(() => {
            expect(screen.getByText('Ejecutado')).toBeDefined();
        });
    });

    it('filas pendientes muestran etiqueta "Pendiente"', async () => {
        api.get.mockResolvedValue(respuestaOk);

        render(
            <TablaAmortizacion activoId={1} activoNombre="Computador" onCerrar={() => {}} />
        );

        await waitFor(() => {
            expect(screen.getByText('Pendiente')).toBeDefined();
        });
    });

    it('filas ejecutadas tienen clase de fondo verde', async () => {
        api.get.mockResolvedValue(respuestaOk);

        const { container } = render(
            <TablaAmortizacion activoId={1} activoNombre="Computador" onCerrar={() => {}} />
        );

        await waitFor(() => {
            expect(screen.getByText('2025-01')).toBeDefined();
        });

        const filasVerdes = container.querySelectorAll('tr.bg-green-50');
        expect(filasVerdes.length).toBeGreaterThan(0);
    });

    it('muestra mensaje de error cuando la API falla con excepción', async () => {
        api.get.mockRejectedValue(new Error('Network Error'));

        render(
            <TablaAmortizacion activoId={1} activoNombre="Computador" onCerrar={() => {}} />
        );

        await waitFor(() => {
            expect(screen.getByText('Error al conectar con el servidor.')).toBeDefined();
        });
    });

    it('muestra mensaje de error cuando success es false', async () => {
        api.get.mockResolvedValue({ success: false });

        render(
            <TablaAmortizacion activoId={1} activoNombre="Computador" onCerrar={() => {}} />
        );

        await waitFor(() => {
            expect(screen.getByText('No se pudo cargar la tabla de amortización.')).toBeDefined();
        });
    });

    it('renderiza estado vacío (sin filas) si la tabla viene vacía', async () => {
        api.get.mockResolvedValue({
            success: true,
            data: {
                resumen: { ...resumenBase, depreciacion_acumulada_real: 0, porcentaje_depreciado: 0, valor_libro_actual: 1200000 },
                filas: [],
            },
        });

        const { container } = render(
            <TablaAmortizacion activoId={1} activoNombre="Computador" onCerrar={() => {}} />
        );

        await waitFor(() => {
            expect(screen.getByText('Valor Adquisición')).toBeDefined();
        });

        // No hay filas en el tbody
        const filasDatos = container.querySelectorAll('tbody tr');
        expect(filasDatos.length).toBe(0);
    });

    it('muestra el nombre del activo en el header', async () => {
        api.get.mockResolvedValue(respuestaOk);

        render(
            <TablaAmortizacion activoId={5} activoNombre="Vehículo de reparto" onCerrar={() => {}} />
        );

        expect(screen.getByText('Vehículo de reparto')).toBeDefined();
    });

    it('llama a la API con el activoId correcto', async () => {
        api.get.mockResolvedValue(respuestaOk);

        render(
            <TablaAmortizacion activoId={42} activoNombre="Activo" onCerrar={() => {}} />
        );

        await waitFor(() => {
            expect(api.get).toHaveBeenCalledWith('/activos/42/amortizacion');
        });
    });
});
