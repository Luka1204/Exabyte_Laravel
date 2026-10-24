import { isAxiosError } from 'axios';

interface ApiErrorResponse {
    message?: string;
    errors?: Record<string, string[]>;
}

export function apiErrorMessage(exception: unknown, fallback: string): string {
    if (isAxiosError<ApiErrorResponse>(exception)) {
        return Object.values(exception.response?.data.errors ?? {})[0]?.[0]
            ?? exception.response?.data.message
            ?? fallback;
    }

    return fallback;
}
