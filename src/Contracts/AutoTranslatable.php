<?php

declare(strict_types=1);

namespace JeffersonGoncalves\AutoTranslate\Contracts;

/**
 * A model TranslateAttributeJob can translate. spatie/laravel-translatable's
 * HasTranslations trait already implements getTranslation()/setTranslation(),
 * so a model using it only adds autoTranslated().
 */
interface AutoTranslatable
{
    public function getTranslation(string $key, string $locale, bool $useFallbackLocale = true): mixed;

    public function setTranslation(string $key, string $locale, $value): self;

    /**
     * Called once every target locale of $attribute was translated from
     * $source, right before the model is saved. Store a hash of $source here
     * to skip re-translating text that did not change; leave it empty if not
     * needed.
     */
    public function autoTranslated(string $attribute, string $source): void;
}
