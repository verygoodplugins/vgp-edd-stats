import React from 'react';

function SitesPage() {
	return (
		<div className="space-y-6">
			<div className="bg-white rounded-lg shadow-sm border border-gray-200 p-8 text-center">
				<div className="max-w-md mx-auto">
					<svg
						className="mx-auto h-12 w-12 text-gray-400"
						fill="none"
						stroke="currentColor"
						viewBox="0 0 24 24"
					>
						<path
							strokeLinecap="round"
							strokeLinejoin="round"
							strokeWidth={2}
							d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
						/>
					</svg>
					<h3 className="mt-2 text-lg font-semibold text-gray-900">
						Sites Stats Coming Soon
					</h3>
					<p className="mt-1 text-sm text-gray-500">
						This section will display usage statistics for your sites.
						Requires custom site tracking implementation specific to your application.
					</p>
				</div>
			</div>
		</div>
	);
}

export default SitesPage;
