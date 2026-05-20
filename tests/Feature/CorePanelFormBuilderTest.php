<?php

declare(strict_types=1);

use CorePanel\Support\FormBuilder\Fields\EmailInput;
use CorePanel\Support\FormBuilder\Fields\Repeater;
use CorePanel\Support\FormBuilder\Fields\Select;
use CorePanel\Support\FormBuilder\Fields\TextInput;
use CorePanel\Support\FormBuilder\Form;

it('serializes a form schema to json-ready arrays', function (): void {
    $form = Form::make('user-form')->schema([
        TextInput::make('name')->label('Name')->required(),
        EmailInput::make('email')->label('E-Mail')->required(),
        Select::make('role')->options([
            'admin' => 'Admin',
            'editor' => 'Editor',
        ]),
    ]);

    $payload = $form->toArray();

    expect($payload['name'])->toBe('user-form')
        ->and($payload['schema'][0])->toMatchArray([
            'label' => 'Name',
            'name' => 'name',
            'required' => true,
            'rules' => ['string', 'required'],
            'type' => 'text',
        ])
        ->and($payload['schema'][1])->toMatchArray([
            'label' => 'E-Mail',
            'name' => 'email',
            'required' => true,
            'rules' => ['email', 'required'],
            'type' => 'email',
        ])
        ->and($payload['schema'][2])->toMatchArray([
            'name' => 'role',
            'options' => [
                'admin' => 'Admin',
                'editor' => 'Editor',
            ],
            'rules' => [],
            'type' => 'select',
        ])
        ->and($payload)->toHaveKey('validation.rules');
});

it('derives validation rules from required fields', function (): void {
    $form = Form::make('user-form')->schema([
        TextInput::make('name')->required()->rules(['min:3']),
    ]);

    expect($form->rules())->toBe([
        'name' => ['string', 'min:3', 'required'],
    ]);
});

it('keeps translation metadata on fields', function (): void {
    $form = Form::make('translated-form')->schema([
        TextInput::make('name')
            ->labelTranslations([
                'de' => 'Name',
                'en' => 'Name',
            ])
            ->placeholderTranslations([
                'de' => 'Vollständiger Name',
                'en' => 'Full name',
            ])
            ->helpTranslations([
                'de' => 'Bitte vollständig ausfüllen.',
                'en' => 'Please enter the full name.',
            ])
            ->validationMessageTranslations([
                'required' => [
                    'de' => 'Name ist erforderlich.',
                    'en' => 'Name is required.',
                ],
            ])
            ->required(),
    ]);

    $field = $form->toArray()['schema'][0];

    expect($field['labelTranslations'])->toBe([
        'de' => 'Name',
        'en' => 'Name',
    ])->and($field['placeholderTranslations'])->toBe([
        'de' => 'Vollständiger Name',
        'en' => 'Full name',
    ])->and($field['helpTranslations'])->toBe([
        'de' => 'Bitte vollständig ausfüllen.',
        'en' => 'Please enter the full name.',
    ])->and($form->messages('de'))->toBe([
        'name.required' => 'Name ist erforderlich.',
    ]);
});

it('serializes field options and option translations', function (): void {
    $form = Form::make('role-form')->schema([
        Select::make('role')
            ->options([
                'admin' => 'Admin',
                'editor' => 'Editor',
            ])
            ->optionTranslations([
                'admin' => [
                    'de' => 'Administrator',
                    'en' => 'Administrator',
                ],
                'editor' => [
                    'de' => 'Redaktion',
                    'en' => 'Editor',
                ],
            ]),
    ]);

    expect($form->toArray()['schema'][0])->toMatchArray([
        'name' => 'role',
        'options' => [
            'admin' => 'Admin',
            'editor' => 'Editor',
        ],
        'optionTranslations' => [
            'admin' => [
                'de' => 'Administrator',
                'en' => 'Administrator',
            ],
            'editor' => [
                'de' => 'Redaktion',
                'en' => 'Editor',
            ],
        ],
    ]);
});

it('builds nested repeater validation rules and schema', function (): void {
    $form = Form::make('addresses')->schema([
        Repeater::make('addresses')->schema([
            TextInput::make('street')->required(),
            TextInput::make('city')->required(),
        ]),
    ]);

    $payload = $form->toArray();

    expect($form->rules())->toBe([
        'addresses' => ['array'],
        'addresses.*.street' => ['string', 'required'],
        'addresses.*.city' => ['string', 'required'],
    ])->and($payload['schema'][0])->toMatchArray([
        'name' => 'addresses',
        'rules' => ['array'],
        'type' => 'repeater',
    ])
        ->and($payload['schema'][0]['schema'][0])->toMatchArray([
            'name' => 'street',
            'required' => true,
            'rules' => ['string', 'required'],
            'type' => 'text',
        ])
        ->and($payload['schema'][0]['schema'][1])->toMatchArray([
            'name' => 'city',
            'required' => true,
            'rules' => ['string', 'required'],
            'type' => 'text',
        ]);
});
