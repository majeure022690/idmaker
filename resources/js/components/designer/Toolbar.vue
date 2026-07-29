<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useDesignerStore } from '../../stores/designer';
import { api } from '../../lib/api';
import type { IdRecord } from '../../types/records';

const emit = defineEmits<{ (e: 'save'): void }>();

const store = useDesignerStore();
const zoomLabel = computed(() => `${Math.round(store.zoom * 100)}%`);

const previewRecords = ref<IdRecord[]>([]);
const previewRecordId = ref<number | ''>('');
const previewing = ref(false);

function recordLabel(r: IdRecord): string {
    const candidates = ['full_name', 'grantee_name', 'name', 'id_number', 'household_id'];
    for (const key of candidates) {
        if (r.data[key]) return String(r.data[key]);
    }
    const firstValue = Object.values(r.data).find((v) => v);
    return firstValue ? String(firstValue) : `Record #${r.id}`;
}

async function previewWithRecord(): Promise<void> {
    if (!previewRecordId.value) return;
    previewing.value = true;
    try {
        const response = await api.post(
            '/templates/preview',
            {
                width: store.width,
                height: store.height,
                design: store.currentSide,
                record_id: previewRecordId.value,
            },
            { responseType: 'blob' },
        );
        const url = URL.createObjectURL(response.data as Blob);
        window.open(url, '_blank');
    } finally {
        previewing.value = false;
    }
}

onMounted(async () => {
    const { data } = await api.get('/id-records', { params: { per_page: 200 } });
    previewRecords.value = data.data;
});

function zoomIn(): void {
    store.zoom = Math.min(4, +(store.zoom * 1.2).toFixed(2));
}

function zoomOut(): void {
    store.zoom = Math.max(0.25, +(store.zoom / 1.2).toFixed(2));
}

function fitToScreen(): void {
    store.zoom = 1;
}

function toggleBackSide(): void {
    if (store.back && !confirm('Remove the back side? Its elements will be lost when you save.')) return;
    store.toggleBack(!store.back);
}
</script>

<template>
    <div class="flex items-center gap-2 border-b border-slate-200 bg-white px-4 py-2">
        <router-link to="/" class="mr-2 text-sm text-slate-500 hover:text-slate-800">&larr; Templates</router-link>

        <span class="mr-4 text-sm font-medium text-slate-900">{{ store.name }}</span>

        <button
            class="rounded px-2 py-1 text-sm text-slate-700 hover:bg-slate-100 disabled:opacity-40"
            :disabled="!store.canUndo"
            title="Undo"
            @click="store.undo()"
        >
            ↶ Undo
        </button>
        <button
            class="rounded px-2 py-1 text-sm text-slate-700 hover:bg-slate-100 disabled:opacity-40"
            :disabled="!store.canRedo"
            title="Redo"
            @click="store.redo()"
        >
            ↷ Redo
        </button>

        <div class="mx-2 h-5 w-px bg-slate-200" />

        <div v-if="store.back" class="flex overflow-hidden rounded border border-slate-300 text-sm">
            <button
                class="px-3 py-1"
                :class="store.side === 'front' ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-100'"
                @click="store.setSide('front')"
            >
                Front
            </button>
            <button
                class="px-3 py-1"
                :class="store.side === 'back' ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-100'"
                @click="store.setSide('back')"
            >
                Back
            </button>
        </div>
        <button class="rounded px-2 py-1 text-sm text-slate-600 hover:bg-slate-100" @click="toggleBackSide">
            {{ store.back ? 'Remove back' : '+ Add back side' }}
        </button>

        <div class="mx-2 h-5 w-px bg-slate-200" />

        <select v-model="previewRecordId" class="max-w-40 rounded border border-slate-300 px-2 py-1 text-sm" title="Record to preview with">
            <option value="">Preview with record…</option>
            <option v-for="r in previewRecords" :key="r.id" :value="r.id">{{ recordLabel(r) }}</option>
        </select>
        <button
            class="rounded border border-slate-300 px-2 py-1 text-sm text-slate-700 hover:bg-slate-50 disabled:opacity-40"
            :disabled="!previewRecordId || previewing"
            title="Render this side with the selected record's real data, exactly as it will print"
            @click="previewWithRecord"
        >
            {{ previewing ? 'Rendering…' : 'Preview' }}
        </button>

        <div class="mx-2 h-5 w-px bg-slate-200" />

        <label class="flex items-center gap-1 text-sm text-slate-600">
            <input type="checkbox" :checked="store.showGrid" @change="store.setShowGrid(($event.target as HTMLInputElement).checked)" />
            Grid
        </label>
        <label class="flex items-center gap-1 text-sm text-slate-600">
            <input type="checkbox" :checked="store.snapToGrid" @change="store.setSnapToGrid(($event.target as HTMLInputElement).checked)" />
            Snap
        </label>
        <input
            type="number"
            min="1"
            step="1"
            class="w-14 rounded border border-slate-300 px-1 py-0.5 text-sm"
            title="Grid size (mm)"
            :value="store.gridSizeMm"
            @change="store.setGridSize(Number(($event.target as HTMLInputElement).value) || 5)"
        />

        <div class="ml-auto flex items-center gap-2">
            <button class="rounded px-2 py-1 text-sm text-slate-700 hover:bg-slate-100" title="Zoom out" @click="zoomOut">−</button>
            <span class="w-12 text-center text-sm text-slate-600">{{ zoomLabel }}</span>
            <button class="rounded px-2 py-1 text-sm text-slate-700 hover:bg-slate-100" title="Zoom in" @click="zoomIn">+</button>
            <button class="rounded px-2 py-1 text-sm text-slate-700 hover:bg-slate-100" title="Fit to screen" @click="fitToScreen">Fit</button>

            <div class="mx-2 h-5 w-px bg-slate-200" />

            <span v-if="store.saving" class="text-xs text-slate-400">Saving…</span>
            <span v-else-if="!store.dirty && store.id" class="text-xs text-slate-400">Saved</span>

            <button
                class="rounded bg-slate-900 px-4 py-1.5 text-sm font-medium text-white hover:bg-slate-700"
                @click="emit('save')"
            >
                Save
            </button>
        </div>
    </div>
</template>
