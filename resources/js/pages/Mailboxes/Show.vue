<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Download, Inbox, Pencil, Trash2 } from '@lucide/vue';
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

type IncomingEmail = {
    id: number;
    from_address: string | null;
    from_name: string | null;
    subject: string | null;
    body_text: string | null;
    received_at: string | null;
};

const props = defineProps<{
    mailbox: Mailbox;
    incomingEmails: IncomingEmail[];
    incomingEmailsCount: number;
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

function toggle(email: IncomingEmail): void {
    openEmailId.value = openEmailId.value === email.id ? null : email.id;
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
                        <button
                            type="button"
                            class="flex w-full flex-col gap-0.5 px-3 py-2 text-left text-sm hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:outline-none sm:flex-row sm:items-center sm:gap-4"
                            :aria-expanded="openEmailId === email.id"
                            @click="toggle(email)"
                        >
                            <span class="truncate font-medium sm:w-56">{{
                                email.from_name || email.from_address || '—'
                            }}</span>
                            <span class="min-w-0 flex-1 truncate">{{
                                email.subject || '(nincs tárgy)'
                            }}</span>
                            <span
                                class="shrink-0 text-xs text-muted-foreground"
                                >{{ formatDate(email.received_at) }}</span
                            >
                        </button>
                        <pre
                            v-if="openEmailId === email.id"
                            class="max-h-96 overflow-auto border-t bg-muted/40 px-3 py-2 font-mono text-xs whitespace-pre-wrap"
                            >{{
                                email.body_text || '(nincs szöveges tartalom)'
                            }}</pre>
                    </li>
                </ul>
            </CardContent>
        </Card>
    </div>
</template>
