import React, { useCallback, useEffect, useState } from 'react';
import Swal from 'sweetalert2';
import EstadoCarga from '../../../Componentes/EstadoCarga';
import TarjetaCertificadoActivo from '../Componentes/TarjetaCertificadoActivo';
import UploaderCertificado from '../Componentes/UploaderCertificado';
import siiApi from '../Servicios/siiApi';

const CertificadoSii = () => {
    const [certificado, setCertificado] = useState(null);
    const [cargando, setCargando] = useState(true);
    const [mostrarUploaderReemplazo, setMostrarUploaderReemplazo] = useState(false);

    const cargarCert = useCallback(async () => {
        setCargando(true);
        try {
            const data = await siiApi.certificado.obtener();
            setCertificado(data);
        } catch (e) {
            // 404 = no hay cert activo; otros ya los manejo api.js (Swal).
            if (e && e.status === 404) {
                setCertificado(null);
            }
        } finally {
            setCargando(false);
        }
    }, []);

    useEffect(() => {
        cargarCert();
    }, [cargarCert]);

    const handleSubidoExitosamente = async () => {
        setMostrarUploaderReemplazo(false);
        await cargarCert();
    };

    const handleRevocar = async (cert) => {
        try {
            await siiApi.certificado.revocar(cert.id);
            await Swal.fire({
                icon: 'success',
                title: 'Certificado revocado',
                text: 'El certificado quedo marcado como revocado.',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2200,
            });
            await cargarCert();
        } catch (_) {
            // ya notificado por api.js
        }
    };

    return (
        <div className="max-w-4xl mx-auto p-6 md:p-8 space-y-6">
            <header>
                <h1 className="text-2xl md:text-3xl font-black text-slate-900 flex items-center gap-3">
                    <i className="fas fa-certificate text-emerald-600" />
                    Certificado Digital SII
                </h1>
                <p className="text-sm text-slate-500 mt-1">
                    El certificado se almacena cifrado con AES-256 y se usa exclusivamente para firmar DTE.
                </p>
            </header>

            <EstadoCarga
                cargando={cargando}
                mensajeCargando="Cargando certificado..."
                color="emerald"
                tamano="compacto"
            >
                {certificado ? (
                    <>
                        <TarjetaCertificadoActivo certificado={certificado} onRevocar={handleRevocar} />

                        <div className="bg-slate-50 border border-slate-200 rounded-2xl p-5">
                            <div className="flex items-center justify-between">
                                <div>
                                    <h3 className="font-bold text-slate-800">Reemplazar certificado</h3>
                                    <p className="text-xs text-slate-500 mt-1">
                                        Al subir uno nuevo, el actual pasa a estado <b>cuarentena</b>.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setMostrarUploaderReemplazo((s) => !s)}
                                    className="px-4 py-2 bg-white border border-slate-300 hover:bg-slate-100 text-slate-700 font-bold rounded-lg text-sm transition-colors"
                                >
                                    {mostrarUploaderReemplazo ? 'Cancelar' : 'Reemplazar'}
                                </button>
                            </div>

                            {mostrarUploaderReemplazo && (
                                <div className="mt-5">
                                    <UploaderCertificado onSubidoExitosamente={handleSubidoExitosamente} />
                                </div>
                            )}
                        </div>
                    </>
                ) : (
                    <>
                        <TarjetaCertificadoActivo certificado={null} />
                        <UploaderCertificado onSubidoExitosamente={handleSubidoExitosamente} />
                    </>
                )}
            </EstadoCarga>
        </div>
    );
};

export default CertificadoSii;
