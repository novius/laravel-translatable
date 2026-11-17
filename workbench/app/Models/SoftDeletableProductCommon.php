<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Novius\LaravelTranslatable\Traits\IsCommonRecord;

/**
 * The data of a soft deletable product shared by all the locales.
 *
 * @property int $id
 */
class SoftDeletableProductCommon extends Model
{
    /** @use IsCommonRecord<SoftDeletableProduct> */
    use IsCommonRecord;

    protected $table = 'product_commons';

    protected $guarded = [];

    /**
     * @return HasMany<SoftDeletableProduct, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(SoftDeletableProduct::class, 'product_common_id');
    }

    public function commonRecordLabelAttribute(): string
    {
        return 'title';
    }
}
