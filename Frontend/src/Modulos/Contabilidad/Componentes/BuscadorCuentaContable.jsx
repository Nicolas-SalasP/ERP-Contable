import React, { useState, useEffect, useRef } from 'react';
import { api } from '../../../Configuracion/api';

import { logger } from '../../../Configuracion/logger';
const BuscadorCuentaContable = ({
    cuentas = [],
    valor,
    onChange,
    cuentaSeleccionada,
    setCuentaSeleccionada
}) => {
    const [localCuentas, setLocalCuentas] = useState([]);
    const [busqueda, setBusqueda] = useState('');
    const [abierto, setAbierto] = useState(false);
    const [cargando, setCargando] = useState(false);
    const ref = useRef(null);
    const activeValue = valor !== undefined ? valor : cuentaSeleccionada;
    const activeOnChange = onChange || setCuentaSeleccionada;
    const activeCuentas = cuentas.length > 0 ? cuentas : localCuentas;

    useEffect(() => {
        if (cuentas.length === 0) {
            const fetchCuentas = async () => {
                setCargando(true);
                try {
                    const res = await api.get('/contabilidad/plan-cuentas');
                    if (res.success) {
                        const lista = res.data.filter(c => c.imputable);
                        setLocalCuentas(lista);

                        if (!activeValue && activeOnChange) {
                            const cuentaPuente = lista.find(c =>
                                c.codigo === '690199' ||
                                c.nombre.toLowerCase().includes('puente') ||
                                c.nombre.toLowerCase().includes('por clasificar')
                            );
                            const final = cuentaPuente || lista[0];
                            if (final) {
                                activeOnChange(final.codigo);
                                setBusqueda(`${final.codigo} - ${final.nombre}`);
                            }
                        }
                    }
                } catch (error) {
                    logger.error("Error cargando plan de cuentas:", error);
                } finally {
                    setCargando(false);
                }
            };
            fetchCuentas();
        }
    }, [cuentas.length]);

    useEffect(() => {
        const handleClickOutside = (e) => {
            if (ref.current && !ref.current.contains(e.target)) setAbierto(false);
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    useEffect(() => {
        if (activeValue && !abierto && activeCuentas.length > 0) {
            const cuenta = activeCuentas.find(c => c.codigo === activeValue);
            if (cuenta) setBusqueda(`${cuenta.codigo} - ${cuenta.nombre}`);
        }
    }, [activeValue, activeCuentas, abierto]);

    const filtradas = activeCuentas.filter(c =>
        `${c.codigo} ${c.nombre}`.toLowerCase().includes(busqueda.toLowerCase())
    );

    return (
        <div className="relative w-full" ref={ref}>
            <div className="relative">
                <input
                    type="text"
                    className={`w-full border p-2.5 md:py-3 md:pl-3 md:pr-10 rounded-lg text-sm outline-none transition-all font-bold ${activeValue
                            ? 'border-emerald-500 bg-emerald-50 text-emerald-800'
                            : 'border-slate-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-slate-700 bg-white'
                        }`}
                    placeholder={cargando ? "Cargando cuentas..." : "Buscar código o nombre..."}
                    value={busqueda}
                    disabled={cargando}
                    onChange={(e) => {
                        setBusqueda(e.target.value);
                        setAbierto(true);
                        if (activeValue && activeOnChange) activeOnChange('');
                    }}
                    onFocus={() => {
                        setAbierto(true);
                        setBusqueda('');
                    }}
                />
                <div className="absolute right-3 top-3 text-slate-400 pointer-events-none">
                    {cargando ? (
                        <i className="fas fa-circle-notch fa-spin"></i>
                    ) : (
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    )}
                </div>
            </div>

            {abierto && (
                <div className="absolute z-50 w-full mt-1 bg-white border border-slate-200 shadow-2xl max-h-56 overflow-y-auto rounded-lg animate-fade-in custom-scrollbar">
                    {filtradas.length > 0 ? filtradas.map(c => (
                        <div
                            key={c.codigo}
                            className="px-4 py-3 hover:bg-blue-50 cursor-pointer text-sm border-b border-slate-50 last:border-0 transition-colors flex flex-col"
                            onClick={() => {
                                if (activeOnChange) activeOnChange(c.codigo); // Emite el código al padre
                                setBusqueda(`${c.codigo} - ${c.nombre}`);
                                setAbierto(false);
                            }}
                        >
                            <span className="font-mono font-bold text-blue-600">{c.codigo}</span>
                            <span className="text-slate-700 font-medium">{c.nombre}</span>
                        </div>
                    )) : (
                        <div className="px-4 py-3 text-slate-400 text-sm italic text-center">
                            No se encontraron cuentas
                        </div>
                    )}
                </div>
            )}
        </div>
    );
};

export default BuscadorCuentaContable;