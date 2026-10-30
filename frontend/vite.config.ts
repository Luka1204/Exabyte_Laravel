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
                target: 'https://exemption-therapy-belong-sin.trycloudflare.com',
                changeOrigin: true,
            },
        },
        allowedHosts: [
            'deutschland-damaged-hash-stand.trycloudflare.com'
        ]
    },
    test: {
        environment: 'jsdom',
        include: ['src/**/*.test.ts'],
    },
}));