import { action, callableAction } from '../_wayfinder'

export default {
    destroy: callableAction('delete'),
    index: action('get'),
    replace: callableAction('post'),
    store: action('post'),
}
