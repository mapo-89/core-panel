import developer from '@/routes/core-panel/developer'
import files from '@/routes/core-panel/files'
import logs from '@/routes/core-panel/logs'

import {
    useMenuBuilder,
    type MenuBuilderItem,
} from '@/composables/useMenuBuilder'

export function useAdminMenu() {
    const items: MenuBuilderItem[] = [
        {
            href: '/dashboard',
            icon: 'dashboard',
            key: 'dashboard',
            label: 'navigation.dashboard',
            match: ['/dashboard'],
        },
        {
            key: 'system-section',
            label: 'navigation.system',
            section: true,
        },
        {
            href: files.index.url(),
            icon: 'files',
            key: 'files',
            label: 'navigation.files',
            match: ['/core-panel/files'],
            permission: 'files.view',
        },
        {
            anyPermissions: ['activity-logs.view', 'authentication-logs.view'],
            href: logs.index.url(),
            icon: 'activity',
            key: 'logs',
            label: 'navigation.logs',
            match: [logs.index.url()],
        },
        {
            key: 'developer-section',
            label: 'navigation.developer',
            section: true,
        },
        {
            anyPermissions: ['api-routes.view', 'api-docs.view'],
            href: developer.index.url(),
            icon: 'code',
            key: 'routes',
            label: 'navigation.routes',
            match: [developer.index.url()],
        },
    ]

    return useMenuBuilder(items)
}
