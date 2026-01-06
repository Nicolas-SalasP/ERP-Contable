import React from 'react';

const ModalGenerico = ({ 
    isOpen, 
    onClose,
    onConfirm,
    title, 
    message,     
    type = 'info', 
    showCancel = false, 
    confirmText = 'Aceptar',
    cancelText = 'Cancelar'
}) => {
    if (!isOpen) return null;

    const styles = {
        success: { bg: 'bg-emerald-100', text: 'text-emerald-800', iconBg: 'bg-emerald-200', btn: 'bg-emerald-600 hover:bg-emerald-700' },
        danger:  { bg: 'bg-red-100', text: 'text-red-800', iconBg: 'bg-red-200', btn: 'bg-red-600 hover:bg-red-700' },
        warning: { bg: 'bg-yellow-100', text: 'text-yellow-800', iconBg: 'bg-yellow-200', btn: 'bg-yellow-500 hover:bg-yellow-600' },
        info:    { bg: 'bg-blue-100', text: 'text-blue-800', iconBg: 'bg-blue-200', btn: 'bg-blue-600 hover:bg-blue-700' }
    };

    const currentStyle = styles[type] || styles.info;

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            {/* Overlay oscuro */}
            <div className="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div className="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onClick={showCancel ? onClose : undefined}></div>

                <span className="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                {/* Contenedor Modal */}
                <div className="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full animate-fade-in-up">
                    <div className="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div className="sm:flex sm:items-start">
                            
                            {/* Icono Dinámico */}
                            <div className={`mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full ${currentStyle.bg} sm:mx-0 sm:h-10 sm:w-10`}>
                                {type === 'success' && <svg className={`h-6 w-6 ${currentStyle.text}`} fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" /></svg>}
                                {type === 'danger' && <svg className={`h-6 w-6 ${currentStyle.text}`} fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>}
                                {type === 'warning' && <svg className={`h-6 w-6 ${currentStyle.text}`} fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>}
                                {type === 'info' && <svg className={`h-6 w-6 ${currentStyle.text}`} fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>}
                            </div>

                            <div className="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 className="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                    {title}
                                </h3>
                                <div className="mt-2">
                                    <div className="text-sm text-gray-500">
                                        {message}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    {/* Botones Footer */}
                    <div className="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button 
                            type="button" 
                            onClick={onConfirm || onClose}
                            className={`w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 text-base font-medium text-white focus:outline-none focus:ring-2 focus:ring-offset-2 sm:ml-3 sm:w-auto sm:text-sm ${currentStyle.btn}`}
                        >
                            {confirmText}
                        </button>
                        
                        {showCancel && (
                            <button 
                                type="button" 
                                onClick={onClose}
                                className="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                {cancelText}
                            </button>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
};

export default ModalGenerico;