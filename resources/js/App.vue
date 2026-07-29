<script setup lang="ts">
import { useRoute } from 'vue-router';

const route = useRoute();
</script>

<template>
    <div class="flex h-screen flex-col">
        <nav v-if="!route.meta.fullscreen" class="flex items-center gap-1 border-b border-slate-200 bg-white px-6 py-3">
            <span class="mr-4 text-sm font-semibold text-slate-900">ID Maker</span>
            <router-link
                to="/"
                class="rounded px-3 py-1.5 text-sm"
                :class="route.name === 'templates' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'"
            >
                Templates
            </router-link>
            <router-link
                to="/records"
                class="rounded px-3 py-1.5 text-sm"
                :class="route.name === 'records' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'"
            >
                ID Records
            </router-link>
            <router-link
                to="/generate"
                class="rounded px-3 py-1.5 text-sm"
                :class="route.name === 'generate' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'"
            >
                Generate IDs
            </router-link>
        </nav>
        <div class="min-h-0 flex-1">
            <router-view :key="$route.fullPath" />
        </div>
    </div>
</template>
