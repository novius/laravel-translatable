<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Novius\LaravelTranslatable\Support\TranslatableModelConfig;
use Novius\LaravelTranslatable\Traits\Translatable;

class CustomColumnsTranslatableModel extends Model
{
    use Translatable;

    protected $table = 'custom_columns_translatable_models';

    protected $guarded = [];

    public function translatableConfig(): TranslatableModelConfig
    {
        return new TranslatableModelConfig(
            locale_column: 'lang',
            locale_parent_id_column: 'lang_parent_id',
        );
    }
}
