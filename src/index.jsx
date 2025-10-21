import React from 'react';
import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import App from './App';
import './index.css';

// Create React Query client
const queryClient = new QueryClient({
	defaultOptions: {
		queries: {
			refetchOnWindowFocus: false,
			retry: 1,
			staleTime: 5 * 60 * 1000, // 5 minutes
		},
	},
});

// Get root element
const rootElement = document.getElementById('vgp-edd-stats-root');

if (rootElement) {
	const section = rootElement.dataset.section || 'customers-revenue';

	const root = createRoot(rootElement);
	root.render(
		<React.StrictMode>
			<QueryClientProvider client={queryClient}>
				<App section={section} />
			</QueryClientProvider>
		</React.StrictMode>
	);
}
