import React from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './Contextos/AuthContext';
import LayoutPrincipal from './Componentes/Estructura/LayoutPrincipal';
import RegistroFactura from './Modulos/Contabilidad/Componentes/RegistroFactura';
import HistorialFacturas from './Modulos/Contabilidad/Vistas/HistorialFacturas';
import GestionProveedores from './Modulos/Proveedores/GestionProveedores';
import LibroMayor from './Modulos/Contabilidad/Vistas/LibroMayor';
import Login from './Modulos/Autenticacion/Login';
import AnulacionGeneral from './Modulos/Contabilidad/Vistas/AnulacionGeneral';

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
          <Route path="/login" element={<Login />} />

          <Route path="/" element={
            <RutaPrivada>
              <LayoutPrincipal>
                <div className="flex flex-col items-center justify-center h-full text-slate-400">
                  <h1 className="text-2xl font-bold">Bienvenido al ERP Contable</h1>
                </div>
              </LayoutPrincipal>
            </RutaPrivada>
          } />

          <Route path="/facturas/nueva" element={
            <RutaPrivada>
              <LayoutPrincipal>
                <RegistroFactura />
              </LayoutPrincipal>
            </RutaPrivada>
          } />
          <Route path="/facturas/historial" element={
            <RutaPrivada>
              <LayoutPrincipal>
                <HistorialFacturas />
              </LayoutPrincipal>
            </RutaPrivada>
          } />

          <Route path="/proveedores" element={
            <RutaPrivada>
              <LayoutPrincipal>
                <GestionProveedores />
              </LayoutPrincipal>
            </RutaPrivada>
          } />

          <Route path="/contabilidad/libro-mayor" element={
            <RutaPrivada>
              <LayoutPrincipal>
                <LibroMayor />
              </LayoutPrincipal>
            </RutaPrivada>
          } />

          <Route path="/contabilidad/anulacion" element={<AnulacionGeneral />} />

          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  );
}

export default App;