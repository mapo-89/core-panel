<?php

declare(strict_types=1);

it('ships a form renderer that maps text fields to a dedicated vue field component', function (): void {
    $renderer = file_get_contents(__DIR__.'/../../resources/js/components/FormBuilder/FormRenderer.vue');

    expect($renderer)->toContain("import TextField from './fields/TextField.vue'")
        ->and($renderer)->toContain('text: TextField')
        ->and($renderer)->toContain('password: PasswordField');
});

it('ships a select field renderer that resolves schema options dynamically', function (): void {
    $selectField = file_get_contents(__DIR__.'/../../resources/js/components/FormBuilder/fields/SelectField.vue');

    expect($selectField)->toContain('resolveFieldOptions')
        ->and($selectField)->toContain('option-label="label"')
        ->and($selectField)->toContain('option-value="value"');
});

it('ships conditional visibility helpers for form schemas', function (): void {
    $schema = file_get_contents(__DIR__.'/../../resources/js/components/FormBuilder/useFormSchema.ts');
    $renderer = file_get_contents(__DIR__.'/../../resources/js/components/FormBuilder/FormRenderer.vue');

    expect($schema)->toContain('export function evaluateCondition')
        ->and($schema)->toContain('visibleIf')
        ->and($renderer)->toContain('isFieldVisible(field, props.modelValue)');
});

it('ships nested model helpers for schema-backed field paths', function (): void {
    $schema = file_get_contents(__DIR__.'/../../resources/js/components/FormBuilder/useFormSchema.ts');
    $renderer = file_get_contents(__DIR__.'/../../resources/js/components/FormBuilder/FormRenderer.vue');

    expect($schema)->toContain('export function setNestedValue')
        ->and($schema)->toContain('export function getNestedValue')
        ->and($renderer)->toContain('setNestedValue(props.modelValue, name, value)')
        ->and($renderer)->toContain('getNestedValue(modelValue, field.name)');
});

it('ships a renderer with explicit column control and opt-in disabled conditions', function (): void {
    $schema = file_get_contents(__DIR__.'/../../resources/js/components/FormBuilder/useFormSchema.ts');
    $renderer = file_get_contents(__DIR__.'/../../resources/js/components/FormBuilder/FormRenderer.vue');
    $textField = file_get_contents(__DIR__.'/../../resources/js/components/FormBuilder/fields/TextField.vue');
    $emailField = file_get_contents(__DIR__.'/../../resources/js/components/FormBuilder/fields/EmailField.vue');
    $selectField = file_get_contents(__DIR__.'/../../resources/js/components/FormBuilder/fields/SelectField.vue');
    $multiSelectField = file_get_contents(__DIR__.'/../../resources/js/components/FormBuilder/fields/MultiSelectField.vue');

    expect($schema)->toContain('if (field.disabledIf === null || field.disabledIf === undefined) {')
        ->and($renderer)->toContain('columns?: number')
        ->and($renderer)->toContain('columns: 1')
        ->and($renderer)->toContain('class="grid w-full gap-4"')
        ->and($renderer)->toContain('gridTemplateColumns')
        ->and($renderer)->toContain('Math.max(props.columns, 1)')
        ->and($textField)->toContain('fluid')
        ->and($emailField)->toContain('fluid')
        ->and($selectField)->toContain('fluid')
        ->and($multiSelectField)->toContain('fluid');
});

it('ships i18n fallback helpers for labels and option translations', function (): void {
    $schema = file_get_contents(__DIR__.'/../../resources/js/components/FormBuilder/useFormSchema.ts');

    expect($schema)->toContain('resolveTranslatedText')
        ->and($schema)->toContain('labelTranslations')
        ->and($schema)->toContain('optionTranslations')
        ->and($schema)->toContain('?? fallback');
});

it('registers the primevue components required by the form renderer', function (): void {
    $entry = file_get_contents(__DIR__.'/../../resources/js/plugins/core-panel.ts');
    $textField = file_get_contents(__DIR__.'/../../resources/js/components/FormBuilder/fields/TextField.vue');
    $dateField = file_get_contents(__DIR__.'/../../resources/js/components/FormBuilder/fields/DateField.vue');

    expect($entry)->toContain("import InputNumber from 'primevue/inputnumber'")
        ->and($entry)->toContain("import Message from 'primevue/message'")
        ->and($entry)->toContain("import Textarea from 'primevue/textarea'")
        ->and($entry)->toContain("app.component('InputNumber', InputNumber)")
        ->and($entry)->toContain("app.component('Message', Message)")
        ->and($entry)->toContain("app.component('Textarea', Textarea)")
        ->and($textField)->toContain('<InputText')
        ->and($dateField)->toContain('<DatePicker');
});
