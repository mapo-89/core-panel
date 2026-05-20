export type WayfinderResult = {
    method?: 'delete' | 'get' | 'patch' | 'post' | 'put'
    url: (...args: unknown[]) => string
}

export type CallableWayfinder = ((...args: unknown[]) => WayfinderResult) &
    WayfinderResult

export function action(
    method: WayfinderResult['method'] = 'get',
): WayfinderResult {
    return {
        method,
        url: (...args: unknown[]) => {
            void args

            return '#'
        },
    }
}

export function callableAction(
    method: WayfinderResult['method'] = 'get',
): CallableWayfinder {
    const callable = ((...args: unknown[]) => {
        void args

        return {
            method,
            url: () => '#',
        }
    }) as CallableWayfinder

    callable.method = method
    callable.url = (...args: unknown[]) => {
        void args

        return '#'
    }

    return callable
}
