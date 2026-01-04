import React, { useState, useEffect } from 'react';

// Helpers de formato
const formatMoney = (amount) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(amount);
const formatDate = (dateString) => new Date(dateString).toLocaleDateString('es-CL');

const LibroMayor = () => {
    const [activeTab, setActiveTab] = useState('diario'); // 'diario' o 'puc'
    const [asientos, setAsientos] = useState([]);
    const [planCuentas, setPlanCuentas] = useState([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        loadData();
    }, [activeTab]);

    const loadData = () => {
        setLoading(true);
        const endpoint = activeTab === 'diario'
            ? 'http://localhost/ERP-Contable/Backend/Public/api/contabilidad/libro-diario'
            : 'http://localhost/ERP-Contable/Backend/Public/api/contabilidad/plan-cuentas';

        fetch(endpoint)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (activeTab === 'diario') setAsientos(data.data);
                    else setPlanCuentas(data.data);
                }
            })
            .finally(() => setLoading(false));
    };

    // Agrupar asientos por Documento (Smart ID) para visualizarlos como bloques
    const groupAsientos = () => {
        const grouped = {};
        asientos.forEach(row => {
            const key = row.doc_referencia || 'SIN-REF';
            if (!grouped[key]) grouped[key] = [];
            grouped[key].push(row);
        });
        return grouped;
    };

    return (
        <div className="max-w-7xl mx-auto p-6 font-sans text-slate-800">

            <div className="flex justify-between items-center mb-6">
                <h1 className="text-3xl font-bold text-slate-900">Libros Contables</h1>
                <div className="flex bg-white rounded-lg shadow-sm border overflow-hidden">
                    <button
                        onClick={() => setActiveTab('diario')}
                        className={`px-6 py-2 text-sm font-bold transition ${activeTab === 'diario' ? 'bg-slate-800 text-white' : 'hover:bg-slate-50'}`}
                    >
                        Libro Diario
                    </button>
                    <button
                        onClick={() => setActiveTab('puc')}
                        className={`px-6 py-2 text-sm font-bold transition ${activeTab === 'puc' ? 'bg-slate-800 text-white' : 'hover:bg-slate-50'}`}
                    >
                        Plan de Cuentas (PUC)
                    </button>
                </div>
            </div>

            {/* VISTA 1: LIBRO DIARIO */}
            {activeTab === 'diario' && (
                <div className="space-y-6">
                    {loading ? <p className="text-center p-10 text-slate-400">Cargando movimientos...</p> :
                        Object.entries(groupAsientos()).map(([docRef, rows]) => (
                            <div key={docRef} className="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden animate-fade-in-up">
                                {/* Cabecera del Asiento */}
                                <div className="bg-slate-50 p-4 border-b flex justify-between items-center">
                                    <div>
                                        <span className="text-xs font-bold text-emerald-600 bg-emerald-50 px-2 py-1 rounded border border-emerald-200 uppercase tracking-wide">
                                            Ref: {docRef}
                                        </span>
                                        <span className="ml-3 font-bold text-slate-700">{rows[0].entidad || 'Movimiento Interno'}</span>
                                        <span className="ml-3 text-xs text-slate-400 font-mono">Fecha: {formatDate(rows[0].fecha_emision)}</span>
                                    </div>
                                    <div className="text-xs text-slate-400">
                                        {rows[0].glosa}
                                    </div>
                                </div>

                                {/* Tabla del Asiento */}
                                <table className="min-w-full divide-y divide-gray-100">
                                    <thead className="bg-white text-xs uppercase text-slate-400">
                                        <tr>
                                            <th className="px-6 py-2 text-left w-24">Código</th>
                                            <th className="px-6 py-2 text-left">Cuenta Contable</th>
                                            <th className="px-6 py-2 text-right w-32">Debe</th>
                                            <th className="px-6 py-2 text-right w-32">Haber</th>
                                        </tr>
                                    </thead>
                                    <tbody className="text-sm">
                                        {rows.map((row, idx) => (
                                            <tr key={idx} className="hover:bg-slate-50/50">
                                                <td className="px-6 py-2 font-mono text-slate-500">{row.cuenta_codigo}</td>
                                                <td className="px-6 py-2 font-medium text-slate-700">{row.cuenta_nombre}</td>
                                                <td className="px-6 py-2 text-right font-mono text-emerald-700">
                                                    {parseFloat(row.debe) > 0 ? formatMoney(row.debe) : '-'}
                                                </td>
                                                <td className="px-6 py-2 text-right font-mono text-slate-700">
                                                    {parseFloat(row.haber) > 0 ? formatMoney(row.haber) : '-'}
                                                </td>
                                            </tr>
                                        ))}
                                        {/* Totales del Asiento */}
                                        <tr className="bg-slate-50 font-bold text-xs border-t">
                                            <td colSpan="2" className="px-6 py-2 text-right uppercase text-slate-400">Cuadre Asiento</td>
                                            <td className="px-6 py-2 text-right text-emerald-700">
                                                {formatMoney(rows.reduce((acc, curr) => acc + parseFloat(curr.debe), 0))}
                                            </td>
                                            <td className="px-6 py-2 text-right text-slate-700">
                                                {formatMoney(rows.reduce((acc, curr) => acc + parseFloat(curr.haber), 0))}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        ))}
                </div>
            )}

            {/* VISTA 2: PLAN DE CUENTAS */}
            {activeTab === 'puc' && (
                <div className="bg-white rounded-lg shadow border border-slate-200 overflow-hidden">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-slate-100">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase">Código</th>
                                <th className="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase">Nombre de la Cuenta</th>
                                <th className="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase">Clasificación</th>
                                <th className="px-6 py-3 text-center text-xs font-bold text-slate-500 uppercase">Imputable</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {loading ? <tr><td colSpan="4" className="p-4 text-center">Cargando...</td></tr> :
                                planCuentas.map(cta => (
                                    <tr key={cta.id} className={cta.imputable == 0 ? 'bg-slate-50 font-bold' : 'hover:bg-white'}>
                                        <td className="px-6 py-3 font-mono text-sm text-slate-600">{cta.codigo}</td>
                                        <td className="px-6 py-3 text-sm text-slate-800" style={{ paddingLeft: `${cta.nivel * 10}px` }}>
                                            {cta.nombre}
                                        </td>
                                        <td className="px-6 py-3 text-xs">
                                            <span className={`px-2 py-1 rounded-full font-bold 
                                            ${cta.tipo === 'ACTIVO' ? 'bg-blue-100 text-blue-700' :
                                                    cta.tipo === 'PASIVO' ? 'bg-red-100 text-red-700' :
                                                        cta.tipo === 'GASTO' ? 'bg-orange-100 text-orange-700' :
                                                            cta.tipo === 'INGRESO' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'}`}>
                                                {cta.tipo}
                                            </span>
                                        </td>
                                        <td className="px-6 py-3 text-center text-sm">
                                            {cta.imputable == 1 ? '✅' : ''}
                                        </td>
                                    </tr>
                                ))}
                        </tbody>
                    </table>
                </div>
            )}

        </div>
    );
};

export default LibroMayor;