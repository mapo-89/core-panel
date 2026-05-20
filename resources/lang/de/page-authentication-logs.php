<?php

declare(strict_types=1);

return [
    'columns' => [
        'browser' => 'Browser',
        'device' => 'Gerät',
        'device_type' => 'Gerätetyp',
        'guard' => 'Guard',
        'ip_address' => 'IP-Adresse',
        'last_active_at' => 'Zuletzt aktiv',
        'login_at' => 'Anmeldung',
        'logout_at' => 'Abmeldung',
        'method' => 'Methode',
        'platform' => 'Plattform',
        'result' => 'Ergebnis',
        'user' => 'Benutzer',
    ],
    'description' => 'Erfolgreiche und fehlgeschlagene Anmeldungen im Panel nachvollziehen.',
    'device_browser_on_platform' => ':browser auf :platform',
    'detail_title' => 'Authentifizierungsdetails',
    'details_load_failed' => 'Authentifizierungsdetails konnten nicht geladen werden.',
    'empty' => 'Keine Authentifizierungseinträge gefunden.',
    'failed' => 'Fehlgeschlagen',
    'filters' => [
        'date_from' => 'Von',
        'date_to' => 'Bis',
        'guard' => 'Guard',
        'result' => 'Ergebnis',
        'search' => 'Benutzer, IP, Browser',
        'user' => 'Benutzer',
    ],
    'methods' => [
        'form' => 'Login-Form',
        'socialite' => 'Social Login',
        'socialite_provider' => 'Login über :provider',
    ],
    'properties' => 'Eigenschaften',
    'results' => [
        'expired' => 'Session abgelaufen',
        'failed' => 'Fehlgeschlagen',
        'logout' => 'Abgemeldet',
        'revoked' => 'Session gelöscht',
        'successful' => 'Aktiv',
    ],
    'successful' => 'Erfolgreich',
    'title' => 'Authentifizierungsprotokolle',
    'user_agent' => 'User-Agent',
];
