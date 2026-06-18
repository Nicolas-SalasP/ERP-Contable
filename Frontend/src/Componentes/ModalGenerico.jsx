import React from 'react';
import { Check, AlertTriangle, Info } from 'lucide-react';

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
            <div className="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div className="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onClick={showCancel ? onClose : undefined}></div>

                <span className="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div className="inline-block align-bottom bg-white dark:bg-slate-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle max-w-[90vw] sm:max-w-lg w-full animate-fade-in-up">
                    <div className="bg-white dark:bg-slate-800 p-3 sm:p-4 sm:pb-4">
                        <div className="sm:flex sm:items-start">

                            <div className={`mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full ${currentStyle.bg} sm:mx-0 sm:h-10 sm:w-10`}>
                                {type === 'success' && <Check size={24} strokeWidth={1.75} className={currentStyle.text} />}
                                {type === 'danger' && <AlertTriangle size={24} strokeWidth={1.75} className={currentStyle.text} />}
                                {type === 'warning' && <AlertTriangle size={24} strokeWidth={1.75} className={currentStyle.text} />}
                                {type === 'info' && <Info size={24} strokeWidth={1.75} className={currentStyle.text} />}
                            </div>

                            <div className="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 className="text-lg leading-6 font-medium text-gray-900 dark:text-slate-100" id="modal-title">
                                    {title}
                                </h3>
                                <div className="mt-2">
                                    <div className="text-sm text-gray-500 dark:text-slate-400">
                                        {message}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div className="bg-gray-50 dark:bg-slate-900 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
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
                                className="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-slate-600 shadow-sm px-4 py-2 bg-white dark:bg-slate-700 text-base font-medium text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
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