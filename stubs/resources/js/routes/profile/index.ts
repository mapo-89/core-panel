import { action } from '../_wayfinder'

export default {
    sessions: {
        destroyOthers: action('post'),
    },
    security: action('get'),
    show: action('get'),
}
