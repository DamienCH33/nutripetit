<?php

declare(strict_types=1);

namespace App\Service\Product;

use App\Entity\Product;

/**
 * Source de vérité unique pour la détection de la certification biologique.
 *
 * Se fonde EXCLUSIVEMENT sur les labels certifiants renvoyés par Open Food
 * Facts (labels_tags), qui reflètent une certification réelle (Règlement UE
 * 2018/848, Agence Bio). On n'infère jamais le bio depuis le nom commercial :
 * une mention marketing n'est pas une certification.
 */
final class OrganicLabelDetector
{
    /**
     * Tags OFF certifiant une production biologique.
     * Correspondance exacte : ces tags sont normalisés par OFF.
     */
    private const CERTIFIED_ORGANIC_TAGS = [
        'en:organic',
        'en:eu-organic',
        'fr:ab-agriculture-biologique',
        'fr:agriculture-biologique',
        'fr:bio',
        'en:fr-bio-01',
    ];

    public function isOrganic(Product $product): bool
    {
        return $this->hasOrganicLabel($product->getOffRawData()['labels_tags'] ?? []);
    }

    /**
     * @param mixed $labelsTags labels_tags bruts d'Open Food Facts
     */
    public function hasOrganicLabel(mixed $labelsTags): bool
    {
        if (!\is_array($labelsTags)) {
            return false;
        }

        foreach ($labelsTags as $tag) {
            if (\is_string($tag) && \in_array($tag, self::CERTIFIED_ORGANIC_TAGS, true)) {
                return true;
            }
        }

        return false;
    }
}
