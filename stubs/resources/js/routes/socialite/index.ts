import { callableAction } from '../_wayfinder'

export default {
    conflict: callableAction('get'),
    link: callableAction('get'),
    redirect: callableAction('get'),
    resolveAvatarSync: callableAction('post'),
    resolveConflict: callableAction('post'),
    testMail: callableAction('post'),
    unlink: callableAction('delete'),
}
