import { useState, useEffect, useRef } from 'react';
import { useParams, Link } from 'react-router-dom';
import { api } from '../../../Configuracion/api';

const COLOR_ESTADO = {
  nuevo: 'bg-blue-100 text-blue-700',
  abierto: 'bg-amber-100 text-amber-700',
  esperando_cliente: 'bg-purple-100 text-purple-700',
  resuelto: 'bg-green-100 text-green-700',
  cerrado: 'bg-slate-100 text-slate-600',
};

export default function SoporteTicketDetalle() {
  const { id }                        = useParams();
  const [ticket, setTicket]           = useState(null);
  const [cargando, setCargando]       = useState(true);
  const [error, setError]             = useState('');
  const [mensaje, setMensaje]         = useState('');
  const [enviando, setEnviando]       = useState(false);
  const [errorReply, setErrorReply]   = useState('');
  const hiloRef                       = useRef(null);

  const cargar = async (signal) => {
    setError('');
    try {
      const { data } = await api.get(`/soporte/tickets/${id}`, { signal });
      setTicket(data);
      setTimeout(() => {
        if (hiloRef.current) {
          hiloRef.current.scrollTop = hiloRef.current.scrollHeight;
        }
      }, 80);
    } catch (e) {
      if (e?.name !== 'AbortError' && e?.name !== 'CanceledError') {
        setError('No se pudo cargar el ticket.');
      }
    } finally {
      setCargando(false);
    }
  };

  useEffect(() => {
    const controller = new AbortController();
    cargar(controller.signal);
    return () => controller.abort();
  }, [id]);

  const responder = async (e) => {
    e.preventDefault();
    if (!mensaje.trim()) return;
    setEnviando(true);
    setErrorReply('');
    try {
      await api.post(`/soporte/tickets/${id}/reply`, { message: mensaje });
      setMensaje('');
      await cargar();
    } catch (e) {
      setErrorReply(e?.response?.data?.message ?? 'No se pudo enviar la respuesta.');
    } finally {
      setEnviando(false);
    }
  };

  const cerrado = ticket ? ['resuelto', 'cerrado'].includes(ticket.status) : false;

  if (cargando) {
    return <p className="text-slate-500 text-sm p-6">Cargando...</p>;
  }

  if (error && !ticket) {
    return <p className="text-red-600 text-sm p-6">{error}</p>;
  }

  return (
    <div className="max-w-3xl mx-auto space-y-4">
      <Link to="/soporte/tickets" className="text-sm text-blue-600 hover:underline">
        &larr; Volver a tickets
      </Link>

      <div className="bg-white border border-slate-200 rounded-xl p-5">
        <div className="flex items-start justify-between gap-2 mb-2">
          <h1 className="text-base font-semibold text-slate-800">{ticket.subject}</h1>
          <span className={`text-xs font-medium px-2 py-1 rounded-full shrink-0 ${COLOR_ESTADO[ticket.status] ?? 'bg-slate-100 text-slate-600'}`}>
            {ticket.status}
          </span>
        </div>
        <p className="text-xs text-slate-500">
          {ticket.ticket_code} · {ticket.category} · Prioridad: {ticket.priority}
        </p>
        {ticket.assignee && (
          <p className="text-xs text-slate-500 mt-1">Asignado a: {ticket.assignee.name}</p>
        )}
      </div>

      <div
        ref={hiloRef}
        className="bg-white border border-slate-200 rounded-xl divide-y divide-slate-100 max-h-[50vh] overflow-y-auto"
      >
        {(ticket.messages ?? []).map(m => {
          const esStaff = m.user_id !== null && m.user_id !== undefined;
          const autor   = esStaff
            ? (m.user?.name ?? 'Soporte Tenri')
            : (m.autor_nombre ?? ticket.origen_nombre ?? 'Tu');

          return (
            <div key={m.id} className={`p-4 ${esStaff ? 'bg-slate-50' : ''}`}>
              <div className="flex items-center gap-2 mb-1 flex-wrap">
                <span className="text-xs font-medium text-slate-700">{autor}</span>
                {esStaff && (
                  <span className="text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded">Soporte</span>
                )}
                <span className="text-xs text-slate-400 ml-auto">
                  {new Date(m.created_at).toLocaleString('es-CL')}
                </span>
              </div>
              <p className="text-sm text-slate-700 whitespace-pre-wrap">{m.message}</p>
            </div>
          );
        })}
      </div>

      {!cerrado ? (
        <form onSubmit={responder} className="bg-white border border-slate-200 rounded-xl p-4 space-y-3">
          <textarea
            rows={4}
            value={mensaje}
            onChange={e => setMensaje(e.target.value)}
            placeholder="Escribe tu respuesta..."
            className="w-full border border-slate-300 rounded-lg px-3 py-2 text-base md:text-sm resize-none"
            disabled={enviando}
          />
          {errorReply && <p className="text-red-600 text-sm">{errorReply}</p>}
          <div className="flex justify-end">
            <button
              type="submit"
              disabled={enviando || !mensaje.trim()}
              className="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg disabled:opacity-60"
            >
              {enviando ? 'Enviando...' : 'Responder'}
            </button>
          </div>
        </form>
      ) : (
        <p className="text-sm text-slate-500 text-center py-3">
          Este ticket esta {ticket.status}. No se pueden agregar mas respuestas.
        </p>
      )}
    </div>
  );
}
