<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | API key
    |--------------------------------------------------------------------------
    |
    | Create one at https://www.einvoicing.dev. Test keys are free and
    | unmetered and cannot touch the account, which makes them the right
    | thing to put in CI.
    |
    */

    'key' => env('EINVOICING_KEY'),

    /*
    |--------------------------------------------------------------------------
    | API host
    |--------------------------------------------------------------------------
    */

    'url' => env('EINVOICING_URL', 'https://api.einvoicing.dev'),

    /*
    |--------------------------------------------------------------------------
    | Ruleset
    |--------------------------------------------------------------------------
    |
    | Pin validation to a published release, such as
    | "peppol-bis-billing-3.0.21". Leave it null and the current ruleset is
    | used — which means a release elsewhere can turn a passing build red
    | without anything of yours changing.
    |
    */

    'ruleset' => env('EINVOICING_RULESET'),

    /*
    |--------------------------------------------------------------------------
    | Participant lookups
    |--------------------------------------------------------------------------
    |
    | A lookup is a live network call. Registrations change rarely, so the
    | answer is cached; the API itself caches for five minutes, and matching
    | that is a sensible default. Set the ttl to 0 to always ask.
    |
    */

    'cache' => [
        'store' => env('EINVOICING_CACHE_STORE'),
        'ttl' => (int) env('EINVOICING_CACHE_TTL', 300),
        'prefix' => 'einvoicing:participant:',
    ],

];
