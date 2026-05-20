import { action, callableAction } from '../_wayfinder'

export default {
    destroy: callableAction('delete'),
    store: action('post'),
    update: callableAction('put'),
}
