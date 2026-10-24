<?php

declare(strict_types=1);

namespace JeffersonGoncalves\AutoTranslate;

use JeffersonGoncalves\AutoTranslate\Contracts\Translator;
use JeffersonGoncalves\AutoTranslate\Translators\MyMemoryTranslator;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class AutoTranslateServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-auto-translate')
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        // bindIf: an app binding its own Translator (DeepL, LibreTranslate…) wins.
        $this->app->bindIf(Translator::class, MyMemoryTranslator::class);
    }
}
