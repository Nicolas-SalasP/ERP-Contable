import React from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './Contextos/AuthContext';
import { usePermisos } from './Contextos/Permisos';
import LayoutPrincipal from './Componentes/Estructura/LayoutPrincipal';

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
import CrearEmpresa from './Modulos/Bienvenida/CrearEmpresa';
import GestionUsuarios from './Modulos/Administrador/GestionUsuarios';
import GestionRoles from './Modulos/Administrador/GestionRoles';

const RutaPrivada = ({ children, requireEmpresa = true }) => {
  const { isAuthenticated, loading, user } = useAuth();

  if (loading) return <div>Cargando...</div>;

  if (!isAuthenticated) {
    return <Navigate to="/login" />;
  }
  if (requireEmpresa && (!user?.empresa_id)) {
    return <Navigate to="/crear-empresa" />;
  }
  if (!requireEmpresa && user?.empresa_id) {
    return <Navigate to="/" />;
  }

  return children;
};

const RutaProtegida = ({ permiso, children }) => {
  const { tienePermiso } = usePermisos();
  if (!tienePermiso(permiso)) {
    return <Navigate to="/" replace />;
  }
  return children;
};

function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <Routes>
          <Route path="/login" element={<Login />} />
          <Route path="/recuperar" element={<RecuperarPassword />} />
          <Route path="/crear-empresa" element={
            <RutaPrivada requireEmpresa={false}>
              <CrearEmpresa />
            </RutaPrivada>
          } />

          <Route path="/" element={
            <RutaPrivada>
              <LayoutPrincipal>
                <Dashboard />
              </LayoutPrincipal>
            </RutaPrivada>
          } />

          <Route path="/facturas/nueva" element={
            <RutaPrivada>
              <RutaProtegida permiso="compras.crear">
                <LayoutPrincipal><RegistroFactura /></LayoutPrincipal>
              </RutaProtegida>
            </RutaPrivada>
          } />

          <Route path="/facturas/historial" element={
            <RutaPrivada>
              <RutaProtegida permiso="compras.ver">
                <LayoutPrincipal><HistorialFacturas /></LayoutPrincipal>
              </RutaProtegida>
            </RutaPrivada>
          } />

          <Route path="/cotizaciones" element={
            <RutaPrivada>
              <RutaProtegida permiso="ventas.ver">
                <LayoutPrincipal><GestionCotizaciones /></LayoutPrincipal>
              </RutaProtegida>
            </RutaPrivada>
          } />

          <Route path="/cotizaciones/nueva" element={
            <RutaPrivada>
              <RutaProtegida permiso="ventas.crear">
                <LayoutPrincipal><CrearCotizacion /></LayoutPrincipal>
              </RutaProtegida>
            </RutaPrivada>
          } />

          <Route path="/clientes" element={
            <RutaPrivada>
              <RutaProtegida permiso="clientes.ver">
                <LayoutPrincipal><GestionClientes /></LayoutPrincipal>
              </RutaProtegida>
            </RutaPrivada>
          } />

          <Route path="/proveedores" element={
            <RutaPrivada>
              <RutaProtegida permiso="proveedores.ver">
                <LayoutPrincipal><GestionProveedores /></LayoutPrincipal>
              </RutaProtegida>
            </RutaPrivada>
          } />

          <Route path="/contabilidad/libro-mayor" element={
            <RutaPrivada>
              <RutaProtegida permiso="contabilidad.ver">
                <LayoutPrincipal><LibroMayor /></LayoutPrincipal>
              </RutaProtegida>
            </RutaPrivada>
          } />

          <Route path="/contabilidad/anulacion" element={
            <RutaPrivada>
              <RutaProtegida permiso="contabilidad.ver">
                <LayoutPrincipal><AnulacionGeneral /></LayoutPrincipal>
              </RutaProtegida>
            </RutaPrivada>
          } />

          <Route path="/contabilidad/plan-cuentas" element={
            <RutaPrivada>
              <RutaProtegida permiso="contabilidad.ver">
                <LayoutPrincipal><AdministradorCuentas /></LayoutPrincipal>
              </RutaProtegida>
            </RutaPrivada>
          } />

          <Route path="/empresa/perfil" element={
            <RutaPrivada>
              <LayoutPrincipal><PerfilEmpresa /></LayoutPrincipal>
            </RutaPrivada>
          } />

          <Route path="/activos" element={
            <RutaPrivada>
              <RutaProtegida permiso="activos.ver">
                <LayoutPrincipal><GestionActivos /></LayoutPrincipal>
              </RutaProtegida>
            </RutaPrivada>
          } />

          <Route path="/tributario/renta" element={
            <RutaPrivada>
              <RutaProtegida permiso="tributario.ver">
                <LayoutPrincipal><DashboardRenta /></LayoutPrincipal>
              </RutaProtegida>
            </RutaPrivada>
          } />

          <Route path="/facturas/:id/auditoria" element={
            <RutaPrivada>
              <RutaProtegida permiso="contabilidad.ver">
                <LayoutPrincipal><VisorAuditoriaFactura /></LayoutPrincipal>
              </RutaProtegida>
            </RutaPrivada>
          } />

          <Route path="/banco/nomina-pagos" element={
            <RutaPrivada>
              <RutaProtegida permiso="tesoreria.ver">
                <LayoutPrincipal><NominaPagos /></LayoutPrincipal>
              </RutaProtegida>
            </RutaPrivada>
          } />

          <Route path="/banco/cartola" element={
            <RutaPrivada>
              <RutaProtegida permiso="tesoreria.ver">
                <LayoutPrincipal><CartolaBancaria /></LayoutPrincipal>
              </RutaProtegida>
            </RutaPrivada>
          } />

          <Route path="/banco/conciliacion" element={
            <RutaPrivada>
              <RutaProtegida permiso="tesoreria.ver">
                <LayoutPrincipal><MesaConciliacion /></LayoutPrincipal>
              </RutaProtegida>
            </RutaPrivada>
          } />

          <Route path="/contabilidad/cierre-f29" element={
            <RutaPrivada>
              <RutaProtegida permiso="tributario.ver">
                <LayoutPrincipal><CierreF29 /></LayoutPrincipal>
              </RutaProtegida>
            </RutaPrivada>
          } />

          <Route path="/contabilidad/asiento-manual" element={
            <RutaPrivada>
              <RutaProtegida permiso="contabilidad.crear">
                <LayoutPrincipal><AsientoManual /></LayoutPrincipal>
              </RutaProtegida>
            </RutaPrivada>
          } />

          <Route path="/proveedores/visor" element={
            <RutaPrivada>
              <RutaProtegida permiso="proveedores.ver">
                <LayoutPrincipal><VisorProveedor /></LayoutPrincipal>
              </RutaProtegida>
            </RutaPrivada>
          } />

          <Route path="/proveedores/visor/:id" element={
            <RutaPrivada>
              <RutaProtegida permiso="proveedores.ver">
                <LayoutPrincipal><VisorProveedor /></LayoutPrincipal>
              </RutaProtegida>
            </RutaPrivada>
          } />

          <Route path="/empresa/usuarios" element={
            <RutaPrivada>
              <RutaProtegida permiso="usuarios.gestionar">
                <LayoutPrincipal><GestionUsuarios /></LayoutPrincipal>
              </RutaProtegida>
            </RutaPrivada>
          } />

          <Route path="/empresa/roles" element={
            <RutaPrivada>
              <RutaProtegida permiso="usuarios.gestionar">
                <LayoutPrincipal><GestionRoles /></LayoutPrincipal>
              </RutaProtegida>
            </RutaPrivada>
          } />

          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  );
}

export default App;