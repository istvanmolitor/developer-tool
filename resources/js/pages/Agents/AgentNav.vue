<script setup lang="ts">
import { Link, usePage } from "@inertiajs/vue3";
import {
    ListTodo,
    MessageSquare,
    MessagesSquare,
    UserRound,
} from "@lucide/vue";
import { computed } from "vue";
import agents from "@/routes/agents";

const props = defineProps<{
    agentId: number;
}>();

const page = usePage();

const items = computed(() => [
    {
        title: "Chat",
        icon: MessageSquare,
        href: agents.show(props.agentId).url,
        component: "Agents/Show",
    },
    {
        title: "Profil",
        icon: UserRound,
        href: agents.profile(props.agentId).url,
        component: "Agents/Profile",
    },
    {
        title: "Feladatok",
        icon: ListTodo,
        href: agents.tasks(props.agentId).url,
        component: "Agents/Tasks",
    },
    {
        title: "Üzenetek",
        icon: MessagesSquare,
        href: agents.messages(props.agentId).url,
        component: "Agents/Messages",
    },
]);
</script>

<template>
    <nav class="flex items-center gap-1 border-b">
        <Link
            v-for="item in items"
            :key="item.component"
            :href="item.href"
            class="-mb-px flex items-center gap-2 border-b-2 px-3 py-2 text-sm font-medium transition-colors"
            :class="
                page.component === item.component
                    ? 'border-primary text-foreground'
                    : 'border-transparent text-muted-foreground hover:text-foreground'
            "
        >
            <component :is="item.icon" class="size-4" />
            {{ item.title }}
        </Link>
    </nav>
</template>
