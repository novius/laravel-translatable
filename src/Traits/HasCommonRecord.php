<?php

namespace Novius\LaravelTranslatable\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Novius\LaravelTranslatable\Exceptions\TranslatableException;

/**
 * For a translatable model whose data shared by all the locales is held by a common record: one row per locale for
 * the texts (`Product`, with Translatable), one common row for the rest (`ProductCommon`, with IsCommonRecord).
 *
 * - A translation created with the key of its parent in the locale parent id column, and without common record, gets
 *   the common record of its parent. `translate()` keeps it: `replicate()` copies the foreign key.
 * - An original created without common record gets the one returned by `createCommonRecord()`.
 * - All the translations of a group share the same common record, and a common record belongs to one group only: a
 *   model saved otherwise is refused. A group without common record (data prior to the trait) is accepted.
 * - The common record is deleted when no model holds it any more: deleted with its last translation (for a soft
 *   deletable model, when the last one is force deleted), or left by a model moved to another common record, unless
 *   `deletesCommonRecordWithLastTranslation()` returns false.
 *
 * @template TCommonRecord of Model
 *
 * @mixin Model
 * @mixin Translatable
 */
trait HasCommonRecord
{
    /**
     * The common record of the translation.
     *
     * @return BelongsTo<TCommonRecord, $this>
     */
    abstract public function commonRecord(): BelongsTo;

    /**
     * The common record of an original created without one, saved here if it is not yet: fill the required columns of
     * the common table, as `return new ProductCommon(['position' => 0]);`. A form saving the common record afterwards
     * (a Filament `relationship()`, for instance) updates this one.
     *
     * @return TCommonRecord
     */
    abstract protected function createCommonRecord(): Model;

    /**
     * The name of the foreign key to the common record.
     */
    public function commonRecordKeyName(): string
    {
        return $this->commonRecord()->getForeignKeyName();
    }

    /**
     * Whether the common record is deleted with the last of its translations.
     */
    public function deletesCommonRecordWithLastTranslation(): bool
    {
        return true;
    }

    /**
     * @throws TranslatableException
     */
    public static function bootHasCommonRecord(): void
    {
        // On creating, Translatable calls prepareCommonRecordOnCreating() once the group is resolved

        static::updating(static function (Model $translation) {
            /** @var static $translation */
            if ($translation->isDirty([$translation->commonRecordKeyName(), $translation->translatableConfig()->locale_parent_id_column])) {
                $translation->ensureCommonRecordOfItsGroup();
            }
        });

        static::updated(static function (Model $translation) {
            /** @var static $translation */
            $foreignKey = $translation->commonRecordKeyName();
            if ($translation->wasChanged($foreignKey)) {
                // The original attributes are synced after the updated event: they still hold the former common record
                $translation->deleteCommonRecordIfUnused($translation->getOriginal($foreignKey));
            }
        });

        static::deleted(static function (Model $translation) {
            /** @var static $translation */
            // A soft deleted translation can be restored: it keeps its common record
            if (method_exists($translation, 'isForceDeleting') && ! $translation->isForceDeleting()) {
                return;
            }

            $translation->deleteCommonRecordIfUnused($translation->{$translation->commonRecordKeyName()});
        });
    }

    /**
     * Called by the creating listener of Translatable, once the group of the new model is resolved: the parent found,
     * null when not given, not found or soft deleted (the model is then not a translation).
     *
     * @throws TranslatableException
     */
    protected function prepareCommonRecordOnCreating(?Model $parent): void
    {
        $relation = $this->commonRecord();
        $foreignKey = $relation->getForeignKeyName();
        if ($this->{$foreignKey} === null) {
            if ($parent !== null) {
                // Null for a parent without common record (data prior to the trait): the group keeps none
                $this->{$foreignKey} = $parent->{$foreignKey};
            } else {
                $commonRecord = $this->createCommonRecord();
                if (! $commonRecord->exists) {
                    $commonRecord->save();
                }
                $this->{$foreignKey} = $commonRecord->getAttribute($relation->getOwnerKeyName());
                $this->setRelation('commonRecord', $commonRecord);
            }
        }

        $this->ensureCommonRecordOfItsGroup();
    }

    /**
     * Deletes the common record no model holds any more, soft deleted included, unless the model keeps it.
     */
    protected function deleteCommonRecordIfUnused(mixed $commonId): void
    {
        $foreignKey = $this->commonRecordKeyName();
        if ($commonId === null
            || ! $this->deletesCommonRecordWithLastTranslation()
            || $this->queryWithTrashed()->where($foreignKey, $commonId)->exists()) {
            return;
        }

        // By the owner key of the relationship, which may not be the primary key of the common record
        $relation = $this->commonRecord();
        $relation->getRelated()->newQuery()->where($relation->getOwnerKeyName(), $commonId)->first()?->delete();
    }

    /**
     * Checks, before the model is saved, that its group of translations shares its common record (or none), and that
     * no model of another group holds it.
     *
     * @throws TranslatableException
     */
    protected function ensureCommonRecordOfItsGroup(): void
    {
        $foreignKey = $this->commonRecordKeyName();
        $commonId = $this->{$foreignKey};
        $groupId = $this->{$this->translatableConfig()->locale_parent_id_column};

        if ($groupId !== null) {
            $otherCommonRecord = $this->groupQuery($groupId)
                ->when($this->exists, fn (Builder $query) => $query->whereKeyNot($this->getKey()))
                ->where(fn (Builder $query) => $commonId === null
                    ? $query->whereNotNull($foreignKey)
                    : $query->whereNull($foreignKey)->orWhere($foreignKey, '!=', $commonId))
                ->exists();

            if ($otherCommonRecord) {
                throw new TranslatableException(trans('translatable::messages.common_record_mismatch'));
            }
        }

        if ($commonId !== null && $this->isCommonRecordUsedOutsideItsGroup($commonId)) {
            throw new TranslatableException(trans('translatable::messages.common_record_shared'));
        }
    }

    /**
     * Whether a model out of the group of this one, soft deleted included, holds the common record.
     */
    protected function isCommonRecordUsedOutsideItsGroup(mixed $commonId): bool
    {
        $localeParentIdColumn = $this->translatableConfig()->locale_parent_id_column;
        $groupId = $this->{$localeParentIdColumn};

        return $this->queryWithTrashed()
            ->where($this->commonRecordKeyName(), $commonId)
            ->when($this->exists, fn (Builder $query) => $query->whereKeyNot($this->getKey()))
            // Not `NOT (… OR …)`: a null locale parent id would make the condition null, and hide the model
            ->when($groupId !== null, fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query
                    ->whereNull($localeParentIdColumn)
                    ->orWhere($localeParentIdColumn, '!=', $groupId))
                ->where($this->getKeyName(), '!=', $groupId))
            ->exists();
    }

    /**
     * A query on the models of a group of translations, soft deleted included: its translations, and its root, which
     * does not hold its own key in the locale parent id column until it is translated.
     */
    protected function groupQuery(mixed $groupId): Builder
    {
        return $this->queryWithTrashed()->where(fn (Builder $query) => $query
            ->where($this->translatableConfig()->locale_parent_id_column, $groupId)
            ->orWhere($this->getKeyName(), $groupId));
    }

    /**
     * A query on the translations, soft deleted included.
     */
    protected function queryWithTrashed(): Builder
    {
        $query = static::query();
        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            $query->withoutGlobalScope(SoftDeletingScope::class);
        }

        return $query;
    }
}
