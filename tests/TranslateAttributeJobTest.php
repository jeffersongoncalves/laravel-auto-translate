<?php

use Illuminate\Support\Facades\Queue;
use JeffersonGoncalves\AutoTranslate\Contracts\Translator;
use JeffersonGoncalves\AutoTranslate\Jobs\TranslateAttributeJob;
use JeffersonGoncalves\AutoTranslate\Tests\Post;

function fakeTranslator(array $failing = []): void
{
    app()->instance(Translator::class, new class($failing) implements Translator
    {
        public function __construct(private array $failing) {}

        public function translate(string $text, string $from, string $to): string
        {
            if (in_array($to, $this->failing, true)) {
                throw new RuntimeException("no $to");
            }

            return "[$to] $text";
        }
    });
}

it('translates every target locale, calls autoTranslated and saves', function () {
    fakeTranslator();
    $post = Post::create(['title' => ['en' => 'Hello']]);

    TranslateAttributeJob::dispatchSync($post, 'title', 'en', ['pt', 'de']);

    $post->refresh();
    expect($post->getTranslations('title'))->toBe(['en' => 'Hello', 'pt' => '[pt] Hello', 'de' => '[de] Hello'])
        ->and($post->title_hash)->toBe(sha1('Hello'));
});

it('saves the locales that worked, skips autoTranslated and fails when one locale fails', function () {
    fakeTranslator(failing: ['de']);
    $post = Post::create(['title' => ['en' => 'Hello', 'de' => 'Alt']]);

    expect(fn () => (new TranslateAttributeJob($post, 'title', 'en', ['pt', 'de']))->handle(app(Translator::class)))
        ->toThrow(RuntimeException::class, 'Translation failed for de: no de');

    $post->refresh();
    expect($post->getTranslations('title'))->toBe(['en' => 'Hello', 'de' => 'Alt', 'pt' => '[pt] Hello'])
        ->and($post->title_hash)->toBeNull();
});

it('does nothing when the source locale is empty', function () {
    fakeTranslator();
    $post = Post::create(['title' => ['pt' => 'Olá']]);

    TranslateAttributeJob::dispatchSync($post, 'title', 'en', ['de']);

    expect($post->refresh()->getTranslations('title'))->toBe(['pt' => 'Olá']);
});

it('is dispatched to the configured queue and connection', function () {
    config(['auto-translate.queue' => 'translations', 'auto-translate.connection' => 'redis']);
    Queue::fake();

    TranslateAttributeJob::dispatch(Post::create(['title' => ['en' => 'Hello']]), 'title', 'en', ['pt']);

    Queue::assertPushedOn('translations', TranslateAttributeJob::class, fn (TranslateAttributeJob $job): bool => $job->connection === 'redis');
});
