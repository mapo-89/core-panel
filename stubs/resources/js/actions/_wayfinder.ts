export type WayfinderAction = {
    method?: 'delete' | 'get' | 'patch' | 'post' | 'put'
    url: (...args: unknown[]) => string
}

export type CallableWayfinderAction = ((
    ...args: unknown[]
) => WayfinderAction) &
    WayfinderAction

export function action(
    method: WayfinderAction['method'] = 'get',
): WayfinderAction {
    return {
        method,
        url: (...args: unknown[]) => {
            void args

            return '#'
        },
    }
}

export function callableAction(
    method: WayfinderAction['method'] = 'get',
): CallableWayfinderAction {
    const callable = ((...args: unknown[]) => {
        void args

        return {
            method,
            url: () => '#',
        }
    }) as CallableWayfinderAction

    callable.method = method
    callable.url = (...args: unknown[]) => {
        void args

        return '#'
    }

    return callable
}
