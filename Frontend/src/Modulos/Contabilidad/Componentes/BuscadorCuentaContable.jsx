import React, { useState, useEffect, useRef } from 'react';
import { api } from '../../../Configuracion/api';

const BuscadorCuentaContable = ({ cuentaSeleccionada, setCuentaSeleccionada }) => {
    const [cuentas, setCuentas] = useState([]);
    const [busqueda, setBusqueda] = useState('');
    const [abierto, setAbierto] = useState(false);
    const [cargando, setCargando] = useState(true);

    const dropdownRef = useRef(null);

    useEffect(() => {
        cargarPlanDeCuentas();

        const handleClickFuera = (event) => {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
                setAbierto(false);
            }
        };
        document.addEventListener('mousedown', handleClickFuera);
        return () => document.removeEventListener('mousedown', handleClickFuera);
    }, []);

    const cargarPlanDeCuentas = async () => {
        try {
            setCargando(true);
            const res = await api.get('/contabilidad/plan-cuentas');

            if (res.success) {
                const listaCuentas = res.data.filter(c => c.imputable);
                setCuentas(listaCuentas);

                if (!cuentaSeleccionada) {
                    const cuentaPuente = listaCuentas.find(c => 
                        c.codigo === '690199' || 
                        c.nombre.toLowerCase().includes('puente') ||
                        c.nombre.toLowerCase().includes('por clasificar')
                    );
                    
                    const cuentaFinal = cuentaPuente || listaCuentas[0];

                    if (cuentaFinal) {
                        setCuentaSeleccionada(cuentaFinal.codigo);
                        setBusqueda(`${cuentaFinal.codigo} - ${cuentaFinal.nombre}`);
                    }
                }
            }
        } catch (error) {
            console.error("Error cargando plan de cuentas:", error);
        } finally {
            setCargando(false);
        }
    };

    useEffect(() => {
        if (!abierto && cuentaSeleccionada && cuentas.length > 0) {
            const cuenta = cuentas.find(x => x.codigo === cuentaSeleccionada);
            if (cuenta) {
                setBusqueda(`${cuenta.codigo} - ${cuenta.nombre}`);
            }
        }
    }, [abierto, cuentaSeleccionada, cuentas]);

    const cuentasFiltradas = cuentas.filter(c =>
        `${c.codigo} ${c.nombre}`.toLowerCase().includes(busqueda.toLowerCase())
    );

    const seleccionarCuenta = (cuenta) => {
        setCuentaSeleccionada(cuenta.codigo);
        setBusqueda(`${cuenta.codigo} - ${cuenta.nombre}`);
        setAbierto(false);
    };

    return (
        <div className="relative w-full" ref={dropdownRef}>
            <div className="relative">
                <input
                    type="text"
                    value={busqueda}
                    onChange={(e) => {
                        setBusqueda(e.target.value);
                        if (!abierto) setAbierto(true);
                    }}
                    onClick={() => setAbierto(true)}
                    placeholder={cargando ? "Cargando cuentas..." : "Ej: 690199 - Compras..."}
                    disabled={cargando}
                    className="w-full border border-slate-300 rounded-lg py-3 pl-3 pr-10 focus:ring-2 focus:ring-blue-500 outline-none transition-all font-semibold text-slate-700 bg-white"
                />
                <div className="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-slate-400">
                    {cargando ? <i className="fas fa-circle-notch fa-spin"></i> : <i className="fas fa-search"></i>}
                </div>
            </div>

            {abierto && (
                <div className="absolute z-50 w-full mt-1 bg-white border border-slate-200 rounded-lg shadow-2xl max-h-48 overflow-y-auto custom-scrollbar">
                    {cuentasFiltradas.length === 0 ? (
                        <div className="p-4 text-center text-slate-500 text-sm">No se encontraron cuentas.</div>
                    ) : (
                        cuentasFiltradas.map(cuenta => (
                            <div
                                key={cuenta.id}
                                onClick={() => seleccionarCuenta(cuenta)}
                                className={`px-4 py-3 cursor-pointer transition-colors flex flex-col group border-b last:border-b-0 border-slate-100 ${
                                    cuentaSeleccionada === cuenta.codigo
                                        ? 'bg-blue-50 border-l-4 border-blue-500'
                                        : 'hover:bg-slate-50 border-l-4 border-transparent'
                                }`}
                            >
                                <span className={`text-sm font-bold ${cuentaSeleccionada === cuenta.codigo ? 'text-blue-700' : 'text-slate-700'}`}>
                                    {cuenta.codigo} - {cuenta.nombre}
                                </span>
                            </div>
                        ))
                    )}
                </div>
            )}
        </div>
    );
};

export default BuscadorCuentaContable;