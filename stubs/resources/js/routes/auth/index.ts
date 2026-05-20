import { action } from '../_wayfinder'
import password from './password'
import twoFactor from './two-factor'
import verification from './verification'

export default {
    login: action('get'),
    password,
    register: action('get'),
    twoFactor,
    verification,
}
