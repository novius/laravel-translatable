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
        return $query->where('locale', $locale);
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

        $this->getConnection()->beginTransaction();

        if (! $translatedItem->save()) {
            $this->getConnection()->rollBack();
            throw new TranslatableException(trans('translatable::messages.error_during_translation'));
        }

        if (empty($this->{$localeParentIdColumn})) {
            $this->{$localeParentIdColumn} = $localeParentId;
            if (! $this->save()) {
                $this->getConnection()->rollBack();
                throw new TranslatableException(trans('translatable::messages.error_during_translation'));
            }
        }

        $this->getConnection()->commit();

        return $translatedItem;
    }

    public function getTranslation(string $locale, bool $withDeleted = false): ?static
    {
        if ($this->{$this->translatableConfig()->locale_parent_id_column} === null) {
            $translations = new Collection([$this]);
        } else {
            $translations = $withDeleted ? $this->translationsWithDeleted() : $this->translations();
        }

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
