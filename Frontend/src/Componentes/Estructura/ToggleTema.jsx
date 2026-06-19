import { Sun, Monitor, CloudMoon } from 'lucide-react';
import { useTema } from '../../hooks/useTema';

const OPCIONES = [
    { valor: 'claro',       Icono: Sun,       label: 'Claro' },
    { valor: 'sistema',     Icono: Monitor,   label: 'Auto' },
    { valor: 'gris-oscuro', Icono: CloudMoon, label: 'Oscuro' },
];

export function ToggleTema() {
    const { tema, setTema } = useTema();

    return (
        <div
            className="flex items-center bg-slate-100 dark:bg-slate-700 rounded-lg p-0.5 gap-0.5"
            role="group"
            aria-label="Tema de color"
        >
            {OPCIONES.map(({ valor, Icono, label }) => {
                const Icon = Icono;
                return (
                    <button
                        key={valor}
                        onClick={() => setTema(valor)}
                        aria-pressed={tema === valor}
                        className={`flex items-center gap-1 px-2 py-1 rounded-md transition-colors text-xs font-medium ${
                            tema === valor
                                ? 'bg-white dark:bg-slate-500 text-slate-900 dark:text-slate-100 shadow-sm'
                                : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'
                        }`}
                    >
                        <Icon size={13} strokeWidth={1.75} />
                        <span>{label}</span>
                    </button>
                );
            })}
        </div>
    );
}
