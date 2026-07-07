import React, { useState } from 'react';

const MANUALES = [
  {
    num: '01',
    icono: 'fas fa-map',
    titulo: 'Primeros Pasos',
    subtitulo: 'Interfaz y Navegación',
    desc: 'Conoce la barra lateral, el layout principal y cómo moverte por el sistema.',
  },
  {
    num: '02',
    icono: 'fas fa-chart-line',
    titulo: 'Dashboard',
    subtitulo: 'Métricas y Resumen',
    desc: 'Panel principal con indicadores financieros, deuda y flujo del mes.',
  },
  {
    num: '03',
    icono: 'fas fa-file-invoice-dollar',
    titulo: 'Ventas y Comercial',
    subtitulo: 'Cotizaciones y Clientes',
    desc: 'Gestión de cotizaciones, clientes y el ciclo comercial completo.',
  },
  {
    num: '04',
    icono: 'fas fa-shopping-cart',
    titulo: 'Compras y Gastos',
    subtitulo: 'Facturas y Proveedores',
    desc: 'Registro de facturas de compra, proveedores y control de gastos.',
  },
  {
    num: '05',
    icono: 'fas fa-university',
    titulo: 'Tesorería y Banco',
    subtitulo: 'Cartola y Conciliación',
    desc: 'Nómina de pagos, cartola bancaria y mesa de conciliación.',
  },
  {
    num: '06',
    icono: 'fas fa-book',
    titulo: 'Contabilidad General',
    subtitulo: 'Libro Mayor y Asientos',
    desc: 'Libro mayor, plan de cuentas, asientos manuales y cierre de período.',
  },
  {
    num: '07',
    icono: 'fas fa-boxes',
    titulo: 'Activos Fijos',
    subtitulo: 'Registro y Depreciación',
    desc: 'Alta, baja, depreciación y seguimiento de activos fijos de la empresa.',
  },
  {
    num: '08',
    icono: 'fas fa-warehouse',
    titulo: 'Inventario',
    subtitulo: 'Stock y Logística',
    desc: 'Bodegas, productos, picking, packing, despachos y tomas físicas.',
  },
  {
    num: '09',
    icono: 'fas fa-landmark',
    titulo: 'Gestión Tributaria',
    subtitulo: 'Renta y Declaraciones',
    desc: 'Dashboard de renta, corrección monetaria y declaraciones juradas SII.',
  },
  {
    num: '10',
    icono: 'fas fa-file-alt',
    titulo: 'Facturación Electrónica',
    subtitulo: 'SII y DTE',
    desc: 'Configuración SII, certificado, folios CAF y emisión de DTE.',
  },
  {
    num: '11',
    icono: 'fas fa-users',
    titulo: 'Recursos Humanos',
    subtitulo: 'Remuneraciones y RRHH',
    desc: 'Empleados, contratos, liquidaciones, finiquitos, Previred y LRE.',
  },
  {
    num: '12',
    icono: 'fas fa-headset',
    titulo: 'Soporte',
    subtitulo: 'Tickets y Ayuda',
    desc: 'Apertura y seguimiento de tickets de soporte técnico con Tenri.',
  },
  {
    num: '13',
    icono: 'fas fa-cogs',
    titulo: 'Administración',
    subtitulo: 'Usuarios y Roles',
    desc: 'Gestión del equipo, roles, permisos, propietarios y datos de la empresa.',
  },
  {
    num: '14',
    icono: 'fas fa-question-circle',
    titulo: 'Ayuda y Glosario',
    subtitulo: 'Términos y Contexto',
    desc: 'Glosario de módulos, ayuda contextual y referencia de conceptos clave.',
  },
];

export default function Manuales() {
  const [hover, setHover] = useState(null);

  const pdfUrl = (num) => `/manuales/Tenri-ERP-Cloud-Manual-${num}.pdf`;

  return (
    <div className="min-h-screen bg-slate-950 text-slate-200 p-6">
      <div className="mb-8">
        <div className="flex items-center gap-3 mb-2">
          <div className="w-10 h-10 rounded-xl bg-emerald-500/15 flex items-center justify-center">
            <i className="fas fa-book-open text-emerald-400 text-lg"></i>
          </div>
          <div>
            <h1 className="text-2xl font-bold text-white">Manuales de Usuario</h1>
            <p className="text-slate-400 text-sm">Tenri ERP Cloud — documentación completa por módulo</p>
          </div>
        </div>
        <div className="mt-4 p-4 bg-slate-900 border border-slate-700/60 rounded-xl flex items-start gap-3">
          <i className="fas fa-info-circle text-emerald-400 mt-0.5 flex-shrink-0"></i>
          <p className="text-sm text-slate-300">
            Cada manual cubre un módulo completo del sistema: funcionalidades, flujos de trabajo y
            preguntas frecuentes. Puedes verlos en el navegador o descargarlos en PDF.
          </p>
        </div>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        {MANUALES.map((m) => (
          <div
            key={m.num}
            onMouseEnter={() => setHover(m.num)}
            onMouseLeave={() => setHover(null)}
            className={`
              relative flex flex-col bg-slate-900 border rounded-xl overflow-hidden
              transition-all duration-200
              ${hover === m.num
                ? 'border-emerald-500/60 shadow-lg shadow-emerald-500/10 -translate-y-0.5'
                : 'border-slate-700/60'}
            `}
          >
            <div className="absolute top-3 right-3 text-xs font-mono font-bold text-slate-500 bg-slate-800 px-2 py-0.5 rounded-full">
              #{m.num}
            </div>

            <div className="p-5 pb-3 flex-1">
              <div className="w-10 h-10 rounded-lg bg-emerald-500/10 flex items-center justify-center mb-3">
                <i className={`${m.icono} text-emerald-400`}></i>
              </div>
              <h2 className="font-semibold text-white text-base leading-tight">{m.titulo}</h2>
              <p className="text-emerald-400/80 text-xs font-medium mt-0.5">{m.subtitulo}</p>
              <p className="text-slate-400 text-sm mt-2 leading-relaxed">{m.desc}</p>
            </div>

            <div className="px-5 pb-4 flex gap-2">
              <a
                href={pdfUrl(m.num)}
                target="_blank"
                rel="noopener noreferrer"
                className="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold transition-colors"
              >
                <i className="fas fa-eye"></i>
                Ver
              </a>
              <a
                href={pdfUrl(m.num)}
                download={`Tenri-ERP-Cloud-Manual-${m.num}.pdf`}
                className="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-200 text-xs font-semibold transition-colors"
              >
                <i className="fas fa-download"></i>
                Descargar
              </a>
            </div>
          </div>
        ))}
      </div>

      <div className="mt-8 text-center text-xs text-slate-600">
        14 manuales disponibles · Tenri ERP Cloud
      </div>
    </div>
  );
}
