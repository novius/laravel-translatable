# [Unreleased]

Add the `originals()` scope and the `isOriginal()` method on the `Translatable` trait: the original models are those
not translated and the root of each group of translations, which holds its own key in the locale parent id column once
translated.
`translate()` now unloads the `translations` and `translationsWithDeleted` relations of the model and of its new
translation: loaded before, they no longer showed the group of translations.
`translate()` now rolls back its transaction on any exception, including those thrown by the `creating` event (a locale
not available, for example): the transaction was left open.
The `withLocale()` scope now uses the locale column of `translatableConfig()`, qualified with the table name: it used
an unqualified `locale` column.

# [2.0.0]

Introduction of the `translatableConfig` method on the `Translatable` trait. 
The trait no longer detects the name of the `locale` and `local_parent_id` columns from `LOCALE` and `LOCAL_PARENT_ID` constants.
The trait no longer contains `getLocaleColumn()`, `getLocaleParentIdColumn()`, `getQualifiedLocaleColumn()`, `getQualifiedLocaleParentIdColumn()` methods.
