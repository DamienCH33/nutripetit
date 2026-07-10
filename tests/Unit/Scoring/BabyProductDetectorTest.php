<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scoring;

use App\Entity\Product;
use App\Service\Scoring\BabyProductDetector;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * Régression : les sous-chaînes ' ar'/'ac '/'ha ' et les mots-clés trop
     * génériques (bifidus, sans lactose, hydrolysé…) faisaient passer des
     * produits adultes pour des produits bébé.
     *
     * @return iterable<string, array{string}>
     */
    public static function adultProductNames(): iterable
    {
        yield 'artisanal (ex-match " ar")' => ['Yaourt nature artisanal'];
        yield 'nectar (ex-match "ar ")' => ['Nectar de poire bio'];
        yield 'acacia (ex-match " ac")' => ['Miel d\'acacia de France'];
        yield 'matcha (ex-match "ha ")' => ['Matcha latte prêt à boire'];
        yield 'sans lactose adulte' => ['Lait demi-écrémé sans lactose'];
        yield 'bifidus adulte' => ['Yaourt bifidus nature'];
        yield 'transit adulte' => ['Yaourt aux fibres spécial transit'];
        yield 'whey hydrolysée' => ['Whey protéine hydrolysée vanille'];
        yield 'BCAA sport' => ['Boisson acides aminés BCAA citron'];
        yield 'formula générique' => ['Formula 1 shake fraise'];
        yield 'croissance hors lait' => ['Engrais croissance plantes vertes'];
    }

    #[DataProvider('adultProductNames')]
    public function testDoesNotDetectAdultProductsByName(string $name): void
    {
        $product = new Product('1000000000003', $name)
            ->setOffRawData(['categories_tags' => [], 'brands_tags' => []]);

        self::assertFalse($this->detector->isBabyProduct($product));
    }

    /**
     * Les vrais produits bébé restent détectés après le durcissement.
     *
     * @return iterable<string, array{string}>
     */
    public static function babyProductNames(): iterable
    {
        yield 'acronyme AR isolé (token)' => ['Candia AR'];
        yield 'acronyme HA isolé (token)' => ['Novalait HA'];
        yield 'lait de croissance' => ['Lait de croissance vanille'];
        yield 'lait sans lactose bébé' => ['Lait sans lactose 2ème âge'];
        yield 'préparation pour nourrissons' => ['Préparation pour nourrissons bio'];
        yield 'infant formula EN' => ['Organic infant formula stage 1'];
    }

    #[DataProvider('babyProductNames')]
    public function testStillDetectsRealBabyProductsByName(string $name): void
    {
        $product = new Product('1000000000004', $name)
            ->setOffRawData(['categories_tags' => [], 'brands_tags' => []]);

        self::assertTrue($this->detector->isBabyProduct($product));
    }
}
