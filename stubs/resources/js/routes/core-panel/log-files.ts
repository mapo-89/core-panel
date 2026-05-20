import { action, callableAction } from '@/routes/_wayfinder'

export default {
    entries: callableAction('get'),
    index: action('get'),
    show: callableAction('get'),
}
