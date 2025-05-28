# [2.0.0]

Introduction of the `translatableConfig` method on the `Translatable` trait. 
The trait no longer detects the name of the `locale` and `local_parent_id` columns from `LOCALE` and `LOCAL_PARENT_ID` constants.
The trait no longer contains `getLocaleColumn()`, `getLocaleParentIdColumn()`, `getQualifiedLocaleColumn()`, `getQualifiedLocaleParentIdColumn()` methods.
