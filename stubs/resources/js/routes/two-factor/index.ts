import { action } from '../_wayfinder'

export default {
    confirm: action('post'),
    disable: action('delete'),
    enable: action('post'),
    login: {
        store: action('post'),
    },
    qrCode: action('get'),
    recoveryCodes: action('get'),
    regenerateRecoveryCodes: action('post'),
    secretKey: action('get'),
}
