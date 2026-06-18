import { useEffect, useRef, useState } from 'react';
import { useNavigation } from 'react-router-dom';

export function BarraProgresoNavegacion() {
    const navigation = useNavigation();
    const [width, setWidth] = useState(0);
    const [visible, setVisible] = useState(false);
    const t1 = useRef(null);
    const t2 = useRef(null);
    const t3 = useRef(null);

    useEffect(() => {
        if (navigation.state === 'loading') {
            setVisible(true);
            setWidth(15);
            t1.current = setTimeout(() => setWidth(50), 150);
            t2.current = setTimeout(() => setWidth(75), 500);
        } else if (navigation.state === 'idle' && visible) {
            clearTimeout(t1.current);
            clearTimeout(t2.current);
            setWidth(100);
            t3.current = setTimeout(() => {
                setVisible(false);
                setWidth(0);
            }, 300);
        }

        return () => {
            clearTimeout(t1.current);
            clearTimeout(t2.current);
            clearTimeout(t3.current);
        };
    }, [navigation.state]);

    if (!visible) return null;

    return (
        <div className="fixed top-0 left-0 right-0 z-[9999] h-[2px] bg-transparent pointer-events-none">
            <div
                className="h-full bg-blue-500 shadow-[0_0_8px_rgba(59,130,246,0.6)] transition-all ease-out"
                style={{
                    width: `${width}%`,
                    transitionDuration: width === 100 ? '200ms' : '500ms',
                }}
            />
        </div>
    );
}
