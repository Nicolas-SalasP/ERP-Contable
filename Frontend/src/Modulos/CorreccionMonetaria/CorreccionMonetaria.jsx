import React, { useState, useEffect } from 'react';
import AyudaModulo from '../../Componentes/AyudaModulo';
import TabIndicesIpc from './Componentes/TabIndicesIpc';
import TabConfiguracion from './Componentes/TabConfiguracion';
import TabSimulador from './Componentes/TabSimulador';
import TabHistorial from './Componentes/TabHistorial';
import { api } from '../../Configuracion/api';

const TABS = [
    { id: 'indices',   label: 'Índices IPC',    icon: 'fas fa-chart-line' },
    { id: 'config',    label: 'Configuración',  icon: 'fas fa-sliders-h' },
    { id: 'simulador', label: 'Simulador',       icon: 'fas fa-calculator' },
    { id: 'historial', label: 'Historial',       icon: 'fas fa-history' },
];

const CorreccionMonetaria = () => {
    const [tab, setTab]           = useState('indices');
    const [config, setConfig]     = useState(null);
    const [loadingConfig, setLoadingConfig] = useState(true);
    const anioActual = new Date().getFullYear();

    useEffect(() => {
        cargarConfig();
    }, []);

    const cargarConfig = async () => {
        setLoadingConfig(true);
        try {
            const res = await api.get('/correccion-monetaria/configuracion');
            if (res.success) setConfig(res.data);
        } catch (_) {
        } finally {
            setLoadingConfig(false);
        }
    };

    return (
        <div className="max-w-7xl mx-auto p-4 md:p-6 lg:p-8 animate-fade-in pb-16">
            <div className="flex flex-col md:flex-row justify-between items-start md:items-end mb-8 gap-4">
                <div>
                    <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-violet-100 text-violet-700 text-[10px] font-black uppercase tracking-widest border border-violet-200 mb-3">
                        <i className="fas fa-balance-scale text-[9px]"></i> Art. 41 LIR
                    </span>
                    <div className="flex items-center gap-3">
                        <h1 className="text-3xl md:text-4xl font-black text-slate-900 tracking-tight">
                            Corrección Monetaria
                        </h1>
                        <AyudaModulo moduloId="correccionMonetaria" size={28} />
                    </div>
                    <p className="text-slate-500 font-medium mt-1">
                        Revalorización de activos y patrimonio según variación IPC.
                    </p>
                </div>

                {!loadingConfig && config && (
                    <div className="flex items-center gap-3 flex-wrap">
                        <span className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold border ${
                            config.aplica_cm
                                ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                : 'bg-amber-50 text-amber-700 border-amber-200'
                        }`}>
                            <i className={`fas fa-circle text-[8px] ${config.aplica_cm ? 'text-emerald-500' : 'text-amber-500'}`}></i>
                            {config.aplica_cm ? 'Activa' : 'No aplica (14D8)'}
                        </span>
                        <span className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-slate-100 text-slate-600 border border-slate-200">
                            <i className="fas fa-calendar-alt text-[10px]"></i>
                            Modalidad: {config.modalidad === 'mensual' ? 'Mensual' : `Anual (${config.nombre_mes_cierre})`}
                        </span>
                    </div>
                )}
            </div>

            <div className="bg-slate-100 p-1 rounded-xl inline-flex gap-1 mb-6 flex-wrap">
                {TABS.map(t => (
                    <button
                        key={t.id}
                        onClick={() => setTab(t.id)}
                        className={`flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-bold transition-all ${
                            tab === t.id
                                ? 'bg-white text-violet-700 shadow-sm'
                                : 'text-slate-500 hover:text-slate-700'
                        }`}
                    >
                        <i className={`${t.icon} text-xs`}></i>
                        {t.label}
                    </button>
                ))}
            </div>

            {tab === 'indices' && (
                <TabIndicesIpc anioInicial={anioActual} />
            )}
            {tab === 'config' && (
                <TabConfiguracion config={config} onConfigChange={cargarConfig} />
            )}
            {tab === 'simulador' && (
                <TabSimulador config={config} />
            )}
            {tab === 'historial' && (
                <TabHistorial anioInicial={anioActual} />
            )}
        </div>
    );
};

export default CorreccionMonetaria;
