<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
    laravelVersion: {
        type: String,
        required: true,
    },
    phpVersion: {
        type: String,
        required: true,
    },
});

const features = [
    {
        title: 'Ledger, not a column',
        body:
            "Stock is never stored as a single mutable number. Every receipt, sale, and correction is an append-only row in an inventory_movements table, so current stock is always a computed sum — auditable back to the exact movement that produced it.",
    },
    {
        title: 'A real import pipeline',
        body:
            'Supplier feeds move through a five-stage pipeline — parse, validate, dedupe, map, and commit — with mismatched or malformed rows skipped and logged rather than silently dropped or crashing the run.',
    },
    {
        title: 'Restricted admin controls',
        body:
            'A single admin account can issue manual stock adjustments with a required reason. Every adjustment lands on the same movement ledger as automated imports, so nothing bypasses the audit trail.',
    },
];
</script>

<template>
    <Head title="GreenStock" />

    <div class="min-h-screen bg-gray-50 text-gray-900 dark:bg-gray-900 dark:text-gray-100">
        <div class="mx-auto max-w-6xl px-6">
            <header class="flex items-center justify-between py-8">
                <div class="flex items-center gap-3">
                    <ApplicationLogo class="h-9 w-9 fill-current text-green-700 dark:text-green-400" />
                    <span class="text-lg font-semibold tracking-tight">GreenStock</span>
                </div>

                <nav class="flex items-center gap-2">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="route('dashboard')"
                        class="rounded-md px-3 py-2 text-sm font-medium text-gray-700 transition hover:text-gray-900 dark:text-gray-300 dark:hover:text-gray-100"
                    >
                        Dashboard
                    </Link>
                    <template v-else-if="canLogin">
                        <Link
                            :href="route('login')"
                            class="rounded-md px-3 py-2 text-sm font-medium text-gray-700 transition hover:text-gray-900 dark:text-gray-300 dark:hover:text-gray-100"
                        >
                            Log in
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="rounded-md px-3 py-2 text-sm font-medium text-gray-700 transition hover:text-gray-900 dark:text-gray-300 dark:hover:text-gray-100"
                        >
                            Register
                        </Link>
                    </template>
                    <ThemeToggle />
                </nav>
            </header>

            <main>
                <section class="py-16 sm:py-24">
                    <p class="text-sm font-medium uppercase tracking-wide text-green-700 dark:text-green-400">
                        Live demo
                    </p>
                    <h1 class="mt-3 max-w-2xl text-4xl font-bold tracking-tight sm:text-5xl">
                        Inventory tracking built on an audit trail, not a guess.
                    </h1>
                    <p class="mt-6 max-w-2xl text-lg text-gray-600 dark:text-gray-400">
                        GreenStock is a Laravel + Inertia/Vue demo app: supplier feeds are ingested
                        through a validating import pipeline, every stock change is recorded on an
                        append-only movement ledger, and a small admin area lets a trusted account
                        make manual corrections without losing that history.
                    </p>
                    <div class="mt-8 flex flex-wrap items-center gap-4">
                        <Link
                            v-if="!$page.props.auth.user && canLogin"
                            :href="route('login')"
                            class="rounded-md bg-green-700 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-green-800 dark:bg-green-600 dark:hover:bg-green-700"
                        >
                            Log in to the demo
                        </Link>
                        <Link
                            v-else-if="$page.props.auth.user"
                            :href="route('dashboard')"
                            class="rounded-md bg-green-700 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-green-800 dark:bg-green-600 dark:hover:bg-green-700"
                        >
                            Go to dashboard
                        </Link>
                        <a
                            href="https://github.com/ftocheri/greenstock"
                            class="rounded-md border border-gray-300 px-5 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-100 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                        >
                            View source on GitHub
                        </a>
                    </div>
                </section>

                <section class="grid gap-6 border-t border-gray-200 py-16 sm:grid-cols-3 dark:border-gray-700">
                    <div v-for="feature in features" :key="feature.title">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                            {{ feature.title }}
                        </h2>
                        <p class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                            {{ feature.body }}
                        </p>
                    </div>
                </section>
            </main>

            <footer
                class="flex flex-col items-center justify-between gap-4 border-t border-gray-200 py-8 text-sm text-gray-500 sm:flex-row dark:border-gray-700 dark:text-gray-400"
            >
                <span>Laravel v{{ laravelVersion }} (PHP v{{ phpVersion }})</span>
                <div class="flex items-center gap-4">
                    <a href="https://github.com/ftocheri/greenstock" class="hover:text-gray-900 dark:hover:text-gray-100">
                        GitHub
                    </a>
                    <a href="https://ftocheri.github.io" class="hover:text-gray-900 dark:hover:text-gray-100">
                        ftocheri.github.io
                    </a>
                </div>
            </footer>
        </div>
    </div>
</template>
