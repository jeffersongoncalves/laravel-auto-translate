# Changelog

All notable changes to `laravel-auto-translate` will be documented in this file.

## 1.1.1 - 2026-10-24

- Fix: a single sentence over MyMemory's 450-byte request limit is now split between words instead of failing.

## 1.1.0 - 2026-10-24

- Cache translations per text + locale pair (CachingTranslator, config auto-translate.cache), so retries and repeated text don't spend the provider quota. Wraps an app-bound Translator too; failures are never cached.
- Ship Laravel Boost guidelines and the auto-translate-development skill.

## 1.0.0 - 2026-10-24

Initial release: TranslateAttributeJob + AutoTranslatable contract for spatie/laravel-translatable models, Translator contract, and a free MyMemory driver (protected terms, sentence chunking, quota-warning detection).
