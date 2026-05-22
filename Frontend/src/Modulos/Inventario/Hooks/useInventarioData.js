import { useContext } from 'react';
import { InventarioDataContext } from '../Contextos/InventarioDataContext';

export const useInventarioData = () => {
    const context = useContext(InventarioDataContext);

    if (!context) {
        throw new Error('useInventarioData debe usarse dentro de InventarioDataProvider.');
    }

    return context;
};

export default useInventarioData;