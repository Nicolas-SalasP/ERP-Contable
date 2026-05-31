import React from 'react';
import Swal from 'sweetalert2';

const calcularDiasParaVencer = (validoHasta) => {
    if (!validoHasta) return -Infinity;
    const fin = new Date(validoHasta).getTime();
    if (Number.isNaN(fin)) return -Infinity;
    const ahora = Date.now();
    return Math.floor((fin - ahora) / (1000 * 60 * 60 * 24));
};

const badgeDeVigencia = (dias) => {
    if (dias < 0) return { texto: 'Vencido', clases: 'bg-slate-200 text-slate-700 border-slate-300' };
    if (dias <= 7) return { texto: `Vence en ${dias}d`, clases: 'bg-rose-100 text-rose-700 border-rose-300' };
    if (dias <= 30) return { texto: `Vence en ${dias}d`, clases: 'bg-orange-100 text-orange-700 border-orange-300' };
    if (dias <= 60) return { texto: `Vence en ${dias}d`, clases: 'bg-amber-100 text-amber-700 border-amber-300' };
    return { texto: `Vigente (${dias}d)`, clases: 'bg-emerald-100 text-emerald-700 border-emerald-300' };
};

const abreviarFingerprint = (fp) => {
    if (!fp || fp.length < 16) return fp || '—';
    return `${fp.slice(0, 8)}…${fp.slice(-8)}`;
};

const formatearFechaCorta = (iso) => {
    if (!iso) return '—';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return iso;
    return d.toLocaleDateString('es-CL', { year: 'numeric', month: 'short', day: '2-digit' });
};

const TarjetaCertificadoActivo = ({ certificado, onRevocar }) => {
    if (!certificado) {
        return (
            <div
                data-testid="tarjeta-cert-placeholder"
                className="bg-white rounded-2xl border-2 border-dashed border-slate-300 p-10 text-center animate-fade-in"
            >
                <div className="text-6xl mb-3">🔐</div>
                <h3 className="text-xl font-bold text-slate-700 mb-2">Sin certificado digital</h3>
                <p className="text-sm text-slate-500 max-w-md mx-auto">
                    Sube tu certificado <b>.pfx</b> o <b>.p12</b> emitido por una entidad acreditada por el SII para
                    comenzar a emitir documentos tributarios electronicos.
                </p>
            </div>
        );
    }

    const dias = calcularDiasParaVencer(certificado.valido_hasta);
    const badge = badgeDeVigencia(dias);

    const confirmarRevocacion = async () => {
        const { isConfirmed } = await Swal.fire({
            icon: 'warning',
            title: 'Revocar certificado',
            html: 'El certificado quedara marcado como <b>revocado</b> y no podra firmar DTE nuevos.<br/><br/>Confirmas la revocacion?',
            showCancelButton: true,
            confirmButtonText: 'Si, revocar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc2626',
        });
        if (isConfirmed && typeof onRevocar === 'function') {
            onRevocar(certificado);
        }
    };

    return (
        <div
            data-testid="tarjeta-cert-activo"
            className="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 animate-fade-in"
        >
            <div className="flex items-start justify-between gap-4 mb-4">
                <div>
                    <h3 className="text-lg font-bold text-slate-800 flex items-center gap-2">
                        <i className="fas fa-certificate text-emerald-600" />
                        {certificado.subject_common_name || 'Certificado sin nombre'}
                    </h3>
                    <p className="text-sm text-slate-500 mt-1">
                        RUT titular: <span className="font-mono">{certificado.subject_rut || '—'}</span>
                    </p>
                </div>
                <span
                    data-testid="badge-vigencia"
                    className={`text-xs font-bold px-3 py-1.5 rounded-full border ${badge.clases}`}
                >
                    {badge.texto}
                </span>
            </div>

            <dl className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <dt className="text-slate-500 text-xs uppercase tracking-wide">Emisor</dt>
                    <dd className="text-slate-800 font-medium">{certificado.issuer_common_name || '—'}</dd>
                </div>
                <div>
                    <dt className="text-slate-500 text-xs uppercase tracking-wide">Estado</dt>
                    <dd className="text-slate-800 font-medium capitalize">{certificado.estado}</dd>
                </div>
                <div>
                    <dt className="text-slate-500 text-xs uppercase tracking-wide">Valido desde</dt>
                    <dd className="text-slate-800 font-medium">{formatearFechaCorta(certificado.valido_desde)}</dd>
                </div>
                <div>
                    <dt className="text-slate-500 text-xs uppercase tracking-wide">Valido hasta</dt>
                    <dd className="text-slate-800 font-medium">{formatearFechaCorta(certificado.valido_hasta)}</dd>
                </div>
                <div className="sm:col-span-2">
                    <dt className="text-slate-500 text-xs uppercase tracking-wide">Fingerprint SHA-256</dt>
                    <dd className="text-slate-800 font-mono text-xs">{abreviarFingerprint(certificado.fingerprint_sha256)}</dd>
                </div>
            </dl>

            <div className="mt-6 flex justify-end">
                <button
                    type="button"
                    onClick={confirmarRevocacion}
                    data-testid="boton-revocar"
                    className="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-lg shadow-sm transition-colors flex items-center gap-2 text-sm"
                >
                    <i className="fas fa-ban" /> Revocar Certificado
                </button>
            </div>
        </div>
    );
};

export default TarjetaCertificadoActivo;
