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
        $product = new Product($dto->ean, $dto->name);
        $product->setBrand($dto->brand);
        $product->setImageUrl($dto->imageUrl);
        $product->setIngredientsRaw($dto->ingredientsRaw);
        $product->setNutriments($dto->nutriments);
        $product->setAllergens($dto->allergens);
        $product->setAdditives($dto->additives);
        $product->setOffRawData($dto->rawData);

        $this->em->persist($product);
        $this->em->flush();

        return $product;
    }
}
