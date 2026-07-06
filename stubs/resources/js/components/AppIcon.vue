<script setup lang="ts">
import type { Component } from 'vue'
import { computed } from 'vue'
import {
    ArrowLeft,
    ArrowRight,
    Ban,
    Bell,
    Building2,
    Camera,
    Check,
    CircleAlert,
    CircleCheckBig,
    ChevronUp,
    ChevronDown,
    Clock3,
    Code2,
    Columns3,
    Copy,
    Eye,
    EllipsisVertical,
    Funnel,
    Globe,
    Folder,
    Grid2x2,
    History,
    House,
    Image,
    Inbox,
    Info,
    KeyRound,
    Lock,
    LogOut,
    Mail,
    Menu,
    Monitor,
    Moon,
    PanelLeft,
    Pencil,
    Plus,
    RefreshCw,
    Save,
    Search,
    Settings,
    Smartphone,
    Shield,
    Sun,
    TriangleAlert,
    Trash2,
    Upload,
    User,
    UserPlus,
    Users,
    Workflow,
    X,
    Zap,
} from 'lucide-vue-next'

const props = withDefaults(
    defineProps<{
        name: string
        strokeWidth?: number
    }>(),
    {
        strokeWidth: 1.9,
    },
)

const icons: Record<string, Component> = {
    activity: History,
    'arrow-left': ArrowLeft,
    'arrow-right': ArrowRight,
    back: ArrowLeft,
    ban: Ban,
    bell: Bell,
    building: Building2,
    camera: Camera,
    check: Check,
    'circle-alert': CircleAlert,
    'circle-check': CircleCheckBig,
    'circle-check-big': CircleCheckBig,
    'chevron-up': ChevronUp,
    'chevron-down': ChevronDown,
    'clock-3': Clock3,
    cog: Settings,
    code: Code2,
    bolt: Zap,
    copy: Copy,
    dashboard: House,
    desktop: Monitor,
    edit: Pencil,
    email: Mail,
    envelope: Mail,
    eye: Eye,
    'ellipsis-vertical': EllipsisVertical,
    files: Folder,
    filter: Funnel,
    globe: Globe,
    image: Image,
    info: Info,
    inbox: Inbox,
    key: KeyRound,
    lock: Lock,
    columns: Columns3,
    logo: Grid2x2,
    logout: LogOut,
    menu: Menu,
    moon: Moon,
    'more-vertical': EllipsisVertical,
    pencil: Pencil,
    plus: Plus,
    refresh: RefreshCw,
    save: Save,
    roles: Shield,
    search: Search,
    settings: Settings,
    shield: Shield,
    smartphone: Smartphone,
    sidebar: PanelLeft,
    'sign-out': LogOut,
    sun: Sun,
    sitemap: Workflow,
    'triangle-alert': TriangleAlert,
    times: X,
    trash: Trash2,
    upload: Upload,
    user: User,
    'user-plus': UserPlus,
    users: Users,
    x: X,
}

function normalizeIconName(value: string): string {
    return value
        .trim()
        .toLowerCase()
        .replace(/^pi\s+pi-/, '')
        .replace(/^pi-/, '')
        .replace(/\s+/g, '-')
}

const iconComponent = computed(
    () => icons[normalizeIconName(props.name)] ?? Grid2x2,
)
</script>

<template>
    <component
        :is="iconComponent"
        aria-hidden="true"
        class="cp-icon"
        :stroke-width="props.strokeWidth"
    />
</template>
