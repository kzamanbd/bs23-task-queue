import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
// @ts-ignore
import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';

// https://vite.dev/config/
export default defineConfig({
    plugins: [
        react({
            babel: {
                plugins: [['babel-plugin-react-compiler']]
            }
        }),
        tailwindcss()
    ],
    resolve: {
        alias: {
            // @ts-ignore
            '@': fileURLToPath(new URL('./src', import.meta.url))
        }
    },
    server: {
        open: true
    }
});
