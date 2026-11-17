<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Novius\LaravelTranslatable\Support\TranslatableModelConfig;
use Novius\LaravelTranslatable\Traits\HasCommonRecord;
use Novius\LaravelTranslatable\Traits\Translatable;

/**
 * A soft deletable product in one locale, its data shared by all the locales in ProductCommon. HasCommonRecord is used
 * before Translatable, as Pint orders them.
 */
class SoftDeletableProduct extends Model
{
    /** @use HasCommonRecord<SoftDeletableProductCommon> */
    use HasCommonRecord;

    use SoftDeletes;
    use Translatable;

    protected $table = 'soft_deletable_products';

    protected $guarded = [];

    public function translatableConfig(): TranslatableModelConfig
    {
        // Three locales: a translation can be made from a translation
        return new TranslatableModelConfig(available_locales: ['fr', 'en', 'es']);
    }

    /**
     * @return BelongsTo<SoftDeletableProductCommon, $this>
     */
    public function commonRecord(): BelongsTo
    {
        return $this->belongsTo(SoftDeletableProductCommon::class, 'product_common_id');
    }

    protected function createCommonRecord(): Model
    {
        return new SoftDeletableProductCommon;
    }
}
