<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Download, Inbox, Paperclip, Pencil, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import mailboxes from '@/routes/mailboxes';

type Mailbox = {
    id: number;
    name: string;
    email: string;
    from_name: string | null;
    username: string;
    imap_host: string;
    imap_port: number;
    imap_encryption: string;
    imap_folder: string;
    smtp_host: string;
    smtp_port: number;
    smtp_encryption: string;
    is_active: boolean;
    last_fetched_at: string | null;
};

type Attachment = {
    id: number;
    filename: string;
    mime_type: string;
    size: number;
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
};

const props = defineProps<{
    mailbox: Mailbox;
    incomingEmails: IncomingEmail[];
    incomingEmailsCount: number;
    unreadEmailsCount: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Postafiókok', href: mailboxes.index().url }],
    },
});

const fetching = ref(false);
const openEmailId = ref<number | null>(null);

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

function destroy(): void {
    if (
        !window.confirm(
            `Törlöd a(z) „${props.mailbox.name}” postafiókot és a letöltött leveleit? Ez a művelet nem vonható vissza.`,
        )
    ) {
        return;
    }

    router.delete(mailboxes.destroy(props.mailbox.id).url);
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

function toggle(email: IncomingEmail): void {
    openEmailId.value = openEmailId.value === email.id ? null : email.id;

    if (openEmailId.value === email.id && !email.read_at) {
        markAsRead(email);
    }
}

function markAsRead(email: IncomingEmail): void {
    router.post(
        mailboxes.emails.read({
            mailbox: props.mailbox.id,
            incomingEmail: email.id,
        }).url,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            only: ['incomingEmails', 'unreadEmailsCount'],
        },
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
    <Head :title="`${mailbox.name} — Postafiókok`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading :title="mailbox.name" :description="mailbox.email" />
            <div class="flex shrink-0 flex-wrap items-center gap-1">
                <Button size="sm" :disabled="fetching" @click="fetchMail">
                    <Download class="size-4" />
                    {{ fetching ? 'Letöltés…' : 'Levelek letöltése' }}
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="mailboxes.edit(mailbox.id).url">
                        <Pencil class="size-4" />
                        Szerkesztés
                    </Link>
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    class="text-destructive hover:text-destructive"
                    @click="destroy"
                >
                    <Trash2 class="size-4" />
                    Törlés
                </Button>
            </div>
        </div>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-base">
                    Kapcsolat
                    <Badge
                        :variant="mailbox.is_active ? 'secondary' : 'outline'"
                        >{{ mailbox.is_active ? 'Aktív' : 'Inaktív' }}</Badge
                    >
                </CardTitle>
            </CardHeader>
            <CardContent>
                <dl class="grid gap-4 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">
                            Felhasználónév
                        </dt>
                        <dd class="mt-1 break-all">{{ mailbox.username }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">
                            IMAP
                        </dt>
                        <dd class="mt-1 font-mono break-all">
                            {{ mailbox.imap_host }}:{{ mailbox.imap_port }} ({{
                                mailbox.imap_encryption
                            }}, {{ mailbox.imap_folder }})
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">
                            SMTP
                        </dt>
                        <dd class="mt-1 font-mono break-all">
                            {{ mailbox.smtp_host }}:{{ mailbox.smtp_port }} ({{
                                mailbox.smtp_encryption
                            }})
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">
                            Utolsó letöltés
                        </dt>
                        <dd class="mt-1">
                            {{ formatDate(mailbox.last_fetched_at) }}
                        </dd>
                    </div>
                </dl>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="text-base"
                    >Bejövő levelek
                    <span class="font-normal text-muted-foreground"
                        >({{ incomingEmailsCount }})</span
                    >
                    <Badge v-if="unreadEmailsCount > 0" class="ml-1"
                        >{{ unreadEmailsCount }} olvasatlan</Badge
                    ></CardTitle
                >
            </CardHeader>
            <CardContent>
                <div
                    v-if="incomingEmails.length === 0"
                    class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
                >
                    <Inbox class="mx-auto mb-2 size-6" />
                    Még nincs letöltött levél.
                </div>

                <ul v-else class="divide-y rounded-md border">
                    <li v-for="email in incomingEmails" :key="email.id">
                        <div class="flex items-center">
                            <button
                                type="button"
                                class="flex min-w-0 flex-1 flex-col gap-0.5 px-3 py-2 text-left text-sm hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:outline-none sm:flex-row sm:items-center sm:gap-4"
                                :aria-expanded="openEmailId === email.id"
                                @click="toggle(email)"
                            >
                                <span
                                    class="flex min-w-0 items-center gap-2 sm:w-56"
                                >
                                    <span
                                        class="size-2 shrink-0 rounded-full"
                                        :class="
                                            email.read_at
                                                ? 'bg-transparent'
                                                : 'bg-primary'
                                        "
                                        aria-hidden="true"
                                    />
                                    <span
                                        class="truncate"
                                        :class="
                                            email.read_at
                                                ? 'text-muted-foreground'
                                                : 'font-semibold'
                                        "
                                        >{{
                                            email.from_name ||
                                            email.from_address ||
                                            '—'
                                        }}</span
                                    >
                                </span>
                                <span
                                    class="min-w-0 flex-1 truncate pl-4 sm:pl-0"
                                    :class="{ 'font-semibold': !email.read_at }"
                                    >{{ email.subject || '(nincs tárgy)' }}
                                    <span v-if="!email.read_at" class="sr-only"
                                        >(olvasatlan)</span
                                    ></span
                                >
                                <span
                                    class="flex shrink-0 items-center gap-2 text-xs text-muted-foreground"
                                >
                                    <span
                                        v-if="email.attachments.length > 0"
                                        class="flex items-center gap-0.5"
                                        :title="`${email.attachments.length} csatolmány`"
                                    >
                                        <Paperclip
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                        <span class="sr-only">Csatolmány:</span>
                                        {{ email.attachments.length }}
                                    </span>
                                    {{ formatDate(email.received_at) }}
                                </span>
                            </button>
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
                            <pre
                                class="max-h-96 overflow-auto px-3 py-2 font-mono text-xs whitespace-pre-wrap"
                                >{{
                                    email.body_text ||
                                    '(nincs szöveges tartalom)'
                                }}</pre>
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
                                        <span class="truncate">{{
                                            attachment.filename
                                        }}</span>
                                        <span
                                            class="shrink-0 text-muted-foreground"
                                            >{{
                                                formatSize(attachment.size)
                                            }}</span
                                        >
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                </ul>
            </CardContent>
        </Card>
    </div>
</template>
