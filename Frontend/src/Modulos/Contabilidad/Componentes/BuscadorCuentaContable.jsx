import React, { useState, useEffect, useRef } from 'react';
import { api } from '../../../Configuracion/api';
import { Search } from 'lucide-react';
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

    // Refs para leer el valor/callback más reciente dentro del effect (evita stale closures).
    const activeValueRef = useRef(activeValue);
    const activeOnChangeRef = useRef(activeOnChange);
    useEffect(() => {
        activeValueRef.current = activeValue;
        activeOnChangeRef.current = activeOnChange;
    });

    useEffect(() => {
        if (cuentas.length === 0) {
            const fetchCuentas = async () => {
                setCargando(true);
                try {
                    const res = await api.get('/contabilidad/plan-cuentas');
                    if (res.success) {
                        const lista = res.data.filter(c => c.imputable);
                        setLocalCuentas(lista);

                        if (!activeValueRef.current && activeOnChangeRef.current) {
                            const cuentaPuente = lista.find(c =>
                                c.codigo === '690199' ||
                                c.nombre.toLowerCase().includes('puente') ||
                                c.nombre.toLowerCase().includes('por clasificar')
                            );
                            const final = cuentaPuente || lista[0];
                            if (final) {
                                activeOnChangeRef.current(final.codigo);
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
                            : 'border-slate-300 dark:border-slate-600 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-700'
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
                        <Search size={20} strokeWidth={1.75} />
                    )}
                </div>
            </div>

            {abierto && (
                <div className="absolute z-50 w-full mt-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-2xl max-h-56 overflow-y-auto rounded-lg animate-fade-in custom-scrollbar">
                    {filtradas.length > 0 ? filtradas.map(c => (
                        <div
                            key={c.codigo}
                            className="px-4 py-3 hover:bg-blue-50 cursor-pointer text-sm border-b border-slate-50 dark:border-slate-800 last:border-0 transition-colors flex flex-col"
                            role="option"
                            aria-selected="false"
                            tabIndex={0}
                            onClick={() => {
                                if (activeOnChange) activeOnChange(c.codigo);
                                setBusqueda(`${c.codigo} - ${c.nombre}`);
                                setAbierto(false);
                            }}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter' || e.key === ' ') {
                                    e.preventDefault();
                                    if (activeOnChange) activeOnChange(c.codigo);
                                    setBusqueda(`${c.codigo} - ${c.nombre}`);
                                    setAbierto(false);
                                }
                            }}
                        >
                            <span className="font-mono font-bold text-blue-600">{c.codigo}</span>
                            <span className="text-slate-700 dark:text-slate-300 font-medium">{c.nombre}</span>
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
