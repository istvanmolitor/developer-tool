<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    MessageSquare,
    Pencil,
    Plus,
    Trash2,
} from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import ListSearchInput from '@/components/ListSearchInput.vue';
import PaginationBar from '@/components/PaginationBar.vue';
import type { Paginator } from '@/components/PaginationBar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useListFilters } from '@/composables/useListFilters';
import mailAddressRoutes from '@/routes/mail-addresses';

type MailAddress = {
    id: number;
    mail: string;
    name: string | null;
    is_spam: boolean;
    created_at: string | null;
};

type SortKey = 'mail' | 'name' | 'is_spam' | 'created_at';

const props = defineProps<{
    mailAddresses: Paginator & { data: MailAddress[] };
    filters: { q: string; sort: SortKey; direction: 'asc' | 'desc' };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'E-mail címek', href: mailAddressRoutes.index().url }],
    },
});

const { filters } = useListFilters({ ...props.filters });

const columns: { key: SortKey; label: string; class?: string }[] = [
    { key: 'mail', label: 'E-mail cím' },
    { key: 'name', label: 'Név' },
    { key: 'is_spam', label: 'Spam' },
    { key: 'created_at', label: 'Felvéve' },
];

/**
 * Sort by a column; clicking the active column flips the direction.
 */
function toggleSort(key: SortKey): void {
    if (filters.sort === key) {
        filters.direction = filters.direction === 'asc' ? 'desc' : 'asc';
    } else {
        filters.sort = key;
        filters.direction = 'asc';
    }
}

function destroy(address: MailAddress): void {
    if (
        !window.confirm(
            `Törlöd a(z) „${address.mail}” címet a listából? A levelek megmaradnak.`,
        )
    ) {
        return;
    }

    router.delete(mailAddressRoutes.destroy(address.id).url, {
        preserveScroll: true,
    });
}

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString('hu-HU') : '—';
}
</script>

<template>
    <Head title="E-mail címek" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="E-mail címek"
                description="Minden bejövő és kimenő levélben szereplő cím."
            />
            <Button size="sm" as-child>
                <Link :href="mailAddressRoutes.create().url">
                    <Plus class="size-4" />
                    Új cím
                </Link>
            </Button>
        </div>

        <ListSearchInput
            v-model="filters.q"
            placeholder="Keresés cím vagy név szerint…"
            class="max-w-md"
        />

        <div class="overflow-x-auto rounded-md border">
            <table class="w-full text-sm">
                <thead class="border-b bg-muted/50 text-left">
                    <tr>
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            class="px-3 py-2 font-medium"
                        >
                            <button
                                type="button"
                                class="flex items-center gap-1 hover:text-foreground focus-visible:outline-none"
                                :class="
                                    filters.sort === column.key
                                        ? 'text-foreground'
                                        : 'text-muted-foreground'
                                "
                                @click="toggleSort(column.key)"
                            >
                                {{ column.label }}
                                <ArrowUp
                                    v-if="
                                        filters.sort === column.key &&
                                        filters.direction === 'asc'
                                    "
                                    class="size-3.5"
                                    aria-hidden="true"
                                />
                                <ArrowDown
                                    v-else-if="filters.sort === column.key"
                                    class="size-3.5"
                                    aria-hidden="true"
                                />
                                <ArrowUpDown
                                    v-else
                                    class="size-3.5 opacity-50"
                                    aria-hidden="true"
                                />
                            </button>
                        </th>
                        <th class="px-3 py-2 text-right font-medium">
                            <span class="sr-only">Műveletek</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr
                        v-if="mailAddresses.data.length === 0"
                    >
                        <td
                            colspan="5"
                            class="px-3 py-8 text-center text-muted-foreground"
                        >
                            Nincs a szűrésnek megfelelő cím.
                        </td>
                    </tr>
                    <tr
                        v-for="address in mailAddresses.data"
                        :key="address.id"
                        class="hover:bg-muted/40"
                    >
                        <td class="px-3 py-2">
                            <Link
                                :href="mailAddressRoutes.show(address.id).url"
                                class="font-medium break-all underline-offset-4 hover:underline"
                                >{{ address.mail }}</Link
                            >
                        </td>
                        <td class="px-3 py-2">
                            {{ address.name || '—' }}
                        </td>
                        <td class="px-3 py-2">
                            <Badge v-if="address.is_spam" variant="destructive"
                                >Spam</Badge
                            >
                            <span v-else class="text-muted-foreground">—</span>
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap text-muted-foreground">
                            {{ formatDate(address.created_at) }}
                        </td>
                        <td class="px-3 py-2">
                            <div class="flex items-center justify-end gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-8"
                                    as-child
                                >
                                    <Link
                                        :href="mailAddressRoutes.show(address.id).url"
                                        :aria-label="`${address.mail} beszélgetése`"
                                        title="Üzenetváltás"
                                    >
                                        <MessageSquare class="size-4" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-8"
                                    as-child
                                >
                                    <Link
                                        :href="mailAddressRoutes.edit(address.id).url"
                                        :aria-label="`${address.mail} szerkesztése`"
                                        title="Szerkesztés"
                                    >
                                        <Pencil class="size-4" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-8 text-muted-foreground hover:text-destructive"
                                    :aria-label="`${address.mail} törlése`"
                                    title="Törlés"
                                    @click="destroy(address)"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <PaginationBar :paginator="mailAddresses" noun="cím" />
    </div>
</template>
