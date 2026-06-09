<?php

declare(strict_types=1);

namespace App\Service\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Entity\Product;
use App\Entity\ScoringRule;
use App\Service\Scoring\RuleEvaluator;

/**
 * Détecte la présence d'allergènes majeurs (information sans impact score).
 *
 * Source : Règlement EU 1169/2011 (INCO)
 * 14 allergènes à déclaration obligatoire selon le règlement INCO.
 *
 * L'impact est 0 : ce n'est pas un malus, juste une information importante
 * que les parents doivent voir mise en avant.
 */
final class MajorAllergensEvaluator implements RuleEvaluator
{
    public function supports(ScoringRule $rule): bool
    {
        return 'major_allergens' === $rule->getCode();
    }

    private const ALLERGEN_FR = [
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
     * @param list<string> $allergens
     *
     * @return list<string>
     */
    private function translateAllergens(array $allergens): array
    {
        $result = [];
        foreach ($allergens as $tag) {
            $clean = str_replace(['en:', 'fr:'], '', (string) $tag);
            $result[] = self::ALLERGEN_FR[$clean] ?? ucfirst($clean);
        }

        return $result;
    }

    public function evaluate(
        Product $product,
        ScoringRule $rule,
        ?int $babyAgeMonths,
    ): ?AppliedRuleDto {
        $allergens = $product->getAllergens();

        if ([] === $allergens) {
            return null;
        }

        // Nettoyage des préfixes OFF
        $cleanAllergens = array_map(
            static function (string $a): string {
                $parts = explode(':', $a);

                return ucfirst(end($parts));
            },
            $allergens,
        );

        $translated = $this->translateAllergens($product->getAllergens());
        $reason = \sprintf(
            'Allergène(s) majeur(s) déclaré(s) : %s. Vigilance recommandée selon le règlement INCO.',
            implode(', ', $translated),
        );

        return new AppliedRuleDto(
            ruleCode: $rule->getCode(),
            ruleLabel: $rule->getLabel(),
            pointsImpact: $rule->getPointsImpact(),
            reason: $reason,
            sourceName: $rule->getSourceName(),
            sourceUrl: $rule->getSourceUrl(),
        );
    }
}
