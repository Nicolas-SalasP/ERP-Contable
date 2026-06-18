import { createContext, useContext, useState, useEffect } from 'react';

const STORAGE_KEY = 'tenri_tema';

function aplicarTema(tema) {
    const el = document.documentElement;
    if (tema === 'gris-oscuro') {
        el.classList.add('dark');
    } else if (tema === 'claro') {
        el.classList.remove('dark');
    } else {
        // sistema — sigue prefers-color-scheme
        window.matchMedia('(prefers-color-scheme: dark)').matches
            ? el.classList.add('dark')
            : el.classList.remove('dark');
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
    // eslint-disable-next-line react-hooks/exhaustive-deps
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
