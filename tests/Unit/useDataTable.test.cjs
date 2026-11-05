const { test } = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')
const ts = require('typescript')
const vue = require('vue')

function setup() {
    const requests = []
    const timers = new Map()
    const cleanups = []
    const schema = vue.ref({
        mode: 'remote', columns: [], rows: [], actions: [], bulkActions: [],
        state: { search: '', filters: {}, sort: '', visibleColumns: [] },
        pagination: { page: 1, perPage: 10, lastPage: 1 },
    })
    const source = fs.readFileSync(`${__dirname}/../../resources/js/components/TableBuilder/useDataTable.ts`, 'utf8')
    const context = {
        exports: {},
        URLSearchParams,
        require: (name) => name === 'vue'
            ? { ...vue, onBeforeUnmount: (fn) => cleanups.push(fn) }
            : { usePage: () => ({ url: '/users' }), router: {
                get: (url, data, options) => {
                    const request = { data, options, cancelled: false }
                    requests.push(request)
                    options.onCancelToken?.({ cancel: () => { request.cancelled = true } })
                },
            } },
        setTimeout: (fn) => { const id = Symbol(); timers.set(id, fn); return id },
        clearTimeout: (id) => timers.delete(id),
    }
    vm.runInNewContext(ts.transpileModule(source, {
        compilerOptions: { module: ts.ModuleKind.CommonJS },
    }).outputText, context)
    const scope = vue.effectScope()
    const table = scope.run(() => context.exports.useDataTable(schema))
    return { table, requests, schema, timers,
        flush: () => { for (const [id, fn] of timers) { timers.delete(id); fn() } },
        dispose: () => { cleanups.forEach((fn) => fn()); scope.stop() },
    }
}

test('keeps newer input when an older search response arrives', async () => {
    const s = setup()
    s.table.setSearch('a')
    s.flush()
    s.table.setSearch('abc')
    assert.equal(s.requests[0].cancelled, true)
    s.schema.value.state = { ...s.schema.value.state, search: 'a' }
    await vue.nextTick()
    assert.equal(s.table.search.value, 'abc')
    s.flush()
    assert.equal(s.requests.at(-1).data.search, 'abc')
    s.dispose()
})

test('coalesces typing and cancels pending searches on unmount', () => {
    const s = setup()
    s.table.setSearch('a')
    s.table.setSearch('ab')
    s.flush()
    assert.equal(s.requests.length, 1)
    assert.equal(s.requests[0].data.search, 'ab')
    s.table.setSearch('abc')
    s.dispose()
    s.flush()
    assert.equal(s.requests.length, 1)
})

test('accepts server search changes when there is no newer input', async () => {
    const s = setup()
    s.schema.value.state = { ...s.schema.value.state, search: 'restored' }
    await vue.nextTick()
    assert.equal(s.table.search.value, 'restored')
    s.dispose()
})
