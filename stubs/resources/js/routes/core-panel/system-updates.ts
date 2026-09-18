import { action } from '../_wayfinder'

export default {
    check: action('post'),
    settings: {
        update: action('put'),
    },
    status: action('get'),
    update: action('post'),
}
