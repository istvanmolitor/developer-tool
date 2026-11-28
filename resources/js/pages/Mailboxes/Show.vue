<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import agents from '@/routes/agents';
import mailboxes from '@/routes/mailboxes';
import projects from '@/routes/projects';
import MailboxTabs from './MailboxTabs.vue';

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
    auto_read: boolean;
    auto_reply: boolean;
    agent: { id: number; name: string } | null;
    project: { id: number; name: string } | null;
};

const props = defineProps<{
    mailbox: Mailbox;
    incomingEmailsCount: number;
    unreadEmailsCount: number;
    outgoingEmailsCount: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Postafiókok', href: mailboxes.index().url }],
    },
});

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

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString('hu-HU') : '—';
}
</script>

<template>
    <Head :title="`${mailbox.name} — Postafiókok`" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading :title="mailbox.name" :description="mailbox.email" />
            <div class="flex shrink-0 flex-wrap items-center gap-1">
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

        <MailboxTabs
            :mailbox-id="mailbox.id"
            active="connection"
            :incoming-count="incomingEmailsCount"
            :unread-count="unreadEmailsCount"
            :outgoing-count="outgoingEmailsCount"
        />

        <Card class="gap-0 py-0">
            <CardHeader class="gap-0 border-b py-3 [.border-b]:pb-3">
                <CardTitle class="flex items-center gap-2 text-base">
                    Kapcsolat
                    <Badge :variant="mailbox.is_active ? 'secondary' : 'outline'">{{
                        mailbox.is_active ? 'Aktív' : 'Inaktív'
                    }}</Badge>
                </CardTitle>
            </CardHeader>
            <CardContent class="py-4">
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">
                            Agent
                        </dt>
                        <dd class="mt-1 break-all">
                            <Link
                                v-if="mailbox.agent"
                                :href="agents.show(mailbox.agent.id).url"
                                class="font-medium underline-offset-4 hover:underline"
                                >{{ mailbox.agent.name }}</Link
                            >
                            <span v-else class="text-muted-foreground"
                                >Nincs hozzárendelve</span
                            >
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">
                            Projekt
                        </dt>
                        <dd class="mt-1 break-all">
                            <Link
                                v-if="mailbox.project"
                                :href="projects.show(mailbox.project).url"
                                class="font-medium underline-offset-4 hover:underline"
                                >{{ mailbox.project.name }}</Link
                            >
                            <span v-else class="text-muted-foreground"
                                >Nincs hozzárendelve</span
                            >
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">
                            Olvasás
                        </dt>
                        <dd class="mt-1">
                            {{
                                mailbox.auto_read
                                    ? 'Az agent automatikusan elolvassa az új leveleket'
                                    : 'Kézzel, levelenként'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">
                            Válaszok
                        </dt>
                        <dd class="mt-1">
                            {{
                                mailbox.auto_reply
                                    ? 'Az agent válaszai azonnal kimennek'
                                    : 'Jóváhagyás után mennek ki'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">
                            Felhasználónév
                        </dt>
                        <dd class="mt-1 break-all">{{ mailbox.username }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">
                            Utolsó letöltés
                        </dt>
                        <dd class="mt-1">
                            {{ formatDate(mailbox.last_fetched_at) }}
                        </dd>
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
                </dl>
            </CardContent>
        </Card>
    </div>
</template>
