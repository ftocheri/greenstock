<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    product: Object,
    movements: Array,
});

const typeStyles = {
    in: 'text-green-700 dark:text-green-400',
    out: 'text-red-700 dark:text-red-400',
    adjustment: 'text-gray-500 dark:text-gray-400',
};
</script>

<template>
    <Head :title="product.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">
                    {{ product.name }}
                </h2>
                <Link :href="route('products.index')" class="text-sm text-green-700 hover:underline dark:text-green-400">
                    &larr; Back to products
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div class="grid grid-cols-2 gap-6 rounded-lg bg-white p-6 shadow-sm sm:grid-cols-4 dark:bg-gray-800">
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">SKU</div>
                        <div class="font-medium text-gray-900 dark:text-gray-100">{{ product.sku }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Current Stock</div>
                        <div
                            class="font-medium"
                            :class="product.current_stock <= product.reorder_threshold ? 'text-amber-600 dark:text-amber-400' : 'text-gray-900 dark:text-gray-100'"
                        >
                            {{ product.current_stock }}
                        </div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Category</div>
                        <div class="font-medium text-gray-900 dark:text-gray-100">{{ product.category?.name }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Supplier</div>
                        <div class="font-medium text-gray-900 dark:text-gray-100">{{ product.supplier?.name }}</div>
                    </div>
                </div>

                <div v-if="product.description" class="rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Description</div>
                    <p class="mt-1 text-gray-900 dark:text-gray-100">{{ product.description }}</p>
                </div>

                <div class="overflow-hidden rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
                    <h3 class="mb-4 text-sm font-medium text-gray-500 dark:text-gray-400">
                        Movement history (most recent 50)
                    </h3>
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="text-gray-500 dark:text-gray-400">
                                <th class="pb-2">Date</th>
                                <th class="pb-2">Type</th>
                                <th class="pb-2">Quantity</th>
                                <th class="pb-2">Source</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="movement in movements"
                                :key="movement.id"
                                class="border-t border-gray-100 dark:border-gray-700"
                            >
                                <td class="py-2 text-gray-500 dark:text-gray-400">
                                    {{ new Date(movement.occurred_at).toLocaleDateString() }}
                                </td>
                                <td class="py-2 font-medium" :class="typeStyles[movement.type]">
                                    {{ movement.type }}
                                </td>
                                <td class="py-2 text-gray-900 dark:text-gray-100">{{ movement.quantity }}</td>
                                <td class="py-2 text-gray-500 dark:text-gray-400">{{ movement.source }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
