<?php

declare(strict_types=1);

namespace JeffersonGoncalves\AutoTranslate\Jobs;

use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use JeffersonGoncalves\AutoTranslate\Contracts\AutoTranslatable;
use JeffersonGoncalves\AutoTranslate\Contracts\Translator;
use JeffersonGoncalves\AutoTranslate\Exceptions\QuotaExceededException;
use RuntimeException;
use Throwable;

/**
 * Machine-translates one translatable attribute from $source into $targets.
 * Locales that fail keep their old value, the rest are saved, and the job
 * then fails so it retries. When the provider's quota is spent, it stops
 * calling it and is released until the quota resets, without using a try.
 */
class TranslateAttributeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Quota releases are bounded by retryUntil(), real errors by $maxExceptions. */
    public int $tries = 0;

    public int $maxExceptions = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    /**
     * @param  list<string>  $targets
     */
    public function __construct(
        public Model&AutoTranslatable $model,
        public string $attribute,
        public string $source,
        public array $targets,
    ) {
        $this->onConnection(config('auto-translate.connection'));
        $this->onQueue(config('auto-translate.queue'));
    }

    public function handle(Translator $translator): void
    {
        $model = $this->model;
        $text = trim((string) $model->getTranslation($this->attribute, $this->source, false));

        if ($text === '') {
            return;
        }

        $failed = [];
        $quota = null;

        foreach ($this->targets as $locale) {
            try {
                $model->setTranslation($this->attribute, $locale, $translator->translate($text, $this->source, $locale));
            } catch (QuotaExceededException $e) {
                $quota = $e;

                break;
            } catch (Throwable $e) {
                $failed[$locale] = $e->getMessage();
            }
        }

        if ($failed === [] && $quota === null) {
            $model->autoTranslated($this->attribute, $text);
        }

        if ($model->isDirty()) {
            $model->save();
        }

        // ponytail: the retry re-runs every target; the translation cache keeps
        // the locales already done from spending the quota again.
        // Real failures take the normal exception path (counted, with backoff).
        if ($quota !== null && $failed === []) {
            // Sync driver or a direct call has no queue to release onto.
            if ($this->job === null || $this->job instanceof SyncJob) {
                throw $quota;
            }

            $this->release($quota->retryAfter);

            return;
        }

        if ($failed !== []) {
            throw new RuntimeException('Translation failed for '.implode(', ', array_keys($failed)).': '.reset($failed));
        }
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addDays(3);
    }

    public function failed(?Throwable $e): void
    {
        Log::error('TranslateAttributeJob failed', [
            'model' => $this->model::class,
            'id' => $this->model->getKey(),
            'attribute' => $this->attribute,
            'targets' => $this->targets,
            'error' => $e?->getMessage(),
        ]);
    }
}
