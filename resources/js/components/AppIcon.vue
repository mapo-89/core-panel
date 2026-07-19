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
    CalendarCheck,
    Check,
    CircleAlert,
    CircleCheckBig,
    ChevronUp,
    ChevronDown,
    Clock3,
    Cloud,
    Code2,
    Columns3,
    Copy,
    Download,
    Eye,
    EllipsisVertical,
    FileText,
    FolderCheck,
    Funnel,
    Globe,
    Folder,
    FolderOpen,
    Grid2x2,
    HardDrive,
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
    PackageSearch,
    Pencil,
    Plus,
    RefreshCw,
    RotateCw,
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
    UserRoundPlus,
    Users,
    Workflow,
    Wrench,
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
    'calendar-check': CalendarCheck,
    camera: Camera,
    check: Check,
    'circle-alert': CircleAlert,
    'circle-check': CircleCheckBig,
    'circle-check-big': CircleCheckBig,
    'chevron-up': ChevronUp,
    'chevron-down': ChevronDown,
    cloud: Cloud,
    'clock-3': Clock3,
    cog: Settings,
    code: Code2,
    bolt: Zap,
    copy: Copy,
    dashboard: House,
    desktop: Monitor,
    download: Download,
    edit: Pencil,
    email: Mail,
    envelope: Mail,
    eye: Eye,
    'ellipsis-vertical': EllipsisVertical,
    'file-text': FileText,
    files: Folder,
    filter: Funnel,
    'folder-check': FolderCheck,
    'folder-open': FolderOpen,
    globe: Globe,
    'hard-drive': HardDrive,
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
    'package-search': PackageSearch,
    refresh: RefreshCw,
    'refresh-cw': RefreshCw,
    'rotate-cw': RotateCw,
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
    'user-round-plus': UserRoundPlus,
    users: Users,
    wrench: Wrench,
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
