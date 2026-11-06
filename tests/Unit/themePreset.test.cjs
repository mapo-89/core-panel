const { test } = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const ts = require('typescript')

function loadThemeModule(filename, cache = new Map()) {
    if (cache.has(filename)) return cache.get(filename)
    const context = {
        exports: {},
        require: name => name.startsWith('.')
            ? loadThemeModule(path.resolve(path.dirname(filename), name + '.ts'), cache)
            : require(name),
    }
    cache.set(filename, context.exports)
    const source = fs.readFileSync(filename, 'utf8')
    vm.runInNewContext(ts.transpileModule(source, {
        compilerOptions: { module: ts.ModuleKind.CommonJS },
    }).outputText, context)
    return context.exports
}

test('preserves component radius tokens when defining the CorePanel preset', () => {
    const filename = path.resolve(__dirname, '../../resources/js/theme/core-panel/preset.ts')
    const { corePanelRuntimePreset } = loadThemeModule(filename)
    assert.equal(corePanelRuntimePreset.semantic.formField.borderRadius, '12px')
    assert.equal(corePanelRuntimePreset.semantic.content.borderRadius, '8px')
    assert.equal(corePanelRuntimePreset.semantic.overlay.modal.borderRadius, '18px')
})
