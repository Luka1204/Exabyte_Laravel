import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';
import vuetify from 'vite-plugin-vuetify';

export default defineConfig(({ mode }) => ({
    plugins: mode === 'test'
        ? [vue()]
        : [
            vue(),
            vuetify({ autoImport: true }),
        ],
    server: {
        port: 5173,
        proxy: {
            '/api': {
                target: 'http://localhost:8000',
                changeOrigin: true,
            },
        },
    },
    test: {
        environment: 'jsdom',
        include: ['src/**/*.test.ts'],
    },
}));