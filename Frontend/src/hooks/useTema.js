import { useEffect, useState } from 'react';

const STORAGE_KEY = 'tenri_tema';

function aplicarTema(tema) {
    const el = document.documentElement;
    if (tema === 'oscuro') {
        el.classList.add('dark');
    } else if (tema === 'claro') {
        el.classList.remove('dark');
    } else {
        window.matchMedia('(prefers-color-scheme: dark)').matches
            ? el.classList.add('dark')
            : el.classList.remove('dark');
    }
}

export function useTema() {
    const [tema, setTemaState] = useState(() => {
        try { return localStorage.getItem(STORAGE_KEY) ?? 'sistema'; } catch { return 'sistema'; }
    });

    const setTema = (nuevoTema) => {
        setTemaState(nuevoTema);
        try { localStorage.setItem(STORAGE_KEY, nuevoTema); } catch {}
        aplicarTema(nuevoTema);
    };

    useEffect(() => {
        if (tema !== 'sistema') return;
        const mq = window.matchMedia('(prefers-color-scheme: dark)');
        const handler = () => aplicarTema('sistema');
        mq.addEventListener('change', handler);
        return () => mq.removeEventListener('change', handler);
    }, [tema]);

    return { tema, setTema };
}
