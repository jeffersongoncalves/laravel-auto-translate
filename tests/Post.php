<?php

namespace JeffersonGoncalves\AutoTranslate\Tests;

use Illuminate\Database\Eloquent\Model;
use JeffersonGoncalves\AutoTranslate\Contracts\AutoTranslatable;
use Spatie\Translatable\HasTranslations;

class Post extends Model implements AutoTranslatable
{
    use HasTranslations;

    public $timestamps = false;

    protected $guarded = [];

    /** @var list<string> */
    public array $translatable = ['title'];

    public function autoTranslated(string $attribute, string $source): void
    {
        $this->title_hash = sha1($source);
    }
}
