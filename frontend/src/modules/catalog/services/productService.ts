import { http } from '../../../shared/api/http';
import { useAuthStore } from '../../identity/stores/authStore';
import type { PaginatedResponse } from '../types/category';

export interface Product {
    id: number;
    name: string;
    sku: string;
    barcode: string | null;
    category_id: number | null;
    price: string;
    tax_rate: string;
    is_active: boolean;
}

export interface ProductInput {
    name: string;
    sku: string;
    barcode: string | null;
    category_id: number | null;
    price: string;
    tax_rate: string;
    is_active?: boolean;
}

export const productService = {
    async list(perPage = 100) {
        const auth = useAuthStore();
        const response = await http.get<PaginatedResponse<Product>>('/products', {
            params: { per_page: perPage },
            headers: auth.companyHeaders(),
        });

        return response.data;
    },

    async create(data: ProductInput) {
        const auth = useAuthStore();
        const response = await http.post<{ data: Product }>('/products', data, {
            headers: auth.companyHeaders(),
        });

        return response.data.data;
    },

    async update(id: number, data: ProductInput) {
        const auth = useAuthStore();
        const response = await http.put<{ data: Product }>(`/products/${id}`, data, {
            headers: auth.companyHeaders(),
        });

        return response.data.data;
    },

    async setActive(id: number, isActive: boolean) {
        const auth = useAuthStore();
        const response = await http.patch<{ data: Product }>(
            `/products/${id}/status`,
            { is_active: isActive },
            { headers: auth.companyHeaders() },
        );

        return response.data.data;
    },
};
