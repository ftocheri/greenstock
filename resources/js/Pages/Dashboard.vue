<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Line } from 'vue-chartjs';
import {
    Chart as ChartJS,
    Title,
    Tooltip,
    Legend,
    LineElement,
    PointElement,
    CategoryScale,
    LinearScale,
} from 'chart.js';

ChartJS.register(Title, Tooltip, Legend, LineElement, PointElement, CategoryScale, LinearScale);

const props = defineProps({
    stats: Object,
    chart: Object,
    recentImports: Array,
});

const chartData = {
    labels: props.chart.labels,
    datasets: [
        {
            label: 'Units received',
            data: props.chart.received,
            borderColor: '#16a34a',
            backgroundColor: '#16a34a',
            tension: 0.3,
        },
        {
            label: 'Units sold',
            data: props.chart.sold,
            borderColor: '#dc2626',
            backgroundColor: '#dc2626',
            tension: 0.3,
        },
    ],
};

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        // A fixed mid-gray reads acceptably on both a white and a dark-gray
        // card, so the chart doesn't need to react to the theme toggle.
        legend: { position: 'bottom', labels: { color: '#9ca3af' } },
    },
    scales: {
        x: { ticks: { color: '#9ca3af' }, grid: { color: 'rgba(156,163,175,0.15)' } },
        y: { ticks: { color: '#9ca3af' }, grid: { color: 'rgba(156,163,175,0.15)' } },
    },
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">
                Dashboard
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div class="overflow-hidden rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total SKUs</div>
                        <div class="mt-1 text-3xl font-semibold text-gray-900 dark:text-gray-100">
                            {{ stats.totalSkus }}
                        </div>
                    </div>
                    <div class="overflow-hidden rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Low Stock Items</div>
                        <div class="mt-1 text-3xl font-semibold text-amber-600 dark:text-amber-400">
                            {{ stats.lowStockCount }}
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
                    <h3 class="mb-4 text-sm font-medium text-gray-500 dark:text-gray-400">
                        Stock movement, last 6 months
                    </h3>
                    <div class="h-72">
                        <Line :data="chartData" :options="chartOptions" />
                    </div>
                </div>

                <div class="overflow-hidden rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
                    <h3 class="mb-4 text-sm font-medium text-gray-500 dark:text-gray-400">
                        Recent supplier feed imports
                    </h3>
                    <table class="w-full text-left text-sm" v-if="recentImports.length">
                        <thead>
                            <tr class="text-gray-500 dark:text-gray-400">
                                <th class="pb-2">File</th>
                                <th class="pb-2">Processed</th>
                                <th class="pb-2">Skipped</th>
                                <th class="pb-2">Ran at</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="log in recentImports"
                                :key="log.id"
                                class="border-t border-gray-100 dark:border-gray-700"
                            >
                                <td class="py-2 text-gray-900 dark:text-gray-100">{{ log.filename }}</td>
                                <td class="py-2 text-green-700 dark:text-green-400">{{ log.rows_processed }}</td>
                                <td class="py-2 text-amber-700 dark:text-amber-400">{{ log.rows_skipped }}</td>
                                <td class="py-2 text-gray-500 dark:text-gray-400">
                                    {{ new Date(log.ran_at).toLocaleString() }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="text-sm text-gray-500 dark:text-gray-400">
                        No imports yet — run
                        <code class="rounded bg-gray-100 px-1 dark:bg-gray-700 dark:text-gray-200">php artisan inventory:import</code>
                        against a supplier feed to see runs here.
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
