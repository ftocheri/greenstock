<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

const props = defineProps({
    products: Object,
    filters: Object,
});

const page = usePage();
const search = ref(props.filters.search ?? '');
const openRowId = ref(null);
const form = reactive({ delta: '', reason: '' });
const errors = ref({});

function submitSearch() {
    router.get(route('admin.inventory.index'), { search: search.value }, {
        preserveState: true,
        replace: true,
    });
}

function toggleRow(productId) {
    openRowId.value = openRowId.value === productId ? null : productId;
    form.delta = '';
    form.reason = '';
    errors.value = {};
}

function submitAdjustment(product) {
    router.post(
        route('admin.inventory.adjust', product.id),
        { delta: form.delta, reason: form.reason },
        {
            preserveScroll: true,
            onSuccess: () => {
                openRowId.value = null;
            },
            onError: (e) => {
                errors.value = e;
            },
        },
    );
}
</script>

<template>
    <Head title="Admin — Inventory" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">
                Admin — Inventory
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div
                    v-if="page.props.flash.success"
                    class="mb-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-800 dark:bg-green-900/40 dark:text-green-200"
                >
                    {{ page.props.flash.success }}
                </div>

                <div class="overflow-hidden rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
                    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                        Sorted by stock, lowest first. Adjustments are recorded on the movement
                        ledger as type <code class="rounded bg-gray-100 px-1 dark:bg-gray-700 dark:text-gray-200">adjustment</code>
                        — they show up in each product's movement history too.
                    </p>

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

                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="text-gray-500 dark:text-gray-400">
                                <th class="pb-2">SKU</th>
                                <th class="pb-2">Name</th>
                                <th class="pb-2">Stock</th>
                                <th class="pb-2">Reorder at</th>
                                <th class="pb-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="product in products.data" :key="product.id">
                                <tr class="border-t border-gray-100 dark:border-gray-700">
                                    <td class="py-2">
                                        <Link
                                            :href="route('products.show', product.id)"
                                            class="text-green-700 hover:underline dark:text-green-400"
                                        >
                                            {{ product.sku }}
                                        </Link>
                                    </td>
                                    <td class="py-2 text-gray-900 dark:text-gray-100">{{ product.name }}</td>
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
                                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ product.reorder_threshold }}</td>
                                    <td class="py-2 text-right">
                                        <button
                                            type="button"
                                            class="text-sm font-medium text-green-700 hover:underline dark:text-green-400"
                                            @click="toggleRow(product.id)"
                                        >
                                            {{ openRowId === product.id ? 'Cancel' : 'Adjust' }}
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="openRowId === product.id" class="border-t border-gray-100 bg-gray-50 dark:border-gray-700 dark:bg-gray-700/40">
                                    <td colspan="5" class="py-4">
                                        <form
                                            @submit.prevent="submitAdjustment(product)"
                                            class="flex flex-wrap items-start gap-3"
                                        >
                                            <div>
                                                <input
                                                    v-model.number="form.delta"
                                                    type="number"
                                                    placeholder="e.g. -5 or 20"
                                                    class="w-32 rounded-md border-gray-300 text-sm shadow-sm focus:border-green-500 focus:ring-green-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 dark:placeholder-gray-400"
                                                />
                                                <p v-if="errors.delta" class="mt-1 text-xs text-red-600 dark:text-red-400">
                                                    {{ errors.delta }}
                                                </p>
                                            </div>
                                            <div class="flex-1">
                                                <input
                                                    v-model="form.reason"
                                                    type="text"
                                                    placeholder="Reason (e.g. cycle count correction)"
                                                    class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-green-500 focus:ring-green-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 dark:placeholder-gray-400"
                                                />
                                                <p v-if="errors.reason" class="mt-1 text-xs text-red-600 dark:text-red-400">
                                                    {{ errors.reason }}
                                                </p>
                                            </div>
                                            <button
                                                type="submit"
                                                class="rounded-md bg-green-700 px-4 py-2 text-sm font-medium text-white hover:bg-green-800 dark:bg-green-600 dark:hover:bg-green-700"
                                            >
                                                Save adjustment
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            </template>
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
