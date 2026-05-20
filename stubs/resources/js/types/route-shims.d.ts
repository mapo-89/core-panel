type CorePanelRouteMethod = 'delete' | 'get' | 'patch' | 'post' | 'put'

type CorePanelRouteNodeValue = CorePanelRouteNode | CorePanelRouteCallable

interface CorePanelRouteNode {
    method?: CorePanelRouteMethod
    url: (...args: unknown[]) => string
    [key: string]: CorePanelRouteNodeValue
}

interface CorePanelRouteCallable extends CorePanelRouteNode {
    (...args: unknown[]): CorePanelRouteNodeValue
}

declare module '@/routes/*' {
    const value: CorePanelRouteNode
    export default value
}

type CorePanelWayfinderResult = {
    method?: CorePanelRouteMethod
    url: (...args: unknown[]) => string
}

type CorePanelCallableWayfinder = ((
    ...args: unknown[]
) => CorePanelWayfinderResult) &
    CorePanelWayfinderResult

declare module '@/routes/_wayfinder' {
    export type WayfinderResult = CorePanelWayfinderResult
    export type CallableWayfinder = CorePanelCallableWayfinder

    export function action(
        method?: CorePanelRouteMethod,
    ): CorePanelWayfinderResult
    export function callableAction(
        method?: CorePanelRouteMethod,
    ): CorePanelCallableWayfinder
}

declare module '*_wayfinder' {
    export type WayfinderResult = CorePanelWayfinderResult
    export type CallableWayfinder = CorePanelCallableWayfinder

    export function action(
        method?: CorePanelRouteMethod,
    ): CorePanelWayfinderResult
    export function callableAction(
        method?: CorePanelRouteMethod,
    ): CorePanelCallableWayfinder
}
