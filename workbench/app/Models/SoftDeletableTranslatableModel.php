<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Novius\LaravelTranslatable\Traits\Translatable;

class SoftDeletableTranslatableModel extends Model
{
    use SoftDeletes;
    use Translatable;

    protected $table = 'soft_deletable_translatable_models';

    protected $guarded = [];
}
