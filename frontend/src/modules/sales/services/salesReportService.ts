import { http } from '../../../shared/api/http';
import { useAuthStore } from '../../identity/stores/authStore';

export interface SalesSummary {
    from: string;
    to: string;
    sales_count: number;
    sales_total: string;
    returns_count: number;
    returns_total: string;
    voided_count: number;
    voided_total: string;
    net_sales: string;
    average_ticket: string;
}

export interface InventorySummary {
    from: string;
    to: string;
    products_count: number;
    active_products_count: number;
    products_with_stock: number;
    out_of_stock_products: number;
    movements: {
        adjustment: number;
        sale: number;
        sale_void: number;
        sale_return: number;
    };
}

export interface CashSummary {
    from: string;
    to: string;
    sessions_count: number;
    closed_sessions_count: number;
    opening_total: string;
    sales_total: string;
    returns_total: string;
    expected_cash_total: string;
    counted_cash_total: string;
    difference_total: string;
}

export const salesReportService = {
    async summary(from: string, to: string) {
        const auth = useAuthStore();
        const response = await http.get<{ data: SalesSummary }>('/reports/sales', {
            params: { from, to },
            headers: auth.companyHeaders(),
        });

        return response.data.data;
    },

    async inventory(from: string, to: string) {
        const auth = useAuthStore();
        const response = await http.get<{ data: InventorySummary }>('/reports/inventory', {
            params: { from, to },
            headers: auth.companyHeaders(),
        });

        return response.data.data;
    },

    async cash(from: string, to: string) {
        const auth = useAuthStore();
        const response = await http.get<{ data: CashSummary }>('/reports/cash', {
            params: { from, to },
            headers: auth.companyHeaders(),
        });

        return response.data.data;
    },
};
