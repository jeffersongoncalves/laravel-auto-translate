<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use JeffersonGoncalves\AutoTranslate\Contracts\Translator;
use JeffersonGoncalves\AutoTranslate\Translators\MyMemoryTranslator;

it('is the default Translator binding', function () {
    expect(app(Translator::class))->toBeInstanceOf(MyMemoryTranslator::class);
});

it('translates through MyMemory, mapping pt to pt-BR and decoding entities', function () {
    Http::fake(['api.mymemory.translated.net/*' => Http::response(myMemoryResponse('Plugin para tabelas &amp; mais'))]);

    expect((new MyMemoryTranslator)->translate('Plugin for tables & more', 'en', 'pt'))
        ->toBe('Plugin para tabelas & mais');

    Http::assertSent(fn (Request $request): bool => $request['langpair'] === 'en|pt-BR' && $request['q'] === 'Plugin for tables & more');
});

it('sends the contact email and turns underscored locales into MyMemory codes', function () {
    config(['auto-translate.mymemory.email' => 'me@example.com']);
    Http::fake(['api.mymemory.translated.net/*' => Http::response(myMemoryResponse('Hallo'))]);

    (new MyMemoryTranslator)->translate('Hello', 'en_US', 'de');

    Http::assertSent(fn (Request $request): bool => $request['langpair'] === 'en-US|de' && $request['de'] === 'me@example.com');
});

it('keeps protected terms, vendor/package ids and code out of the translation', function () {
    Http::fake(['api.mymemory.translated.net/*' => fn (Request $request) => Http::response(myMemoryResponse('Plug-in '.$request['q']))]);

    $out = (new MyMemoryTranslator)->translate('Laravel Zero for spatie/laravel-settings and `make()` via AdminResource', 'en', 'pt');

    Http::assertSent(fn (Request $request): bool => $request['q'] === 'T0X for T1X and T2X via T3X');
    expect($out)->toBe('Plug-in Laravel Zero for spatie/laravel-settings and `make()` via AdminResource');
});

it('reads the protected terms from config', function () {
    config(['auto-translate.protected_terms' => ['Acme']]);
    Http::fake(['api.mymemory.translated.net/*' => fn (Request $request) => Http::response(myMemoryResponse($request['q']))]);

    (new MyMemoryTranslator)->translate('Acme and Filament', 'en', 'pt');

    Http::assertSent(fn (Request $request): bool => $request['q'] === 'T0X and Filament');
});

it('treats the quota warning MyMemory returns as a failure, never as a translation', function () {
    Http::fake(['api.mymemory.translated.net/*' => Http::response(myMemoryResponse('MYMEMORY WARNING: YOU USED ALL AVAILABLE FREE TRANSLATIONS FOR TODAY', 429))]);

    (new MyMemoryTranslator)->translate('Hello', 'en', 'de');
})->throws(RuntimeException::class);

it('sends text over the 500-byte request limit sentence by sentence', function () {
    Http::fake(['api.mymemory.translated.net/*' => Http::response(myMemoryResponse('ok.'))]);

    $text = str_repeat('This sentence is about forty characters long. ', 20);

    expect((new MyMemoryTranslator)->translate(trim($text), 'en', 'fr'))->toStartWith('ok. ok.');

    Http::assertSentCount(3);
    Http::assertSent(fn (Request $request): bool => strlen($request['q']) <= 450);
});
