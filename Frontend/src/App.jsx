import React, { Suspense, lazy } from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './Contextos/AuthContext';
import { usePermisos } from './Contextos/Permisos';
import LayoutPrincipal from './Componentes/Estructura/LayoutPrincipal';
import Login from './Modulos/Autenticacion/Login';
import RecuperarPassword from './Modulos/Autenticacion/RecuperarPassword';
import Dashboard from './Modulos/Dashboard/Dashboard';

const RegistroFactura = lazy(() => import('./Modulos/Contabilidad/Componentes/RegistroFactura'));
const HistorialFacturas = lazy(() => import('./Modulos/Contabilidad/Vistas/HistorialFacturas'));
const GestionProveedores = lazy(() => import('./Modulos/Proveedores/GestionProveedores'));
const LibroMayor = lazy(() => import('./Modulos/Contabilidad/Vistas/LibroMayor'));
const AnulacionGeneral = lazy(() => import('./Modulos/Contabilidad/Vistas/AnulacionGeneral'));
const GestionCotizaciones = lazy(() => import('./Modulos/Cotizaciones/GestionCotizaciones'));
const CrearCotizacion = lazy(() => import('./Modulos/Cotizaciones/CrearCotizacion'));
const GestionClientes = lazy(() => import('./Modulos/Clientes/GestionClientes'));
const PerfilEmpresa = lazy(() => import('./Modulos/Empresa/PerfilEmpresa'));
const GestionActivos = lazy(() => import('./Modulos/Activos/Vistas/GestionActivos'));
const VisorAuditoriaFactura = lazy(() => import('./Modulos/Contabilidad/Vistas/VisorAuditoriaFactura'));
const AdministradorCuentas = lazy(() => import('./Modulos/Contabilidad/Vistas/AdministradorCuentas'));
const DashboardRenta = lazy(() => import('./Modulos/Tributario/Vistas/DashboardRenta'));
const CorreccionMonetaria = lazy(() => import('./Modulos/CorreccionMonetaria/CorreccionMonetaria'));
const NominaPagos = lazy(() => import('./Modulos/Banco/Vistas/NominaPagos'));
const CartolaBancaria = lazy(() => import('./Modulos/Banco/Vistas/CartolaBancaria'));
const MesaConciliacion = lazy(() => import('./Modulos/Banco/Vistas/MesaConciliacion'));
const CierreF29 = lazy(() => import('./Modulos/Contabilidad/Vistas/CierreF29'));
const AsientoManual = lazy(() => import('./Modulos/Contabilidad/Vistas/AsientoManual'));
const VisorProveedor = lazy(() => import('./Modulos/Proveedores/VisorProveedor'));
const CrearEmpresa = lazy(() => import('./Modulos/Bienvenida/CrearEmpresa'));
const GestionUsuarios = lazy(() => import('./Modulos/Administrador/GestionUsuarios'));
const GestionRoles = lazy(() => import('./Modulos/Administrador/GestionRoles'));
const InventarioDashboard = lazy(() => import('./Modulos/Inventario/Vistas/InventarioDashboard'));
const ProductosInventario = lazy(() => import('./Modulos/Inventario/Vistas/ProductosInventario'));
const BodegasInventario = lazy(() => import('./Modulos/Inventario/Vistas/BodegasInventario'));
const MovimientosInventario = lazy(() => import('./Modulos/Inventario/Vistas/MovimientosInventario'));
const KardexInventario = lazy(() => import('./Modulos/Inventario/Vistas/KardexInventario'));
const LotesInventario = lazy(() => import('./Modulos/Inventario/Vistas/LotesInventario'));
const ReservasInventario = lazy(() => import('./Modulos/Inventario/Vistas/ReservasInventario'));
const TomasFisicasInventario = lazy(() => import('./Modulos/Inventario/Vistas/TomasFisicasInventario'));
const ValorizacionInventario = lazy(() => import('./Modulos/Inventario/Vistas/ValorizacionInventario'));
const VisorAsientoCompleto = lazy(() => import('./Modulos/Contabilidad/Vistas/VisorAsientoCompleto'));
const Glosario = lazy(() => import('./Modulos/Glosario/Glosario'));
const ReclasificadorAsiento = lazy(() => import('./Modulos/Contabilidad/Vistas/ReclasificadorAsiento'));

const CargandoModulo = () => (
  <div className="flex items-center justify-center min-h-[60vh]">
    <div className="flex flex-col items-center gap-3">
      <div className="w-10 h-10 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin"></div>
      <p className="text-sm text-slate-500 font-medium">Cargando modulo...</p>
    </div>
  </div>
);

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

const RutaProtegidaAlgunPermiso = ({ permisos = [], children }) => {
  const { tieneAlgunPermiso } = usePermisos();

  if (!tieneAlgunPermiso(permisos)) {
    return <Navigate to="/" replace />;
  }

  return children;
};

const permisosLecturaInventario = [
  'inventario.productos.ver',
  'inventario.bodegas.ver',
  'inventario.movimientos.ver',
  'inventario.kardex.ver',
  'inventario.valorizacion.ver',
  'inventario.lotes.ver',
  'inventario.reservas.ver',
  'inventario.disponibilidad.ver',
  'inventario.tomas_fisicas.ver',
];

function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <Suspense fallback={<CargandoModulo />}>
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

          <Route path="/tributario/correccion-monetaria" element={
            <RutaPrivada>
              <RutaProtegida permiso="tributario.ver">
                <LayoutPrincipal><CorreccionMonetaria /></LayoutPrincipal>
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

          {/* Glosario es publico para todos los usuarios autenticados, no requiere permiso */}
          <Route path="/glosario" element={
            <RutaPrivada>
              <LayoutPrincipal><Glosario /></LayoutPrincipal>
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
          <Route path="/inventario" element={
            <Navigate to="/inventario/dashboard" replace />
          } />

          <Route path="/contabilidad/factura/:id/asiento" element={
            <RutaPrivada>
              <RutaProtegida permiso="contabilidad.ver">
                <LayoutPrincipal>
                  <VisorAsientoCompleto />
                </LayoutPrincipal>
              </RutaProtegida>
            </RutaPrivada>
          } />

          <Route path="/inventario/dashboard" element={
            <RutaPrivada>
              <RutaProtegidaAlgunPermiso permisos={permisosLecturaInventario}>
                <LayoutPrincipal><InventarioDashboard /></LayoutPrincipal>
              </RutaProtegidaAlgunPermiso>
            </RutaPrivada>
          } />

          <Route path="/inventario/productos" element={
            <RutaPrivada>
              <RutaProtegida permiso="inventario.productos.ver">
                <LayoutPrincipal><ProductosInventario /></LayoutPrincipal>
              </RutaProtegida>
            </RutaPrivada>
          } />

          <Route path="/contabilidad/factura/:id/reclasificar" element={
            <RutaPrivada>
              <RutaProtegida permiso="contabilidad.crear">
                <LayoutPrincipal>
                  <ReclasificadorAsiento />
                </LayoutPrincipal>
              </RutaProtegida>
            </RutaPrivada>
          } />

          <Route path="/inventario/bodegas" element={
            <RutaPrivada>
              <RutaProtegida permiso="inventario.bodegas.ver">
                <LayoutPrincipal><BodegasInventario /></LayoutPrincipal>
              </RutaProtegida>
            </RutaPrivada>
          } />

          <Route path="/inventario/movimientos" element={
            <RutaPrivada>
              <RutaProtegida permiso="inventario.movimientos.ver">
                <LayoutPrincipal><MovimientosInventario /></LayoutPrincipal>
              </RutaProtegida>
            </RutaPrivada>
          } />

          <Route path="/inventario/kardex" element={
            <RutaPrivada>
              <RutaProtegida permiso="inventario.kardex.ver">
                <LayoutPrincipal><KardexInventario /></LayoutPrincipal>
              </RutaProtegida>
            </RutaPrivada>
          } />

          <Route path="/inventario/lotes" element={
            <RutaPrivada>
              <RutaProtegida permiso="inventario.lotes.ver">
                <LayoutPrincipal><LotesInventario /></LayoutPrincipal>
              </RutaProtegida>
            </RutaPrivada>
          } />

          <Route path="/inventario/reservas" element={
            <RutaPrivada>
              <RutaProtegida permiso="inventario.reservas.ver">
                <LayoutPrincipal><ReservasInventario /></LayoutPrincipal>
              </RutaProtegida>
            </RutaPrivada>
          } />

          <Route path="/inventario/tomas-fisicas" element={
            <RutaPrivada>
              <RutaProtegida permiso="inventario.tomas_fisicas.ver">
                <LayoutPrincipal><TomasFisicasInventario /></LayoutPrincipal>
              </RutaProtegida>
            </RutaPrivada>
          } />

          <Route path="/inventario/valorizacion" element={
            <RutaPrivada>
              <RutaProtegida permiso="inventario.valorizacion.ver">
                <LayoutPrincipal><ValorizacionInventario /></LayoutPrincipal>
              </RutaProtegida>
            </RutaPrivada>
          } />

          <Route path="*" element={<Navigate to="/" replace />} />
          </Routes>
        </Suspense>
      </BrowserRouter>
    </AuthProvider>
  );
}

export default App;