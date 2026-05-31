import React from 'react';
import Swal from 'sweetalert2';
import EstadoCarga from '../../../Componentes/EstadoCarga';
import FormularioConfiguracionSii from '../Componentes/FormularioConfiguracionSii';
import useSiiConfiguracion from '../Hooks/useSiiConfiguracion';

const ConfiguracionSii = () => {
    const { configuracion, cargando, guardando, actualizar } = useSiiConfiguracion();

    const handleSubmit = async (payload) => {
        const resultado = await actualizar(payload);
        if (resultado) {
            await Swal.fire({
                icon: 'success',
                title: 'Configuracion guardada',
                text: 'Los datos SII de la empresa fueron actualizados.',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2200,
            });
        }
    };

    return (
        <div className="max-w-5xl mx-auto p-6 md:p-8">
            <header className="mb-6">
                <h1 className="text-2xl md:text-3xl font-black text-slate-900 flex items-center gap-3">
                    <i className="fas fa-file-invoice-dollar text-emerald-600" />
                    Configuracion SII
                </h1>
                <p className="text-sm text-slate-500 mt-1">
                    Datos exigidos por el SII para la emision de Documentos Tributarios Electronicos.
                </p>
            </header>

            <EstadoCarga
                cargando={cargando}
                mensajeCargando="Cargando configuracion SII..."
                color="emerald"
                tamano="compacto"
            >
                <FormularioConfiguracionSii
                    configuracion={configuracion}
                    onSubmit={handleSubmit}
                    guardando={guardando}
                />
            </EstadoCarga>
        </div>
    );
};

export default ConfiguracionSii;
