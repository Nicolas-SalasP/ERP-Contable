import React, { useState } from 'react';
import Swal from 'sweetalert2';
import ModalRevocarCaf from '../Componentes/ModalRevocarCaf';
import TablaCafsHistorial from '../Componentes/TablaCafsHistorial';
import TablaSaldosCaf from '../Componentes/TablaSaldosCaf';
import UploaderCaf from '../Componentes/UploaderCaf';
import useSiiCafs from '../Hooks/useSiiCafs';

const FoliosCaf = () => {
    const {
        cafs,
        saldos,
        cargando,
        filtroTipo,
        subiendo,
        revocando,
        cambiarFiltro,
        subirCaf,
        revocarCaf,
    } = useSiiCafs();

    const [modalCaf, setModalCaf] = useState(null);

    const handleSubirCaf = async (file) => {
        return await subirCaf(file);
    };

    const handleAbrirRevocar = (caf) => {
        setModalCaf(caf);
    };

    const handleCerrarRevocar = () => {
        setModalCaf(null);
    };

    const handleConfirmarRevocar = async (motivo) => {
        const ok = await revocarCaf(modalCaf.id, motivo);
        if (ok) {
            setModalCaf(null);
            await Swal.fire({
                icon: 'success',
                title: 'CAF revocado',
                text: 'El CAF quedo en estado revocado.',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2500,
            });
        }
    };

    return (
        <div className="max-w-6xl mx-auto p-6 md:p-8 space-y-6">
            <header>
                <h1 className="text-2xl md:text-3xl font-black text-slate-900 flex items-center gap-3">
                    <i className="fas fa-file-code text-emerald-600" />
                    Folios CAF
                </h1>
                <p className="text-sm text-slate-500 mt-1">
                    Gestion de Codigos de Autorizacion de Folios autorizados por el SII.
                </p>
            </header>

            {/* SALDOS POR TIPO */}
            <section data-testid="seccion-saldos">
                <h2 className="text-sm font-bold uppercase tracking-wide text-slate-600 mb-2">
                    Saldos por tipo de DTE
                </h2>
                <TablaSaldosCaf saldos={saldos} cargando={cargando} />
            </section>

            {/* UPLOADER */}
            <section data-testid="seccion-uploader">
                <UploaderCaf onSubidoExitosamente={handleSubirCaf} deshabilitado={subiendo} />
            </section>

            {/* HISTORIAL */}
            <section data-testid="seccion-historial">
                <TablaCafsHistorial
                    cafs={cafs}
                    cargando={cargando}
                    filtroTipo={filtroTipo}
                    onCambiarFiltro={cambiarFiltro}
                    onRevocar={handleAbrirRevocar}
                />
            </section>

            <ModalRevocarCaf
                abierto={modalCaf !== null}
                caf={modalCaf}
                onCerrar={handleCerrarRevocar}
                onConfirmar={handleConfirmarRevocar}
                revocando={revocando}
            />
        </div>
    );
};

export default FoliosCaf;
