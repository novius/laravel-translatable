<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Novius\LaravelTranslatable\Traits\Translatable;
use Workbench\Database\Factories\TranslatableModelFactory;

class TranslatableModel extends Model
{
    use HasFactory;
    use HasTimestamps;
    use Translatable;

    protected $table = 'translatable_models';

    protected $guarded = [];

    public static function availableLocales(): array
    {
        return [
            'fr' => 'Français',
            'en' => 'English',
        ];
    }

    protected static function newFactory(): TranslatableModelFactory
    {
        return TranslatableModelFactory::new();
    }
}
