import { action, callableAction } from '@/routes/_wayfinder'

export default {
    clear: callableAction('delete'),
    destroy: callableAction('delete'),
    entries: callableAction('get'),
    index: action('get'),
    show: callableAction('get'),
}
