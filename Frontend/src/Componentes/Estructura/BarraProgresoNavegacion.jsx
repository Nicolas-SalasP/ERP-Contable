import { useEffect, useRef, useState } from 'react';
import { useLocation } from 'react-router-dom';

export function BarraProgresoNavegacion() {
    const location = useLocation();
    const [width, setWidth] = useState(0);
    const [visible, setVisible] = useState(false);
    const montado = useRef(false);
    const timers = useRef([]);

    const limpiar = () => timers.current.forEach(clearTimeout);

    useEffect(() => {
        // Ignorar montaje inicial
        if (!montado.current) { montado.current = true; return; }

        limpiar();
        setVisible(true);
        setWidth(20);

        timers.current = [
            setTimeout(() => setWidth(60), 120),
            setTimeout(() => setWidth(85), 300),
            setTimeout(() => setWidth(100), 500),
            setTimeout(() => { setVisible(false); setWidth(0); }, 720),
        ];

        return limpiar;
    }, [location.pathname]);

    if (!visible) return null;

    return (
        <div className="fixed top-0 left-0 right-0 z-[9999] h-[2px] pointer-events-none">
            <div
                className="h-full bg-blue-500 shadow-[0_0_8px_rgba(59,130,246,0.6)] transition-all ease-out"
                style={{ width: `${width}%`, transitionDuration: width === 100 ? '180ms' : '400ms' }}
            />
        </div>
    );
}
