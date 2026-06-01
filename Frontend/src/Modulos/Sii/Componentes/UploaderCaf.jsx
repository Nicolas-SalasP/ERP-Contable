import React, { useState } from 'react';
import Swal from 'sweetalert2';

const MAX_BYTES = 100 * 1024; // 100 KB (alineado con SubirCafRequest backend)
const EXT_VALIDA = /\.xml$/i;

const UploaderCaf = ({ onSubidoExitosamente, deshabilitado = false }) => {
    const [archivo, setArchivo] = useState(null);
    const [errorArchivo, setErrorArchivo] = useState(null);
    const [enviando, setEnviando] = useState(false);

    const handleArchivoChange = (e) => {
        const file = e.target.files?.[0] ?? null;
        setErrorArchivo(null);

        if (!file) {
            setArchivo(null);
            return;
        }

        if (!EXT_VALIDA.test(file.name)) {
            setErrorArchivo('El archivo debe tener extension .xml.');
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
        if (!archivo) return;

        setEnviando(true);
        try {
            const caf = await (typeof onSubidoExitosamente === 'function'
                ? onSubidoExitosamente(archivo)
                : null);
            if (caf) {
                setArchivo(null);
                await Swal.fire({
                    icon: 'success',
                    title: 'CAF cargado',
                    text: `Folios ${caf.folio_desde}-${caf.folio_hasta} del tipo ${caf.tipo_dte} disponibles.`,
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2500,
                });
            }
        } finally {
            setEnviando(false);
        }
    };

    const botonHabilitado = !!archivo && !enviando && !deshabilitado;

    return (
        <form
            onSubmit={handleSubmit}
            data-testid="uploader-caf"
            className="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4 animate-fade-in"
        >
            <div className="flex items-start justify-between gap-4">
                <div>
                    <h3 className="text-lg font-bold text-slate-800 flex items-center gap-2">
                        <i className="fas fa-cloud-upload-alt text-emerald-600" /> Cargar Nuevo CAF
                    </h3>
                    <p className="text-xs text-slate-500 mt-1">
                        Sube el archivo XML del CAF descargado del SII. Maximo 100 KB.
                    </p>
                </div>
            </div>

            <div className="border-2 border-dashed border-slate-300 rounded-xl p-6 text-center hover:border-emerald-400 transition-colors">
                <input
                    id="caf-archivo"
                    data-testid="caf-archivo"
                    type="file"
                    accept=".xml"
                    onChange={handleArchivoChange}
                    disabled={enviando || deshabilitado}
                    className="hidden"
                />
                <label htmlFor="caf-archivo" className="cursor-pointer inline-flex flex-col items-center gap-2">
                    <i className="fas fa-file-code text-4xl text-slate-400" />
                    {archivo ? (
                        <span className="text-sm text-emerald-700 font-bold">{archivo.name}</span>
                    ) : (
                        <>
                            <span className="text-sm font-bold text-slate-700">Selecciona o arrastra el archivo .xml</span>
                            <span className="text-xs text-slate-500">CAFs reales pesan 5-15 KB</span>
                        </>
                    )}
                </label>
            </div>

            {errorArchivo && (
                <p data-testid="caf-archivo-error" className="text-xs text-rose-600">{errorArchivo}</p>
            )}

            <div className="flex justify-end">
                <button
                    type="submit"
                    data-testid="caf-submit"
                    disabled={!botonHabilitado}
                    className="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:bg-slate-400 disabled:cursor-not-allowed text-white font-bold rounded-lg shadow-sm transition-colors flex items-center gap-2"
                >
                    {enviando ? (<><i className="fas fa-spinner fa-spin" /> Subiendo...</>) : (<><i className="fas fa-upload" /> Subir CAF</>)}
                </button>
            </div>
        </form>
    );
};

export default UploaderCaf;
