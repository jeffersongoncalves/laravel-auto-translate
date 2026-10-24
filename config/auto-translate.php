<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Queue (and connection) TranslateAttributeJob is dispatched to. Null uses
    | the application default.
    |
    */

    'queue' => env('AUTO_TRANSLATE_QUEUE'),

    'connection' => env('AUTO_TRANSLATE_QUEUE_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Each translation is remembered by source text + locale pair, so the same
    | text never spends the provider's quota twice. `ttl` in seconds (null =
    | forever), `store` null uses the default cache store.
    |
    */

    'cache' => [
        'enabled' => env('AUTO_TRANSLATE_CACHE', true),
        'store' => env('AUTO_TRANSLATE_CACHE_STORE'),
        'ttl' => 60 * 60 * 24 * 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Protected terms
    |--------------------------------------------------------------------------
    |
    | Names the translator must keep verbatim ("Filament" would otherwise come
    | back as "filamento"). `code`, vendor/package ids and CamelCase
    | identifiers are always protected. Longer terms win over shorter ones.
    |
    */

    'protected_terms' => [
        'Laravel', 'Laravel Zero', 'Filament', 'Livewire', 'Alpine.js', 'Tailwind', 'Blade', 'Eloquent',
        'Pest', 'PHPUnit', 'Composer', 'Packagist', 'npm', 'pnpm', 'Horizon', 'Spatie', 'GitHub',
    ],

    /*
    |--------------------------------------------------------------------------
    | MyMemory
    |--------------------------------------------------------------------------
    |
    | api.mymemory.translated.net: free, no key. Anonymous use allows ~5k
    | characters/day; a contact email raises it to ~50k. `locales` maps an
    | app locale to the code MyMemory expects (it reads "pt" as pt-PT).
    |
    */

    'mymemory' => [
        'email' => env('MYMEMORY_EMAIL'),
        'timeout' => 15,
        'locales' => [
            'pt' => 'pt-BR',
            'pt_BR' => 'pt-BR',
        ],
    ],

];
