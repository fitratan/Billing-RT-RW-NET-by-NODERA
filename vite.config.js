import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    build: {
        target: 'es2020',
        sourcemap: false,
        chunkSizeWarningLimit: 1000,
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('resources/js/lib/i18n')) {
                        return 'i18n';
                    }
                    if (id.includes('resources/js/lib/') || id.includes('resources/js/components/ui/')) {
                        return 'app-common';
                    }
                    if (id.includes('resources/js/components/layout/')) {
                        return 'app-layout';
                    }
                    if (id.includes('node_modules')) {
                        if (id.includes('lucide-react')) {
                            return 'vendor-icons';
                        }
                        if (id.includes('leaflet') || id.includes('react-leaflet')) {
                            return 'vendor-maps';
                        }
                        if (id.includes('recharts') || id.includes('chart.js') || id.includes('d3')) {
                            return 'vendor-charts';
                        }
                        if (id.includes('html2pdf') || id.includes('jspdf') || id.includes('html2canvas')) {
                            return 'vendor-pdf';
                        }
                        if (id.includes('framer-motion') || id.includes('@radix-ui')) {
                            return 'vendor-ui';
                        }
                        if (id.includes('axios') || id.includes('clsx') || id.includes('tailwind-merge') || id.includes('date-fns') || id.includes('dayjs') || id.includes('es-toolkit')) {
                            return 'vendor-helpers';
                        }
                        if (id.includes('react') || id.includes('react-dom') || id.includes('@inertiajs') || id.includes('laravel-vite-plugin')) {
                            return 'vendor-core';
                        }
                    }
                },
            },
        },
    },
});
