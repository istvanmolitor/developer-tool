<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Check,
    MessageSquare,
    Paperclip,
    Reply as ReplyIcon,
    Send,
    SendHorizontal,
    SquarePen,
    Trash2,
} from '@lucide/vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import ListSearchInput from '@/components/ListSearchInput.vue';
import PaginationBar from '@/components/PaginationBar.vue';
import type { Paginator } from '@/components/PaginationBar.vue';
import SearchUnavailableAlert from '@/components/SearchUnavailableAlert.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useListFilters } from '@/composables/useListFilters';
import mailAddresses from '@/routes/mail-addresses';
import mailboxes from '@/routes/mailboxes';
import MailboxTabs from './MailboxTabs.vue';

type Mailbox = {
    id: number;
    name: string;
    email: string;
};

type Attachment = {
    id: number;
    filename: string;
    mime_type: string;
    size: number;
};

type OutgoingEmail = {
    id: number;
    incoming_email_id: number | null;
    to: string[];
    subject: string | null;
    body_text: string | null;
    status: 'draft' | 'sent' | 'failed';
    error: string | null;
    sent_at: string | null;
    created_at: string;
    attachments: Attachment[];
};

const props = defineProps<{
    mailbox: Mailbox;
    outgoingEmails: Paginator & { data: OutgoingEmail[] };
    incomingEmailsCount: number;
    unreadEmailsCount: number;
    outgoingEmailsCount: number;
    mailAddressIds: Record<string, number>;
    filters: { q: string };
    search_degraded: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Postafiókok', href: mailboxes.index().url }],
    },
});

const { filters, isFiltered } = useListFilters({ ...props.filters });

const openOutgoingId = ref<number | null>(null);
const sendingOutgoingId = ref<number | null>(null);

const statusLabels: Record<OutgoingEmail['status'], string> = {
    draft: 'Piszkozat — jóváhagyásra vár',
    sent: 'Elküldve',
    failed: 'A küldés nem sikerült',
};

function conversationUrl(mail: string | null | undefined): string | null {
    if (!mail) {
        return null;
    }

    const id = props.mailAddressIds[mail.toLowerCase()];

    return id ? mailAddresses.show(id).url : null;
}

function toggleOutgoing(email: OutgoingEmail): void {
    openOutgoingId.value = openOutgoingId.value === email.id ? null : email.id;
}

/**
 * Send (or retry) an outgoing message that is still a draft or failed — e.g. a
 * mail the agent composed while automatic sending was off.
 */
function sendOutgoing(email: OutgoingEmail): void {
    router.post(
        mailboxes.replies.send({
            mailbox: props.mailbox.id,
            outgoingEmail: email.id,
        }).url,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => (sendingOutgoingId.value = email.id),
            onFinish: () => (sendingOutgoingId.value = null),
        },
    );
}

function discardOutgoing(email: OutgoingEmail): void {
    if (!window.confirm('Elveted ezt a levelet? Nem megy ki a címzettnek.')) {
        return;
    }

    router.delete(
        mailboxes.replies.destroy({
            mailbox: props.mailbox.id,
            outgoingEmail: email.id,
        }).url,
        {
            preserveScroll: true,
            onSuccess: () => {
                if (openOutgoingId.value === email.id) {
                    openOutgoingId.value = null;
                }
            },
        },
    );
}

function formatSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    const units = ['KB', 'MB', 'GB'];
    let size = bytes / 1024;
    let unit = 0;

    while (size >= 1024 && unit < units.length - 1) {
        size /= 1024;
        unit++;
    }

    return `${size.toLocaleString('hu-HU', { maximumFractionDigits: 1 })} ${units[unit]}`;
}

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString('hu-HU') : '—';
}
</script>

<template>
    <Head :title="`Kimenő — ${mailbox.name}`" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading title="Kimenő levelek" :description="mailbox.name" />
            <Button size="sm" as-child>
                <Link :href="mailboxes.compose(mailbox.id).url">
                    <SquarePen class="size-4" />
                    Új levél
                </Link>
            </Button>
        </div>

        <MailboxTabs
            :mailbox-id="mailbox.id"
            active="outgoing"
            :incoming-count="incomingEmailsCount"
            :unread-count="unreadEmailsCount"
            :outgoing-count="outgoingEmailsCount"
        />

        <ListSearchInput
            v-model="filters.q"
            placeholder="Keresés a levelekben…"
        />

        <SearchUnavailableAlert v-if="search_degraded" />

        <div
            v-if="outgoingEmails.data.length === 0"
            class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            <SendHorizontal class="mx-auto mb-2 size-6" />
            {{
                isFiltered()
                    ? 'Nincs a szűrésnek megfelelő levél.'
                    : 'Még nincs kimenő levél.'
            }}
        </div>

        <ul v-else class="divide-y rounded-md border">
            <li
                v-for="email in outgoingEmails.data"
                :id="`outgoing-${email.id}`"
                :key="email.id"
            >
                <div class="flex items-center">
                    <button
                        type="button"
                        class="flex min-w-0 flex-1 flex-col gap-0.5 px-3 py-2 text-left text-sm hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:outline-none sm:flex-row sm:items-center sm:gap-4"
                        :aria-expanded="openOutgoingId === email.id"
                        @click="toggleOutgoing(email)"
                    >
                        <span class="flex min-w-0 items-center gap-2 sm:w-56">
                            <ReplyIcon
                                v-if="email.incoming_email_id"
                                class="size-3.5 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <span class="truncate text-muted-foreground"
                                >{{ email.to.join(', ') || '—' }}</span
                            >
                        </span>
                        <span class="min-w-0 flex-1 truncate pl-4 sm:pl-0"
                            >{{ email.subject || '(nincs tárgy)' }}</span
                        >
                        <span
                            class="flex shrink-0 items-center gap-2 text-xs text-muted-foreground"
                        >
                            <span
                                v-if="email.status === 'sent'"
                                class="flex items-center"
                                :title="statusLabels.sent"
                            >
                                <Check class="size-4" aria-hidden="true" />
                                <span class="sr-only">{{
                                    statusLabels.sent
                                }}</span>
                            </span>
                            <Badge
                                v-else
                                :variant="
                                    email.status === 'failed'
                                        ? 'destructive'
                                        : 'outline'
                                "
                                >{{ statusLabels[email.status] }}</Badge
                            >
                            <span
                                v-if="email.attachments.length > 0"
                                class="flex items-center gap-0.5"
                                :title="`${email.attachments.length} csatolmány`"
                            >
                                <Paperclip class="size-3.5" aria-hidden="true" />
                                {{ email.attachments.length }}
                            </span>
                            {{ formatDate(email.sent_at || email.created_at) }}
                        </span>
                    </button>
                    <Button
                        v-if="email.status !== 'sent'"
                        variant="ghost"
                        size="icon"
                        class="ml-1 size-8 shrink-0 text-muted-foreground hover:text-primary"
                        :disabled="sendingOutgoingId === email.id"
                        :aria-label="`„${email.subject || '(nincs tárgy)'}” levél küldése`"
                        :title="email.status === 'failed' ? 'Újraküldés' : 'Küldés'"
                        @click="sendOutgoing(email)"
                    >
                        <Send class="size-3.5" />
                    </Button>
                    <span v-else class="ml-1 size-8 shrink-0" aria-hidden="true" />
                    <Button
                        v-if="conversationUrl(email.to[0])"
                        variant="ghost"
                        size="icon"
                        class="ml-1 size-8 shrink-0 text-muted-foreground hover:text-primary"
                        as-child
                    >
                        <Link
                            :href="conversationUrl(email.to[0])!"
                            :aria-label="`Üzenetváltás ${email.to[0]} címmel`"
                            title="Üzenetváltás a címzettel"
                        >
                            <MessageSquare class="size-3.5" />
                        </Link>
                    </Button>
                    <Button
                        v-if="email.status !== 'sent'"
                        variant="ghost"
                        size="icon"
                        class="mx-1 size-8 shrink-0 text-muted-foreground hover:text-destructive"
                        :aria-label="`„${email.subject || '(nincs tárgy)'}” levél elvetése`"
                        title="Levél elvetése"
                        @click="discardOutgoing(email)"
                    >
                        <Trash2 class="size-3.5" />
                    </Button>
                    <span v-else class="mx-1 size-8 shrink-0" aria-hidden="true" />
                </div>
                <div
                    v-if="openOutgoingId === email.id"
                    class="border-t bg-muted/40"
                >
                    <p
                        v-if="email.error"
                        class="border-b px-3 py-2 text-xs text-destructive"
                    >
                        {{ email.error }}
                    </p>
                    <pre
                        class="max-h-96 overflow-auto px-3 py-2 font-mono text-xs whitespace-pre-wrap"
                        >{{ email.body_text || '(nincs szöveges tartalom)' }}</pre
                    >
                    <ul
                        v-if="email.attachments.length > 0"
                        class="flex flex-wrap gap-2 border-t px-3 py-2"
                        aria-label="Csatolmányok"
                    >
                        <li
                            v-for="attachment in email.attachments"
                            :key="attachment.id"
                            class="flex max-w-xs items-center gap-1.5 rounded-md border bg-background px-2 py-1 text-xs"
                        >
                            <Paperclip
                                class="size-3.5 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <span class="truncate">{{ attachment.filename }}</span>
                            <span class="shrink-0 text-muted-foreground">{{
                                formatSize(attachment.size)
                            }}</span>
                        </li>
                    </ul>
                </div>
            </li>
        </ul>

        <PaginationBar :paginator="outgoingEmails" noun="levél" />
    </div>
</template>
