<div class="filament-hidden">

![Laravel Auto Translate](https://raw.githubusercontent.com/jeffersongoncalves/laravel-auto-translate/main/art/jeffersongoncalves-laravel-auto-translate.png)

</div>

# Laravel Auto Translate

[![Buy Me A Coffee](https://img.shields.io/badge/Buy%20Me%20A%20Coffee-support-FFDD00?style=flat-square&logo=buy-me-a-coffee&logoColor=black)](https://buymeacoffee.com/jeffersongoncalves)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jeffersongoncalves/laravel-auto-translate.svg?style=flat-square)](https://packagist.org/packages/jeffersongoncalves/laravel-auto-translate)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/jeffersongoncalves/laravel-auto-translate/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/jeffersongoncalves/laravel-auto-translate/actions?query=workflow%3ATests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/jeffersongoncalves/laravel-auto-translate/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/jeffersongoncalves/laravel-auto-translate/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/jeffersongoncalves/laravel-auto-translate.svg?style=flat-square)](https://packagist.org/packages/jeffersongoncalves/laravel-auto-translate)

Queued machine translation for Laravel translatable model attributes ([spatie/laravel-translatable](https://github.com/spatie/laravel-translatable)). A job translates one attribute from a source locale into the target locales and saves the model. The bundled driver uses [MyMemory](https://mymemory.translated.net/doc/spec.php), which is free and needs no API key or credit card.

The MyMemory driver:

- keeps product names (`Filament` would otherwise come back as "filamento"), `inline code`, `vendor/package` ids and CamelCase identifiers out of the translation;
- splits text over MyMemory's 500-byte request limit sentence by sentence;
- treats MyMemory's quota warning (which can arrive as a normal response) as a failure, so it is never saved as a translation.

## Installation

```bash
composer require jeffersongoncalves/laravel-auto-translate
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="auto-translate-config"
```

```php
return [
    'queue' => env('AUTO_TRANSLATE_QUEUE'),
    'connection' => env('AUTO_TRANSLATE_QUEUE_CONNECTION'),

    // Each translation is remembered per text + locale pair, so it never spends the quota twice.
    'cache' => [
        'enabled' => env('AUTO_TRANSLATE_CACHE', true),
        'store' => env('AUTO_TRANSLATE_CACHE_STORE'), // null = default store
        'ttl' => 60 * 60 * 24 * 30, // seconds, null = forever
    ],

    // Kept verbatim. `code`, vendor/package ids and CamelCase identifiers are always protected.
    'protected_terms' => ['Laravel', 'Laravel Zero', 'Filament', 'Livewire', /* ... */],

    'mymemory' => [
        'email' => env('MYMEMORY_EMAIL'), // raises the free quota from ~5k to ~50k characters/day
        'timeout' => 15,
        'locales' => ['pt' => 'pt-BR', 'pt_BR' => 'pt-BR'], // app locale => MyMemory code
    ],
];
```

## Usage

Implement `AutoTranslatable` on the model. `HasTranslations` already provides `getTranslation()` and `setTranslation()`, so only `autoTranslated()` is left. It runs after every target locale was translated, right before the save:

```php
use Illuminate\Database\Eloquent\Model;
use JeffersonGoncalves\AutoTranslate\Contracts\AutoTranslatable;
use Spatie\Translatable\HasTranslations;

class Post extends Model implements AutoTranslatable
{
    use HasTranslations;

    public array $translatable = ['title'];

    public function autoTranslated(string $attribute, string $source): void
    {
        // Optional: remember what was translated, to skip unchanged text later.
        $this->title_source_hash = sha1($source);
    }
}
```

Dispatch the job with the attribute, the source locale and the target locales:

```php
use JeffersonGoncalves\AutoTranslate\Jobs\TranslateAttributeJob;

TranslateAttributeJob::dispatch($post, 'title', 'en', ['pt', 'es', 'fr', 'de']);
```

When to dispatch is up to you: usually from an observer, only for the locales that are empty, or when the source text changed:

```php
public function saved(Post $post): void
{
    $source = (string) $post->getTranslation('title', 'en', false);

    if ($source !== '' && sha1($source) !== $post->title_source_hash) {
        TranslateAttributeJob::dispatch($post, 'title', 'en', ['pt', 'es', 'fr', 'de']);
    }
}
```

The job's own save fires the observer again. The hash written in `autoTranslated()` stops it from dispatching a second time.

If one locale fails, the others are still saved, `autoTranslated()` is skipped and the job fails, so it is retried (3 tries, 60s and 300s backoff).

### Another translation service

Bind your own implementation of `JeffersonGoncalves\AutoTranslate\Contracts\Translator` (DeepL, LibreTranslate, Google Cloud…) in a service provider. It replaces MyMemory:

```php
use JeffersonGoncalves\AutoTranslate\Contracts\Translator;

$this->app->bind(Translator::class, DeepLTranslator::class);
```

Your translator is still wrapped by the cache (disable it with `auto-translate.cache.enabled`).

```php
interface Translator
{
    /** @throws \RuntimeException when the text could not be translated */
    public function translate(string $text, string $from, string $to): string;
}
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Jèfferson Gonçalves](https://github.com/jeffersongoncalves)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
