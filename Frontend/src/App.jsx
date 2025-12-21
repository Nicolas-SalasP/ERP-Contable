import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import InvoiceWizard from './Modules/Accounting/Components/InvoiceWizard';

const DashboardLayout = ({ children }) => {
  return (
    <div style={{ display: 'flex', height: '100vh' }}>
      <aside style={{ width: '250px', background: '#2c3e50', color: 'white', padding: '20px' }}>
        <h3>Contabilidad</h3>
        <nav>
          <ul style={{ listStyle: 'none', padding: 0 }}>
            <li style={{ margin: '10px 0' }}>Inicio</li>
            <li style={{ margin: '10px 0' }}>Ingresar Factura</li>
            <li style={{ margin: '10px 0' }}>Bancos</li>
          </ul>
        </nav>
      </aside>
      <main style={{ flex: 1, padding: '20px', background: '#f4f6f8', overflowY: 'auto' }}>
        {children}
      </main>
    </div>
  );
};

function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/" element={<Navigate to="/dashboard" replace />} />
        <Route path="/dashboard" element={
          <DashboardLayout>
            <InvoiceWizard />
          </DashboardLayout>
        } />
      </Routes>
    </BrowserRouter>
  );
}

export default App;