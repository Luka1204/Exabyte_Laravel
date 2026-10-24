import { http } from '../../../shared/api/http';
import { useAuthStore } from '../../identity/stores/authStore';
import type { PaginatedResponse } from '../../catalog/types/category';

export interface CashSession {
    id: number;
    opening_amount: string;
    expected_cash: string;
    opened_at: string;
    closed_at: string | null;
    difference?: string;
}

export interface Sale {
    id: number;
    ticket_number: string;
    status: string;
    subtotal: string;
    tax_total: string;
    total: string;
    cash_received: string;
    change_due: string;
    created_at: string;
    items: Array<{
        id: number;
        product_name: string;
        sku: string;
        quantity: string;
        unit_price: string;
        line_total: string;
        returns?: Array<{
            quantity: string;
            refund_amount: string;
        }>;
    }>;
    returns?: Array<{
        id: number;
        total: string;
        reason: string;
    }>;
}

export const salesService = {
    async currentCashSession() {
        const auth = useAuthStore();
        const response = await http.get<{ data: CashSession | null }>(
            '/cash/session',
            { headers: auth.companyHeaders() },
        );

        return response.data.data;
    },

    async openCashSession(opening_amount: string) {
        const auth = useAuthStore();
        const response = await http.post<{ data: CashSession }>(
            '/cash/session',
            { opening_amount },
            { headers: auth.companyHeaders() },
        );

        return response.data.data;
    },

    async closeCashSession(sessionId: number, counted_cash: string) {
        const auth = useAuthStore();
        const response = await http.post<{ data: CashSession }>(
            `/cash/session/${sessionId}/close`,
            { counted_cash },
            { headers: auth.companyHeaders() },
        );

        return response.data.data;
    },

    async checkout(data: {
        items: Array<{ product_id: number; quantity: string }>;
        cash_received: string;
        idempotency_key: string;
    }) {
        const auth = useAuthStore();
        const response = await http.post<{ data: Sale; replayed: boolean }>(
            '/sales',
            data,
            { headers: auth.companyHeaders() },
        );

        return response.data;
    },

    async voidSale(saleId: number, reason: string) {
        const auth = useAuthStore();
        const response = await http.post<{ data: Sale }>(
            `/sales/${saleId}/void`,
            { reason },
            { headers: auth.companyHeaders() },
        );

        return response.data.data;
    },

    async returnSale(saleId: number, data: {
        items: Array<{ sale_item_id: number; quantity: string }>;
        reason: string;
        idempotency_key: string;
    }) {
        const auth = useAuthStore();
        const response = await http.post<{ data: {
            id: number;
            sale_id: number;
            total: string;
        }; replayed: boolean }>(
            `/sales/${saleId}/returns`,
            data,
            { headers: auth.companyHeaders() },
        );

        return response.data;
    },

    async list() {
        const auth = useAuthStore();
        const response = await http.get<PaginatedResponse<Sale>>('/sales', {
            params: { per_page: 20 },
            headers: auth.companyHeaders(),
        });

        return response.data;
    },
};
