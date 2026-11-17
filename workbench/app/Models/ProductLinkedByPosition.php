<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A product whose common record is linked by an owner key other than its primary key: its position.
 */
class ProductLinkedByPosition extends Product
{
    /**
     * @return BelongsTo<ProductCommon, $this>
     */
    public function commonRecord(): BelongsTo
    {
        return $this->belongsTo(ProductCommon::class, 'product_common_id', 'position');
    }
}
