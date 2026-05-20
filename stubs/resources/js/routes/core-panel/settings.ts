import { action, callableAction } from '../_wayfinder'

export default {
    index: action('get'),
    logo: {
        destroy: callableAction('delete'),
        store: callableAction('post'),
    },
    styles: callableAction('put'),
    update: callableAction('put'),
}
