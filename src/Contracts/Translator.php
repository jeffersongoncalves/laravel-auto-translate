<?php

declare(strict_types=1);

namespace JeffersonGoncalves\AutoTranslate\Contracts;

interface Translator
{
    /**
     * @throws \RuntimeException when the text could not be translated
     */
    public function translate(string $text, string $from, string $to): string;
}
