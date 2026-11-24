import { router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { reactive, watch } from 'vue';

export type ListFilterValue = string | number | null;

/**
 * Keeps a list page's filters in the URL: every change reloads the page
 * (debounced, keeping state and scroll) with the non-empty filters as the
 * query string, and always restarts from the first page.
 */
export function useListFilters<T extends Record<string, ListFilterValue>>(
    initial: T,
) {
    const filters = reactive({ ...initial }) as T;

    function apply(): void {
        const query: Record<string, string | number> = {};

        for (const [key, value] of Object.entries(filters)) {
            if (value !== null && value !== '') {
                query[key] = value;
            }
        }

        router.get(window.location.pathname, query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    const applyDebounced = useDebounceFn(apply, 300);

    watch(() => ({ ...filters }), applyDebounced);

    function reset(): void {
        for (const key of Object.keys(filters) as (keyof T)[]) {
            filters[key] = (
                typeof initial[key] === 'string' ? '' : null
            ) as T[keyof T];
        }
    }

    const isFiltered = (): boolean =>
        Object.values(filters).some((value) => value !== null && value !== '');

    return { filters, reset, isFiltered };
}
