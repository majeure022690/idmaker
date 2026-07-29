import { defineStore } from 'pinia';
import { api } from '../lib/api';
import { NAME_FIELD_CANDIDATES } from '../types/records';
import type { IdRecord, Paginated } from '../types/records';

export const useRecordsStore = defineStore('records', {
    state: () => ({
        items: [] as IdRecord[],
        currentPage: 1,
        lastPage: 1,
        total: 0,
        loading: false,
        search: '',
        filters: {} as Record<string, string>,
        fields: [] as string[],
        usedFields: [] as string[],
        sortBy: 'id' as string,
        sortDir: 'desc' as 'asc' | 'desc',
        selectedIds: new Set<number>(),
    }),

    getters: {
        selectedCount: (state) => state.selectedIds.size,
    },

    actions: {
        async fetchFields(): Promise<void> {
            const { data } = await api.get<{ fields: string[]; used_fields: string[] }>('/id-records/fields');
            this.fields = data.fields;
            this.usedFields = data.used_fields;

            // Default to sorting by whichever name-like field is actually
            // populated on current data (not just "a standard field name" -
            // `fields` always offers e.g. full_name as a suggestion even if
            // every record actually uses grantee_name instead) - only on
            // first load, so it doesn't clobber a sort the user picked.
            if (this.sortBy === 'id') {
                const nameField = NAME_FIELD_CANDIDATES.find((f) => data.used_fields.includes(f));
                if (nameField) {
                    this.sortBy = nameField;
                    this.sortDir = 'asc';
                }
            }
        },

        async fetchPage(page = 1): Promise<void> {
            this.loading = true;
            try {
                const { data } = await api.get<Paginated<IdRecord>>('/id-records', {
                    params: {
                        search: this.search || undefined,
                        filter: this.filters,
                        sort_by: this.sortBy,
                        sort_dir: this.sortDir,
                        page,
                        per_page: 25,
                    },
                });
                this.items = data.data;
                this.currentPage = data.current_page;
                this.lastPage = data.last_page;
                this.total = data.total;
            } finally {
                this.loading = false;
            }
        },

        async fetchAllMatchingIds(): Promise<number[]> {
            const { data } = await api.get<{ ids: number[] }>('/id-records/ids', {
                params: { search: this.search || undefined, filter: this.filters, sort_by: this.sortBy, sort_dir: this.sortDir },
            });
            return data.ids;
        },

        async create(data: Record<string, string | null>): Promise<IdRecord> {
            const { data: record } = await api.post<IdRecord>('/id-records', { data });
            return record;
        },

        async update(id: number, data: Record<string, string | null>): Promise<IdRecord> {
            const { data: record } = await api.put<IdRecord>(`/id-records/${id}`, { data });
            return record;
        },

        async remove(id: number): Promise<void> {
            await api.delete(`/id-records/${id}`);
            this.items = this.items.filter((r) => r.id !== id);
            this.selectedIds.delete(id);
        },

        /** Deletes every currently-selected record (potentially spanning pages, via "select all matching"). */
        async removeSelected(): Promise<number> {
            const ids = Array.from(this.selectedIds);
            const { data } = await api.post<{ deleted: number }>('/id-records/bulk-delete', { ids });
            this.items = this.items.filter((r) => !this.selectedIds.has(r.id));
            this.clearSelection();
            return data.deleted;
        },

        async uploadPhoto(id: number, file: File): Promise<IdRecord> {
            const formData = new FormData();
            formData.append('file', file);
            const { data } = await api.post<IdRecord>(`/id-records/${id}/photo`, formData);
            return data;
        },

        async uploadSignature(id: number, file: File): Promise<IdRecord> {
            const formData = new FormData();
            formData.append('file', file);
            const { data } = await api.post<IdRecord>(`/id-records/${id}/signature`, formData);
            return data;
        },

        toggleSelected(id: number): void {
            if (this.selectedIds.has(id)) this.selectedIds.delete(id);
            else this.selectedIds.add(id);
            this.selectedIds = new Set(this.selectedIds);
        },

        selectAllVisible(): void {
            const next = new Set(this.selectedIds);
            for (const r of this.items) next.add(r.id);
            this.selectedIds = next;
        },

        async selectAllMatching(): Promise<void> {
            const ids = await this.fetchAllMatchingIds();
            this.selectedIds = new Set(ids);
        },

        clearSelection(): void {
            this.selectedIds = new Set();
        },

        removeFromSelection(id: number): void {
            const next = new Set(this.selectedIds);
            next.delete(id);
            this.selectedIds = next;
        },
    },
});
