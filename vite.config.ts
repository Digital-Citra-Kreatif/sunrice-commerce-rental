import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';
import { sunrice } from '@imajiner/admin-sdk/vite';

// The admin bundle (dist/admin.js): the Rental/ pages, using the panel's React, Inertia and UI.
export default defineConfig({
    plugins: [react(), sunrice({ entry: 'resources/js/admin.tsx' })],
});
