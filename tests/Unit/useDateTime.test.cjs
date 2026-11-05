const { test } = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')
const ts = require('typescript')
const vue = require('vue')

test('formats UTC DTO instants in the configured display timezone across DST', () => {
    const context = {
        exports: {},
        require: (name) => name === 'vue' ? vue : {
            usePage: () => ({ props: { locale: { current: 'en-GB' }, corePanel: { settings: { general: { timezone: 'Europe/Berlin' } } } } }),
        },
        Intl, Date,
    }
    const source = fs.readFileSync(`${__dirname}/../../resources/js/composables/useDateTime.ts`, 'utf8')
    vm.runInNewContext(ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS } }).outputText, context)
    const { formatDateTime } = context.exports.useDateTime()
    for (const [value, expected] of [
        ['2026-10-02T10:26:51+00:00', '12:26:51'],
        ['2026-01-15T11:26:51+00:00', '12:26:51'],
        ['2026-03-29T00:59:59+00:00', '01:59:59'],
        ['2026-03-29T01:00:00+00:00', '03:00:00'],
        ['2026-10-25T00:30:00+00:00', '02:30:00'],
        ['2026-10-25T01:30:00+00:00', '02:30:00'],
    ]) {
        assert.ok(formatDateTime(value, { dateStyle: undefined, timeStyle: 'medium' }).includes(expected), value)
    }
})
