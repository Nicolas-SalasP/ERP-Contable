import React from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './Contextos/AuthContext';
import LayoutPrincipal from './Componentes/Estructura/LayoutPrincipal';

// --- IMPORTACIONES DE VISTAS ---
import Login from './Modulos/Autenticacion/Login';
import RecuperarPassword from './Modulos/Autenticacion/RecuperarPassword';
import Dashboard from './Modulos/Dashboard/Dashboard';
import RegistroFactura from './Modulos/Contabilidad/Componentes/RegistroFactura';
import HistorialFacturas from './Modulos/Contabilidad/Vistas/HistorialFacturas';
import GestionProveedores from './Modulos/Proveedores/GestionProveedores';
import LibroMayor from './Modulos/Contabilidad/Vistas/LibroMayor';
import AnulacionGeneral from './Modulos/Contabilidad/Vistas/AnulacionGeneral';
import GestionCotizaciones from './Modulos/Cotizaciones/GestionCotizaciones';
import CrearCotizacion from './Modulos/Cotizaciones/CrearCotizacion';
import GestionClientes from './Modulos/Clientes/GestionClientes';
import PerfilEmpresa from './Modulos/Empresa/PerfilEmpresa';
import GestionActivos from './Modulos/Activos/Vistas/GestionActivos';
import VisorAuditoriaFactura from './Modulos/Contabilidad/Vistas/VisorAuditoriaFactura';
import AdministradorCuentas from './Modulos/Contabilidad/Vistas/AdministradorCuentas';
import DashboardRenta from './Modulos/Tributario/Vistas/DashboardRenta';
import NominaPagos from './Modulos/Banco/Vistas/NominaPagos';
import CartolaBancaria from './Modulos/Banco/Vistas/CartolaBancaria';
import MesaConciliacion from './Modulos/Banco/Vistas/MesaConciliacion';
import CierreF29 from './Modulos/Contabilidad/Vistas/CierreF29';
import AsientoManual from './Modulos/Contabilidad/Vistas/AsientoManual';
import VisorProveedor from './Modulos/Proveedores/VisorProveedor';

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
          <Route path="/recuperar" element={<RecuperarPassword />} />

          <Route path="/" element={
            <RutaPrivada>
              <LayoutPrincipal>
                <Dashboard />
              </LayoutPrincipal>
            </RutaPrivada>
          } />

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

          <Route path="/contabilidad/plan-cuentas" element={
            <RutaPrivada>
              <LayoutPrincipal>
                <AdministradorCuentas />
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

          <Route path="/activos" element={
            <RutaPrivada>
              <LayoutPrincipal>
                <GestionActivos />
              </LayoutPrincipal>
            </RutaPrivada>
          } />

          <Route path="/tributario/renta" element={
            <RutaPrivada>
              <LayoutPrincipal>
                <DashboardRenta />
              </LayoutPrincipal>
            </RutaPrivada>
          } />

          <Route path="/facturas/:id/auditoria" element={
            <RutaPrivada>
              <LayoutPrincipal>
                <VisorAuditoriaFactura />
              </LayoutPrincipal>
            </RutaPrivada>
          } />

          <Route path="/banco/nomina-pagos" element={
            <RutaPrivada>
              <LayoutPrincipal>
                <NominaPagos />
              </LayoutPrincipal>
            </RutaPrivada>
          } />

          <Route path="/banco/cartola" element={
            <RutaPrivada>
              <LayoutPrincipal>
                <CartolaBancaria />
              </LayoutPrincipal>
            </RutaPrivada>
          } />

          <Route path="/banco/conciliacion" element={
            <RutaPrivada>
              <LayoutPrincipal>
                <MesaConciliacion />
              </LayoutPrincipal>
            </RutaPrivada>
          } />

          <Route path="/contabilidad/cierre-f29" element={
            <RutaPrivada>
              <LayoutPrincipal>
                <CierreF29 />
              </LayoutPrincipal>
            </RutaPrivada>
          } />

          <Route path="/contabilidad/asiento-manual" element={
            <RutaPrivada>
              <LayoutPrincipal>
                <AsientoManual />
              </LayoutPrincipal>
            </RutaPrivada>
          } />

          <Route path="/proveedores/visor" element={<RutaPrivada><LayoutPrincipal><VisorProveedor /></LayoutPrincipal></RutaPrivada>} />
          <Route path="/proveedores/visor/:id" element={<RutaPrivada><LayoutPrincipal><VisorProveedor /></LayoutPrincipal></RutaPrivada>} />

          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  );
}

export default App; 