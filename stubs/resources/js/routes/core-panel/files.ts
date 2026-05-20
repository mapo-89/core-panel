import { action, callableAction } from '../_wayfinder'

export default {
    destroy: callableAction('delete'),
    download: callableAction('get'),
    index: action('get'),
    preview: callableAction('get'),
    store: action('post'),
}
