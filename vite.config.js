import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
    server: {
        host: '127.0.0.1', // Avoid IPv6 [::1] so CSP script-src matches without parsing issues
    },
    plugins: [
        laravel({
            input: 'resources/js/app.jsx',
            refresh: true,
        }),
        react(),
    ],
    resolve: {
        alias: [
            {
                find: /^react-aria\/(.*)/,
                replacement: path.resolve(__dirname, 'node_modules/react-aria/dist/exports/$1.js'),
            },
        ],
    },
    build: {
        target: 'es2020',
        cssCodeSplit: true,
        chunkSizeWarningLimit: 1500,
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules')) {
                        if (id.includes('handsontable')) {
                            return 'vendor-handsontable';
                        }
                        if (id.includes('exceljs')) {
                            return 'vendor-exceljs';
                        }
                        if (id.includes('recharts') || id.includes('d3-') || id.includes('victory')) {
                            return 'vendor-charts';
                        }
                        if (id.includes('@xyflow') || id.includes('@dnd-kit')) {
                            return 'vendor-flows';
                        }
                        if (
                            id.includes('react') ||
                            id.includes('react-dom') ||
                            id.includes('@inertiajs') ||
                            id.includes('@headlessui') ||
                            id.includes('axios')
                        ) {
                            return 'vendor-framework';
                        }
                        if (id.includes('lucide-react')) {
                            return 'vendor-icons';
                        }
                        if (id.includes('i18next')) {
                            return 'vendor-i18n';
                        }
                        if (
                            id.includes('firebase') ||
                            id.includes('pusher-js') ||
                            id.includes('laravel-echo')
                        ) {
                            return 'vendor-realtime';
                        }
                    }
                },
            },
        },
    },
});
