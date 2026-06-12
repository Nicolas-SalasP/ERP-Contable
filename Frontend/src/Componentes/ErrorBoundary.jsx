import React from 'react';
import { captureException } from '../Configuracion/sentry';

// Error boundaries deben ser componentes de clase (React no expone hook equivalente).
class ErrorBoundary extends React.Component {
    constructor(props) {
        super(props);
        this.state = { hasError: false, error: null };
    }

    static getDerivedStateFromError(error) {
        return { hasError: true, error };
    }

    componentDidCatch(error, info) {
        if (import.meta.env?.DEV) {
            console.error('ErrorBoundary capturó un error:', error, info);
        }
        captureException(error, { extra: { componentStack: info?.componentStack } });
    }

    handleReintentar = () => {
        this.setState({ hasError: false, error: null });
    };

    handleRecargar = () => {
        if (typeof window !== 'undefined') window.location.reload();
    };

    render() {
        if (!this.state.hasError) return this.props.children;

        return (
            <div data-testid="error-boundary" className="min-h-[50vh] flex flex-col items-center justify-center p-8 text-center">
                <div className="bg-red-50 border border-red-200 rounded-2xl p-8 max-w-md shadow-sm">
                    <div className="text-4xl mb-3" aria-hidden="true">⚠️</div>
                    <h2 className="text-xl font-black text-slate-800 mb-2">Algo salió mal</h2>
                    <p className="text-sm text-slate-500 mb-6">
                        {this.props.mensaje || 'Ocurrió un error inesperado en esta sección. Tu sesión sigue activa.'}
                    </p>
                    {import.meta.env?.DEV && this.state.error && (
                        <p className="text-xs text-red-500 font-mono mb-4 text-left bg-red-100 rounded p-2 max-h-24 overflow-y-auto">
                            {this.state.error.message}
                        </p>
                    )}
                    <div className="flex flex-col gap-2 items-center">
                        <button
                            onClick={this.handleReintentar}
                            className="px-5 py-2.5 bg-slate-900 text-white font-bold rounded-lg hover:bg-slate-800 transition-colors"
                        >
                            Reintentar
                        </button>
                        <button
                            onClick={this.handleRecargar}
                            className="text-sm text-slate-400 hover:text-slate-600 underline bg-transparent border-0 cursor-pointer"
                        >
                            Recargar página
                        </button>
                    </div>
                </div>
            </div>
        );
    }
}

export default ErrorBoundary;
