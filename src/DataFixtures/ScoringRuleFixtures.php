<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\ScoringRule;
use App\Scoring\ScoringRulesProvider;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Fixtures des 16 règles de scoring NutriPetit v1.0.0.
 *
 * Sources officielles :
 * - ANSES Avis 0-3 ans (2019)
 * - HCSP Avis (2020)
 * - PNNS 4 / Santé publique France (2021)
 * - OMS Guideline Sugars (2015)
 * - EFSA / Règlements européens
 */
final class ScoringRuleFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        foreach (self::getRules() as $ruleData) {
            $rule = new ScoringRule(
                code: $ruleData['code'],
                label: $ruleData['label'],
                description: $ruleData['description'],
                algoVersion: ScoringRulesProvider::ALGO_VERSION,
                pointsImpact: $ruleData['pointsImpact'],
                sourceName: $ruleData['sourceName'],
                sourceUrl: $ruleData['sourceUrl'],
            );

            if (isset($ruleData['thresholdValue'])) {
                $rule->setThresholdValue($ruleData['thresholdValue']);
            }
            if (isset($ruleData['thresholdUnit'])) {
                $rule->setThresholdUnit($ruleData['thresholdUnit']);
            }
            if (isset($ruleData['ageMinMonths'])) {
                $rule->setAgeMinMonths($ruleData['ageMinMonths']);
            }
            if (isset($ruleData['ageMaxMonths'])) {
                $rule->setAgeMaxMonths($ruleData['ageMaxMonths']);
            }

            $manager->persist($rule);
        }

        $manager->flush();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function getRules(): array
    {
        return ScoringRulesProvider::getRules();
    }
}
