<script setup lang="ts">
import { reactive, ref } from 'vue';
import { useRecordsStore } from '../../stores/records';
import { resolveStorageUrl } from '../../lib/storage';
import { fieldLabel } from '../../types/records';
import type { IdRecord } from '../../types/records';

const props = defineProps<{ record: IdRecord | null; availableFields: string[] }>();
const emit = defineEmits<{ (e: 'close'): void; (e: 'saved'): void }>();

const store = useRecordsStore();
const saving = ref(false);
const photoInput = ref<HTMLInputElement | null>(null);
const signatureInput = ref<HTMLInputElement | null>(null);
const currentRecord = ref<IdRecord | null>(props.record);

const rows = reactive<{ key: string; value: string }[]>(
    props.record
        ? Object.entries(props.record.data).map(([key, value]) => ({ key, value: value ?? '' }))
        : [{ key: 'full_name', value: '' }, { key: 'id_number', value: '' }]
);
const newFieldKey = ref('');

function addField(key?: string): void {
    const k = (key ?? newFieldKey.value).trim();
    if (!k || rows.some((r) => r.key === k)) return;
    rows.push({ key: k, value: '' });
    newFieldKey.value = '';
}

function removeField(index: number): void {
    rows.splice(index, 1);
}

async function save(): Promise<void> {
    saving.value = true;
    try {
        const data: Record<string, string | null> = {};
        for (const row of rows) {
            if (row.key.trim()) data[row.key.trim()] = row.value;
        }
        if (currentRecord.value) {
            await store.update(currentRecord.value.id, data);
        } else {
            currentRecord.value = await store.create(data);
        }
        emit('saved');
    } finally {
        saving.value = false;
    }
}

async function onPhotoSelected(event: Event): Promise<void> {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file || !currentRecord.value) return;
    currentRecord.value = await store.uploadPhoto(currentRecord.value.id, file);
    emit('saved');
}

async function onSignatureSelected(event: Event): Promise<void> {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file || !currentRecord.value) return;
    currentRecord.value = await store.uploadSignature(currentRecord.value.id, file);
    emit('saved');
}

const suggestions = props.availableFields.filter((f) => !rows.some((r) => r.key === f));
</script>

<template>
    <div class="fixed inset-0 z-20 flex items-center justify-center bg-black/30 p-4">
        <div class="flex max-h-[90vh] w-full max-w-lg flex-col rounded-lg bg-white shadow-xl">
            <div class="border-b border-slate-200 px-6 py-4">
                <h2 class="text-lg font-semibold">{{ currentRecord ? 'Edit Record' : 'New Record' }}</h2>
            </div>

            <div class="flex-1 overflow-y-auto px-6 py-4">
                <div class="flex flex-col gap-2">
                    <div v-for="(row, index) in rows" :key="index" class="flex items-center gap-2">
                        <input
                            v-model="row.key"
                            type="text"
                            class="w-36 shrink-0 rounded border border-slate-300 px-2 py-1 text-xs text-slate-600"
                            :placeholder="fieldLabel(row.key)"
                        />
                        <input v-model="row.value" type="text" class="flex-1 rounded border border-slate-300 px-2 py-1 text-sm" />
                        <button class="shrink-0 text-red-500 hover:text-red-700" title="Remove field" @click="removeField(index)">✕</button>
                    </div>
                </div>

                <div class="mt-3 flex items-center gap-2">
                    <input
                        v-model="newFieldKey"
                        type="text"
                        placeholder="custom_field_name"
                        class="flex-1 rounded border border-slate-300 px-2 py-1 text-sm"
                        @keydown.enter.prevent="addField()"
                    />
                    <button class="rounded border border-slate-300 px-3 py-1 text-sm hover:bg-slate-50" @click="addField()">Add field</button>
                </div>

                <div v-if="suggestions.length" class="mt-2 flex flex-wrap gap-1">
                    <button
                        v-for="s in suggestions"
                        :key="s"
                        class="rounded-full border border-slate-200 px-2 py-0.5 text-xs text-slate-500 hover:bg-slate-100"
                        @click="addField(s)"
                    >
                        + {{ fieldLabel(s) }}
                    </button>
                </div>

                <div v-if="currentRecord" class="mt-5 grid grid-cols-2 gap-4 border-t border-slate-100 pt-4">
                    <div>
                        <p class="mb-1 text-xs text-slate-500">Photo</p>
                        <img
                            v-if="currentRecord.photo_path"
                            :src="resolveStorageUrl(currentRecord.photo_path)"
                            class="mb-2 h-20 w-16 rounded border border-slate-200 object-cover"
                        />
                        <button class="rounded border border-slate-300 px-2 py-1 text-xs hover:bg-slate-50" @click="photoInput?.click()">Upload</button>
                        <input ref="photoInput" type="file" accept="image/png,image/jpeg" class="hidden" @change="onPhotoSelected" />
                    </div>
                    <div>
                        <p class="mb-1 text-xs text-slate-500">Signature</p>
                        <img
                            v-if="currentRecord.signature_path"
                            :src="resolveStorageUrl(currentRecord.signature_path)"
                            class="mb-2 h-12 w-24 rounded border border-slate-200 object-contain bg-slate-50"
                        />
                        <button class="rounded border border-slate-300 px-2 py-1 text-xs hover:bg-slate-50" @click="signatureInput?.click()">Upload</button>
                        <input ref="signatureInput" type="file" accept="image/png,image/jpeg" class="hidden" @change="onSignatureSelected" />
                    </div>
                </div>
                <p v-else class="mt-4 text-xs text-slate-400">Save the record first to attach a photo or signature.</p>
            </div>

            <div class="flex justify-end gap-2 border-t border-slate-200 px-6 py-4">
                <button class="rounded px-4 py-2 text-sm text-slate-600 hover:bg-slate-100" @click="emit('close')">Close</button>
                <button
                    class="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700 disabled:opacity-50"
                    :disabled="saving"
                    @click="save"
                >
                    {{ saving ? 'Saving…' : 'Save' }}
                </button>
            </div>
        </div>
    </div>
</template>
