<div class="overflow-x-auto">
    <table class="w-full divide-y divide-gray-200 text-left text-xs dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-3 py-2 font-semibold">Ward</th>
                <th class="px-3 py-2 font-semibold">Incharge</th>
                <th class="px-3 py-2 text-center font-semibold">Beds</th>
                <th class="px-3 py-2 text-center font-semibold">B/F</th>
                <th class="px-3 py-2 text-center font-semibold">Adm</th>
                <th class="px-3 py-2 text-center font-semibold">Tr In</th>
                <th class="px-3 py-2 text-center font-semibold">Disch</th>
                <th class="px-3 py-2 text-center font-semibold">Tr Out</th>
                <th class="px-3 py-2 text-center font-semibold">Deaths</th>
                <th class="px-3 py-2 text-center font-semibold">LAMA</th>
                <th class="px-3 py-2 text-center font-semibold">Rem.</th>
                <th class="px-3 py-2 text-center font-semibold">Occ %</th>
                <th class="px-3 py-2 font-semibold">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            @forelse ($rows as $row)
                <tr>
                    <td class="px-3 py-2 font-medium text-gray-900 dark:text-white">{{ $row['ward_name'] }}</td>
                    <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ $row['incharge'] ?? '—' }}</td>
                    <td class="px-3 py-2 text-center">{{ $row['total_beds'] }}@if($row['blocked_beds']) ({{ $row['blocked_beds'] }} blk)@endif</td>
                    <td class="px-3 py-2 text-center">{{ $row['brought_forward'] }}</td>
                    <td class="px-3 py-2 text-center">{{ $row['admissions'] }}</td>
                    <td class="px-3 py-2 text-center">{{ $row['transfers_in'] }}</td>
                    <td class="px-3 py-2 text-center">{{ $row['discharges'] }}</td>
                    <td class="px-3 py-2 text-center">{{ $row['transfers_out'] }}</td>
                    <td class="px-3 py-2 text-center {{ $row['deaths'] > 0 ? 'font-semibold text-danger-600' : '' }}">{{ $row['deaths'] }}</td>
                    <td class="px-3 py-2 text-center">{{ $row['lama_absconded'] }}</td>
                    <td class="px-3 py-2 text-center font-semibold">{{ $row['remaining'] }}</td>
                    <td class="px-3 py-2 text-center">{{ $row['occupancy_pct'] }}%</td>
                    <td class="px-3 py-2">{{ $row['status_label'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="13" class="px-3 py-4 text-center text-gray-500">No active wards found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
