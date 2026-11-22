<?php

use Illuminate\Support\Facades\Queue;
use JeffersonGoncalves\AutoTranslate\Contracts\Translator;
use JeffersonGoncalves\AutoTranslate\Exceptions\QuotaExceededException;
use JeffersonGoncalves\AutoTranslate\Jobs\TranslateAttributeJob;
use JeffersonGoncalves\AutoTranslate\Tests\Post;

function fakeTranslator(array $failing = [], array $quota = []): object
{
    $translator = new class($failing, $quota) implements Translator
    {
        public array $calls = [];

        public function __construct(private array $failing, private array $quota) {}

        public function translate(string $text, string $from, string $to): string
        {
            $this->calls[] = $to;

            if (in_array($to, $this->quota, true)) {
                throw new QuotaExceededException('quota', 3600);
            }

            if (in_array($to, $this->failing, true)) {
                throw new RuntimeException("no $to");
            }

            return "[$to] $text";
        }
    };

    app()->instance(Translator::class, $translator);

    return $translator;
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

it('stops at the quota and releases itself until it resets instead of failing', function () {
    $translator = fakeTranslator(quota: ['pt']);
    $post = Post::create(['title' => ['en' => 'Hello']]);

    $job = (new TranslateAttributeJob($post, 'title', 'en', ['pt', 'de', 'fr']))->withFakeQueueInteractions();
    $job->handle($translator);

    $job->assertReleased(delay: 3600)->assertNotFailed();
    expect($translator->calls)->toBe(['pt'])
        ->and($post->refresh()->getTranslations('title'))->toBe(['en' => 'Hello']);
});

it('saves the locales translated before the quota ran out, then releases', function () {
    $translator = fakeTranslator(quota: ['de']);
    $post = Post::create(['title' => ['en' => 'Hello']]);

    $job = (new TranslateAttributeJob($post, 'title', 'en', ['pt', 'de', 'fr']))->withFakeQueueInteractions();
    $job->handle($translator);

    $job->assertReleased(delay: 3600);
    $post->refresh();
    expect($translator->calls)->toBe(['pt', 'de'])
        ->and($post->getTranslations('title'))->toBe(['en' => 'Hello', 'pt' => '[pt] Hello'])
        ->and($post->title_hash)->toBeNull();
});

it('rethrows the quota on the sync driver after saving what it translated', function () {
    fakeTranslator(quota: ['de']);
    $post = Post::create(['title' => ['en' => 'Hello']]);

    expect(fn () => TranslateAttributeJob::dispatchSync($post, 'title', 'en', ['pt', 'de']))
        ->toThrow(QuotaExceededException::class);

    expect($post->refresh()->getTranslations('title'))->toBe(['en' => 'Hello', 'pt' => '[pt] Hello']);
});

it('fails normally instead of releasing when a real error came before the quota', function () {
    $translator = fakeTranslator(failing: ['pt'], quota: ['de']);
    $post = Post::create(['title' => ['en' => 'Hello']]);

    $job = (new TranslateAttributeJob($post, 'title', 'en', ['pt', 'de']))->withFakeQueueInteractions();

    expect(fn () => $job->handle($translator))->toThrow(RuntimeException::class, 'Translation failed for pt: no pt');
    $job->assertNotReleased();
});

it('bounds quota waits by time and real errors by exception count', function () {
    $job = new TranslateAttributeJob(Post::create(['title' => ['en' => 'Hello']]), 'title', 'en', ['pt']);

    expect($job->tries)->toBe(0)
        ->and($job->maxExceptions)->toBe(3)
        ->and($job->backoff)->toBe([60, 300])
        ->and($job->retryUntil()->getTimestamp())->toBe(now()->addDays(3)->getTimestamp());
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
