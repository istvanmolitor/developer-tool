<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3";
import Heading from "@/components/Heading.vue";
import mailboxes from "@/routes/mailboxes";
import MailboxForm from "./MailboxForm.vue";
import type { MailboxFormFields } from "./MailboxForm.vue";

type Mailbox = Omit<
    MailboxFormFields,
    "password" | "from_name" | "agent_id" | "project_id"
> & {
    id: number;
    agent_id: number | null;
    project_id: number | null;
    from_name: string | null;
};

const props = defineProps<{
    mailbox: Mailbox;
    encryptions: { value: string; label: string }[];
    agents: { id: number; name: string }[];
    projects: { id: number; name: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: "Postafiókok", href: mailboxes.index().url },
            { title: "Szerkesztés", href: "" },
        ],
    },
});

const form = useForm<MailboxFormFields>({
    agent_id:
        props.mailbox.agent_id === null ? "" : String(props.mailbox.agent_id),
    project_id:
        props.mailbox.project_id === null
            ? ""
            : String(props.mailbox.project_id),
    name: props.mailbox.name,
    email: props.mailbox.email,
    from_name: props.mailbox.from_name ?? "",
    username: props.mailbox.username,
    password: "",
    imap_host: props.mailbox.imap_host,
    imap_port: props.mailbox.imap_port,
    imap_encryption: props.mailbox.imap_encryption,
    imap_folder: props.mailbox.imap_folder,
    smtp_host: props.mailbox.smtp_host,
    smtp_port: props.mailbox.smtp_port,
    smtp_encryption: props.mailbox.smtp_encryption,
    is_active: props.mailbox.is_active,
    auto_read: props.mailbox.auto_read,
    auto_reply: props.mailbox.auto_reply,
});

function submit(): void {
    form.put(mailboxes.update(props.mailbox.id).url);
}
</script>

<template>
    <Head :title="`${mailbox.name} szerkesztése`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <Heading
            :title="`${mailbox.name} szerkesztése`"
            description="A jelszó mezőt üresen hagyva a tárolt jelszó megmarad."
        />

        <MailboxForm
            :form="form"
            :encryptions="encryptions"
            :agents="agents"
            :projects="projects"
            submit-label="Változtatások mentése"
            :cancel-href="mailboxes.show(mailbox.id).url"
            password-optional
            @submit="submit"
        />
    </div>
</template>
