<?php

namespace Workbench\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Novius\LaravelTranslatable\Factories\MakesTranslations;
use Workbench\App\Models\Product;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    use MakesTranslations;

    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'title' => 'Produit',
            // The model creates the common record of the original: translationOf() gives the one of the parent
            'locale' => 'fr',
        ];
    }
}
