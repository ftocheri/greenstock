<script setup>
import { useTheme } from '@/composables/useTheme';

const { theme, toggleTheme } = useTheme();
</script>

<template>
    <button
        type="button"
        @click="toggleTheme"
        :aria-label="theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'"
        class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none dark:text-gray-500 dark:hover:bg-gray-700 dark:hover:text-gray-300 dark:focus:bg-gray-700 dark:focus:text-gray-300"
    >
        <!-- Sun (shown in dark mode, click to go light) -->
        <svg v-if="theme === 'dark'" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
            <circle cx="12" cy="12" r="4" />
            <path stroke-linecap="round" d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
        </svg>
        <!-- Moon (shown in light mode, click to go dark) -->
        <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
        </svg>
    </button>
</template>
