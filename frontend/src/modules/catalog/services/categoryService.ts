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
                headers: auth.authHeaders(),
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
            { headers: auth.authHeaders() },
        );

        return response.data.data;
    },
};