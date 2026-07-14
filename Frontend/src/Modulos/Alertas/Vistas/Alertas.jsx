import { useState, useEffect, useCallback } from 'react';
import Swal from 'sweetalert2';
import alertasApi from '../alertasApi';
import { usePermisos } from '../../../Contextos/Permisos';
import { EstadoVacioDiv } from '../../../Componentes/EstadoVacio';

const TIPOS = [
  { value: '', label: 'Todos los tipos' },
  { value: 'periodo_sin_cerrar', label: 'Periodo sin cerrar' },
  { value: 'f29_sin_declarar', label: 'F29 sin declarar' },
  { value: 'cxc_vencida', label: 'Cuenta por cobrar vencida' },
  { value: 'cxp_vencida', label: 'Cuenta por pagar vencida' },
  { value: 'contrato_por_vencer', label: 'Contrato por vencer' },
];

const NIVELES = [
  { value: '', label: 'Todos los niveles' },
  { value: 'critico', label: 'Critico' },
  { value: 'advertencia', label: 'Advertencia' },
  { value: 'info', label: 'Informativo' },
];

const COLOR_NIVEL = {
  critico: 'bg-red-100 text-red-700 border-red-200',
  advertencia: 'bg-amber-100 text-amber-700 border-amber-200',
  info: 'bg-blue-100 text-blue-700 border-blue-200',
};

const labelTipo = (tipo) => TIPOS.find((t) => t.value === tipo)?.label ?? tipo;

export default function Alertas() {
  const [alertas, setAlertas] = useState([]);
  const [cargando, setCargando] = useState(false);
  const [error, setError] = useState('');
  const [filtroTipo, setFiltroTipo] = useState('');
  const [filtroNivel, setFiltroNivel] = useState('');
  const [resolviendoId, setResolviendoId] = useState(null);

  const { tienePermiso } = usePermisos();
  const puedeGestionar = tienePermiso('alertas.gestionar');

  const cargar = useCallback(async (signal) => {
    setCargando(true);
    setError('');
    try {
      const params = {};
      if (filtroTipo) params.tipo = filtroTipo;
      if (filtroNivel) params.nivel = filtroNivel;
      const { data } = await alertasApi.listar(params, signal);
      setAlertas(data.data?.data ?? []);
    } catch (e) {
      if (e?.name !== 'AbortError' && e?.name !== 'CanceledError') {
        setError('No se pudieron cargar las alertas.');
      }
    } finally {
      setCargando(false);
    }
  }, [filtroTipo, filtroNivel]);

  useEffect(() => {
    const controller = new AbortController();
    cargar(controller.signal);
    return () => controller.abort();
  }, [cargar]);

  const resolver = async (alerta, estado) => {
    const confirmacion = await Swal.fire({
      icon: 'question',
      title: estado === 'resuelta' ? 'Marcar como resuelta' : 'Descartar alerta',
      text: alerta.mensaje,
      showCancelButton: true,
      confirmButtonText: estado === 'resuelta' ? 'Marcar resuelta' : 'Descartar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: estado === 'resuelta' ? '#10b981' : '#64748b',
    });

    if (!confirmacion.isConfirmed) {
      return;
    }

    setResolviendoId(alerta.id);
    try {
      await alertasApi.resolver(alerta.id, estado);
      setAlertas((actuales) => actuales.filter((a) => a.id !== alerta.id));
    } catch (e) {
      Swal.fire({
        icon: 'error',
        title: 'No se pudo actualizar la alerta',
        text: e?.response?.data?.message ?? 'Intenta nuevamente.',
        confirmButtonColor: '#ef4444',
      });
    } finally {
      setResolviendoId(null);
    }
  };

  return (
    <div className="space-y-4 max-w-5xl mx-auto">
      <div className="flex items-center justify-between flex-wrap gap-2">
        <div>
          <h1 className="text-xl font-semibold text-slate-800 dark:text-slate-200">Central de Alertas</h1>
          <p className="text-sm text-slate-500 dark:text-slate-400">
            Cumplimiento tributario y gestion: periodos sin cerrar, F29, cuentas por cobrar/pagar vencidas y contratos por vencer.
          </p>
        </div>
      </div>

      <div className="flex gap-2 flex-wrap">
        <select
          value={filtroTipo}
          onChange={(e) => setFiltroTipo(e.target.value)}
          className="text-base md:text-sm border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200"
        >
          {TIPOS.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
        </select>
        <select
          value={filtroNivel}
          onChange={(e) => setFiltroNivel(e.target.value)}
          className="text-base md:text-sm border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200"
        >
          {NIVELES.map((n) => <option key={n.value} value={n.value}>{n.label}</option>)}
        </select>
      </div>

      {error && <p className="text-red-600 text-sm">{error}</p>}

      {cargando ? (
        <div className="py-8 text-center text-slate-400">Cargando...</div>
      ) : alertas.length === 0 ? (
        <EstadoVacioDiv mensaje="No hay alertas pendientes para los filtros seleccionados." />
      ) : (
        <div className="space-y-2">
          {alertas.map((alerta) => (
            <div
              key={alerta.id}
              className="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4"
            >
              <div className="flex items-start justify-between gap-3 flex-wrap">
                <div className="flex-1 min-w-[240px]">
                  <div className="flex items-center gap-2 mb-1">
                    <span className={`text-[11px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full border ${COLOR_NIVEL[alerta.nivel] || COLOR_NIVEL.info}`}>
                      {alerta.nivel}
                    </span>
                    <span className="text-xs font-semibold text-slate-500 dark:text-slate-400">
                      {labelTipo(alerta.tipo)}
                    </span>
                  </div>
                  <p className="text-sm text-slate-800 dark:text-slate-200">{alerta.mensaje}</p>
                  <p className="text-xs text-slate-400 mt-1">
                    {new Date(alerta.created_at).toLocaleString('es-CL')}
                  </p>
                </div>

                {puedeGestionar && (
                  <div className="flex gap-2 shrink-0">
                    <button
                      disabled={resolviendoId === alerta.id}
                      onClick={() => resolver(alerta, 'resuelta')}
                      className="bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 font-semibold py-1.5 px-3 rounded-lg text-xs disabled:opacity-50"
                    >
                      Resolver
                    </button>
                    <button
                      disabled={resolviendoId === alerta.id}
                      onClick={() => resolver(alerta, 'descartada')}
                      className="bg-slate-100 hover:bg-slate-200 text-slate-600 border border-slate-200 font-semibold py-1.5 px-3 rounded-lg text-xs disabled:opacity-50"
                    >
                      Descartar
                    </button>
                  </div>
                )}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
