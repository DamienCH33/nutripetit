<?php

declare(strict_types=1);

namespace App\Tests\Unit\Product;

use App\Entity\Product;
use App\Service\Product\PalmOilDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PalmOilDetectorTest extends TestCase
{
    private PalmOilDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new PalmOilDetector();
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function ingredientsProvider(): array
    {
        return [
            'huile de palme' => ['Sucre, huile de palme, noisettes', true],
            'huile de palmiste' => ['Farine, huile de palmiste', true],
            'graisse de palme' => ['Farine, graisse de palme', true],
            'mention entre parenthèses' => ['Huiles végétales (palme, colza), lactose', true],
            'anglais' => ['Sugar, palm oil', true],
            'anglais palmiste' => ['Sugar, palm kernel oil', true],
            'sans palme' => ['Pommes, huile de colza', false],
            'palmitate (vitamine)' => ['Lactose, palmitate d\'ascorbyle, palmitate de rétinyle', false],
            'mention négative' => ['Pommes, poires. Sans huile de palme', false],
        ];
    }

    #[DataProvider('ingredientsProvider')]
    public function testContainsPalmOil(string $ingredients, bool $expected): void
    {
        $product = new Product('3000000000400', 'Produit')->setIngredientsRaw($ingredients);

        self::assertSame($expected, $this->detector->containsPalmOil($product));
    }

    public function testUnknownWithoutIngredients(): void
    {
        self::assertNull($this->detector->containsPalmOil(new Product('3000000000401', 'Produit')));
        self::assertNull($this->detector->containsPalmOil(
            new Product('3000000000402', 'Produit')->setIngredientsRaw('   '),
        ));
    }

    public function testFirstIngredient(): void
    {
        $first = new Product('3000000000403', 'Lait')->setIngredientsRaw('Huile de palme, lactose');
        $later = new Product('3000000000404', 'Lait')->setIngredientsRaw('Lactose, huile de palme');
        $none = new Product('3000000000405', 'Lait');

        self::assertTrue($this->detector->isFirstIngredient($first));
        self::assertFalse($this->detector->isFirstIngredient($later));
        self::assertFalse($this->detector->isFirstIngredient($none));
    }
}
