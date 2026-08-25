<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    products: Object,
    filters: Object,
});

const search = ref(props.filters.search ?? '');

function submitSearch() {
    router.get(
        route('products.index'),
        { search: search.value, sort: props.filters.sort, direction: props.filters.direction },
        { preserveState: true, replace: true },
    );
}

function sortBy(column) {
    const direction =
        props.filters.sort === column && props.filters.direction === 'asc' ? 'desc' : 'asc';

    router.get(
        route('products.index'),
        { search: search.value, sort: column, direction },
        { preserveState: true, replace: true },
    );
}

function sortIndicator(column) {
    if (props.filters.sort !== column) return '';
    return props.filters.direction === 'desc' ? ' ↓' : ' ↑';
}
</script>

<template>
    <Head title="Products" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Products
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-lg bg-white p-6 shadow-sm">
                    <form @submit.prevent="submitSearch" class="mb-4 flex gap-2">
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search by name or SKU..."
                            class="w-full max-w-sm rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm"
                        />
                        <button
                            type="submit"
                            class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700"
                        >
                            Search
                        </button>
                    </form>

                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="text-gray-500">
                                <th class="cursor-pointer pb-2" @click="sortBy('sku')">
                                    SKU{{ sortIndicator('sku') }}
                                </th>
                                <th class="cursor-pointer pb-2" @click="sortBy('name')">
                                    Name{{ sortIndicator('name') }}
                                </th>
                                <th class="pb-2">Category</th>
                                <th class="pb-2">Supplier</th>
                                <th class="cursor-pointer pb-2" @click="sortBy('current_stock')">
                                    Stock{{ sortIndicator('current_stock') }}
                                </th>
                                <th class="cursor-pointer pb-2" @click="sortBy('unit_price')">
                                    Price{{ sortIndicator('unit_price') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="product in products.data"
                                :key="product.id"
                                class="border-t border-gray-100"
                            >
                                <td class="py-2">
                                    <Link
                                        :href="route('products.show', product.id)"
                                        class="text-green-700 hover:underline"
                                    >
                                        {{ product.sku }}
                                    </Link>
                                </td>
                                <td class="py-2 text-gray-900">{{ product.name }}</td>
                                <td class="py-2 text-gray-500">{{ product.category?.name }}</td>
                                <td class="py-2 text-gray-500">{{ product.supplier?.name }}</td>
                                <td class="py-2">
                                    <span
                                        :class="
                                            product.current_stock <= product.reorder_threshold
                                                ? 'rounded bg-amber-100 px-2 py-0.5 text-amber-800'
                                                : 'text-gray-900'
                                        "
                                    >
                                        {{ product.current_stock }}
                                    </span>
                                </td>
                                <td class="py-2 text-gray-900">
                                    ${{ Number(product.unit_price).toFixed(2) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-if="products.data.length === 0" class="py-6 text-sm text-gray-500">
                        No products match that search.
                    </div>

                    <div class="mt-4 flex flex-wrap gap-1">
                        <Link
                            v-for="link in products.links"
                            :key="link.label"
                            :href="link.url ?? '#'"
                            v-html="link.label"
                            class="rounded px-3 py-1 text-sm"
                            :class="[
                                link.active ? 'bg-gray-800 text-white' : 'text-gray-600 hover:bg-gray-100',
                                !link.url && 'pointer-events-none opacity-50',
                            ]"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
