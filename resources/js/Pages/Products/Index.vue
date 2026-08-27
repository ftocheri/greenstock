<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

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

// The AI search box is fully decoupled from applying a filter — it only ever produces the same
// query params a human could type into the URL bar. This component never treats an AI-derived
// request any differently from a manually-typed one once it comes back.
const aiQuery = ref('');
const aiError = ref(null);
const aiLoading = ref(false);

async function submitAiSearch() {
    const trimmed = aiQuery.value.trim();
    if (!trimmed) return;

    aiError.value = null;
    aiLoading.value = true;

    try {
        const response = await fetch(route('products.ai-search', { query: trimmed }), {
            headers: { Accept: 'application/json' },
        });
        const data = await response.json();

        if (!response.ok) {
            aiError.value = data.error ?? 'Something went wrong — try again.';
            return;
        }

        router.get(route('products.index'), data.filters, { preserveState: true, replace: true });
    } catch {
        aiError.value = "Couldn't reach the AI service — try again, or use the search box below.";
    } finally {
        aiLoading.value = false;
    }
}

// Surfaces which of the filter dimensions are active — supplier/category/stock-range/low-stock
// have no dedicated input anywhere else on this page, so without this the AI search would be
// a black box: results change, but nothing shows why.
const activeFilterSummary = computed(() => {
    const parts = [];
    if (props.filters.search) parts.push(`matching "${props.filters.search}"`);
    if (props.filters.supplier) parts.push(`from ${props.filters.supplier}`);
    if (props.filters.category) parts.push(`in ${props.filters.category}`);
    if (props.filters.min_stock) parts.push(`stock ≥ ${props.filters.min_stock}`);
    if (props.filters.max_stock) parts.push(`stock ≤ ${props.filters.max_stock}`);
    if (props.filters.low_stock) parts.push('at or below reorder threshold');

    return parts.length ? `Filtered: ${parts.join(', ')}` : null;
});
</script>

<template>
    <Head title="Products" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">
                Products
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
                    <form @submit.prevent="submitSearch" class="mb-4 flex gap-2">
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search by name or SKU..."
                            class="w-full max-w-sm rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 dark:placeholder-gray-400"
                        />
                        <button
                            type="submit"
                            class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 dark:bg-gray-600 dark:hover:bg-gray-500"
                        >
                            Search
                        </button>
                    </form>

                    <form
                        @submit.prevent="submitAiSearch"
                        class="mb-2 flex gap-2 rounded-md border border-dashed border-green-300 bg-green-50/50 p-3 dark:border-green-800 dark:bg-green-900/10"
                    >
                        <input
                            v-model="aiQuery"
                            type="text"
                            placeholder='Ask about your inventory — "everything from Wisoky under 20 units"'
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 dark:placeholder-gray-400"
                        />
                        <button
                            type="submit"
                            :disabled="aiLoading"
                            class="whitespace-nowrap rounded-md bg-green-700 px-4 py-2 text-sm font-medium text-white hover:bg-green-800 disabled:opacity-60 dark:bg-green-600 dark:hover:bg-green-700"
                        >
                            {{ aiLoading ? 'Asking…' : 'Ask AI' }}
                        </button>
                    </form>
                    <p v-if="aiError" class="mb-4 text-sm text-red-600 dark:text-red-400">{{ aiError }}</p>
                    <p v-else-if="activeFilterSummary" class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                        {{ activeFilterSummary }}
                    </p>

                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="text-gray-500 dark:text-gray-400">
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
                                class="border-t border-gray-100 dark:border-gray-700"
                            >
                                <td class="py-2">
                                    <Link
                                        :href="route('products.show', product.id)"
                                        class="text-green-700 hover:underline dark:text-green-400"
                                    >
                                        {{ product.sku }}
                                    </Link>
                                </td>
                                <td class="py-2 text-gray-900 dark:text-gray-100">{{ product.name }}</td>
                                <td class="py-2 text-gray-500 dark:text-gray-400">{{ product.category?.name }}</td>
                                <td class="py-2 text-gray-500 dark:text-gray-400">{{ product.supplier?.name }}</td>
                                <td class="py-2">
                                    <span
                                        :class="
                                            product.current_stock <= product.reorder_threshold
                                                ? 'rounded bg-amber-100 px-2 py-0.5 text-amber-800 dark:bg-amber-900 dark:text-amber-200'
                                                : 'text-gray-900 dark:text-gray-100'
                                        "
                                    >
                                        {{ product.current_stock }}
                                    </span>
                                </td>
                                <td class="py-2 text-gray-900 dark:text-gray-100">
                                    ${{ Number(product.unit_price).toFixed(2) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-if="products.data.length === 0" class="py-6 text-sm text-gray-500 dark:text-gray-400">
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
                                link.active ? 'bg-gray-800 text-white dark:bg-gray-600' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700',
                                !link.url && 'pointer-events-none opacity-50',
                            ]"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
