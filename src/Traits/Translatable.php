<?php

namespace Novius\LaravelTranslatable\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Novius\LaravelTranslatable\Exceptions\TranslatableException;
use Novius\LaravelTranslatable\Support\TranslatableModelConfig;
use Throwable;

/**
 * @property-read Collection<int, static> $translations
 * @property-read Collection<int, static> $translationsWithDeleted
 *
 * @mixin Model
 * @mixin SoftDeletes
 */
trait Translatable
{
    protected ?Model $parentToSave = null;

    /**
     * Boot the translatable trait for a model.
     *
     * @throws TranslatableException
     */
    public static function bootTranslatable(): void
    {
        static::creating(static function (Model $model) {
            /** @var static $model */
            $localeColumn = $model->translatableConfig()->locale_column;
            $localeParentIdColumn = $model->translatableConfig()->locale_parent_id_column;
            $locale = $model->{$localeColumn};
            $locale_parent_id = $model->{$localeParentIdColumn};

            if (! in_array($locale, $model->translatableConfig()->available_locales, true)) {
                throw new TranslatableException(trans('translatable::messages.locale_forbidden'));
            }

            if ($locale_parent_id) {
                $parent = $model::find($locale_parent_id);
                if ($parent === null) {
                    $model->{$localeParentIdColumn} = null;
                } else {
                    $translation = $model->getTranslation($locale, true);
                    if ($translation !== null) {
                        if (in_array(SoftDeletes::class, class_uses_recursive($model), true) && $translation->{$model->getDeletedAtColumn()}) {
                            throw new TranslatableException(trans('translatable::messages.translation_deleted_exist'));
                        }
                        throw new TranslatableException(trans('translatable::messages.already_translated'));
                    }

                    $model->{$localeParentIdColumn} = $parent->{$localeParentIdColumn} ?? $parent->{$model->getKeyName()};

                    if (empty($parent->{$localeParentIdColumn})) {
                        $model->parentToSave = $parent;
                    }

                    $model->translateAttributes($parent);
                }
            }
        });
        static::created(static function (Model $model) {
            /** @var static $model */
            $localeParentIdColumn = $model->translatableConfig()->locale_parent_id_column;

            if ($model->parentToSave !== null) {
                $model->parentToSave->{$localeParentIdColumn} = $model->{$localeParentIdColumn};
                if (! $model->parentToSave->save()) {
                    throw new TranslatableException(trans('translatable::messages.error_during_translation'));
                }
            }
        });
    }

    public function translatableConfig(): TranslatableModelConfig
    {
        return new TranslatableModelConfig;
    }

    public function translations(): HasMany
    {
        return $this->hasMany(static::class, $this->translatableConfig()->locale_parent_id_column, $this->translatableConfig()->locale_parent_id_column);
    }

    public function translationsWithDeleted(): HasMany
    {
        if (in_array(SoftDeletes::class, class_uses_recursive($this), true)) {
            return $this->hasMany(static::class, $this->translatableConfig()->locale_parent_id_column, $this->translatableConfig()->locale_parent_id_column)->withoutGlobalScope(SoftDeletingScope::class);
        }

        return $this->translations();
    }

    public function scopeWithLocale(Builder $query, ?string $locale): Builder
    {
        return $query->where($this->qualifyColumn($this->translatableConfig()->locale_column), $locale);
    }

    /**
     * Only the original models: those not translated, and the root of each group of translations. Once translated,
     * the root holds its own key in the locale parent id column, like its translations.
     */
    public function scopeOriginals(Builder $query): Builder
    {
        $localeParentIdColumn = $this->qualifyColumn($this->translatableConfig()->locale_parent_id_column);

        return $query->where(fn (Builder $query) => $query
            ->whereNull($localeParentIdColumn)
            ->orWhereColumn($localeParentIdColumn, $this->getQualifiedKeyName()));
    }

    /**
     * Whether the model is an original: not translated, or the root of its group of translations.
     */
    public function isOriginal(): bool
    {
        $localeParentId = $this->{$this->translatableConfig()->locale_parent_id_column};

        // The key is cast by Eloquent, the locale parent id is not: compare them as strings, whatever the driver returns
        return $localeParentId === null || (string) $localeParentId === (string) $this->getKey();
    }

    /**
     * @throws TranslatableException
     * @throws Throwable
     */
    public function translate(string $locale, array $translateAttributes = []): static
    {
        $localeColumn = $this->translatableConfig()->locale_column;
        $localeParentIdColumn = $this->translatableConfig()->locale_parent_id_column;

        if (! empty($this->{$localeParentIdColumn})) {
            $translation = $this->getTranslation($locale);
            if ($translation !== null) {
                if (in_array(SoftDeletes::class, class_uses_recursive($this), true) && $translation->{$this->getDeletedAtColumn()}) {
                    throw new TranslatableException(trans('translatable::messages.translation_deleted_exist'));
                }
                throw new TranslatableException(trans('translatable::messages.already_translated'));
            }
        } elseif ($this->{$localeColumn} === $locale) {
            throw new TranslatableException(trans('translatable::messages.already_translated'));
        }

        $localeParentId = $this->{$localeParentIdColumn} ?? $this->{$this->getKeyName()};

        $translatedItem = $this->replicate();
        $translatedItem->translateAttributes($this);
        foreach ($translateAttributes as $translateAttribute => $value) {
            $translatedItem->{$translateAttribute} = $value;
        }
        $translatedItem->{$localeColumn} = $locale;
        $translatedItem->{$localeParentIdColumn} = $localeParentId;

        // Rolled back on any exception, including those thrown by the creating and created events
        $this->getConnection()->transaction(function () use ($translatedItem, $localeParentIdColumn, $localeParentId) {
            if (! $translatedItem->save()) {
                throw new TranslatableException(trans('translatable::messages.error_during_translation'));
            }

            if (empty($this->{$localeParentIdColumn})) {
                $this->{$localeParentIdColumn} = $localeParentId;
                if (! $this->save()) {
                    throw new TranslatableException(trans('translatable::messages.error_during_translation'));
                }
            }
        });

        // The group of translations changed: relations loaded before (and copied by replicate()) are out of date
        foreach ([$this, $translatedItem] as $model) {
            $model->unsetRelation('translations');
            $model->unsetRelation('translationsWithDeleted');
        }

        return $translatedItem;
    }

    public function getTranslation(string $locale, bool $withDeleted = false): ?static
    {
        if ($this->{$this->translatableConfig()->locale_parent_id_column} === null) {
            $translations = new Collection([$this]);
        } else {
            $translations = $withDeleted ? $this->translationsWithDeleted() : $this->translations();
        }

        // @phpstan-ignore-next-line
        return $translations->where($this->translatableConfig()->locale_column, $locale)->first();
    }

    /**
     * @deprecated
     */
    public function getLocaleColumn(): string
    {
        trigger_error('getLocaleColumn() is deprecated since version 1.2 and will be removed in 2.0. Use translatableConfig()->locale_column instead.', E_USER_DEPRECATED);

        return $this->translatableConfig()->locale_column;
    }

    /**
     * @deprecated
     */
    public function getLocaleParentIdColumn(): string
    {
        trigger_error('getLocaleParentIdColumn() is deprecated since version 1.2 and will be removed in 2.0. Use translatableConfig()->locale_parent_id_column instead.', E_USER_DEPRECATED);

        return $this->translatableConfig()->locale_parent_id_column;
    }

    protected function translateAttributes($parent): void
    {
        // To be implemented if you want to translate certain attributes
    }
}
