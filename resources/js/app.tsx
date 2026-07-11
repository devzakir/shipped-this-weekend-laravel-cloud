import { createInertiaApp } from '@inertiajs/react';
import { initializeTheme } from '@/hooks/use-appearance';

const appName = import.meta.env.VITE_APP_NAME || 'Shipped This Weekend';

createInertiaApp({
    title: (title: string) => (title ? `${title} - ${appName}` : appName),
    strictMode: true,
    progress: {
        color: '#f97316',
    },
});

// This will set light / dark mode on load...
initializeTheme();
