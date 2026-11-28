<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    BookOpenCheck,
    Check,
    Download,
    Inbox,
    MessageSquare,
    Paperclip,
    Reply as ReplyIcon,
    Send,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import FilterSelect from '@/components/FilterSelect.vue';
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
import projects from '@/routes/projects';
import MailboxTabs from './MailboxTabs.vue';

type Mailbox = {
    id: number;
    name: string;
    email: string;
    agent: { id: number; name: string } | null;
    project: { id: number; name: string } | null;
};

type Attachment = {
    id: number;
    filename: string;
    mime_type: string;
    size: number;
};

type Reply = {
    id: number;
    to: string[];
    subject: string;
    body_text: string;
    status: 'draft' | 'sent' | 'failed';
    error: string | null;
    sent_at: string | null;
    created_at: string;
};

type IncomingEmail = {
    id: number;
    from_address: string | null;
    from_name: string | null;
    subject: string | null;
    body_text: string | null;
    received_at: string | null;
    read_at: string | null;
    attachments: Attachment[];
    prompt_run: { id: number; status: string } | null;
    replies: Reply[];
};

const props = defineProps<{
    mailbox: Mailbox;
    incomingEmails: Paginator & { data: IncomingEmail[] };
    incomingEmailsCount: number;
    unreadEmailsCount: number;
    outgoingEmailsCount: number;
    mailAddressIds: Record<string, number>;
    filters: { q: string; read: string | null };
    search_degraded: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Postafiókok', href: mailboxes.index().url }],
    },
});

const { filters, isFiltered } = useListFilters({ ...props.filters });

const readOptions = [
    { value: 'unread', label: 'Olvasatlan' },
    { value: 'read', label: 'Olvasott' },
];

const fetching = ref(false);
const openEmailId = ref<number | null>(null);
const readingEmailId = ref<number | null>(null);
const sendingReplyId = ref<number | null>(null);

const canAgentRead = computed(
    () => props.mailbox.agent !== null && props.mailbox.project !== null,
);

const promptRunStatusLabels: Record<string, string> = {
    pending: 'sorban áll',
    running: 'fut',
    completed: 'kész',
    failed: 'sikertelen',
};

const replyStatusLabels: Record<Reply['status'], string> = {
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

function hasPendingReply(email: IncomingEmail): boolean {
    return email.replies.some((reply) => reply.status !== 'sent');
}

function toggle(email: IncomingEmail): void {
    openEmailId.value = openEmailId.value === email.id ? null : email.id;
}

function fetchMail(): void {
    router.post(
        mailboxes.fetch(props.mailbox.id).url,
        {},
        {
            preserveScroll: true,
            onStart: () => (fetching.value = true),
            onFinish: () => (fetching.value = false),
        },
    );
}

/**
 * Hand the message to the mailbox's agent: it receives it as a prompt on the mailbox's project.
 */
function sendToAgent(email: IncomingEmail): void {
    router.post(
        mailboxes.emails.read({
            mailbox: props.mailbox.id,
            incomingEmail: email.id,
        }).url,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => (readingEmailId.value = email.id),
            onFinish: () => (readingEmailId.value = null),
        },
    );
}

function destroyEmail(email: IncomingEmail): void {
    if (
        !window.confirm(
            `Törlöd a(z) „${email.subject || '(nincs tárgy)'}” levelet? A levél csak itt törlődik, a levelezőszerveren megmarad.`,
        )
    ) {
        return;
    }

    router.delete(
        mailboxes.emails.destroy({
            mailbox: props.mailbox.id,
            incomingEmail: email.id,
        }).url,
        {
            preserveScroll: true,
            onSuccess: () => {
                if (openEmailId.value === email.id) {
                    openEmailId.value = null;
                }
            },
        },
    );
}

/**
 * Approve the agent's reply: send it (or retry a failed send) from the mailbox.
 */
function sendReply(reply: Reply): void {
    router.post(
        mailboxes.replies.send({
            mailbox: props.mailbox.id,
            outgoingEmail: reply.id,
        }).url,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => (sendingReplyId.value = reply.id),
            onFinish: () => (sendingReplyId.value = null),
        },
    );
}

function discardReply(reply: Reply): void {
    if (
        !window.confirm(
            'Elveted az agent válaszát? A levél nem megy ki a feladónak.',
        )
    ) {
        return;
    }

    router.delete(
        mailboxes.replies.destroy({
            mailbox: props.mailbox.id,
            outgoingEmail: reply.id,
        }).url,
        { preserveScroll: true, preserveState: true },
    );
}

function attachmentUrl(email: IncomingEmail, attachment: Attachment): string {
    return mailboxes.emails.attachments.download({
        mailbox: props.mailbox.id,
        incomingEmail: email.id,
        attachment: attachment.id,
    }).url;
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
    <Head :title="`Bejövő — ${mailbox.name}`" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading title="Bejövő levelek" :description="mailbox.name" />
            <Button size="sm" :disabled="fetching" @click="fetchMail">
                <Download class="size-4" />
                {{ fetching ? 'Letöltés…' : 'Levelek letöltése' }}
            </Button>
        </div>

        <MailboxTabs
            :mailbox-id="mailbox.id"
            active="incoming"
            :incoming-count="incomingEmailsCount"
            :unread-count="unreadEmailsCount"
            :outgoing-count="outgoingEmailsCount"
        />

        <p v-if="!canAgentRead" class="text-sm text-muted-foreground">
            Ahhoz, hogy az agent elolvashassa a leveleket, állíts be agentet és
            projektet a postafiók
            <Link
                :href="mailboxes.edit(mailbox.id).url"
                class="font-medium underline"
                >szerkesztésénél</Link
            >.
        </p>

        <div class="flex flex-wrap items-center gap-2">
            <ListSearchInput
                v-model="filters.q"
                placeholder="Keresés a levelekben…"
            />
            <FilterSelect
                v-model="filters.read"
                :options="readOptions"
                all-label="Minden levél"
                label="Olvasottság"
            />
        </div>

        <SearchUnavailableAlert v-if="search_degraded" />

        <div
            v-if="incomingEmails.data.length === 0"
            class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            <Inbox class="mx-auto mb-2 size-6" />
            {{
                isFiltered()
                    ? 'Nincs a szűrésnek megfelelő levél.'
                    : 'Még nincs letöltött levél.'
            }}
        </div>

        <ul v-else class="divide-y rounded-md border">
            <li
                v-for="email in incomingEmails.data"
                :id="`email-${email.id}`"
                :key="email.id"
            >
                <div class="flex items-center">
                    <button
                        type="button"
                        class="flex min-w-0 flex-1 flex-col gap-0.5 px-3 py-2 text-left text-sm hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:outline-none sm:flex-row sm:items-center sm:gap-4"
                        :aria-expanded="openEmailId === email.id"
                        @click="toggle(email)"
                    >
                        <span class="flex min-w-0 items-center gap-2 sm:w-56">
                            <span
                                class="flex size-3.5 shrink-0 items-center justify-center"
                                :title="
                                    email.read_at ? 'Olvasott' : 'Olvasatlan'
                                "
                            >
                                <Check
                                    v-if="email.read_at"
                                    class="size-3.5 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <span
                                    v-else
                                    class="size-2 rounded-full bg-primary"
                                    aria-hidden="true"
                                />
                                <span class="sr-only">{{
                                    email.read_at ? 'Olvasott' : 'Olvasatlan'
                                }}</span>
                            </span>
                            <span
                                class="truncate"
                                :class="
                                    email.read_at
                                        ? 'text-muted-foreground'
                                        : 'font-semibold'
                                "
                                >{{
                                    email.from_name || email.from_address || '—'
                                }}</span
                            >
                        </span>
                        <span
                            class="min-w-0 flex-1 truncate pl-4 sm:pl-0"
                            :class="{ 'font-semibold': !email.read_at }"
                            >{{ email.subject || '(nincs tárgy)' }}
                            <span v-if="!email.read_at" class="sr-only"
                                >(az agent még nem olvasta)</span
                            ></span
                        >
                        <span
                            class="flex shrink-0 items-center gap-2 text-xs text-muted-foreground"
                        >
                            <Badge
                                v-if="hasPendingReply(email)"
                                variant="outline"
                                class="gap-1"
                            >
                                <ReplyIcon class="size-3" aria-hidden="true" />
                                Válasz vár
                            </Badge>
                            <span
                                v-if="email.attachments.length > 0"
                                class="flex items-center gap-0.5"
                                :title="`${email.attachments.length} csatolmány`"
                            >
                                <Paperclip class="size-3.5" aria-hidden="true" />
                                <span class="sr-only">Csatolmány:</span>
                                {{ email.attachments.length }}
                            </span>
                            {{ formatDate(email.received_at) }}
                        </span>
                    </button>
                    <Button
                        v-if="!email.read_at"
                        variant="ghost"
                        size="icon"
                        class="ml-1 size-8 shrink-0 text-muted-foreground hover:text-primary"
                        :disabled="!canAgentRead || readingEmailId === email.id"
                        :aria-label="`„${email.subject || '(nincs tárgy)'}” levél elolvastatása az agenttel`"
                        :title="
                            canAgentRead
                                ? `Olvassa el ${mailbox.agent?.name} a(z) ${mailbox.project?.name} projekten`
                                : 'Előbb állíts be agentet és projektet a postafióknál'
                        "
                        @click="sendToAgent(email)"
                    >
                        <BookOpenCheck class="size-3.5" />
                    </Button>
                    <span v-else class="ml-1 size-8 shrink-0" aria-hidden="true" />
                    <Button
                        v-if="conversationUrl(email.from_address)"
                        variant="ghost"
                        size="icon"
                        class="ml-1 size-8 shrink-0 text-muted-foreground hover:text-primary"
                        as-child
                    >
                        <Link
                            :href="conversationUrl(email.from_address)!"
                            :aria-label="`Üzenetváltás ${email.from_address} címmel`"
                            title="Üzenetváltás a feladóval"
                        >
                            <MessageSquare class="size-3.5" />
                        </Link>
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="mx-1 size-8 shrink-0 text-muted-foreground hover:text-destructive"
                        :aria-label="`„${email.subject || '(nincs tárgy)'}” levél törlése`"
                        title="Levél törlése"
                        @click="destroyEmail(email)"
                    >
                        <Trash2 class="size-3.5" />
                    </Button>
                </div>
                <div
                    v-if="openEmailId === email.id"
                    class="border-t bg-muted/40"
                >
                    <p
                        v-if="email.read_at"
                        class="flex flex-wrap items-center gap-x-2 border-b px-3 py-2 text-xs text-muted-foreground"
                    >
                        <BookOpenCheck class="size-3.5 shrink-0" aria-hidden="true" />
                        Az agent elolvasta: {{ formatDate(email.read_at) }}
                        <template v-if="email.prompt_run">
                            · futás:
                            {{
                                promptRunStatusLabels[email.prompt_run.status] ??
                                email.prompt_run.status
                            }}
                            <Link
                                v-if="mailbox.project"
                                :href="projects.prompts(mailbox.project).url"
                                class="font-medium underline"
                                >Promptok megnyitása</Link
                            >
                        </template>
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
                        >
                            <a
                                :href="attachmentUrl(email, attachment)"
                                class="flex max-w-xs items-center gap-1.5 rounded-md border bg-background px-2 py-1 text-xs hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                :title="`${attachment.filename} letöltése`"
                            >
                                <Paperclip
                                    class="size-3.5 shrink-0 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <span class="truncate">{{ attachment.filename }}</span>
                                <span class="shrink-0 text-muted-foreground">{{
                                    formatSize(attachment.size)
                                }}</span>
                            </a>
                        </li>
                    </ul>
                    <section
                        v-for="reply in email.replies"
                        :key="reply.id"
                        class="border-t bg-background px-3 py-2"
                        :aria-label="`Az agent válasza: ${reply.subject}`"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p
                                class="flex flex-wrap items-center gap-x-2 text-xs text-muted-foreground"
                            >
                                <ReplyIcon class="size-3.5 shrink-0" aria-hidden="true" />
                                <span class="font-medium text-foreground"
                                    >Az agent válasza</span
                                >
                                → {{ reply.to.join(', ') }}
                                <Badge
                                    :variant="
                                        reply.status === 'failed'
                                            ? 'destructive'
                                            : reply.status === 'sent'
                                              ? 'secondary'
                                              : 'outline'
                                    "
                                    >{{ replyStatusLabels[reply.status] }}</Badge
                                >
                                <span v-if="reply.sent_at">{{
                                    formatDate(reply.sent_at)
                                }}</span>
                            </p>
                            <div
                                v-if="reply.status !== 'sent'"
                                class="flex shrink-0 items-center gap-1"
                            >
                                <Button
                                    size="sm"
                                    :disabled="sendingReplyId === reply.id"
                                    @click="sendReply(reply)"
                                >
                                    <Send class="size-3.5" />
                                    {{
                                        sendingReplyId === reply.id
                                            ? 'Küldés…'
                                            : reply.status === 'failed'
                                              ? 'Újraküldés'
                                              : 'Küldés'
                                    }}
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="text-destructive hover:text-destructive"
                                    @click="discardReply(reply)"
                                >
                                    <Trash2 class="size-3.5" />
                                    Elvetés
                                </Button>
                            </div>
                        </div>
                        <p v-if="reply.error" class="mt-1 text-xs text-destructive">
                            {{ reply.error }}
                        </p>
                        <p class="mt-1 text-xs font-medium">{{ reply.subject }}</p>
                        <pre
                            class="mt-1 max-h-96 overflow-auto font-mono text-xs whitespace-pre-wrap"
                            >{{ reply.body_text }}</pre
                        >
                    </section>
                </div>
            </li>
        </ul>

        <PaginationBar :paginator="incomingEmails" noun="levél" />
    </div>
</template>
