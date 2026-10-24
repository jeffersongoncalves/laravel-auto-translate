<?php

declare(strict_types=1);

namespace JeffersonGoncalves\AutoTranslate\Translators;

use Illuminate\Support\Facades\Http;
use JeffersonGoncalves\AutoTranslate\Contracts\Translator;
use RuntimeException;

/**
 * MyMemory (api.mymemory.translated.net): free, no key. Each request takes at
 * most 500 bytes, so longer text is sent sentence by sentence.
 */
final class MyMemoryTranslator implements Translator
{
    private const MAX_BYTES = 450;

    public function translate(string $text, string $from, string $to): string
    {
        [$masked, $tokens] = self::protect($text);

        $parts = array_map(
            fn (string $chunk): string => $this->request($chunk, $from, $to),
            self::chunks($masked),
        );

        return strtr(implode(' ', $parts), $tokens);
    }

    /**
     * Swap protected terms, `code`, vendor/package ids and CamelCase
     * identifiers for T0X-style placeholders — MyMemory keeps those verbatim
     * and even treats them as proper nouns ("Plug-in T0X", "T0X-Plugin").
     *
     * @return array{0: string, 1: array<string, string>}
     */
    private static function protect(string $text): array
    {
        $terms = array_filter((array) config('auto-translate.protected_terms', []), fn ($term): bool => is_string($term) && $term !== '');
        usort($terms, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        $patterns = ['`[^`]+`', '\b[\w.-]+\/[\w.-]+\b'];

        if ($terms !== []) {
            $patterns[] = '\b(?:'.implode('|', array_map(fn (string $term): string => preg_quote($term, '/'), $terms)).')\b';
        }

        $patterns[] = '\b[A-Z][a-z]+[A-Z]\w*\b';

        $tokens = [];

        $masked = preg_replace_callback(
            '/'.implode('|', $patterns).'/',
            function (array $m) use (&$tokens): string {
                $key = 'T'.count($tokens).'X';
                $tokens[$key] = $m[0];

                return $key;
            },
            $text,
        ) ?? $text;

        return [$masked, $tokens];
    }

    private function request(string $text, string $from, string $to): string
    {
        $response = Http::timeout((int) config('auto-translate.mymemory.timeout', 15))
            ->get('https://api.mymemory.translated.net/get', array_filter([
                'q' => $text,
                'langpair' => self::code($from).'|'.self::code($to),
                'de' => config('auto-translate.mymemory.email'),
            ]));

        $translated = (string) $response->json('responseData.translatedText', '');

        // Quota/limit errors can come back as HTTP 200, with the warning as
        // the "translation" — never return that text.
        if (! $response->successful()
            || (int) $response->json('responseStatus') !== 200
            || $translated === ''
            || str_contains($translated, 'MYMEMORY WARNING')) {
            throw new RuntimeException('MyMemory translation failed: '.($response->json('responseDetails') ?: $response->status()));
        }

        return html_entity_decode($translated, ENT_QUOTES | ENT_HTML5);
    }

    /**
     * @return list<string>
     */
    private static function chunks(string $text): array
    {
        if (strlen($text) <= self::MAX_BYTES) {
            return [$text];
        }

        $chunks = [];
        $current = '';

        foreach (preg_split('/(?<=[.!?])\s+/', $text) ?: [$text] as $sentence) {
            // A sentence over the limit on its own is cut between words.
            foreach (strlen($sentence) > self::MAX_BYTES ? explode("\n", wordwrap($sentence, self::MAX_BYTES, "\n")) : [$sentence] as $piece) {
                if ($current !== '' && strlen($current.' '.$piece) > self::MAX_BYTES) {
                    $chunks[] = $current;
                    $current = $piece;
                } else {
                    $current = $current === '' ? $piece : $current.' '.$piece;
                }
            }
        }

        $chunks[] = $current;

        // ponytail: a single word over 450 bytes (a URL, say) still goes in one
        // request; MyMemory rejects it and the translation fails.
        return $chunks;
    }

    private static function code(string $locale): string
    {
        $map = (array) config('auto-translate.mymemory.locales', []);

        return is_string($map[$locale] ?? null) ? $map[$locale] : str_replace('_', '-', $locale);
    }
}
