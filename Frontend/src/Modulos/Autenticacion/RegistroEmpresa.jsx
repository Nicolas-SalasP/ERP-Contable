import React, { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { api } from '../../Configuracion/api';
import Swal from 'sweetalert2'; 

const RegistroEmpresa = () => {
    const navigate = useNavigate();
    const [loading, setLoading] = useState(false);
    
    // Estado del formulario
    const [formData, setFormData] = useState({
        empresa_rut: '',
        empresa_razon_social: '',
        admin_nombre: '',
        admin_email: '',
        admin_password: '',
        confirm_password: ''
    });

    const handleChange = (e) => {
        setFormData({ ...formData, [e.target.name]: e.target.value });
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        
        if (formData.admin_password !== formData.confirm_password) {
            Swal.fire('Error', 'Las contraseñas no coinciden.', 'error');
            return;
        }

        setLoading(true);

        try {
            const payload = {
                empresa_rut: formData.empresa_rut,
                empresa_razon_social: formData.empresa_razon_social,
                admin_nombre: formData.admin_nombre,
                admin_email: formData.admin_email,
                admin_password: formData.admin_password
            };

            const res = await api.post('/empresas/registro', payload);

            if (res.success) {
                await Swal.fire({
                    icon: 'success',
                    title: '¡Cuenta Creada!',
                    text: 'Tu empresa ha sido registrada correctamente. Inicia sesión para continuar.',
                    confirmButtonColor: '#059669'
                });
                navigate('/login');
            } else {
                throw new Error(res.message || 'Error al registrar');
            }

        } catch (error) {
            console.error(error);
            const msg = error.message || 'No se pudo completar el registro.';
            Swal.fire('Error de Registro', msg, 'error');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 p-4">
            <div className="max-w-5xl w-full bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col md:flex-row animate-fade-in-up">
                
                {/* SECCIÓN IZQUIERDA: INFORMACIÓN (Similar al Login) */}
                <div className="w-full md:w-5/12 bg-emerald-700 p-12 flex flex-col justify-between text-white relative overflow-hidden">
                    <div className="absolute top-0 left-0 w-40 h-40 bg-white opacity-10 rounded-full -translate-x-10 -translate-y-10"></div>
                    <div className="absolute bottom-0 right-0 w-60 h-60 bg-white opacity-10 rounded-full translate-x-20 translate-y-20"></div>

                    <div className="relative z-10">
                        <h1 className="text-2xl font-bold tracking-wider mb-8 flex items-center gap-2">
                            <i className="fas fa-chart-line"></i> ERP CONTABLE
                        </h1>
                        <h2 className="text-3xl font-extrabold mb-4 leading-tight">Comienza tu viaje.</h2>
                        <p className="text-emerald-100 text-lg opacity-90">Únete a cientos de empresas que ya gestionan su contabilidad de forma inteligente.</p>
                    </div>
                    
                    <div className="relative z-10 mt-8 text-sm text-emerald-200">
                        ¿Ya tienes cuenta?
                        <Link to="/login" className="block mt-2 text-white font-bold underline text-lg">Inicia Sesión aquí</Link>
                    </div>
                </div>

                {/* SECCIÓN DERECHA: FORMULARIO */}
                <div className="w-full md:w-7/12 p-8 md:p-12 bg-gray-50">
                    <div className="text-center mb-8">
                        <h3 className="text-2xl font-bold text-slate-800">Crear Nueva Empresa</h3>
                        <p className="text-slate-500 text-sm">Configura tu entorno de trabajo en segundos</p>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        
                        {/* DATOS DE EMPRESA */}
                        <div className="bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
                            <h4 className="text-xs font-bold text-slate-400 uppercase mb-3">Datos de la Empresa</h4>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-slate-700 mb-1">RUT Empresa</label>
                                    <input
                                        name="empresa_rut"
                                        value={formData.empresa_rut}
                                        onChange={handleChange}
                                        placeholder="76.xxx.xxx-x"
                                        className="w-full border border-gray-300 rounded p-2 text-sm outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-slate-700 mb-1">Razón Social</label>
                                    <input
                                        name="empresa_razon_social"
                                        value={formData.empresa_razon_social}
                                        onChange={handleChange}
                                        placeholder="Mi Empresa SpA"
                                        className="w-full border border-gray-300 rounded p-2 text-sm outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                                        required
                                    />
                                </div>
                            </div>
                        </div>

                        {/* DATOS DE ADMINISTRADOR */}
                        <div className="bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
                            <h4 className="text-xs font-bold text-slate-400 uppercase mb-3">Datos del Administrador</h4>
                            <div className="space-y-3">
                                <div>
                                    <label className="block text-xs font-bold text-slate-700 mb-1">Nombre Completo</label>
                                    <input
                                        name="admin_nombre"
                                        value={formData.admin_nombre}
                                        onChange={handleChange}
                                        className="w-full border border-gray-300 rounded p-2 text-sm outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-slate-700 mb-1">Correo Electrónico</label>
                                    <input
                                        type="email"
                                        name="admin_email"
                                        value={formData.admin_email}
                                        onChange={handleChange}
                                        className="w-full border border-gray-300 rounded p-2 text-sm outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                                        required
                                    />
                                </div>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-xs font-bold text-slate-700 mb-1">Contraseña</label>
                                        <input
                                            type="password"
                                            name="admin_password"
                                            value={formData.admin_password}
                                            onChange={handleChange}
                                            className="w-full border border-gray-300 rounded p-2 text-sm outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                                            required
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-xs font-bold text-slate-700 mb-1">Confirmar</label>
                                        <input
                                            type="password"
                                            name="confirm_password"
                                            value={formData.confirm_password}
                                            onChange={handleChange}
                                            className="w-full border border-gray-300 rounded p-2 text-sm outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                                            required
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button
                            type="submit"
                            disabled={loading}
                            className="w-full py-3 bg-emerald-600 text-white font-bold rounded-lg hover:bg-emerald-700 shadow-lg transition-all transform active:scale-95 disabled:opacity-50 disabled:scale-100"
                        >
                            {loading ? (
                                <span className="flex items-center justify-center gap-2">
                                    <i className="fas fa-circle-notch fa-spin"></i> Registrando...
                                </span>
                            ) : 'Registrar Empresa'}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    );
};

export default RegistroEmpresa;