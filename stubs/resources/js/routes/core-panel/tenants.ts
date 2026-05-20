import { callableAction } from '@/routes/_wayfinder'

export default {}
export const destroy = callableAction('delete')
export const dtApi = callableAction()
export const index = callableAction()
export const show = callableAction()
export const store = callableAction('post')
export const update = callableAction('put')
