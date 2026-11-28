<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Inbox, Pencil } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import mailAddresses from '@/routes/mail-addresses';

type MailAddress = {
    id: number;
    mail: string;
    name: string | null;
    is_spam: boolean;
};

type ConversationMessage = {
    id: string;
    direction: 'incoming' | 'outgoing';
    subject: string | null;
    body: string | null;
    status: 'draft' | 'sent' | 'failed' | null;
    mailbox: { id: number; name: string; email: string } | null;
    date: string | null;
};

defineProps<{
    mailAddress: MailAddress;
    conversation: ConversationMessage[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'E-mail címek', href: mailAddresses.index().url }],
    },
});

const statusLabels: Record<'draft' | 'sent' | 'failed', string> = {
    draft: 'Piszkozat',
    sent: 'Elküldve',
    failed: 'Sikertelen',
};

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString('hu-HU') : '—';
}
</script>

<template>
    <Head :title="`${mailAddress.mail} — üzenetváltás`" />

    <div class="mx-auto flex h-full w-full max-w-3xl flex-1 flex-col gap-4 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="mailAddress.name || mailAddress.mail"
                :description="mailAddress.name ? mailAddress.mail : 'Üzenetváltás'"
            />
            <div class="flex shrink-0 items-center gap-2">
                <Badge v-if="mailAddress.is_spam" variant="destructive"
                    >Spam</Badge
                >
                <Button variant="outline" size="sm" as-child>
                    <Link :href="mailAddresses.edit(mailAddress.id).url">
                        <Pencil class="size-4" />
                        Szerkesztés
                    </Link>
                </Button>
            </div>
        </div>

        <div
            v-if="conversation.length === 0"
            class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            <Inbox class="mx-auto mb-2 size-6" />
            Ezzel a címmel még nincs levélváltás.
        </div>

        <ol v-else class="flex flex-col gap-3">
            <li
                v-for="message in conversation"
                :key="message.id"
                class="flex"
                :class="
                    message.direction === 'outgoing'
                        ? 'justify-end'
                        : 'justify-start'
                "
            >
                <div
                    class="max-w-[85%] rounded-2xl border px-4 py-2.5 sm:max-w-[75%]"
                    :class="
                        message.direction === 'outgoing'
                            ? 'rounded-br-sm bg-primary/10'
                            : 'rounded-bl-sm bg-muted'
                    "
                >
                    <div
                        class="mb-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground"
                    >
                        <span class="font-medium text-foreground">
                            {{
                                message.direction === 'outgoing'
                                    ? message.mailbox?.name || 'Mi'
                                    : mailAddress.name || mailAddress.mail
                            }}
                        </span>
                        <Badge
                            v-if="message.status"
                            :variant="
                                message.status === 'failed'
                                    ? 'destructive'
                                    : message.status === 'sent'
                                      ? 'secondary'
                                      : 'outline'
                            "
                            >{{ statusLabels[message.status] }}</Badge
                        >
                        <span>{{ formatDate(message.date) }}</span>
                    </div>
                    <p class="text-sm font-medium">
                        {{ message.subject || '(nincs tárgy)' }}
                    </p>
                    <pre
                        class="mt-1 max-h-80 overflow-auto font-sans text-sm whitespace-pre-wrap"
                        >{{ message.body || '(nincs szöveges tartalom)' }}</pre
                    >
                    <p
                        v-if="message.mailbox"
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        {{ message.mailbox.name }} ({{ message.mailbox.email }})
                    </p>
                </div>
            </li>
        </ol>
    </div>
</template>
