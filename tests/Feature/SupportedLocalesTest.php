<?php

declare(strict_types=1);

use CorePanel\Support\Locale\SupportedLocales;

it('prefers app language keys as the available locale catalog', function (): void {
    config()->set('app.languages', [
        'de' => 'Deutsch',
        'en' => 'English',
    ]);
    config()->set('core-panel.i18n.supported_locales', ['de']);

    expect(SupportedLocales::availableCodes())->toContain('de', 'en')
        ->and(SupportedLocales::codes())->toBe(['de'])
        ->and(SupportedLocales::labels())->toBe([
            'de' => 'Deutsch',
            'en' => 'English',
        ])
        ->and(SupportedLocales::availableOptions())->toContain(
            ['label' => 'Deutsch', 'value' => 'de'],
            ['label' => 'English', 'value' => 'en'],
        )
        ->and(SupportedLocales::options())->toBe([
            ['label' => 'Deutsch', 'value' => 'de'],
        ]);
});

it('detects locales from language directories and json files', function (): void {
    config()->set('app.languages', []);
    config()->set('core-panel.i18n.supported_locales', []);

    $langPath = app()->langPath();
    $directoryLocalePath = $langPath.'/fr';
    $jsonLocalePath = $langPath.'/it.json';

    if (! is_dir($directoryLocalePath)) {
        mkdir($directoryLocalePath, 0777, true);
    }

    file_put_contents($directoryLocalePath.'/messages.php', "<?php\n\nreturn [];\n");
    file_put_contents($jsonLocalePath, json_encode(['hello' => 'ciao']));

    expect(SupportedLocales::availableCodes())->toContain('fr', 'it')
        ->and(SupportedLocales::availableOptions())->toContain(
            ['label' => 'français', 'value' => 'fr'],
            ['label' => 'italiano', 'value' => 'it'],
        );

    unlink($directoryLocalePath.'/messages.php');
    rmdir($directoryLocalePath);
    unlink($jsonLocalePath);
});

it('falls back to core panel locales and native labels when app languages are not configured', function (): void {
    config()->set('app.languages', []);
    config()->set('core-panel.i18n.supported_locales', ['de', 'en']);

    expect(SupportedLocales::availableCodes())->toContain('de', 'en')
        ->and(SupportedLocales::codes())->toBe(['de', 'en'])
        ->and(SupportedLocales::labels())->toBe([])
        ->and(SupportedLocales::labelsFor(['de', 'en', 'tr']))->toBe([
            'de' => 'Deutsch',
            'en' => 'English',
            'tr' => 'Türkçe',
        ]);
});

it('normalizes configured locale settings against the configured language source', function (): void {
    config()->set('app.languages', [
        'de' => 'Deutsch',
        'en' => 'English',
    ]);
    config()->set('core-panel.i18n.supported_locales', ['de']);

    expect(SupportedLocales::normalize(['de', '', 'de', 'en']))->toBe(['de', 'en'])
        ->and(SupportedLocales::normalize([]))->toContain('de', 'en')
        ->and(SupportedLocales::codes())->toBe(['de'])
        ->and(SupportedLocales::labelsFor(['de']))->toBe(['de' => 'Deutsch']);
});

it('ignores the vendor language namespace directory when discovering locales', function (): void {
    config()->set('app.languages', []);
    config()->set('core-panel.i18n.supported_locales', []);

    expect(SupportedLocales::availableCodes())->not->toContain('vendor');
});
