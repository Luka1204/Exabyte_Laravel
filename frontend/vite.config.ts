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
                target: 'http://127.0.0.1:8000',
                changeOrigin: true,
            },
        },
        allowedHosts: [
            'alex-meyer-vocabulary-discussions.trycloudflare.com'
        ]
    },
    test: {
        environment: 'jsdom',
        include: ['src/**/*.test.ts'],
    },
}));