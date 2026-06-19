export function Skeleton({ className = '' }) {
    return (
        <div className={`animate-pulse bg-slate-200 dark:bg-slate-700 rounded ${className}`} />
    );
}

const ANCHOS = [72, 85, 60, 90, 75, 65, 80, 55, 70, 88];

export function TablaSkeleton({ filas = 6, columnas = 5 }) {
    return (
        <>
            {Array.from({ length: filas }).map((_, i) => (
                <tr key={i} className="border-b border-slate-100 dark:border-slate-700">
                    {Array.from({ length: columnas }).map((_, j) => (
                        <td key={j} className="px-4 py-3">
                            <div
                                className="h-3.5 bg-slate-200 dark:bg-slate-700 rounded animate-pulse"
                                style={{ width: `${ANCHOS[(i * columnas + j) % ANCHOS.length]}%` }}
                            />
                        </td>
                    ))}
                </tr>
            ))}
        </>
    );
}
