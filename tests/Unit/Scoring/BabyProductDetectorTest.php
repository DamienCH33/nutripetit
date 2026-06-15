<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scoring;

use App\Entity\Product;
use App\Service\Scoring\BabyProductDetector;
use PHPUnit\Framework\TestCase;

final class BabyProductDetectorTest extends TestCase
{
    private BabyProductDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new BabyProductDetector();
    }

    public function testDetectsByEnglishCategoryTag(): void
    {
        $product = new Product('1000000000001', 'Petit pot carottes')
            ->setOffRawData(['categories_tags' => ['en:baby-foods']]);

        self::assertTrue($this->detector->isBabyProduct($product));
    }

    public function testDetectsByFrenchCategoryTag(): void
    {
        // Cas Hipp Crousti Plaisir : tag FR fr:gateaux-pour-bebe.
        $product = new Product('4062300462267', 'Crousti Plaisir')
            ->setOffRawData(['categories_tags' => ['en:biscuits', 'fr:gateaux-pour-bebe']]);

        self::assertTrue($this->detector->isBabyProduct($product));
    }

    public function testDetectsByBabyBrand(): void
    {
        // Cas Hipp : marque 100% bébé, même sans tag bébé.
        $product = new Product('4062300462267', 'Crousti Plaisir')
            ->setOffRawData(['categories_tags' => ['en:biscuits'], 'brands_tags' => ['Hipp']]);

        self::assertTrue($this->detector->isBabyProduct($product));
    }

    public function testDetectsBledinaGrowingUpByBrandAndName(): void
    {
        // Cas Bledidej croissance : catégories vides, marque Blédina + "croissance".
        $product = new Product('3041091616890', 'Bledidej croissance')
            ->setOffRawData(['categories_tags' => [], 'brands_tags' => ['Blédina']]);

        self::assertTrue($this->detector->isBabyProduct($product));
    }

    public function testDoesNotDetectGenericBrand(): void
    {
        // Nectar Nestlé : pas de tag bébé, marque généraliste -> refusé (pas de faux positif).
        $product = new Product('7613036760881', 'NATURNES BIO boisson poire abricot')
            ->setOffRawData(['categories_tags' => ['en:fruit-nectars', 'en:beverages'], 'brands_tags' => ['nestle']]);

        self::assertFalse($this->detector->isBabyProduct($product));
    }

    public function testDoesNotDetectRegularProduct(): void
    {
        $product = new Product('1000000000002', 'Chips paprika')
            ->setOffRawData(['categories_tags' => ['en:chips'], 'brands_tags' => ['lays']]);

        self::assertFalse($this->detector->isBabyProduct($product));
    }
}
