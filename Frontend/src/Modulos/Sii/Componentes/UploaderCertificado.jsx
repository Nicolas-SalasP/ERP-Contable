import React, { useState } from 'react';
import Swal from 'sweetalert2';
import siiApi from '../Servicios/siiApi';

const MAX_BYTES = 50 * 1024; // 50 KB (alineado al FormRequest max:50)
const EXT_VALIDAS = /\.(pfx|p12)$/i;

const UploaderCertificado = ({ onSubidoExitosamente, deshabilitado = false }) => {
    const [archivo, setArchivo] = useState(null);
    const [errorArchivo, setErrorArchivo] = useState(null);
    const [password, setPassword] = useState('');
    const [subiendo, setSubiendo] = useState(false);

    const handleArchivoChange = (e) => {
        const file = e.target.files?.[0] ?? null;
        setErrorArchivo(null);

        if (!file) {
            setArchivo(null);
            return;
        }

        if (!EXT_VALIDAS.test(file.name)) {
            setErrorArchivo('El archivo debe tener extension .pfx o .p12.');
            setArchivo(null);
            e.target.value = '';
            return;
        }

        if (file.size > MAX_BYTES) {
            setErrorArchivo(`El archivo no puede superar ${MAX_BYTES / 1024} KB.`);
            setArchivo(null);
            e.target.value = '';
            return;
        }

        setArchivo(file);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!archivo || !password) return;
        setSubiendo(true);
        try {
            const certificado = await siiApi.certificado.subir(archivo, password);
            await Swal.fire({
                icon: 'success',
                title: 'Certificado cargado',
                text: 'El certificado fue procesado y persistido cifrado en la base de datos.',
                confirmButtonColor: '#0f172a',
            });
            setArchivo(null);
            setPassword('');
            if (typeof onSubidoExitosamente === 'function') {
                onSubidoExitosamente(certificado);
            }
        } catch (_) {
            // El error 422 ya fue mostrado por api.js (Swal). Solo reset del password.
            setPassword('');
        } finally {
            setSubiendo(false);
        }
    };

    const botonHabilitado = !!archivo && !!password && !subiendo && !deshabilitado;

    return (
        <form
            onSubmit={handleSubmit}
            className="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-5 animate-fade-in"
            data-testid="uploader-cert"
        >
            <div>
                <label htmlFor="cert-archivo" className="block text-sm font-medium text-gray-700 mb-1">
                    Archivo .pfx o .p12 <span className="text-rose-500">*</span>
                </label>
                <div className="border-2 border-dashed border-slate-300 rounded-xl p-6 text-center hover:border-emerald-400 transition-colors">
                    <input
                        id="cert-archivo"
                        data-testid="cert-archivo"
                        type="file"
                        accept=".pfx,.p12"
                        onChange={handleArchivoChange}
                        disabled={subiendo || deshabilitado}
                        className="hidden"
                    />
                    <label
                        htmlFor="cert-archivo"
                        className="cursor-pointer inline-flex flex-col items-center gap-2"
                    >
                        <i className="fas fa-file-shield text-4xl text-slate-400" />
                        {archivo ? (
                            <span className="text-sm text-emerald-700 font-bold">{archivo.name}</span>
                        ) : (
                            <>
                                <span className="text-sm font-bold text-slate-700">Selecciona o arrastra tu certificado</span>
                                <span className="text-xs text-slate-500">Maximo 50 KB. Extensiones .pfx o .p12.</span>
                            </>
                        )}
                    </label>
                </div>
                {errorArchivo && (
                    <p data-testid="cert-archivo-error" className="text-xs text-rose-600 mt-2">{errorArchivo}</p>
                )}
            </div>

            <div>
                <label htmlFor="cert-password" className="block text-sm font-medium text-gray-700 mb-1">
                    Contrasena del certificado <span className="text-rose-500">*</span>
                </label>
                <input
                    id="cert-password"
                    data-testid="cert-password"
                    type="password"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    disabled={subiendo || deshabilitado}
                    autoComplete="off"
                    maxLength={256}
                />
                <p className="text-xs text-slate-500 mt-1">
                    La contrasena se cifra con AES-256 (APP_KEY) antes de persistir. Jamas se almacena en claro.
                </p>
            </div>

            <div className="flex justify-end">
                <button
                    type="submit"
                    data-testid="cert-submit"
                    disabled={!botonHabilitado}
                    className="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:bg-slate-400 disabled:cursor-not-allowed text-white font-bold rounded-lg shadow-sm transition-colors flex items-center gap-2"
                >
                    {subiendo ? (<><i className="fas fa-spinner fa-spin" /> Subiendo...</>) : (<><i className="fas fa-upload" /> Subir y Validar</>)}
                </button>
            </div>
        </form>
    );
};

export default UploaderCertificado;
