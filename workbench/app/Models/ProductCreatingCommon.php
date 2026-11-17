<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A product creating the common record of its original saved already: Product returns it not saved.
 */
class ProductCreatingCommon extends Product
{
    protected function createCommonRecord(): Model
    {
        return ProductCommon::query()->create(['position' => 1]);
    }
}
