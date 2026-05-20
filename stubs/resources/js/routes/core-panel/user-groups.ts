import { action, callableAction } from '../_wayfinder'

export default {
    destroy: callableAction('delete'),
    import: action('post'),
    index: action('get'),
    preview: action('post'),
    store: action('post'),
    update: callableAction('put'),
}
