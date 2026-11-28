<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    AtSign,
    BookOpen,
    Bot,
    FolderKanban,
    LayoutGrid,
    ListTodo,
    Mail,
    Settings,
    Terminal,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import agents from '@/routes/agents';
import { edit as appSettings } from '@/routes/app-settings';
import { dashboard } from '@/routes';
import mailAddresses from '@/routes/mail-addresses';
import mailboxes from '@/routes/mailboxes';
import { index as promptRuns } from '@/routes/prompt-runs';
import projects from '@/routes/projects';
import tasks from '@/routes/tasks';
import documents from '@/routes/projects/documents';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Vezérlőpult',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Projektek',
        href: projects.index(),
        icon: FolderKanban,
    },
    {
        title: 'Feladatok',
        href: tasks.index(),
        icon: ListTodo,
    },
    {
        title: 'Agentek',
        href: agents.index(),
        icon: Bot,
    },
    {
        title: 'Postafiókok',
        href: mailboxes.index(),
        icon: Mail,
    },
    {
        title: 'E-mail címek',
        href: mailAddresses.index(),
        icon: AtSign,
    },
    {
        title: 'Prompt sor',
        href: promptRuns(),
        icon: Terminal,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Beállítások',
        href: appSettings(),
        icon: Settings,
    },
    {
        title: 'Dokumentumok',
        href: documents.index('developer-tool'),
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar">
        <SidebarHeader class="border-b border-sidebar-border">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter class="border-t border-sidebar-border">
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
