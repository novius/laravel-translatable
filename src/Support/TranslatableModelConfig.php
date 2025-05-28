<?php

namespace Novius\LaravelTranslatable\Support;

use LaravelLang\Locales\Facades\Locales;

class TranslatableModelConfig
{
    /**
     * @var array<string>
     */
    public mixed $available_locales;

    public function __construct(
        ?array $available_locales = null,
        public string $locale_column = 'locale',
        public string $locale_parent_id_column = 'locale_parent_id'
    ) {
        $this->available_locales = $available_locales ?? Locales::available()->pluck('code')->toArray();
    }
}
