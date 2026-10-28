import { http } from '../../../shared/api/http';
import { useAuthStore } from '../../identity/stores/authStore';
import type { PaginatedResponse } from '../../catalog/types/category';

export interface InventoryStock {
    product_id: number;
    name: string;
    sku: string;
    barcode: string | null;
    price: string;
    tax_rate: string;
    is_active: boolean;
    quantity: string;
}

export interface InventoryMovement {
    id: number;
    type: 'adjustment' | 'sale' | 'sale_void' | 'sale_return';
    quantity_delta: string;
    balance_after: string;
    reason: string;
    created_at: string;
    product: { id: number; name: string; sku: string };
    user: { id: number; name: string };
}

export const inventoryService = {
    async stocks(perPage = 100) {
        const auth = useAuthStore();
        const response = await http.get<PaginatedResponse<InventoryStock>>(
            '/inventory/stocks',
            {
                params: { per_page: perPage },
                headers: auth.companyHeaders(),
            },
        );

        return response.data;
    },

    async movements(perPage = 25) {
        const auth = useAuthStore();
        const response = await http.get<PaginatedResponse<InventoryMovement>>(
            '/inventory/movements',
            {
                params: { per_page: perPage },
                headers: auth.companyHeaders(),
            },
        );

        return response.data;
    },

    async adjust(data: {
        product_id: number;
        quantity_delta: string;
        reason: string;
        idempotency_key: string;
    }) {
        const auth = useAuthStore();
        const response = await http.post('/inventory/adjustments', data, {
            headers: auth.companyHeaders(),
        });

        return response.data;
    },
};
