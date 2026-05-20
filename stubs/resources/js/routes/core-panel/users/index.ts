import avatar from './avatar'
import roles from './roles'
import sessions from './sessions'

import { action, callableAction } from '@/routes/_wayfinder'

export default {
    avatar,
    destroy: callableAction('delete'),
    edit: callableAction('get'),
    forceDelete: callableAction('delete'),
    index: action('get'),
    roles,
    restore: callableAction('post'),
    sessions,
    show: callableAction('get'),
    store: action('post'),
    update: callableAction('put'),
}
