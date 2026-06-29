import React, { Suspense, lazy } from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './Contextos/AuthContext';
import { TemaProvider } from './Contextos/TemaContext';
import { ToastProvider } from './Contextos/ToastContext';
import { usePermisos } from './Contextos/Permisos';
import LayoutPrincipal from './Componentes/Estructura/LayoutPrincipal';
import ErrorBoundary from './Componentes/ErrorBoundary';

import Login from './Modulos/Autenticacion/Login';
import RecuperarPassword from './Modulos/Autenticacion/RecuperarPassword';
import SsoCallback from './Modulos/Autenticacion/SsoCallback';
import Dashboard from './Modulos/Dashboard/Dashboard';
const RegistroFactura = lazy(() => import('./Modulos/Contabilidad/Componentes/RegistroFactura'));
const HistorialFacturas = lazy(() => import('./Modulos/Contabilidad/Vistas/HistorialFacturas'));
import GestionProveedores from './Modulos/Proveedores/GestionProveedores';
const LibroMayor = lazy(() => import('./Modulos/Contabilidad/Vistas/LibroMayor'));
const BalanceComprobacion = lazy(() => import('./Modulos/Contabilidad/Vistas/BalanceComprobacion'));
const AnulacionGeneral = lazy(() => import('./Modulos/Contabilidad/Vistas/AnulacionGeneral'));
import GestionCotizaciones from './Modulos/Cotizaciones/GestionCotizaciones';
import CrearCotizacion from './Modulos/Cotizaciones/CrearCotizacion';
import GestionClientes from './Modulos/Clientes/GestionClientes';
import PerfilEmpresa from './Modulos/Empresa/PerfilEmpresa';
import GestionActivos from './Modulos/Activos/Vistas/GestionActivos';
const VisorAuditoriaFactura = lazy(() => import('./Modulos/Contabilidad/Vistas/VisorAuditoriaFactura'));
const AdministradorCuentas = lazy(() => import('./Modulos/Contabilidad/Vistas/AdministradorCuentas'));
import DashboardRenta from './Modulos/Tributario/Vistas/DashboardRenta';
import NominaPagos from './Modulos/Banco/Vistas/NominaPagos';
import CartolaBancaria from './Modulos/Banco/Vistas/CartolaBancaria';
import MesaConciliacion from './Modulos/Banco/Vistas/MesaConciliacion';
const CierreF29 = lazy(() => import('./Modulos/Contabilidad/Vistas/CierreF29'));
const AsientoManual = lazy(() => import('./Modulos/Contabilidad/Vistas/AsientoManual'));
import VisorProveedor from './Modulos/Proveedores/VisorProveedor';
import CrearEmpresa from './Modulos/Bienvenida/CrearEmpresa';
import GestionUsuarios from './Modulos/Administrador/GestionUsuarios';
import GestionRoles from './Modulos/Administrador/GestionRoles';
const InventarioDashboard = lazy(() => import('./Modulos/Inventario/Vistas/InventarioDashboard'));
const ProductosInventario = lazy(() => import('./Modulos/Inventario/Vistas/ProductosInventario'));
const BodegasInventario = lazy(() => import('./Modulos/Inventario/Vistas/BodegasInventario'));
const UbicacionesInventario = lazy(() => import('./Modulos/Inventario/Vistas/UbicacionesInventario'));
const PickingInventario = lazy(() => import('./Modulos/Inventario/Vistas/PickingInventario'));
const PackingInventario = lazy(() => import('./Modulos/Inventario/Vistas/PackingInventario'));
const DespachosInventario = lazy(() => import('./Modulos/Inventario/Vistas/DespachosInventario'));
const DevolucionesInventario = lazy(() => import('./Modulos/Inventario/Vistas/DevolucionesInventario'));
const AuditoriaInventario = lazy(() => import('./Modulos/Inventario/Vistas/AuditoriaInventario'));
const EventosIntegracionInventario = lazy(() => import('./Modulos/Inventario/Vistas/EventosIntegracionInventario'));
const MovimientosInventario = lazy(() => import('./Modulos/Inventario/Vistas/MovimientosInventario'));
import CorreccionMonetaria from './Modulos/CorreccionMonetaria/CorreccionMonetaria';
const KardexInventario = lazy(() => import('./Modulos/Inventario/Vistas/KardexInventario'));
const LotesInventario = lazy(() => import('./Modulos/Inventario/Vistas/LotesInventario'));
const ReservasInventario = lazy(() => import('./Modulos/Inventario/Vistas/ReservasInventario'));
const TomasFisicasInventario = lazy(() => import('./Modulos/Inventario/Vistas/TomasFisicasInventario'));
const ValorizacionInventario = lazy(() => import('./Modulos/Inventario/Vistas/ValorizacionInventario'));
const AlertasInventario = lazy(() => import('./Modulos/Inventario/Vistas/AlertasInventario'));
const ReportesInventario = lazy(() => import('./Modulos/Inventario/Vistas/ReportesInventario'));
import InventarioProviderWrapper from './Modulos/Inventario/InventarioProviderWrapper';
const ConfiguracionSii = lazy(() => import('./Modulos/Sii/Vistas/ConfiguracionSii'));
const CertificadoSii = lazy(() => import('./Modulos/Sii/Vistas/CertificadoSii'));
const FoliosCaf = lazy(() => import('./Modulos/Sii/Vistas/FoliosCaf'));
const FacturasSii = lazy(() => import('./Modulos/Sii/Vistas/FacturasSii'));
const EmpleadosRrhh = lazy(() => import('./Modulos/Rrhh/Vistas/EmpleadosRrhh'));
const ContratosRrhh = lazy(() => import('./Modulos/Rrhh/Vistas/ContratosRrhh'));
const LiquidacionesRrhh = lazy(() => import('./Modulos/Rrhh/Vistas/LiquidacionesRrhh'));
const FiniquitosRrhh = lazy(() => import('./Modulos/Rrhh/Vistas/FiniquitosRrhh'));
const ParametrosRrhh = lazy(() => import('./Modulos/Rrhh/Vistas/ParametrosRrhh'));
const CentralizacionRrhh = lazy(() => import('./Modulos/Rrhh/Vistas/CentralizacionRrhh'));
const PreviredRrhh = lazy(() => import('./Modulos/Rrhh/Vistas/PreviredRrhh'));
const LreRrhh = lazy(() => import('./Modulos/Rrhh/Vistas/LreRrhh'));
const EmrclRrhh = lazy(() => import('./Modulos/Rrhh/Vistas/EmrclRrhh'));
const Dj1887 = lazy(() => import('./Modulos/Tributario/Vistas/Dj1887'));
const HonorariosRecibidos = lazy(() => import('./Modulos/Comercial/Vistas/HonorariosRecibidos'));
const Dj1879 = lazy(() => import('./Modulos/Tributario/Vistas/Dj1879'));
const Dj1947 = lazy(() => import('./Modulos/Tributario/Vistas/Dj1947'));
const PropietariosEmpresa = lazy(() => import('./Modulos/Core/Vistas/PropietariosEmpresa'));
import Glosario from './Modulos/Glosario/Glosario';
const PanelDpo = lazy(() => import('./Modulos/Cumplimiento/PanelDpo'));
const SoporteTickets = lazy(() => import('./Modulos/Soporte/Vistas/SoporteTickets'));
const SoporteTicketDetalle = lazy(() => import('./Modulos/Soporte/Vistas/SoporteTicketDetalle'));
const ArAging = lazy(() => import('./Modulos/Contabilidad/Vistas/ArAging'));
const ApAging = lazy(() => import('./Modulos/Contabilidad/Vistas/ApAging'));

// Si ya está autenticado, redirige al inicio (evita volver a /login sin logout).
const RutaPublica = ({ children }) => {
  const auth = useAuth();
  if (auth?.isAuthenticated) return <Navigate to="/" replace />;
  return children;
};

const RutaPrivada = ({ children, requireEmpresa = true }) => {
  const auth = useAuth();
  if (!auth) return <div>Cargando...</div>;

  const { isAuthenticated, loading, user } = auth;

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

// Guard de plan/módulo: replica la lógica de BarraLateral (moduloRequerido).
// Fail-open cuando module_keys está vacío o es '*' (admins sin SSO ven todo).
const RutaPorModulo = ({ modulo, children }) => {
  const { tieneModulo } = usePermisos();

  if (!tieneModulo(modulo)) {
    return <Navigate to="/" replace />;
  }

  return children;
};

const permisosLecturaInventario = [
  'inventario.dashboard.ver',
  'inventario.reportes.ver',
  'inventario.productos.ver',
  'inventario.bodegas.ver',
  'inventario.ubicaciones.ver',
  'inventario.stock_ubicaciones.ver',
  'inventario.picking.ver',
  'inventario.packing.ver',
  'inventario.despachos.ver',
  'inventario.devoluciones.ver',
  'inventario.auditoria.ver',
  'inventario.eventos_integracion.ver',
  'inventario.movimientos.ver',
  'inventario.kardex.ver',
  'inventario.valorizacion.ver',
  'inventario.lotes.ver',
  'inventario.reservas.ver',
  'inventario.disponibilidad.ver',
  'inventario.tomas_fisicas.ver',
  'inventario.alertas.ver',
  'inventario.reglas_reposicion.ver',
];


const InventarioLayout = ({ children }) => (
  <InventarioProviderWrapper>
    <LayoutPrincipal>{children}</LayoutPrincipal>
  </InventarioProviderWrapper>
);

function App() {
  return (
    <ErrorBoundary>
      <TemaProvider>
      <ToastProvider>
      <AuthProvider>
        <BrowserRouter>
        <Suspense fallback={<div>Cargando...</div>}>
        <Routes>
          <Route path="/login" element={<RutaPublica><Login /></RutaPublica>} />
          <Route path="/sso-callback" element={<SsoCallback />} />
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

          <Route path="/contabilidad/balance-comprobacion" element={
            <RutaPrivada>
              <RutaProtegida permiso="contabilidad.ver">
                <LayoutPrincipal><BalanceComprobacion /></LayoutPrincipal>
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

          <Route path="/tributario/dj-1887" element={
            <RutaPrivada>
              <RutaProtegida permiso="tributario.ver">
                <LayoutPrincipal><Dj1887 /></LayoutPrincipal>
              </RutaProtegida>
            </RutaPrivada>
          } />

          <Route path="/comercial/honorarios-recibidos" element={
            <RutaPrivada>
              <RutaProtegida permiso="tributario.ver">
                <LayoutPrincipal><HonorariosRecibidos /></LayoutPrincipal>
              </RutaProtegida>
            </RutaPrivada>
          } />

          <Route path="/tributario/dj-1879" element={
            <RutaPrivada>
              <RutaProtegida permiso="tributario.ver">
                <LayoutPrincipal><Dj1879 /></LayoutPrincipal>
              </RutaProtegida>
            </RutaPrivada>
          } />

          <Route path="/tributario/dj-1947" element={
            <RutaPrivada>
              <RutaProtegida permiso="tributario.ver">
                <LayoutPrincipal><Dj1947 /></LayoutPrincipal>
              </RutaProtegida>
            </RutaPrivada>
          } />

          <Route path="/empresa/propietarios" element={
            <RutaPrivada>
              <RutaProtegida permiso="contabilidad.ver">
                <LayoutPrincipal><PropietariosEmpresa /></LayoutPrincipal>
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

          <Route path="/contabilidad/ar-aging" element={
            <RutaPrivada>
              <RutaProtegida permiso="contabilidad.ver">
                <LayoutPrincipal><ArAging /></LayoutPrincipal>
              </RutaProtegida>
            </RutaPrivada>
          } />

          <Route path="/contabilidad/ap-aging" element={
            <RutaPrivada>
              <RutaProtegida permiso="contabilidad.ver">
                <LayoutPrincipal><ApAging /></LayoutPrincipal>
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
             <Route path="/inventario" element={
            <Navigate to="/inventario/dashboard" replace />
          } />

          <Route path="/inventario/dashboard" element={
            <RutaPrivada>
              <RutaPorModulo modulo="inventario">
                <RutaProtegidaAlgunPermiso permisos={permisosLecturaInventario}>
                  <InventarioLayout><InventarioDashboard /></InventarioLayout>
                </RutaProtegidaAlgunPermiso>
              </RutaPorModulo>
            </RutaPrivada>
          } />

          <Route path="/inventario/productos" element={
            <RutaPrivada>
              <RutaPorModulo modulo="inventario">
                <RutaProtegida permiso="inventario.productos.ver">
                  <InventarioLayout><ProductosInventario /></InventarioLayout>
                </RutaProtegida>
              </RutaPorModulo>
            </RutaPrivada>
          } />

          <Route path="/inventario/bodegas" element={
            <RutaPrivada>
              <RutaPorModulo modulo="inventario">
                <RutaProtegida permiso="inventario.bodegas.ver">
                  <InventarioLayout><BodegasInventario /></InventarioLayout>
                </RutaProtegida>
              </RutaPorModulo>
            </RutaPrivada>
          } />

          <Route path="/inventario/ubicaciones" element={
            <RutaPrivada>
              <RutaPorModulo modulo="inventario">
                <RutaProtegida permiso="inventario.ubicaciones.ver">
                  <InventarioLayout><UbicacionesInventario /></InventarioLayout>
                </RutaProtegida>
              </RutaPorModulo>
            </RutaPrivada>
          } />



          <Route path="/inventario/picking" element={
            <RutaPrivada>
              <RutaPorModulo modulo="inventario">
                <RutaProtegida permiso="inventario.picking.ver">
                  <InventarioLayout><PickingInventario /></InventarioLayout>
                </RutaProtegida>
              </RutaPorModulo>
            </RutaPrivada>
          } />

          <Route path="/inventario/packing" element={
            <RutaPrivada>
              <RutaPorModulo modulo="inventario">
                <RutaProtegida permiso="inventario.packing.ver">
                  <InventarioLayout><PackingInventario /></InventarioLayout>
                </RutaProtegida>
              </RutaPorModulo>
            </RutaPrivada>
          } />

          <Route path="/inventario/despachos" element={
            <RutaPrivada>
              <RutaPorModulo modulo="inventario">
                <RutaProtegida permiso="inventario.despachos.ver">
                  <InventarioLayout><DespachosInventario /></InventarioLayout>
                </RutaProtegida>
              </RutaPorModulo>
            </RutaPrivada>
          } />

          <Route path="/inventario/devoluciones" element={
            <RutaPrivada>
              <RutaPorModulo modulo="inventario">
                <RutaProtegida permiso="inventario.devoluciones.ver">
                  <InventarioLayout><DevolucionesInventario /></InventarioLayout>
                </RutaProtegida>
              </RutaPorModulo>
            </RutaPrivada>
          } />

          <Route path="/inventario/auditoria" element={
            <RutaPrivada>
              <RutaPorModulo modulo="inventario">
                <RutaProtegida permiso="inventario.auditoria.ver">
                  <InventarioLayout><AuditoriaInventario /></InventarioLayout>
                </RutaProtegida>
              </RutaPorModulo>
            </RutaPrivada>
          } />

          <Route path="/inventario/eventos-integracion" element={
            <RutaPrivada>
              <RutaPorModulo modulo="inventario">
                <RutaProtegida permiso="inventario.eventos_integracion.ver">
                  <InventarioLayout><EventosIntegracionInventario /></InventarioLayout>
                </RutaProtegida>
              </RutaPorModulo>
            </RutaPrivada>
          } />

          <Route path="/inventario/movimientos" element={
            <RutaPrivada>
              <RutaPorModulo modulo="inventario">
                <RutaProtegida permiso="inventario.movimientos.ver">
                  <InventarioLayout><MovimientosInventario /></InventarioLayout>
                </RutaProtegida>
              </RutaPorModulo>
            </RutaPrivada>
          } />

          <Route path="/inventario/kardex" element={
            <RutaPrivada>
              <RutaPorModulo modulo="inventario">
                <RutaProtegida permiso="inventario.kardex.ver">
                  <InventarioLayout><KardexInventario /></InventarioLayout>
                </RutaProtegida>
              </RutaPorModulo>
            </RutaPrivada>
          } />

          <Route path="/inventario/lotes" element={
            <RutaPrivada>
              <RutaPorModulo modulo="inventario">
                <RutaProtegida permiso="inventario.lotes.ver">
                  <InventarioLayout><LotesInventario /></InventarioLayout>
                </RutaProtegida>
              </RutaPorModulo>
            </RutaPrivada>
          } />

          <Route path="/inventario/reservas" element={
            <RutaPrivada>
              <RutaPorModulo modulo="inventario">
                <RutaProtegida permiso="inventario.reservas.ver">
                  <InventarioLayout><ReservasInventario /></InventarioLayout>
                </RutaProtegida>
              </RutaPorModulo>
            </RutaPrivada>
          } />

          <Route path="/inventario/tomas-fisicas" element={
            <RutaPrivada>
              <RutaPorModulo modulo="inventario">
                <RutaProtegida permiso="inventario.tomas_fisicas.ver">
                  <InventarioLayout><TomasFisicasInventario /></InventarioLayout>
                </RutaProtegida>
              </RutaPorModulo>
            </RutaPrivada>
          } />

          <Route path="/inventario/valorizacion" element={
            <RutaPrivada>
              <RutaPorModulo modulo="inventario">
                <RutaProtegida permiso="inventario.valorizacion.ver">
                  <InventarioLayout><ValorizacionInventario /></InventarioLayout>
                </RutaProtegida>
              </RutaPorModulo>
            </RutaPrivada>
          } />

          <Route path="/inventario/reportes" element={
            <RutaPrivada>
              <RutaPorModulo modulo="inventario">
                <RutaProtegida permiso="inventario.reportes.ver">
                  <InventarioLayout><ReportesInventario /></InventarioLayout>
                </RutaProtegida>
              </RutaPorModulo>
            </RutaPrivada>
          } />

          <Route path="/inventario/alertas" element={
            <RutaPrivada>
              <RutaPorModulo modulo="inventario">
                <RutaProtegidaAlgunPermiso permisos={[
                  'inventario.alertas.ver',
                  'inventario.reglas_reposicion.ver',
                ]}>
                  <InventarioLayout><AlertasInventario /></InventarioLayout>
                </RutaProtegidaAlgunPermiso>
              </RutaPorModulo>
            </RutaPrivada>
          } />

          <Route path="/sii/configuracion" element={
            <RutaPrivada>
              <RutaPorModulo modulo="sii">
                <RutaProtegida permiso="sii.configuracion.ver">
                  <LayoutPrincipal><ConfiguracionSii /></LayoutPrincipal>
                </RutaProtegida>
              </RutaPorModulo>
            </RutaPrivada>
          } />

          <Route path="/sii/certificado" element={
            <RutaPrivada>
              <RutaPorModulo modulo="sii">
                <RutaProtegida permiso="sii.certificado.ver">
                  <LayoutPrincipal><CertificadoSii /></LayoutPrincipal>
                </RutaProtegida>
              </RutaPorModulo>
            </RutaPrivada>
          } />

          <Route path="/sii/folios-caf" element={
            <RutaPrivada>
              <RutaPorModulo modulo="sii">
                <RutaProtegida permiso="sii.caf.ver">
                  <LayoutPrincipal><FoliosCaf /></LayoutPrincipal>
                </RutaProtegida>
              </RutaPorModulo>
            </RutaPrivada>
          } />

          <Route path="/sii/caf" element={<Navigate to="/sii/folios-caf" replace />} />

          <Route path="/sii/facturas" element={
            <RutaPrivada>
              <RutaPorModulo modulo="sii">
                <RutaProtegida permiso="sii.dte.ver">
                  <LayoutPrincipal><FacturasSii /></LayoutPrincipal>
                </RutaProtegida>
              </RutaPorModulo>
            </RutaPrivada>
          } />


          {/* ── RRHH y Remuneraciones ── */}
          <Route path="/rrhh/empleados" element={
            <RutaPrivada>
              <RutaPorModulo modulo="rrhh">
                <RutaProtegida permiso="rrhh.empleados.ver">
                  <LayoutPrincipal><EmpleadosRrhh /></LayoutPrincipal>
                </RutaProtegida>
              </RutaPorModulo>
            </RutaPrivada>
          } />

          <Route path="/rrhh/contratos" element={
            <RutaPrivada>
              <RutaPorModulo modulo="rrhh">
                <RutaProtegida permiso="rrhh.empleados.ver">
                  <LayoutPrincipal><ContratosRrhh /></LayoutPrincipal>
                </RutaProtegida>
              </RutaPorModulo>
            </RutaPrivada>
          } />

          <Route path="/rrhh/liquidaciones" element={
            <RutaPrivada>
              <RutaPorModulo modulo="rrhh">
                <RutaProtegida permiso="rrhh.remuneraciones.ver">
                  <LayoutPrincipal><LiquidacionesRrhh /></LayoutPrincipal>
                </RutaProtegida>
              </RutaPorModulo>
            </RutaPrivada>
          } />

          <Route path="/rrhh/finiquitos" element={
            <RutaPrivada>
              <RutaPorModulo modulo="rrhh">
                <RutaProtegida permiso="rrhh.remuneraciones.ver">
                  <LayoutPrincipal><FiniquitosRrhh /></LayoutPrincipal>
                </RutaProtegida>
              </RutaPorModulo>
            </RutaPrivada>
          } />

          <Route path="/rrhh/parametros" element={
            <RutaPrivada>
              <RutaPorModulo modulo="rrhh">
                <RutaProtegida permiso="rrhh.parametros.ver">
                  <LayoutPrincipal><ParametrosRrhh /></LayoutPrincipal>
                </RutaProtegida>
              </RutaPorModulo>
            </RutaPrivada>
          } />

          <Route path="/rrhh/centralizacion" element={
            <RutaPrivada>
              <RutaPorModulo modulo="rrhh">
                <RutaProtegida permiso="rrhh.parametros.ver">
                  <LayoutPrincipal><CentralizacionRrhh /></LayoutPrincipal>
                </RutaProtegida>
              </RutaPorModulo>
            </RutaPrivada>
          } />

          <Route path="/rrhh/previred" element={
            <RutaPrivada>
              <RutaPorModulo modulo="rrhh">
                <RutaProtegida permiso="rrhh.remuneraciones.ver">
                  <LayoutPrincipal><PreviredRrhh /></LayoutPrincipal>
                </RutaProtegida>
              </RutaPorModulo>
            </RutaPrivada>
          } />

          <Route path="/rrhh/lre" element={
            <RutaPrivada>
              <RutaPorModulo modulo="rrhh">
                <RutaProtegida permiso="rrhh.remuneraciones.ver">
                  <LayoutPrincipal><LreRrhh /></LayoutPrincipal>
                </RutaProtegida>
              </RutaPorModulo>
            </RutaPrivada>
          } />

          <Route path="/rrhh/emrcl" element={
            <RutaPrivada>
              <RutaPorModulo modulo="rrhh">
                <RutaProtegida permiso="rrhh.remuneraciones.ver">
                  <LayoutPrincipal><EmrclRrhh /></LayoutPrincipal>
                </RutaProtegida>
              </RutaPorModulo>
            </RutaPrivada>
          } />

          <Route path="/glosario" element={
            <RutaPrivada>
              <LayoutPrincipal><Glosario /></LayoutPrincipal>
            </RutaPrivada>
          } />

          <Route path="/cumplimiento" element={
            <RutaPrivada>
              <RutaProtegida permiso="usuarios.gestionar">
                <LayoutPrincipal><PanelDpo /></LayoutPrincipal>
              </RutaProtegida>
            </RutaPrivada>
          } />

          <Route path="/soporte/tickets" element={
            <RutaPrivada>
              <RutaProtegida permiso="soporte.ver">
                <LayoutPrincipal><SoporteTickets /></LayoutPrincipal>
              </RutaProtegida>
            </RutaPrivada>
          } />

          <Route path="/soporte/tickets/:id" element={
            <RutaPrivada>
              <RutaProtegida permiso="soporte.ver">
                <LayoutPrincipal><SoporteTicketDetalle /></LayoutPrincipal>
              </RutaProtegida>
            </RutaPrivada>
          } />

          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
        </Suspense>
        </BrowserRouter>
      </AuthProvider>
      </ToastProvider>
      </TemaProvider>
    </ErrorBoundary>
  );
}

export default App;