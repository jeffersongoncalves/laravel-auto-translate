---
name: auto-translate-development
description: Development guide for laravel-auto-translate, queued machine translation of spatie/laravel-translatable model attributes with a free MyMemory driver and a translation cache.
---

# Auto Translate Development Skill

## When to use this skill

- When adding machine translation to a translatable model
- When developing or extending the laravel-auto-translate package
- When adding another translation provider (DeepL, LibreTranslate, Google Cloud…)
- When debugging translations that were not saved, or product names that got translated

## Setup

- PHP 8.2+, Laravel 11, 12 or 13
- `spatie/laravel-translatable` on the models (its `HasTranslations` implements the contract's translation methods)

```bash
composer require jeffersongoncalves/laravel-auto-translate
php artisan vendor:publish --tag="auto-translate-config"
```

## Package Structure

```
src/
  AutoTranslateServiceProvider.php   # Config; binds Translator (MyMemory) wrapped in CachingTranslator
  Contracts/
    AutoTranslatable.php             # getTranslation(), setTranslation(), autoTranslated()
    Translator.php                   # translate($text, $from, $to): string, throws RuntimeException
  Jobs/
    TranslateAttributeJob.php        # (model, attribute, source, targets); tries 3, backoff 60/300
  Translators/
    MyMemoryTranslator.php           # Protects terms, chunks text, rejects quota warnings
    CachingTranslator.php            # Cache::remember per text + locale pair
config/
  auto-translate.php                 # queue, cache, protected_terms, mymemory
```

## How It Works

1. `TranslateAttributeJob` reads `$model->getTranslation($attribute, $source, false)`; an empty source is a no-op.
2. For each target locale it calls `Translator::translate()` and `setTranslation()`. Failures are collected, not thrown yet.
3. If all succeeded, `$model->autoTranslated($attribute, $source)` runs (store a hash there).
4. The model is saved if dirty, then the job throws when any locale failed, so it is retried.

`MyMemoryTranslator` masks protected terms, `code`, `vendor/package` ids and CamelCase identifiers as `T0X` placeholders before the request and restores them after. Text over 450 bytes is split by sentence. A `MYMEMORY WARNING` response (quota) is an exception, never a translation.

## Adding a provider

```php
use JeffersonGoncalves\AutoTranslate\Contracts\Translator;

final class DeepLTranslator implements Translator
{
    public function translate(string $text, string $from, string $to): string
    {
        // call the API; throw RuntimeException on any failure
    }
}

// AppServiceProvider::register()
$this->app->bind(Translator::class, DeepLTranslator::class);
```

The provider's `extend()` still wraps it in `CachingTranslator`.

## Testing Patterns

```php
use Illuminate\Support\Facades\Http;

it('translates the title', function () {
    Http::fake(['api.mymemory.translated.net/*' => Http::response([
        'responseData' => ['translatedText' => 'Olá'],
        'responseStatus' => 200,
    ])]);

    $post = Post::create(['title' => ['en' => 'Hello']]);

    TranslateAttributeJob::dispatchSync($post, 'title', 'en', ['pt']);

    expect($post->refresh()->getTranslation('title', 'pt'))->toBe('Olá');
});
```

```bash
vendor/bin/pest
vendor/bin/phpstan analyse
vendor/bin/pint
```
