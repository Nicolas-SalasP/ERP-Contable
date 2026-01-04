import React from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';

import LayoutPrincipal from './Componentes/Estructura/LayoutPrincipal';
import RegistroFactura from './Modulos/Contabilidad/Componentes/RegistroFactura';
import GestionProveedores from './Modulos/Proveedores/GestionProveedores';
import LibroMayor from './Modulos/Contabilidad/Vistas/LibroMayor';

function App() {
  return (
    <BrowserRouter>
      <LayoutPrincipal>
        <Routes>
          <Route path="/" element={
            <div className="flex flex-col items-center justify-center h-full text-slate-400">
              <h1 className="text-2xl font-bold">Bienvenido al ERP Contable</h1>
            </div>
          } />

          <Route path="/facturas/nueva" element={<RegistroFactura />} />
          <Route path="/proveedores" element={<GestionProveedores />} />
          <Route path="/contabilidad/libro-mayor" element={<LibroMayor />} />

          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </LayoutPrincipal>
    </BrowserRouter>
  );
}

export default App;