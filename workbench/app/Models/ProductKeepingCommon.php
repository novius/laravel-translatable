<?php

namespace Workbench\App\Models;

/**
 * A product whose common record is kept when its last translation is deleted.
 */
class ProductKeepingCommon extends Product
{
    public function deletesCommonRecordWithLastTranslation(): bool
    {
        return false;
    }
}
