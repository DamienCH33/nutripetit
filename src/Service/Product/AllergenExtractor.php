<?php

declare(strict_types=1);

namespace App\Service\Product;

use App\Entity\Product;

/**
 * Source de vérité unique pour les allergènes d'un produit (Règlement UE
 * 1169/2011, INCO : 14 allergènes à déclaration obligatoire).
 *
 * Ne conclut jamais « sans allergène » sans liste d'ingrédients : une liste
 * vide dans Open Food Facts peut simplement vouloir dire « non renseigné ».
 */
final class AllergenExtractor
{
    private const LABELS_FR = [
        'gluten' => 'Gluten',
        'crustaceans' => 'Crustacés',
        'eggs' => 'Œufs',
        'fish' => 'Poisson',
        'peanuts' => 'Arachides',
        'soybeans' => 'Soja',
        'milk' => 'Lait',
        'nuts' => 'Fruits à coque',
        'celery' => 'Céleri',
        'mustard' => 'Moutarde',
        'sesame-seeds' => 'Graines de sésame',
        'sulphur-dioxide-and-sulphites' => 'Sulfites',
        'lupin' => 'Lupin',
        'molluscs' => 'Mollusques',
    ];

    /**
     * Allergènes déclarés et traces (« peut contenir »), pour la fiche produit.
     * Null si la liste d'ingrédients est absente (inconnu).
     *
     * @return array{declared: list<array{code: string, label: string}>, traces: list<array{code: string, label: string}>}|null
     */
    public function extract(Product $product): ?array
    {
        $ingredients = $product->getIngredientsRaw();
        if (null === $ingredients || '' === trim($ingredients)) {
            return null;
        }

        $traces = $product->getOffRawData()['traces_tags'] ?? [];

        return [
            'declared' => $this->describe($product->getAllergens()),
            'traces' => $this->describe(\is_array($traces) ? $traces : []),
        ];
    }

    /**
     * Libellés français des allergènes déclarés, dans l'ordre d'Open Food Facts.
     *
     * @return list<string>
     */
    public function declaredLabels(Product $product): array
    {
        return array_column($this->describe($product->getAllergens()), 'label');
    }

    /**
     * @param array<mixed> $tags tags OFF bruts (« en:milk »)
     *
     * @return list<array{code: string, label: string}>
     */
    private function describe(array $tags): array
    {
        $result = [];
        foreach ($tags as $tag) {
            if (!\is_string($tag) || '' === $tag) {
                continue;
            }
            $parts = explode(':', $tag);
            $code = end($parts);
            $result[$code] = ['code' => $code, 'label' => self::LABELS_FR[$code] ?? ucfirst($code)];
        }

        return array_values($result);
    }
}
