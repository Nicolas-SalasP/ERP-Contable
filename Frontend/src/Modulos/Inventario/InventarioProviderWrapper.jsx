import React from 'react';
import { InventarioDataProvider } from './Contextos/InventarioDataContext';

const InventarioProviderWrapper = ({ children }) => {
    return (
        <InventarioDataProvider>
            {children}
        </InventarioDataProvider>
    );
};

export default InventarioProviderWrapper;