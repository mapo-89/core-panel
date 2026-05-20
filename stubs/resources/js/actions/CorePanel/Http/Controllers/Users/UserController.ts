import { action, callableAction } from '../../../../_wayfinder'

export default {
    create: action('get'),
    destroy: callableAction('delete'),
    index: action('get'),
    store: action('post'),
}
