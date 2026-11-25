<script setup lang="ts">
import { Link, usePage } from "@inertiajs/vue3";
import {
    BookText,
    LayoutDashboard,
    ListTodo,
    MessageSquare,
    Paperclip,
} from "@lucide/vue";
import { computed } from "vue";
import projects from "@/routes/projects";

const props = defineProps<{
    name: string;
}>();

const page = usePage();

const items = computed(() => [
    {
        title: "Chat",
        icon: MessageSquare,
        href: projects.prompts(props.name).url,
        component: "Projects/Prompts",
    },
    {
        title: "Feladatok",
        icon: ListTodo,
        href: projects.tasks.index(props.name).url,
        component: "Projects/Tasks",
        // The project-wide change log is reached from the tasks screen.
        also: ["Projects/TaskChanges"],
    },
    {
        title: "Áttekintés",
        icon: LayoutDashboard,
        href: projects.show(props.name).url,
        component: "Projects/Show",
    },
    {
        title: "Dokumentáció",
        icon: BookText,
        href: projects.documentations.index(props.name).url,
        // The documentation section spans several pages (index, form, show),
        // so match on the folder prefix to keep the tab active throughout.
        component: "Projects/Documentations",
    },
    {
        title: "Csatolmányok",
        icon: Paperclip,
        href: projects.attachments.index(props.name).url,
        component: "Projects/Attachments",
    },
]);

function isActive(item: { component: string; also?: string[] }): boolean {
    return [item.component, ...(item.also ?? [])].some(
        (component) =>
            page.component === component ||
            page.component.startsWith(`${component}/`),
    );
}
</script>

<template>
    <nav class="flex items-center gap-1 border-b">
        <Link
            v-for="item in items"
            :key="item.component"
            :href="item.href"
            class="-mb-px flex items-center gap-2 border-b-2 px-3 py-2 text-sm font-medium transition-colors"
            :class="
                isActive(item)
                    ? 'border-primary text-foreground'
                    : 'border-transparent text-muted-foreground hover:text-foreground'
            "
        >
            <component :is="item.icon" class="size-4" />
            {{ item.title }}
        </Link>
    </nav>
</template>
