## Laravel Auto Translate

### Overview
Queued machine translation for `spatie/laravel-translatable` model attributes. `TranslateAttributeJob` translates one attribute from a source locale into target locales and saves the model. The default driver is MyMemory (free, no API key), wrapped in a cache.

**Namespace:** `JeffersonGoncalves\AutoTranslate`
**Service Provider:** `AutoTranslateServiceProvider` (auto-discovered)
**Job:** `JeffersonGoncalves\AutoTranslate\Jobs\TranslateAttributeJob`
**Contracts:** `Contracts\AutoTranslatable` (model), `Contracts\Translator` (driver)

### Making a model translatable

@verbatim
<code-snippet name="model" lang="php">
use JeffersonGoncalves\AutoTranslate\Contracts\AutoTranslatable;
use Spatie\Translatable\HasTranslations;

class Post extends Model implements AutoTranslatable
{
    use HasTranslations; // provides getTranslation()/setTranslation()

    public array $translatable = ['title'];

    // Runs once every target locale succeeded, right before the save.
    public function autoTranslated(string $attribute, string $source): void
    {
        $this->title_source_hash = sha1($source);
    }
}
</code-snippet>
@endverbatim

### Dispatching

@verbatim
<code-snippet name="dispatch" lang="php">
use JeffersonGoncalves\AutoTranslate\Jobs\TranslateAttributeJob;

TranslateAttributeJob::dispatch($post, 'title', 'en', ['pt', 'es', 'fr', 'de']);
</code-snippet>
@endverbatim

### Conventions
- The app decides when to dispatch (usually an observer). Guard against the job's own save re-dispatching: compare a hash stored in `autoTranslated()`, or only target empty locales.
- If one locale fails, the others are saved, `autoTranslated()` is skipped and the job throws so it retries.
- Product names that must not be translated go in `config('auto-translate.protected_terms')`; `code`, `vendor/package` ids and CamelCase identifiers are always protected.
- Another provider: bind your own `Contracts\Translator`; it is still wrapped by the cache (`auto-translate.cache.enabled`).
- In tests, swap the driver with `app()->instance(Translator::class, $fake)` or `Http::fake(['api.mymemory.translated.net/*' => ...])`.
