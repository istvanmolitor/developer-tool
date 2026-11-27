<script setup lang="ts">
import type { InertiaForm } from "@inertiajs/vue3";
import AgentSelect from "@/components/AgentSelect.vue";
import InputError from "@/components/InputError.vue";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";

type Option = { value: string; label: string };
type NamedOption = { id: number; name: string };
type AgentOption = NamedOption & { avatar_url?: string | null };

export type MailboxFormFields = {
    agent_id: string;
    project_id: string;
    name: string;
    email: string;
    from_name: string;
    username: string;
    password: string;
    imap_host: string;
    imap_port: number;
    imap_encryption: string;
    imap_folder: string;
    smtp_host: string;
    smtp_port: number;
    smtp_encryption: string;
    is_active: boolean;
    auto_read: boolean;
    auto_reply: boolean;
};

const props = defineProps<{
    form: InertiaForm<MailboxFormFields>;
    encryptions: Option[];
    agents: AgentOption[];
    projects: NamedOption[];
    submitLabel: string;
    cancelHref: string;
    passwordOptional?: boolean;
}>();

const emit = defineEmits<{ submit: [] }>();

// Sentinel value for the "unassigned" select item — empty string isn't a valid
// Select item value, so use a distinct token and map it back to '' on submit.
const UNASSIGNED = "none";

function optionLabel(options: NamedOption[], value: string): string {
    if (value === "" || value === UNASSIGNED) return "Nincs hozzárendelve";

    return (
        options.find((option) => String(option.id) === value)?.name ??
        "Nincs hozzárendelve"
    );
}

function encryptionLabel(value: string): string | undefined {
    return props.encryptions.find((option) => option.value === value)?.label;
}
</script>

<template>
    <form
        class="flex max-w-2xl flex-col gap-6"
        @submit.prevent="emit('submit')"
    >
        <div class="flex flex-col gap-2">
            <Label for="name">Név</Label>
            <Input
                id="name"
                v-model="form.name"
                type="text"
                placeholder="AI agent 1"
                autocomplete="off"
            />
            <InputError :message="form.errors.name" />
        </div>

        <div class="flex flex-col gap-2">
            <Label for="agent"
                >Agent
                <span class="text-muted-foreground">(opcionális)</span>
            </Label>
            <AgentSelect
                id="agent"
                :model-value="form.agent_id === '' ? UNASSIGNED : form.agent_id"
                :agents="agents"
                :none-value="UNASSIGNED"
                none-label="Nincs hozzárendelve"
                trigger-class="w-full sm:max-w-xs"
                @update:model-value="
                    (value) =>
                        (form.agent_id = value === UNASSIGNED ? '' : value)
                "
            />
            <p class="text-xs text-muted-foreground">
                Az agent, amelyik ezen a postafiókon keresztül levelez.
            </p>
            <InputError :message="form.errors.agent_id" />
        </div>

        <div class="flex flex-col gap-2">
            <Label for="project"
                >Projekt
                <span class="text-muted-foreground">(opcionális)</span>
            </Label>
            <Select
                :model-value="
                    form.project_id === '' ? UNASSIGNED : form.project_id
                "
                @update:model-value="
                    (value) =>
                        (form.project_id =
                            String(value) === UNASSIGNED ? '' : String(value))
                "
            >
                <SelectTrigger id="project" class="w-full sm:max-w-xs">
                    <SelectValue>{{
                        optionLabel(projects, form.project_id)
                    }}</SelectValue>
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="UNASSIGNED">
                        Nincs hozzárendelve
                    </SelectItem>
                    <SelectItem
                        v-for="project in projects"
                        :key="project.id"
                        :value="String(project.id)"
                    >
                        {{ project.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <p class="text-xs text-muted-foreground">
                Ezen a projekten dolgozik az agent, amikor elolvas egy levelet.
            </p>
            <InputError :message="form.errors.project_id" />
        </div>

        <div class="flex flex-col gap-1">
            <div class="flex items-center gap-2">
                <Checkbox
                    id="auto_read"
                    :model-value="form.auto_read"
                    @update:model-value="
                        (value) => (form.auto_read = value === true)
                    "
                />
                <Label for="auto_read"
                    >Az agent automatikusan elolvassa az új leveleket</Label
                >
            </div>
            <p class="pl-6 text-xs text-muted-foreground">
                Letöltéskor minden új levelet promptként megkap a projekten.
                Kikapcsolva levelenként te döntöd el, melyiket olvassa el.
            </p>
            <InputError class="pl-6" :message="form.errors.auto_read" />
        </div>

        <div class="flex flex-col gap-1">
            <div class="flex items-center gap-2">
                <Checkbox
                    id="auto_reply"
                    :model-value="form.auto_reply"
                    @update:model-value="
                        (value) => (form.auto_reply = value === true)
                    "
                />
                <Label for="auto_reply"
                    >Az agent válaszai jóváhagyás nélkül kimennek</Label
                >
            </div>
            <p class="pl-6 text-xs text-muted-foreground">
                Kikapcsolva az agent válasza piszkozatként vár a levél alatt, és
                csak a „Küldés” gombra megy ki a feladónak.
            </p>
            <InputError class="pl-6" :message="form.errors.auto_reply" />
        </div>

        <fieldset class="flex flex-col gap-4">
            <legend class="mb-2 text-sm font-semibold">Fiók</legend>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="flex flex-col gap-2">
                    <Label for="email">E-mail cím</Label>
                    <Input
                        id="email"
                        v-model="form.email"
                        type="email"
                        placeholder="agent@example.com"
                        autocomplete="off"
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="flex flex-col gap-2">
                    <Label for="from_name"
                        >Feladó neve
                        <span class="text-muted-foreground"
                            >(opcionális)</span
                        ></Label
                    >
                    <Input
                        id="from_name"
                        v-model="form.from_name"
                        type="text"
                        autocomplete="off"
                    />
                    <InputError :message="form.errors.from_name" />
                </div>

                <div class="flex flex-col gap-2">
                    <Label for="username">Felhasználónév</Label>
                    <Input
                        id="username"
                        v-model="form.username"
                        type="text"
                        placeholder="agent@example.com"
                        autocomplete="off"
                    />
                    <InputError :message="form.errors.username" />
                </div>

                <div class="flex flex-col gap-2">
                    <Label for="password">Jelszó</Label>
                    <Input
                        id="password"
                        v-model="form.password"
                        type="password"
                        autocomplete="new-password"
                        :placeholder="
                            passwordOptional
                                ? 'Hagyd üresen a megtartáshoz'
                                : ''
                        "
                    />
                    <InputError :message="form.errors.password" />
                </div>
            </div>
        </fieldset>

        <fieldset class="flex flex-col gap-4">
            <legend class="mb-2 text-sm font-semibold">
                Bejövő levelek (IMAP)
            </legend>

            <div class="grid gap-4 sm:grid-cols-[1fr_7rem]">
                <div class="flex flex-col gap-2">
                    <Label for="imap_host">Szerver</Label>
                    <Input
                        id="imap_host"
                        v-model="form.imap_host"
                        type="text"
                        placeholder="imap.example.com"
                        autocomplete="off"
                    />
                    <InputError :message="form.errors.imap_host" />
                </div>
                <div class="flex flex-col gap-2">
                    <Label for="imap_port">Port</Label>
                    <Input
                        id="imap_port"
                        v-model.number="form.imap_port"
                        type="number"
                        min="1"
                        max="65535"
                    />
                    <InputError :message="form.errors.imap_port" />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="flex flex-col gap-2">
                    <Label for="imap_encryption">Titkosítás</Label>
                    <Select v-model="form.imap_encryption">
                        <SelectTrigger id="imap_encryption" class="w-full">
                            <SelectValue>{{
                                encryptionLabel(form.imap_encryption)
                            }}</SelectValue>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in encryptions"
                                :key="option.value"
                                :value="option.value"
                                >{{ option.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.imap_encryption" />
                </div>
                <div class="flex flex-col gap-2">
                    <Label for="imap_folder">Mappa</Label>
                    <Input
                        id="imap_folder"
                        v-model="form.imap_folder"
                        type="text"
                        placeholder="INBOX"
                        autocomplete="off"
                    />
                    <InputError :message="form.errors.imap_folder" />
                </div>
            </div>
        </fieldset>

        <fieldset class="flex flex-col gap-4">
            <legend class="mb-2 text-sm font-semibold">
                Kimenő levelek (SMTP)
            </legend>

            <div class="grid gap-4 sm:grid-cols-[1fr_7rem]">
                <div class="flex flex-col gap-2">
                    <Label for="smtp_host">Szerver</Label>
                    <Input
                        id="smtp_host"
                        v-model="form.smtp_host"
                        type="text"
                        placeholder="smtp.example.com"
                        autocomplete="off"
                    />
                    <InputError :message="form.errors.smtp_host" />
                </div>
                <div class="flex flex-col gap-2">
                    <Label for="smtp_port">Port</Label>
                    <Input
                        id="smtp_port"
                        v-model.number="form.smtp_port"
                        type="number"
                        min="1"
                        max="65535"
                    />
                    <InputError :message="form.errors.smtp_port" />
                </div>
            </div>

            <div class="flex flex-col gap-2 sm:max-w-[calc(50%-0.5rem)]">
                <Label for="smtp_encryption">Titkosítás</Label>
                <Select v-model="form.smtp_encryption">
                    <SelectTrigger id="smtp_encryption" class="w-full">
                        <SelectValue>{{
                            encryptionLabel(form.smtp_encryption)
                        }}</SelectValue>
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in encryptions"
                            :key="option.value"
                            :value="option.value"
                            >{{ option.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.smtp_encryption" />
            </div>
        </fieldset>

        <div class="flex items-center gap-2">
            <Checkbox
                id="is_active"
                :model-value="form.is_active"
                @update:model-value="
                    (value) => (form.is_active = value === true)
                "
            />
            <Label for="is_active">Aktív (az ütemezett letöltés része)</Label>
        </div>

        <div class="flex items-center gap-2">
            <Button type="submit" :disabled="form.processing">{{
                submitLabel
            }}</Button>
            <Button type="button" variant="ghost" as-child>
                <a :href="cancelHref">Mégse</a>
            </Button>
        </div>
    </form>
</template>
