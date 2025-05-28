# [2.0.0]

Le trait `Translatable` oblige maintenant à définir la méthode `availableLocales`. Elle doit renvoyer un tableau des locales autorisées sous la forme :
```php

class MyModel extends \Illuminate\Database\Eloquent\Model
{
    public static function availableLocales(): array {
        return [
            'fr' => 'Français',
            'en' => 'English',        
        ];       
    }
}
```
