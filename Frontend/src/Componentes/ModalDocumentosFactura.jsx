import React, { useState, useEffect, useRef, useCallback } from 'react';
import Swal from 'sweetalert2';
import { api } from '../Configuracion/api';

const ES_IMAGEN = (mime) => mime === 'image/jpeg' || mime === 'image/png' || mime === 'image/webp';

const formatearTamano = (bytes) => {
    if (!bytes) return '0 KB';
    const kb = bytes / 1024;
    if (kb < 1024) return `${kb.toFixed(1)} KB`;
    return `${(kb / 1024).toFixed(1)} MB`;
};

/** Modal de documentos adjuntos de una factura (varios, PDF/imagen, zoom); compartido entre VisorProveedor y VisorCliente. */
const ModalDocumentosFactura = ({ facturaId, etiqueta, onCerrar, onCambio }) => {
    const [documentos, setDocumentos] = useState([]);
    const [cargando, setCargando] = useState(true);
    const [subiendo, setSubiendo] = useState(false);
    const [imagenZoom, setImagenZoom] = useState(null); // { url, nombre }
    const [escala, setEscala] = useState(1);
    const inputRef = useRef(null);

    const cargarDocumentos = useCallback(async () => {
        setCargando(true);
        try {
            const res = await api.get(`/facturas/${facturaId}/adjuntos`);
            if (res.success) setDocumentos(res.data);
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudieron cargar los documentos.' });
        } finally {
            setCargando(false);
        }
    }, [facturaId]);

    useEffect(() => {
        cargarDocumentos();
    }, [cargarDocumentos]);

    const subirArchivos = async (files) => {
        if (!files || files.length === 0) return;

        const formData = new FormData();
        Array.from(files).forEach((f) => formData.append('documentos[]', f));

        setSubiendo(true);
        try {
            const res = await api.upload(`/facturas/${facturaId}/adjuntos`, formData);
            if (res.success) {
                await cargarDocumentos();
                if (onCambio) onCambio();
            }
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'Error al subir', text: error.message || 'No se pudo subir el/los documento(s).' });
        } finally {
            setSubiendo(false);
            if (inputRef.current) inputRef.current.value = '';
        }
    };

    const eliminarDocumento = async (doc) => {
        const confirmacion = await Swal.fire({
            icon: 'warning',
            title: '¿Eliminar documento?',
            text: `"${doc.nombre_original}" se borrará del servidor y no se puede deshacer.`,
            showCancelButton: true,
            confirmButtonText: 'Eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc2626',
        });
        if (!confirmacion.isConfirmed) return;

        try {
            const res = await api.delete(`/facturas/${facturaId}/adjuntos/${doc.id}`);
            if (res.success) {
                setDocumentos((prev) => prev.filter((d) => d.id !== doc.id));
                if (onCambio) onCambio();
            }
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'Error', text: error.message || 'No se pudo eliminar el documento.' });
        }
    };

    const abrirDocumento = async (doc) => {
        try {
            const url = await api.blobUrl(`/facturas/${facturaId}/adjuntos/${doc.id}`);
            if (ES_IMAGEN(doc.mime_type)) {
                setEscala(1);
                setImagenZoom({ url, nombre: doc.nombre_original });
            } else {
                window.open(url, '_blank', 'noopener,noreferrer');
            }
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo abrir el documento.' });
        }
    };

    const cerrarZoom = () => {
        if (imagenZoom) window.URL.revokeObjectURL(imagenZoom.url);
        setImagenZoom(null);
        setEscala(1);
    };

    const ajustarZoom = (delta) => {
        setEscala((prev) => Math.min(4, Math.max(1, prev + delta)));
    };

    return (
        <div className="fixed inset-0 bg-slate-900/80 z-[110] flex items-center justify-center p-4 animate-fade-in" onClick={onCerrar}>
            <div className="bg-white dark:bg-slate-800 w-full max-w-2xl rounded-xl shadow-xl overflow-hidden flex flex-col max-h-[85vh] border border-slate-300 dark:border-slate-700" onClick={(e) => e.stopPropagation()}>
                <div className="bg-slate-900 px-6 py-4 flex justify-between items-center text-white shrink-0 border-b border-slate-800">
                    <div>
                        <h2 className="text-base font-bold">Documentos Adjuntos</h2>
                        <p className="text-xs text-slate-400 uppercase">{etiqueta}</p>
                    </div>
                    <button onClick={onCerrar} className="text-slate-400 hover:text-white transition-colors p-2" aria-label="Cerrar">
                        <i className="fas fa-times text-lg"></i>
                    </button>
                </div>

                <div className="p-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 shrink-0">
                    <label className={`flex items-center justify-center gap-2 border-2 border-dashed rounded-xl p-4 cursor-pointer transition-colors ${subiendo ? 'opacity-50 pointer-events-none' : 'border-slate-300 dark:border-slate-600 hover:border-blue-400'}`}>
                        <i className="fas fa-cloud-upload-alt text-blue-500"></i>
                        <span className="text-sm font-bold text-slate-600 dark:text-slate-300">
                            {subiendo ? 'Subiendo...' : 'Subir documentos (PDF o imagen, varios a la vez)'}
                        </span>
                        <input
                            ref={inputRef}
                            type="file"
                            accept="application/pdf,image/jpeg,image/png,image/webp"
                            multiple
                            className="hidden"
                            disabled={subiendo}
                            onChange={(e) => subirArchivos(e.target.files)}
                        />
                    </label>
                    <p className="text-[10px] text-slate-400 mt-1.5">Las fotos se comprimen automáticamente al subirse. Máx. 10 archivos por vez.</p>
                </div>

                <div className="overflow-y-auto flex-1 p-4">
                    {cargando ? (
                        <p className="text-center text-slate-400 text-sm py-8">Cargando...</p>
                    ) : documentos.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-10 text-slate-400">
                            <i className="fas fa-folder-open text-3xl mb-2"></i>
                            <p className="font-bold text-sm">Sin documentos adjuntos</p>
                        </div>
                    ) : (
                        <div className="space-y-2">
                            {documentos.map((doc) => (
                                <div key={doc.id} className="flex items-center gap-3 p-3 rounded border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                                    <div className={`w-9 h-9 rounded flex items-center justify-center shrink-0 ${ES_IMAGEN(doc.mime_type) ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'}`}>
                                        <i className={`fas ${ES_IMAGEN(doc.mime_type) ? 'fa-image' : 'fa-file-pdf'}`}></i>
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <p className="font-bold text-slate-800 dark:text-slate-200 text-sm truncate">{doc.nombre_original}</p>
                                        <p className="text-[10px] text-slate-400">{formatearTamano(doc.tamano_bytes)}</p>
                                    </div>
                                    <button onClick={() => abrirDocumento(doc)} className="text-blue-600 hover:text-blue-800 font-bold text-xs shrink-0 px-2">
                                        Ver
                                    </button>
                                    <button onClick={() => eliminarDocumento(doc)} className="text-rose-500 hover:text-rose-700 font-bold text-xs shrink-0 px-2">
                                        Eliminar
                                    </button>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>

            {imagenZoom && (
                <div className="fixed inset-0 bg-black/90 z-[120] flex flex-col items-center justify-center" onClick={cerrarZoom}>
                    <div className="absolute top-4 right-4 flex gap-2 z-10" onClick={(e) => e.stopPropagation()}>
                        <button onClick={() => ajustarZoom(-0.5)} className="bg-white/10 hover:bg-white/20 text-white w-9 h-9 rounded-full flex items-center justify-center">
                            <i className="fas fa-search-minus"></i>
                        </button>
                        <button onClick={() => ajustarZoom(0.5)} className="bg-white/10 hover:bg-white/20 text-white w-9 h-9 rounded-full flex items-center justify-center">
                            <i className="fas fa-search-plus"></i>
                        </button>
                        <button onClick={cerrarZoom} className="bg-white/10 hover:bg-white/20 text-white w-9 h-9 rounded-full flex items-center justify-center">
                            <i className="fas fa-times"></i>
                        </button>
                    </div>
                    <div className="overflow-auto max-w-full max-h-full" onClick={(e) => e.stopPropagation()}>
                        <img
                            src={imagenZoom.url}
                            alt={imagenZoom.nombre}
                            style={{ transform: `scale(${escala})`, transition: 'transform 0.15s ease' }}
                            className="max-w-none"
                        />
                    </div>
                </div>
            )}
        </div>
    );
};

export default ModalDocumentosFactura;
