import React from 'react';

function SupportPage() {
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
							d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"
						/>
					</svg>
					<h3 className="mt-2 text-lg font-semibold text-gray-900">
						Support Stats Coming Soon
					</h3>
					<p className="mt-1 text-sm text-gray-500">
						This section will display customer support request rates and patterns.
						Requires integration with your support ticket system (e.g., Gravity Forms, Help Scout).
					</p>
				</div>
			</div>
		</div>
	);
}

export default SupportPage;
