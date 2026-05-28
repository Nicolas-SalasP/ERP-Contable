import React, { useEffect, useMemo, useState } from 'react';
import Swal from 'sweetalert2';
import inventarioApi from '../Servicios/inventarioApi';
import { useInventarioData } from '../Hooks/useInventarioData';
import {
    EmptyState,
    ErrorNotice,
    Field,
    LoadingState,
    PageHeader,
    Panel,
    PrimaryButton,
    SecondaryButton,
    TableShell,
    Td,
    Th,
} from '../Componentes/InventarioUI';

const initialForm = {
    codigo: '',
    nombre: '',
    descripcion: '',
    ubicacion: '',
    activa: true,
};

const esBodegaActiva = (bodega) => {
    return bodega?.activa === true
        || bodega?.activa === 1
        || bodega?.activa === '1'
        || bodega?.activo === true
        || bodega?.activo === 1
        || bodega?.activo === '1'
        || String(bodega?.estado || '').toUpperCase() === 'ACTIVA';
};

const BodegasInventario = () => {
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);

    const {
        bodegas,
        cargarBodegasCache,
        invalidarBodegas,
        invalidarCatalogos,
    } = useInventarioData();

    const [mostrarFormulario, setMostrarFormulario] = useState(false);
    const [form, setForm] = useState(initialForm);
    const [busqueda, setBusqueda] = useState('');

    const cargarBodegasVista = async (force = false) => {
        try {
            setLoading(true);
            setError(null);

            await cargarBodegasCache({ force });
        } catch (err) {
            setError(err?.response?.data || err);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        cargarBodegasVista();
    }, []);

    const bodegasFiltradas = useMemo(() => {
        const term = busqueda.trim().toLowerCase();

        if (!term) {
            return bodegas;
        }

        return bodegas.filter((bodega) => {
            return [
                bodega.codigo,
                bodega.nombre,
                bodega.descripcion,
                bodega.ubicacion,
                bodega.estado,
            ].some((value) => String(value || '').toLowerCase().includes(term));
        });
    }, [bodegas, busqueda]);

    const handleChange = (event) => {
        const { name, value, type, checked } = event.target;

        setForm((current) => ({
            ...current,
            [name]: type === 'checkbox' ? checked : value,
        }));
    };

    const limpiarFormulario = () => {
        setForm(initialForm);
        setError(null);
    };

    const guardarBodega = async (event) => {
        event.preventDefault();

        try {
            setSaving(true);
            setError(null);

            const activa = Boolean(form.activa);

            const payload = {
                codigo: form.codigo,
                nombre: form.nombre,
                descripcion: form.descripcion || null,
                ubicacion: form.ubicacion || null,

                /*
                |--------------------------------------------------------------------------
                | Compatibilidad backend
                |--------------------------------------------------------------------------
                | Algunas versiones usan activa boolean.
                | Otras usan estado ACTIVA/INACTIVA.
                | Enviamos ambos para que el frontend sea compatible con la lógica actual.
                */
                activa,
                estado: activa ? 'ACTIVA' : 'INACTIVA',
            };

            await inventarioApi.bodegas.crear(payload);

            await Swal.fire({
                icon: 'success',
                title: 'Bodega creada',
                text: 'La bodega fue registrada correctamente.',
                confirmButtonColor: '#10b981',
            });

            invalidarBodegas();
            invalidarCatalogos();
            limpiarFormulario();
            setMostrarFormulario(false);
            await cargarBodegasVista(true);
        } catch (err) {
            setError(err?.response?.data || err);
        } finally {
            setSaving(false);
        }
    };

    if (loading) {
        return <LoadingState text="Cargando bodegas de inventario..." />;
    }

    return (
        <div className="space-y-8">
            <PageHeader
                title="Bodegas"
                description="Administración de ubicaciones de stock. Las bodegas activas son utilizadas por movimientos, Kardex, lotes, reservas y tomas físicas."
                actions={(
                    <>
                        <SecondaryButton onClick={() => cargarBodegasVista(true)}>
                            <i className="fas fa-rotate-right"></i>
                            Actualizar
                        </SecondaryButton>

                        <PrimaryButton onClick={() => setMostrarFormulario((value) => !value)}>
                            <i className={mostrarFormulario ? 'fas fa-xmark' : 'fas fa-plus'}></i>
                            {mostrarFormulario ? 'Cerrar formulario' : 'Nueva bodega'}
                        </PrimaryButton>
                    </>
                )}
            />

            {mostrarFormulario && (
                <Panel
                    title="Crear bodega"
                    subtitle="Define una ubicación física o lógica para controlar stock."
                >
                    <ErrorNotice error={error} />

                    <form onSubmit={guardarBodega} className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
                        <Field label="Código">
                            <input
                                type="text"
                                name="codigo"
                                value={form.codigo}
                                onChange={handleChange}
                                className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                                placeholder="BOD-001"
                                required
                            />
                        </Field>

                        <Field label="Nombre">
                            <input
                                type="text"
                                name="nombre"
                                value={form.nombre}
                                onChange={handleChange}
                                className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                                placeholder="Bodega central"
                                required
                            />
                        </Field>

                        <Field label="Ubicación">
                            <input
                                type="text"
                                name="ubicacion"
                                value={form.ubicacion}
                                onChange={handleChange}
                                className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                                placeholder="Santiago, piso 1, rack A"
                            />
                        </Field>

                        <div className="flex items-end">
                            <label className="w-full flex items-center gap-3 rounded-2xl border border-slate-200 p-4 cursor-pointer hover:bg-slate-50">
                                <input
                                    type="checkbox"
                                    name="activa"
                                    checked={form.activa}
                                    onChange={handleChange}
                                    className="w-4 h-4 accent-emerald-500"
                                />
                                <span className="font-black text-slate-700 text-sm">
                                    Bodega activa
                                </span>
                            </label>
                        </div>

                        <div className="md:col-span-2 xl:col-span-4">
                            <Field label="Descripción">
                                <textarea
                                    name="descripcion"
                                    value={form.descripcion}
                                    onChange={handleChange}
                                    rows="3"
                                    className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none resize-none"
                                    placeholder="Descripción opcional de la bodega"
                                />
                            </Field>
                        </div>

                        <div className="md:col-span-2 xl:col-span-4 flex flex-wrap justify-end gap-3">
                            <SecondaryButton type="button" onClick={limpiarFormulario}>
                                Limpiar
                            </SecondaryButton>

                            <PrimaryButton type="submit" disabled={saving}>
                                <i className="fas fa-save"></i>
                                {saving ? 'Guardando...' : 'Guardar bodega'}
                            </PrimaryButton>
                        </div>
                    </form>
                </Panel>
            )}

            <Panel
                title="Listado de bodegas"
                subtitle="Bodegas disponibles para operaciones de inventario."
                actions={(
                    <input
                        type="text"
                        value={busqueda}
                        onChange={(event) => setBusqueda(event.target.value)}
                        className="w-full md:w-80 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                        placeholder="Buscar por código, nombre o ubicación..."
                    />
                )}
            >
                {bodegasFiltradas.length === 0 ? (
                    <EmptyState
                        title="Sin bodegas"
                        description="Crea una bodega para comenzar a registrar stock y movimientos."
                        icon="fas fa-warehouse"
                    />
                ) : (
                    <TableShell>
                        <thead className="bg-slate-50">
                            <tr>
                                <Th>Código</Th>
                                <Th>Nombre</Th>
                                <Th>Ubicación</Th>
                                <Th>Descripción</Th>
                                <Th>Estado</Th>
                            </tr>
                        </thead>

                        <tbody className="divide-y divide-slate-100">
                            {bodegasFiltradas.map((bodega) => {
                                const activa = esBodegaActiva(bodega);

                                return (
                                    <tr key={bodega.id} className="hover:bg-slate-50/70 transition-colors">
                                        <Td className="font-black text-slate-800">
                                            {bodega.codigo}
                                        </Td>

                                        <Td className="font-black text-slate-800">
                                            {bodega.nombre}
                                        </Td>

                                        <Td className="font-semibold text-slate-500">
                                            {bodega.ubicacion || '-'}
                                        </Td>

                                        <Td className="text-slate-500 max-w-md">
                                            {bodega.descripcion || '-'}
                                        </Td>

                                        <Td>
                                            <span className={`inline-flex px-2.5 py-1 rounded-full text-[11px] font-black uppercase border ${
                                                activa
                                                    ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                                    : 'bg-slate-100 text-slate-500 border-slate-200'
                                            }`}
                                            >
                                                {activa ? 'Activa' : 'Inactiva'}
                                            </span>
                                        </Td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </TableShell>
                )}
            </Panel>
        </div>
    );
};

export default BodegasInventario;