<?php

use Illuminate\Support\Facades\Http;
use JeffersonGoncalves\AutoTranslate\Tests\TestCase;

uses(TestCase::class)
    ->beforeEach(fn () => Http::preventStrayRequests())
    ->in(__DIR__);

function myMemoryResponse(string $text, int $status = 200): array
{
    return ['responseData' => ['translatedText' => $text], 'responseStatus' => $status];
}
