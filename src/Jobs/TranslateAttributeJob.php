<?php

declare(strict_types=1);

namespace JeffersonGoncalves\AutoTranslate\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use JeffersonGoncalves\AutoTranslate\Contracts\AutoTranslatable;
use JeffersonGoncalves\AutoTranslate\Contracts\Translator;
use RuntimeException;
use Throwable;

/**
 * Machine-translates one translatable attribute from $source into $targets.
 * Locales that fail keep their old value, the rest are saved, and the job
 * then fails so it retries.
 */
class TranslateAttributeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

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

        foreach ($this->targets as $locale) {
            try {
                $model->setTranslation($this->attribute, $locale, $translator->translate($text, $this->source, $locale));
            } catch (Throwable $e) {
                $failed[$locale] = $e->getMessage();
            }
        }

        if ($failed === []) {
            $model->autoTranslated($this->attribute, $text);
        }

        if ($model->isDirty()) {
            $model->save();
        }

        if ($failed !== []) {
            throw new RuntimeException('Translation failed for '.implode(', ', array_keys($failed)).': '.reset($failed));
        }
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
