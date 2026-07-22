import React, { useState, useEffect, useRef } from 'react';
import { Search } from 'lucide-react';
const BuscadorCuentasReclasificar = ({ cuentas, valor, onChange }) => {
    const [busqueda, setBusqueda] = useState('');
    const [abierto, setAbierto] = useState(false);
    const ref = useRef(null);

    useEffect(() => {
        const handleClickOutside = (e) => {
            if (ref.current && !ref.current.contains(e.target)) setAbierto(false);
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    useEffect(() => {
        if (valor && !abierto) {
            const cuenta = cuentas.find(c => c.codigo === valor);
            if (cuenta) setBusqueda(`${cuenta.codigo} - ${cuenta.nombre}`);
        }
    }, [valor, cuentas, abierto]);

    const filtradas = cuentas.filter(c =>
        c.codigo.includes(busqueda) ||
        c.nombre.toLowerCase().includes(busqueda.toLowerCase())
    );

    return (
        <div className="relative w-full" ref={ref}>
            <div className="relative">
                <input
                    type="text"
                    className={`w-full border p-2.5 rounded-lg text-sm outline-none transition-all font-bold pr-8 ${
                        valor
                            ? 'border-emerald-500 bg-emerald-50 text-emerald-800'
                            : 'border-blue-300 dark:border-slate-600 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-slate-700 dark:text-slate-300'
                    }`}
                    placeholder="Escriba código o nombre de la cuenta..."
                    value={busqueda}
                    onChange={(e) => {
                        setBusqueda(e.target.value);
                        setAbierto(true);
                        if (valor) onChange('');
                    }}
                    onFocus={() => {
                        setAbierto(true);
                        setBusqueda('');
                    }}
                />
                <div className="absolute right-3 top-3 text-slate-400 pointer-events-none">
                    <Search size={20} strokeWidth={1.75} />
                </div>
            </div>

            {abierto && (
                <div className="absolute z-50 w-full mt-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-2xl max-h-56 overflow-y-auto rounded-lg rounded-tl-none animate-fade-in custom-scrollbar">
                    {filtradas.length > 0 ? (
                        filtradas.map(c => (
                            <div
                                key={c.codigo}
                                className="px-4 py-3 hover:bg-blue-50 cursor-pointer text-sm border-b border-slate-50 dark:border-slate-800 last:border-0 transition-colors flex flex-col"
                                role="option"
                                aria-selected="false"
                                tabIndex={0}
                                onClick={() => {
                                    onChange(c.codigo);
                                    setBusqueda(`${c.codigo} - ${c.nombre}`);
                                    setAbierto(false);
                                }}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter' || e.key === ' ') {
                                        e.preventDefault();
                                        onChange(c.codigo);
                                        setBusqueda(`${c.codigo} - ${c.nombre}`);
                                        setAbierto(false);
                                    }
                                }}
                            >
                                <span className="font-mono font-bold text-blue-600">{c.codigo}</span>
                                <span className="text-slate-700 dark:text-slate-300 font-medium">{c.nombre}</span>
                            </div>
                        ))
                    ) : (
                        <div className="px-4 py-3 text-slate-400 text-sm italic text-center">
                            No se encontraron cuentas
                        </div>
                    )}
                </div>
            )}
        </div>
    );
};

export default BuscadorCuentasReclasificar;
