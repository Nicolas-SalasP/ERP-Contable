import React from 'react';

class ErrorBoundary extends React.Component {
    constructor(props) {
        super(props);
        this.state = { hasError: false };
    }

    static getDerivedStateFromError() {
        return { hasError: true };
    }

    handleRecargar = () => {
        if (typeof window !== 'undefined') {
            window.location.reload();
        }
    };

    render() {
        if (!this.state.hasError) {
            return this.props.children;
        }

        return (
            <div data-testid="error-boundary" className="min-h-[50vh] flex flex-col items-center justify-center p-8 text-center">
                <div className="bg-red-50 border border-red-200 rounded-2xl p-8 max-w-md shadow-sm">
                    <div className="text-4xl mb-3" aria-hidden="true">⚠️</div>
                    <h2 className="text-xl font-black text-slate-800 mb-2">Algo salió mal</h2>
                    <p className="text-sm text-slate-500 mb-6">
                        {this.props.mensaje || 'Ocurrió un error inesperado en esta sección. Tu sesión sigue activa.'}
                    </p>
                    <button
                        type="button"
                        onClick={this.handleRecargar}
                        className="px-5 py-2.5 bg-slate-900 text-white font-bold rounded-lg hover:bg-slate-800 transition-colors"
                    >
                        Recargar
                    </button>
                </div>
            </div>
        );
    }
}

export default ErrorBoundary;
