<?php

namespace Novius\LaravelTranslatable\Tests\Models;

use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Novius\LaravelTranslatable\Traits\Translatable;

class TranslatableModel extends Model
{
    use HasFactory;
    use HasTimestamps;
    use Translatable;

    protected $table = 'translatable_models';

    protected $guarded = [];
}
