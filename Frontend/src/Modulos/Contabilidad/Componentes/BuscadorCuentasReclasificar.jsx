import React, { useState, useEffect, useRef } from 'react';
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
                            : 'border-blue-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-slate-700'
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
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>

            {abierto && (
                <div className="absolute z-50 w-full mt-1 bg-white border border-slate-200 shadow-2xl max-h-56 overflow-y-auto rounded-lg rounded-tl-none animate-fade-in custom-scrollbar">
                    {filtradas.length > 0 ? (
                        filtradas.map(c => (
                            <div
                                key={c.codigo}
                                className="px-4 py-3 hover:bg-blue-50 cursor-pointer text-sm border-b border-slate-50 last:border-0 transition-colors flex flex-col"
                                onClick={() => {
                                    onChange(c.codigo);
                                    setBusqueda(`${c.codigo} - ${c.nombre}`);
                                    setAbierto(false);
                                }}
                            >
                                <span className="font-mono font-bold text-blue-600">{c.codigo}</span>
                                <span className="text-slate-700 font-medium">{c.nombre}</span>
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
