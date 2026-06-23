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
        $product->setBrand($this->plainTextOrNull($dto->brand));
        $product->setImageUrl($dto->imageUrl);
        $product->setIngredientsRaw($this->plainTextOrNull($dto->ingredientsRaw));
        $product->setNutriments($dto->nutriments);
        $product->setAllergens($dto->allergens);
        $product->setAdditives($dto->additives);
        $product->setOffRawData($dto->rawData);

        $this->em->persist($product);
        $this->em->flush();

        return $product;
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
