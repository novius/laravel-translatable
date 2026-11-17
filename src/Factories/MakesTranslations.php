<?php

namespace Novius\LaravelTranslatable\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * For the factory of a translatable model (trait Translatable, and HasCommonRecord or not).
 *
 * @mixin Factory<Model>
 */
trait MakesTranslations
{
    /**
     * A translation of the given model in another locale, in its group of translations, and sharing its common record
     * with HasCommonRecord: the one given by the definition, if any, would be refused.
     */
    public function translationOf(Model $parent, string $locale): static
    {
        if (! method_exists($parent, 'translatableConfig')) {
            throw new InvalidArgumentException(sprintf('%s must use the Translatable trait.', $parent::class));
        }
        $config = $parent->translatableConfig();
        $state = [
            $config->locale_column => $locale,
            $config->locale_parent_id_column => $parent->getKey(),
        ];

        if (method_exists($parent, 'commonRecordKeyName')) {
            $foreignKey = $parent->commonRecordKeyName();
            $state[$foreignKey] = $parent->getAttribute($foreignKey);
        }

        return $this->state($state);
    }
}
