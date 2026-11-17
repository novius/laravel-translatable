<?php

namespace Novius\LaravelTranslatable\Traits;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Novius\LaravelTranslatable\Exceptions\TranslatableException;

/**
 * For the common record of a translatable model (`ProductCommon`): the data shared by all the locales, while each
 * translation (`Product`, with Translatable and HasCommonRecord) holds the texts of its locale.
 *
 * A common record holding translations, soft deleted included, cannot be deleted: delete its translations, the last
 * one deletes it.
 *
 * @template TTranslation of Model
 *
 * @mixin Model
 */
trait IsCommonRecord
{
    /**
     * @throws TranslatableException
     */
    public static function bootIsCommonRecord(): void
    {
        static::deleting(static function (Model $commonRecord) {
            /** @var static $commonRecord */
            // Not cascading: the translations would be deleted without their own checks, or would delete it again
            if ($commonRecord->translations()->withoutGlobalScope(SoftDeletingScope::class)->exists()) {
                throw new TranslatableException(trans('translatable::messages.common_record_has_translations'));
            }
        });
    }

    /**
     * The translations of the common record, one per locale.
     *
     * @return HasMany<TTranslation, $this>
     */
    abstract public function translations(): HasMany;

    /**
     * The attribute of the translations that labels the common record (`title`, `name`…). Not guessed: the package does
     * not create it, and a missing attribute would silently label every common record by its key.
     */
    abstract public function commonRecordLabelAttribute(): string;

    /**
     * The translation in the locale, read in the translations relation (loaded by the first call).
     *
     * @return TTranslation|null
     */
    public function translation(string $locale): ?Model
    {
        // The translations use Translatable (and HasCommonRecord)
        $localeColumn = $this->translations()->getRelated()->translatableConfig()->locale_column;

        /** @var Collection<int, TTranslation> $translations */
        $translations = $this->loadMissing('translations')->getRelation('translations');

        return $translations->firstWhere($localeColumn, $locale);
    }

    /**
     * The label of the common record: the one of its translation in the current locale, else in the fallback locale of
     * the application, else of any translation, else its key.
     */
    public function label(): string
    {
        $fallbackLocale = config('app.fallback_locale');
        $translation = $this->translation(app()->getLocale())
            ?? (is_string($fallbackLocale) ? $this->translation($fallbackLocale) : null)
            ?? $this->getRelation('translations')->first();
        $label = $translation?->getAttribute($this->commonRecordLabelAttribute());

        return is_scalar($label) && (string) $label !== '' ? (string) $label : '#'.$this->getKey();
    }
}
