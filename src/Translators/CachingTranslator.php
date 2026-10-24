<?php

declare(strict_types=1);

namespace JeffersonGoncalves\AutoTranslate\Translators;

use Illuminate\Support\Facades\Cache;
use JeffersonGoncalves\AutoTranslate\Contracts\Translator;

/**
 * Remembers each translation, so the same text (re-saves, retries, repeated
 * descriptions) doesn't spend the provider's quota again. Failures throw and
 * are never cached.
 */
final class CachingTranslator implements Translator
{
    public function __construct(private readonly Translator $translator) {}

    public function translate(string $text, string $from, string $to): string
    {
        $ttl = config('auto-translate.cache.ttl');

        return Cache::store(config('auto-translate.cache.store'))->remember(
            'auto-translate:'.sha1($from.'|'.$to.'|'.$text),
            is_numeric($ttl) ? (int) $ttl : null,
            fn (): string => $this->translator->translate($text, $from, $to),
        );
    }
}
