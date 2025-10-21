import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { API } from '../utils/api';

function LicensingPage() {
	const { data: licensesData, isLoading } = useQuery({
		queryKey: ['top-licenses'],
		queryFn: () => API.getTopLicenses(20),
	});

	return (
		<div className="space-y-6">
			<div className="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
				<div className="px-6 py-4 border-b border-gray-200">
					<h3 className="text-lg font-semibold text-gray-900">
						Top 20 Licenses by Site Activations
					</h3>
					<p className="text-sm text-gray-500 mt-1">
						Licenses with the most active site installations
					</p>
				</div>

				<div className="overflow-x-auto">
					<table className="min-w-full divide-y divide-gray-200">
						<thead className="bg-gray-50">
							<tr>
								<th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
									License Key
								</th>
								<th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
									Download
								</th>
								<th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
									Activations
								</th>
							</tr>
						</thead>
						<tbody className="bg-white divide-y divide-gray-200">
							{isLoading ? (
								<tr>
									<td colSpan="3" className="px-6 py-8 text-center text-gray-500">
										Loading...
									</td>
								</tr>
							) : licensesData && licensesData.length > 0 ? (
								licensesData.map((license, index) => (
									<tr key={index} className="hover:bg-gray-50">
										<td className="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900">
											{license.license_key}
										</td>
										<td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
											{license.download_name}
										</td>
										<td className="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
											{license.activation_count}
										</td>
									</tr>
								))
							) : (
								<tr>
									<td colSpan="3" className="px-6 py-8 text-center text-gray-500">
										No licensing data available. Make sure EDD Software Licensing is installed and active.
									</td>
								</tr>
							)}
						</tbody>
					</table>
				</div>
			</div>
		</div>
	);
}

export default LicensingPage;
