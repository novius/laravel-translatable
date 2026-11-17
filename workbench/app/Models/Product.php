<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Novius\LaravelTranslatable\Traits\HasCommonRecord;
use Novius\LaravelTranslatable\Traits\Translatable;
use Workbench\Database\Factories\ProductFactory;

/**
 * A product in one locale, its data shared by all the locales in ProductCommon.
 *
 * @property int $id
 * @property string $title
 * @property string $locale
 * @property int|null $locale_parent_id
 * @property int|null $product_common_id
 */
class Product extends Model
{
    /** @use HasCommonRecord<ProductCommon> */
    use HasCommonRecord;

    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    use Translatable;

    protected $table = 'products';

    protected $guarded = [];

    /**
     * @return BelongsTo<ProductCommon, $this>
     */
    public function commonRecord(): BelongsTo
    {
        return $this->belongsTo(ProductCommon::class, 'product_common_id');
    }

    protected function createCommonRecord(): Model
    {
        return new ProductCommon;
    }

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }
}
