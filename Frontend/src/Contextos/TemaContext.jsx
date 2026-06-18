import { createContext, useContext, useState, useEffect } from 'react';

const STORAGE_KEY = 'tenri_tema';

function aplicarTema(tema) {
    const el = document.documentElement;
    if (tema === 'gris-oscuro') {
        el.classList.add('dark');
        el.style.colorScheme = 'dark';
    } else if (tema === 'claro') {
        el.classList.remove('dark');
        el.style.colorScheme = 'light';
    } else {
        const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        isDark ? el.classList.add('dark') : el.classList.remove('dark');
        el.style.colorScheme = 'normal';
    }
}

const TemaContext = createContext(null);

export function TemaProvider({ children }) {
    const [tema, setTemaState] = useState(() => {
        try { return localStorage.getItem(STORAGE_KEY) ?? 'sistema'; } catch { return 'sistema'; }
    });

    const setTema = (nuevoTema) => {
        setTemaState(nuevoTema);
        try { localStorage.setItem(STORAGE_KEY, nuevoTema); } catch {}
        aplicarTema(nuevoTema);
    };

    // Sincroniza la clase dark con el tema almacenado al montar.
    useEffect(() => {
        aplicarTema(tema);
    }, []);

    // Escucha cambios del sistema cuando tema = 'sistema'.
    useEffect(() => {
        if (tema !== 'sistema') return;
        const mq = window.matchMedia('(prefers-color-scheme: dark)');
        const handler = () => aplicarTema('sistema');
        mq.addEventListener('change', handler);
        return () => mq.removeEventListener('change', handler);
    }, [tema]);

    return (
        <TemaContext.Provider value={{ tema, setTema }}>
            {children}
        </TemaContext.Provider>
    );
}

export function useTema() {
    const ctx = useContext(TemaContext);
    if (!ctx) throw new Error('useTema debe usarse dentro de TemaProvider');
    return ctx;
}
