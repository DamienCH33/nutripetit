<?php

declare(strict_types=1);

namespace App\Dto;

use InvalidArgumentException;

/**
 * DTO immutable représentant un produit alimentaire issu d'OpenFoodFacts.
 *
 * Joue le rôle de tampon entre l'API externe et le modèle interne (Product entité).
 * Si OFF change son schéma, seule la méthode fromOff() doit être adaptée.
 */
final readonly class ProductDto
{
    /**
     * @param array<string, mixed> $nutriments
     * @param list<string> $allergens
     * @param list<string> $additives
     * @param list<string> $categories
     * @param list<string> $labels
     * @param array<string, mixed> $rawData
     */
    public function __construct(
        public string $ean,
        public string $name,
        public ?string $brand,
        public ?string $imageUrl,
        public ?string $ingredientsRaw,
        public array $nutriments,
        public array $allergens,
        public array $additives,
        public array $categories,
        public array $labels,
        public array $rawData,
    ) {
    }

    /**
     * Construit un ProductDto depuis une réponse brute OpenFoodFacts.
     *
     * @param array<string, mixed> $rawData Le JSON décodé d'OpenFoodFacts
     *
     * @throws InvalidArgumentException Si le champ "code" est absent ou vide
     */
    public static function fromOff(array $rawData): self
    {
        $product = $rawData['product'] ?? [];
        if (!\is_array($product)) {
            $product = [];
        }

        $ean = trim((string) ($product['code'] ?? $rawData['code'] ?? ''));
        if ('' === $ean) {
            throw new InvalidArgumentException('EAN is missing in OFF response');
        }

        $name = $product['product_name_fr'] ?? $product['product_name'] ?? 'Sans nom';
        if (!\is_string($name) || '' === trim($name)) {
            $name = 'Sans nom';
        }

        $brand = $product['brands'] ?? null;
        $brand = \is_string($brand) && '' !== trim($brand) ? $brand : null;

        $imageUrl = $product['image_front_url'] ?? null;
        $imageUrl = \is_string($imageUrl) && '' !== trim($imageUrl) ? $imageUrl : null;

        $ingredientsRaw = $product['ingredients_text_fr'] ?? $product['ingredients_text'] ?? null;
        $ingredientsRaw = \is_string($ingredientsRaw) && '' !== trim($ingredientsRaw) ? $ingredientsRaw : null;

        $nutriments = $product['nutriments'] ?? [];
        if (!\is_array($nutriments)) {
            $nutriments = [];
        }

        return new self(
            ean: $ean,
            name: $name,
            brand: $brand,
            imageUrl: $imageUrl,
            ingredientsRaw: $ingredientsRaw,
            nutriments: $nutriments,
            allergens: self::extractStringList($product, 'allergens_tags'),
            additives: self::extractStringList($product, 'additives_tags'),
            categories: self::extractStringList($product, 'categories_tags'),
            labels: self::extractStringList($product, 'labels_tags'),
            rawData: $product,
        );
    }

    /**
     * Extrait un tableau de strings depuis un champ "_tags" OFF.
     *
     * @param array<string, mixed> $data
     *
     * @return list<string>
     */
    private static function extractStringList(array $data, string $key): array
    {
        $raw = $data[$key] ?? [];
        if (!\is_array($raw)) {
            return [];
        }

        return array_values(array_filter($raw, 'is_string'));
    }
}
