<?php

declare(strict_types=1);

namespace App\Service\Product;

use App\Entity\Product;

final class EnvironmentAnalyzer
{
    /**
     * @return array<string, mixed>
     */
    public function buildEnvironment(Product $product): array
    {
        $raw = $product->getOffRawData();
        $labels = $raw['labels_tags'] ?? [];
        $ingredients = mb_strtolower((string) ($raw['ingredients_text_fr'] ?? $raw['ingredients_text'] ?? ''));
        $countries = $raw['countries_tags'] ?? [];
        $packagingTags = $raw['packaging_tags'] ?? [];
        $packagingMaterialsTags = $raw['packaging_materials_tags'] ?? [];

        if (!\is_array($labels)) {
            $labels = [];
        }
        if (!\is_array($packagingTags)) {
            $packagingTags = [];
        }
        if (!\is_array($packagingMaterialsTags)) {
            $packagingMaterialsTags = [];
        }
        if (!\is_array($countries)) {
            $countries = [];
        }

        // Origine
        $origin = null;
        if ([] !== $countries) {
            $firstCountry = (string) $countries[0];
            $countryName = ucfirst(str_replace(['en:', 'fr:'], '', $firstCountry));
            $flag = match (true) {
                str_contains(strtolower($countryName), 'france') => '🇫🇷',
                str_contains(strtolower($countryName), 'germany') => '🇩🇪',
                str_contains(strtolower($countryName), 'italy') => '🇮🇹',
                str_contains(strtolower($countryName), 'spain') => '🇪🇸',
                str_contains(strtolower($countryName), 'belgium') => '🇧🇪',
                default => '🌍',
            };
            $origin = ['country' => $countryName, 'flag' => $flag];
        }

        // Emballage
        $packaging = null;
        $allTags = array_merge($packagingTags, $packagingMaterialsTags);

        if ([] !== $allTags) {
            $details = [];
            $allRecyclable = true;
            $hasNonRecyclable = false;
            $seen = [];

            foreach ($allTags as $tag) {
                $clean = str_replace(['en:', 'fr:'], '', (string) $tag);
                $name = $this->translatePackaging($clean);

                if (\in_array($name, $seen, true)) {
                    continue;
                }
                $seen[] = $name;

                $recyclable = $this->isPackagingRecyclable($clean);

                $details[] = ['name' => $name, 'recyclable' => $recyclable];
                if (!$recyclable) {
                    $hasNonRecyclable = true;
                    $allRecyclable = false;
                }
            }

            $count = \count($details);

            $label = match (true) {
                $allRecyclable => 'Recyclable',
                $hasNonRecyclable && $count > 1 => 'Partiellement recyclable',
                $hasNonRecyclable => 'Non recyclable',
                default => 'Non précisé',
            };

            $level = match (true) {
                $allRecyclable => 'ideal',
                $hasNonRecyclable && $count > 1 => 'limit',
                $hasNonRecyclable => 'discouraged',
                default => 'neutral',
            };

            $packaging = [
                'label' => $label,
                'level' => $level,
                'details' => $details,
            ];
        }

        return [
            'origin' => $origin,
            'bio_certified' => \in_array('en:organic', $labels, true) || \in_array('fr:ab-agriculture-biologique', $labels, true),
            'palm_oil_free' => !str_contains($ingredients, 'palme') && !str_contains($ingredients, 'palm oil'),
            'packaging' => $packaging,
        ];
    }

    private function isPackagingRecyclable(string $cleanTag): bool
    {
        $nonRecyclablePatterns = [
            'cellophane',
            'cellulose',
            'composite',
            'sticker',
            'label-non-recyclable',
            'wrapper',
            'individual-wrapper',
            'cork',
            'liège',
            'liege',
        ];

        foreach ($nonRecyclablePatterns as $pattern) {
            if (str_contains($cleanTag, $pattern)) {
                return false;
            }
        }

        return true;
    }

    private function translatePackaging(string $tag): string
    {
        $translations = [
            'plastic' => 'Plastique',
            'box' => 'Boîte',
            'cardboard' => 'Carton',
            'paper' => 'Papier',
            'film' => 'Film',
            'plastic-film' => 'Film plastique',
            'film-en-plastique' => 'Film plastique',
            'bag' => 'Sachet',
            'plastic-bag' => 'Sachet plastique',
            'bottle' => 'Bouteille',
            'plastic-bottle' => 'Bouteille plastique',
            'glass' => 'Verre',
            'glass-bottle' => 'Bouteille en verre',
            'jar' => 'Pot',
            'glass-jar' => 'Pot en verre',
            'can' => 'Boîte de conserve',
            'metal' => 'Métal',
            'aluminium' => 'Aluminium',
            'steel' => 'Acier',
            'tin' => 'Fer-blanc',
            'tetra-pak' => 'Brique Tetra Pak',
            'brique' => 'Brique',
            'wood' => 'Bois',
            'tray' => 'Barquette',
            'plastic-tray' => 'Barquette plastique',
            'lid' => 'Couvercle',
            'cap' => 'Bouchon',
            'cork' => 'Liège',
            'pouch' => 'Sachet souple',
            'sachet' => 'Sachet',
            'pot' => 'Pot',
            'plastic-pot' => 'Pot plastique',
            'sticker' => 'Étiquette',
            'label' => 'Étiquette',
            'wrapping' => 'Emballage',
            'wrapper' => 'Emballage',
            'individual-wrappers' => 'Emballages individuels',
        ];

        if (isset($translations[$tag])) {
            return $translations[$tag];
        }

        return ucfirst(str_replace('-', ' ', $tag));
    }
}
