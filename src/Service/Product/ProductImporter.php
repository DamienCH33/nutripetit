<?php

declare(strict_types=1);

namespace App\Service\Product;

use App\Dto\ProductDto;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;

final class ProductImporter
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function createProductFromDto(ProductDto $dto): Product
    {
        $product = new Product($dto->ean, $this->plainText($dto->name));
        $this->applyDto($product, $dto);

        $this->em->persist($product);
        $this->em->flush();

        return $product;
    }

    /**
     * Rafraîchit un produit existant avec des données OFF plus récentes
     * (reformulations industrielles) et horodate le re-fetch.
     */
    public function updateProductFromDto(Product $product, ProductDto $dto): Product
    {
        $product->setName($this->plainText($dto->name));
        $this->applyDto($product, $dto);
        $product->refreshFetchedAt();

        $this->em->flush();

        return $product;
    }

    private function applyDto(Product $product, ProductDto $dto): void
    {
        $product->setBrand($this->plainTextOrNull($dto->brand));
        $product->setImageUrl($dto->imageUrl);
        $product->setIngredientsRaw($this->plainTextOrNull($dto->ingredientsRaw));
        $product->setNutriments($dto->nutriments);
        $product->setAllergens($dto->allergens);
        $product->setAdditives($dto->additives);
        $product->setOffRawData($dto->rawData);
    }

    /**
     * Nettoie une valeur texte obligatoire issue d'une source externe :
     * suppression des balises HTML et des espaces superflus.
     */
    private function plainText(string $value): string
    {
        return trim(strip_tags($value));
    }

    /**
     * Variante nullable : retourne null si l'entrée est null.
     */
    private function plainTextOrNull(?string $value): ?string
    {
        return null === $value ? null : $this->plainText($value);
    }
}
