import { Sun, Monitor, CloudMoon } from 'lucide-react';
import { useTema } from '../../hooks/useTema';

const OPCIONES = [
    { valor: 'claro',      Icono: Sun,       label: 'Claro' },
    { valor: 'sistema',    Icono: Monitor,   label: 'Automático (sistema)' },
    { valor: 'gris-oscuro', Icono: CloudMoon, label: 'Gris oscuro' },
];

export function ToggleTema() {
    const { tema, setTema } = useTema();

    return (
        <div
            className="flex items-center bg-slate-100 dark:bg-slate-700 rounded-lg p-0.5 gap-0.5"
            role="group"
            aria-label="Tema de color"
        >
            {OPCIONES.map(({ valor, Icono, label }) => (
                <button
                    key={valor}
                    onClick={() => setTema(valor)}
                    title={label}
                    aria-pressed={tema === valor}
                    className={`p-1.5 rounded-md transition-colors ${
                        tema === valor
                            ? 'bg-white dark:bg-slate-500 text-slate-900 dark:text-slate-100 shadow-sm'
                            : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'
                    }`}
                >
                    <Icono size={14} strokeWidth={1.75} />
                </button>
            ))}
        </div>
    );
}
