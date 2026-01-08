import React from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './Contextos/AuthContext';
import LayoutPrincipal from './Componentes/Estructura/LayoutPrincipal';

// --- IMPORTACIONES DE VISTAS ---
import Login from './Modulos/Autenticacion/Login';
import RegistroEmpresa from './Modulos/Autenticacion/RegistroEmpresa';
import RecuperarPassword from './Modulos/Autenticacion/RecuperarPassword';
import RegistroFactura from './Modulos/Contabilidad/Componentes/RegistroFactura';
import HistorialFacturas from './Modulos/Contabilidad/Vistas/HistorialFacturas';
import GestionProveedores from './Modulos/Proveedores/GestionProveedores';
import LibroMayor from './Modulos/Contabilidad/Vistas/LibroMayor';
import AnulacionGeneral from './Modulos/Contabilidad/Vistas/AnulacionGeneral';
import GestionCotizaciones from './Modulos/Cotizaciones/GestionCotizaciones';
import CrearCotizacion from './Modulos/Cotizaciones/CrearCotizacion';
import GestionClientes from './Modulos/Clientes/GestionClientes';
import PerfilEmpresa from './Modulos/Empresa/PerfilEmpresa';

const RutaPrivada = ({ children }) => {
  const { isAuthenticated, loading } = useAuth();

  if (loading) return <div>Cargando...</div>;

  return isAuthenticated ? children : <Navigate to="/login" />;
};

function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <Routes>
          {/* RUTAS PÚBLICAS */}
          <Route path="/login" element={<Login />} />
          <Route path="/registro" element={<RegistroEmpresa />} />
          <Route path="/recuperar" element={<RecuperarPassword />} />

          {/* RUTA DE BIENVENIDA / DASHBOARD */}
          <Route path="/" element={
            <RutaPrivada>
              <LayoutPrincipal>
                <div className="flex flex-col items-center justify-center h-full text-slate-400">
                  <h1 className="text-2xl font-bold">Bienvenido al ERP Contable</h1>
                  <p>Selecciona una opción del menú lateral.</p>
                </div>
              </LayoutPrincipal>
            </RutaPrivada>
          } />

          {/* RESTO DE RUTAS PRIVADAS (Sin cambios) */}
          <Route path="/facturas/nueva" element={
            <RutaPrivada>
              <LayoutPrincipal><RegistroFactura /></LayoutPrincipal>
            </RutaPrivada>
          } />

          <Route path="/facturas/historial" element={
            <RutaPrivada>
              <LayoutPrincipal><HistorialFacturas /></LayoutPrincipal>
            </RutaPrivada>
          } />

          <Route path="/cotizaciones" element={
            <RutaPrivada>
              <LayoutPrincipal><GestionCotizaciones /></LayoutPrincipal>
            </RutaPrivada>
          } />

          <Route path="/cotizaciones/nueva" element={
            <RutaPrivada>
              <LayoutPrincipal><CrearCotizacion /></LayoutPrincipal>
            </RutaPrivada>
          } />

          <Route path="/clientes" element={
            <RutaPrivada>
              <LayoutPrincipal><GestionClientes /></LayoutPrincipal>
            </RutaPrivada>
          } />

          <Route path="/proveedores" element={
            <RutaPrivada>
              <LayoutPrincipal><GestionProveedores /></LayoutPrincipal>
            </RutaPrivada>
          } />

          <Route path="/contabilidad/libro-mayor" element={
            <RutaPrivada>
              <LayoutPrincipal><LibroMayor /></LayoutPrincipal>
            </RutaPrivada>
          } />

          <Route path="/contabilidad/anulacion" element={
            <RutaPrivada>
              <LayoutPrincipal> 
                <AnulacionGeneral />
              </LayoutPrincipal>
            </RutaPrivada>
          } />

          <Route path="/empresa/perfil" element={
            <RutaPrivada>
              <LayoutPrincipal>
                <PerfilEmpresa />
              </LayoutPrincipal>
            </RutaPrivada>
          } />

          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  );
}

export default App;