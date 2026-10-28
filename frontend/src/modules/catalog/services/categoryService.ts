import { http } from '../../../shared/api/http';
import { useAuthStore } from '../../identity/stores/authStore';
import type { Category, PaginatedResponse } from '../types/category';

export const categoryService = {
    async list(perPage = 15) {
        const auth = useAuthStore();

        const response = await http.get<PaginatedResponse<Category>>(
            '/categories',
            {
                params: { per_page: perPage },
                headers: auth.companyHeaders(),
            },
        );

        return response.data;
    },

    async create(data: {
        name: string;
        description: string | null;
    }) {
        const auth = useAuthStore();

        const response = await http.post<{ data: Category }>(
            '/categories',
            data,
            { headers: auth.companyHeaders() },
        );

        return response.data.data;
    },

    async update(id: number, data: { name: string; description: string | null }) {
        const auth = useAuthStore();
        const response = await http.put<{ data: Category }>(
            `/categories/${id}`,
            data,
            { headers: auth.companyHeaders() },
        );

        return response.data.data;
    },

    async setActive(id: number, isActive: boolean) {
        const auth = useAuthStore();
        const response = await http.patch<{ data: Category }>(
            `/categories/${id}/status`,
            { is_active: isActive },
            { headers: auth.companyHeaders() },
        );

        return response.data.data;
    },
};