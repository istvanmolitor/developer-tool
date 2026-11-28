<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Inbox, Plug, SendHorizontal } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import mailboxes from '@/routes/mailboxes';

const props = defineProps<{
    mailboxId: number;
    active: 'connection' | 'incoming' | 'outgoing';
    incomingCount: number;
    unreadCount: number;
    outgoingCount: number;
}>();

const tabs = [
    {
        key: 'connection' as const,
        label: 'Kapcsolat',
        icon: Plug,
        href: mailboxes.show(props.mailboxId).url,
    },
    {
        key: 'incoming' as const,
        label: 'Bejövő',
        icon: Inbox,
        href: mailboxes.incoming(props.mailboxId).url,
    },
    {
        key: 'outgoing' as const,
        label: 'Kimenő',
        icon: SendHorizontal,
        href: mailboxes.outgoing(props.mailboxId).url,
    },
];
</script>

<template>
    <nav
        class="flex gap-1 overflow-x-auto rounded-lg bg-muted p-1"
        aria-label="Postafiók nézetek"
    >
        <Link
            v-for="tab in tabs"
            :key="tab.key"
            :href="tab.href"
            :aria-current="active === tab.key ? 'page' : undefined"
            class="flex flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium whitespace-nowrap transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            :class="
                active === tab.key
                    ? 'bg-background shadow-xs'
                    : 'text-muted-foreground hover:text-foreground'
            "
        >
            <component :is="tab.icon" class="size-4" aria-hidden="true" />
            {{ tab.label }}
            <span v-if="tab.key === 'incoming'" class="text-muted-foreground"
                >({{ incomingCount }})</span
            >
            <span v-else-if="tab.key === 'outgoing'" class="text-muted-foreground"
                >({{ outgoingCount }})</span
            >
            <Badge
                v-if="tab.key === 'incoming' && unreadCount > 0"
                class="ml-0.5"
                :variant="active === 'incoming' ? 'default' : 'secondary'"
                >{{ unreadCount }}</Badge
            >
        </Link>
    </nav>
</template>
