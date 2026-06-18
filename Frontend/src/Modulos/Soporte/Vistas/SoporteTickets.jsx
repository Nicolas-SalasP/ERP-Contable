import { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../../../Configuracion/api';
import { EstadoVacioDiv } from '../../../Componentes/EstadoVacio';

const ESTADOS = [
  { value: '', label: 'Todos los estados' },
  { value: 'nuevo', label: 'Nuevo' },
  { value: 'abierto', label: 'Abierto' },
  { value: 'esperando_cliente', label: 'Esperando respuesta' },
  { value: 'resuelto', label: 'Resuelto' },
  { value: 'cerrado', label: 'Cerrado' },
];

const COLOR_ESTADO = {
  nuevo: 'bg-blue-100 text-blue-700',
  abierto: 'bg-amber-100 text-amber-700',
  esperando_cliente: 'bg-purple-100 text-purple-700',
  resuelto: 'bg-green-100 text-green-700',
  cerrado: 'bg-slate-100 text-slate-600',
};

const COLOR_PRIORIDAD = {
  baja: 'bg-slate-100 text-slate-500',
  media: 'bg-blue-100 text-blue-700',
  alta: 'bg-amber-100 text-amber-700',
  critica: 'bg-red-100 text-red-700',
};

const FORM_INICIAL = { subject: '', category: 'ERP', priority: 'media', message: '' };

export default function SoporteTickets() {
  const [tickets, setTickets]           = useState([]);
  const [cargando, setCargando]         = useState(false);
  const [error, setError]               = useState('');
  const [filtroEstado, setFiltroEstado] = useState('');
  const [mostrarCrear, setMostrarCrear] = useState(false);
  const [form, setForm]                 = useState(FORM_INICIAL);
  const [creando, setCreando]           = useState(false);
  const [errorCrear, setErrorCrear]     = useState('');

  const cargar = useCallback(async (signal) => {
    setCargando(true);
    setError('');
    try {
      const params = {};
      if (filtroEstado) params.status = filtroEstado;
      const { data } = await api.get('/soporte/tickets', { params, signal });
      setTickets(data.data ?? (Array.isArray(data) ? data : []));
    } catch (e) {
      if (e?.name !== 'AbortError' && e?.name !== 'CanceledError') {
        setError('No se pudieron cargar los tickets.');
      }
    } finally {
      setCargando(false);
    }
  }, [filtroEstado]);

  useEffect(() => {
    const controller = new AbortController();
    cargar(controller.signal);
    return () => controller.abort();
  }, [cargar]);

  const abrirCrear = () => {
    setForm(FORM_INICIAL);
    setErrorCrear('');
    setMostrarCrear(true);
  };

  const crear = async (e) => {
    e.preventDefault();
    setCreando(true);
    setErrorCrear('');
    try {
      await api.post('/soporte/tickets', form);
      setMostrarCrear(false);
      cargar();
    } catch (e) {
      setErrorCrear(e?.response?.data?.message ?? 'No se pudo crear el ticket.');
    } finally {
      setCreando(false);
    }
  };

  return (
    <div className="space-y-4 max-w-4xl mx-auto">
      <div className="flex items-center justify-between flex-wrap gap-2">
        <h1 className="text-xl font-semibold text-slate-800 dark:text-slate-200 flex-wrap">Soporte</h1>
        <button
          onClick={abrirCrear}
          className="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg"
        >
          Nuevo ticket
        </button>
      </div>

      <div className="flex gap-2 flex-wrap">
        <select
          value={filtroEstado}
          onChange={e => setFiltroEstado(e.target.value)}
          className="text-base md:text-sm border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200"
        >
          {ESTADOS.map(e => <option key={e.value} value={e.value}>{e.label}</option>)}
        </select>
      </div>

      {error && <p className="text-red-600 text-sm">{error}</p>}

      {cargando ? (
        <div className="py-8 text-center text-slate-400">Cargando...</div>
      ) : tickets.length === 0 ? (
        <EstadoVacioDiv mensaje="Sin tickets de soporte." />
      ) : (
        <div className="space-y-2">
          {tickets.map(t => (
            <Link
              key={t.id}
              to={`/soporte/tickets/${t.id}`}
              className="block bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4 hover:border-blue-300 dark:hover:border-slate-600 hover:shadow-sm transition-all"
            >
              <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                  <p className="text-sm font-medium text-slate-800 dark:text-slate-200 truncate">{t.subject}</p>
                  <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    {t.ticket_code} · {t.messages_count ?? 0} mensaje(s)
                  </p>
                </div>
                <div className="flex gap-1.5 shrink-0 flex-wrap justify-end">
                  <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${COLOR_ESTADO[t.status] ?? 'bg-slate-100 text-slate-600'}`}>
                    {t.status}
                  </span>
                  <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${COLOR_PRIORIDAD[t.priority] ?? 'bg-slate-100'}`}>
                    {t.priority}
                  </span>
                </div>
              </div>
              <p className="text-xs text-slate-400 mt-2">
                {new Date(t.created_at).toLocaleDateString('es-CL')}
              </p>
            </Link>
          ))}
        </div>
      )}

      {mostrarCrear && (
        <div className="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
          <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-xl w-full max-w-lg">
            <div className="p-5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
              <h2 className="font-semibold text-slate-800 dark:text-slate-200">Nuevo ticket de soporte</h2>
              <button
                onClick={() => setMostrarCrear(false)}
                className="w-10 h-10 flex items-center justify-center text-slate-400 hover:text-slate-600 text-xl rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700"
              >
                &times;
              </button>
            </div>
            <form onSubmit={crear} className="p-5 space-y-4">
              <div>
                <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Asunto</label>
                <input
                  type="text"
                  required
                  value={form.subject}
                  onChange={e => setForm(f => ({ ...f, subject: e.target.value }))}
                  className="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-base md:text-sm bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200"
                  placeholder="Describe brevemente el problema"
                />
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Categoria</label>
                  <select
                    value={form.category}
                    onChange={e => setForm(f => ({ ...f, category: e.target.value }))}
                    className="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-base md:text-sm bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200"
                  >
                    <option value="ERP">ERP</option>
                    <option value="Facturacion">Facturacion</option>
                    <option value="Soporte">Soporte general</option>
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Prioridad</label>
                  <select
                    value={form.priority}
                    onChange={e => setForm(f => ({ ...f, priority: e.target.value }))}
                    className="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-base md:text-sm bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200"
                  >
                    <option value="baja">Baja</option>
                    <option value="media">Media</option>
                    <option value="alta">Alta</option>
                    <option value="critica">Critica</option>
                  </select>
                </div>
              </div>
              <div>
                <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Descripcion del problema</label>
                <textarea
                  required
                  rows={5}
                  value={form.message}
                  onChange={e => setForm(f => ({ ...f, message: e.target.value }))}
                  className="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-base md:text-sm resize-none bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200"
                  placeholder="Describe el problema con detalle..."
                />
              </div>
              {errorCrear && <p className="text-red-600 text-sm">{errorCrear}</p>}
              <div className="flex justify-end gap-2">
                <button
                  type="button"
                  onClick={() => setMostrarCrear(false)}
                  className="text-sm px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 bg-white dark:bg-slate-800"
                >
                  Cancelar
                </button>
                <button
                  type="submit"
                  disabled={creando}
                  className="text-sm px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg disabled:opacity-60"
                >
                  {creando ? 'Enviando...' : 'Crear ticket'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
