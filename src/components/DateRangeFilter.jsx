import React, { useState } from 'react';
import DatePicker from 'react-datepicker';
import { subDays, subMonths, startOfYear } from 'date-fns';
import 'react-datepicker/dist/react-datepicker.css';

const PRESETS = [
	{ label: 'Last 30 Days', value: '30', getDates: () => ({ startDate: subDays(new Date(), 30), endDate: new Date() }) },
	{ label: 'Last 90 Days', value: '90', getDates: () => ({ startDate: subDays(new Date(), 90), endDate: new Date() }) },
	{ label: 'Last 12 Months', value: '365', getDates: () => ({ startDate: subMonths(new Date(), 12), endDate: new Date() }) },
	{ label: 'Year to Date', value: 'ytd', getDates: () => ({ startDate: startOfYear(new Date()), endDate: new Date() }) },
	{ label: 'Custom', value: 'custom', getDates: () => null },
];

function DateRangeFilter({ dateRange, onChange }) {
	const [showCustom, setShowCustom] = useState(false);
	const [tempStartDate, setTempStartDate] = useState(dateRange.startDate);
	const [tempEndDate, setTempEndDate] = useState(dateRange.endDate);

	const handlePresetChange = (preset) => {
		if (preset.value === 'custom') {
			setShowCustom(true);
			return;
		}

		const dates = preset.getDates();
		onChange({
			...dates,
			preset: preset.value,
		});
		setShowCustom(false);
	};

	const handleCustomApply = () => {
		onChange({
			startDate: tempStartDate,
			endDate: tempEndDate,
			preset: 'custom',
		});
		setShowCustom(false);
	};

	return (
		<div className="flex items-center gap-3">
			<label className="text-sm font-medium text-gray-700">Date Range:</label>

			<div className="flex items-center gap-2">
				{PRESETS.map((preset) => (
					<button
						key={preset.value}
						onClick={() => handlePresetChange(preset)}
						className={`px-3 py-1.5 text-sm font-medium rounded-md transition-colors ${
							dateRange.preset === preset.value
								? 'bg-primary-600 text-white'
								: 'bg-gray-100 text-gray-700 hover:bg-gray-200'
						}`}
					>
						{preset.label}
					</button>
				))}
			</div>

			{showCustom && (
				<div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
					<div className="bg-white rounded-lg p-6 shadow-xl max-w-md w-full">
						<h3 className="text-lg font-semibold mb-4">Custom Date Range</h3>

						<div className="space-y-4">
							<div>
								<label className="block text-sm font-medium text-gray-700 mb-1">
									Start Date
								</label>
								<DatePicker
									selected={tempStartDate}
									onChange={(date) => setTempStartDate(date)}
									selectsStart
									startDate={tempStartDate}
									endDate={tempEndDate}
									maxDate={tempEndDate}
									className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500"
								/>
							</div>

							<div>
								<label className="block text-sm font-medium text-gray-700 mb-1">
									End Date
								</label>
								<DatePicker
									selected={tempEndDate}
									onChange={(date) => setTempEndDate(date)}
									selectsEnd
									startDate={tempStartDate}
									endDate={tempEndDate}
									minDate={tempStartDate}
									maxDate={new Date()}
									className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500"
								/>
							</div>
						</div>

						<div className="mt-6 flex gap-3 justify-end">
							<button
								onClick={() => setShowCustom(false)}
								className="btn-secondary"
							>
								Cancel
							</button>
							<button
								onClick={handleCustomApply}
								className="btn-primary"
							>
								Apply
							</button>
						</div>
					</div>
				</div>
			)}
		</div>
	);
}

export default DateRangeFilter;
