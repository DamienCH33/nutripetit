<?php

declare(strict_types=1);

namespace App\Service\Scoring\Evaluator;

use App\Enum\ScoreLevel;
use App\Enum\ScoringAlgorithm;
use App\Dto\AppliedRuleDto;
use App\Dto\ScoreCalculationResultDto;
use App\Entity\Product;

/**
 * Calcule le score d'un lait infantile.
 *
 * Spécificité : tous les laits commercialisés en UE sont garantis sûrs
 * par le Règlement UE 2016/127. On note uniquement la qualité optionnelle.
 *
 * - Base : 60/100 (= conforme minimum)
 * - Plage finale : 50-100 (jamais dangereux)
 * - Bonus max : +40, Malus max : -10
 */
final class InfantFormulaScoreCalculator
{
    public const ALGO_VERSION = 'infant_formula_1.0.0';
    private const SCORE_BASE = 100;
    private const SCORE_MIN = 70;
    private const SCORE_MAX = 100;

    public function calculate(Product $product, ?int $babyAgeMonths = null): ScoreCalculationResultDto
    {
        $appliedRules = [];
        $score = self::SCORE_BASE;

        $nutriments = $product->getNutriments();
        $ingredients = mb_strtolower((string) $product->getIngredientsRaw());
        $labels = $product->getOffRawData()['labels_tags'] ?? [];
        if (!\is_array($labels)) {
            $labels = [];
        }

        // BONUS
        // DHA
        if (str_contains($ingredients, 'dha') || str_contains($ingredients, 'docosahexa')) {
            $appliedRules[] = new AppliedRuleDto(
                ruleCode: 'formula_dha_present',
                ruleLabel: 'DHA (Oméga 3) présent',
                pointsImpact: 5,
                reason: 'Le DHA est obligatoire depuis 2020 et essentiel au développement cérébral et visuel.',
                sourceName: 'Règlement UE 2016/127',
                sourceUrl: 'https://eur-lex.europa.eu/legal-content/FR/TXT/?uri=CELEX%3A32016R0127',
            );
            $score += 5;
        }

        // ARA
        if (str_contains($ingredients, 'ara') || str_contains($ingredients, 'arachidonic') || str_contains($ingredients, 'arachidonique')) {
            $appliedRules[] = new AppliedRuleDto(
                ruleCode: 'formula_ara_present',
                ruleLabel: 'ARA (Oméga 6) présent',
                pointsImpact: 5,
                reason: 'L\'ARA accompagne le DHA et est recommandé par l\'European Academy of Paediatrics.',
                sourceName: 'European Academy of Paediatrics 2020',
                sourceUrl: 'https://academic.oup.com/ajcn/article/111/1/10/5701474',
            );
            $score += 5;
        }

        // Sans huile de palme
        if ('' !== $ingredients && !str_contains($ingredients, 'palme') && !str_contains($ingredients, 'palm')) {
            $appliedRules[] = new AppliedRuleDto(
                ruleCode: 'formula_no_palm_oil',
                ruleLabel: 'Sans huile de palme',
                pointsImpact: 8,
                reason: 'L\'absence d\'huile de palme évite l\'exposition aux contaminants 3-MCPD et glycidol.',
                sourceName: 'EFSA Scientific Opinion 2016',
                sourceUrl: 'https://www.efsa.europa.eu/fr/press/news/process-contaminants-vegetable-oils-and-foods',
            );
            $score += 8;
        }

        // Bio
        if (\in_array('en:organic', $labels, true) || \in_array('fr:ab-agriculture-biologique', $labels, true)) {
            $appliedRules[] = new AppliedRuleDto(
                ruleCode: 'formula_organic',
                ruleLabel: 'Certification Bio',
                pointsImpact: 6,
                reason: 'Production biologique réduisant l\'exposition aux pesticides.',
                sourceName: 'Règlement UE 2018/848',
                sourceUrl: 'https://eur-lex.europa.eu/eli/reg/2018/848/oj',
            );
            $score += 6;
        }

        // Prébiotiques
        if (str_contains($ingredients, 'gos') || str_contains($ingredients, 'fos') || str_contains($ingredients, 'oligosaccharide') || str_contains($ingredients, 'galacto') || str_contains($ingredients, 'fructo')) {
            $appliedRules[] = new AppliedRuleDto(
                ruleCode: 'formula_prebiotics',
                ruleLabel: 'Prébiotiques (GOS/FOS)',
                pointsImpact: 4,
                reason: 'Prébiotiques favorisant le microbiote intestinal.',
                sourceName: 'ANSES / mpedia.fr',
                sourceUrl: 'https://www.mpedia.fr/art-choix-lait-infantile/',
            );
            $score += 4;
        }

        // Probiotiques
        if (str_contains($ingredients, 'bifidobacterium') || str_contains($ingredients, 'lactobacillus')) {
            $appliedRules[] = new AppliedRuleDto(
                ruleCode: 'formula_probiotics',
                ruleLabel: 'Probiotiques',
                pointsImpact: 4,
                reason: 'Souches probiotiques ajoutées (Bifidobacterium ou Lactobacillus).',
                sourceName: 'Études cliniques',
                sourceUrl: 'https://www.mpedia.fr/art-choix-lait-infantile/',
            );
            $score += 4;
        }

        // Faible teneur protéines
        $proteins = $nutriments['proteins_prepared_100g'] ?? $nutriments['proteins_100g'] ?? null;
        if (is_numeric($proteins) && (float) $proteins <= 1.34) {
            $appliedRules[] = new AppliedRuleDto(
                ruleCode: 'formula_low_protein',
                ruleLabel: 'Faible teneur en protéines (≤ 1,34g/100ml)',
                pointsImpact: 4,
                reason: 'Taux proche du lait maternel, charge rénale moindre.',
                sourceName: 'mpedia.fr / Société Française de Pédiatrie',
                sourceUrl: 'https://www.mpedia.fr/art-choix-lait-infantile/',
            );
            $score += 4;
        }

        // Faible sodium
        $sodium = $nutriments['sodium_prepared_100g'] ?? $nutriments['sodium_100g'] ?? null;
        if (is_numeric($sodium) && (float) $sodium <= 0.024) {
            $appliedRules[] = new AppliedRuleDto(
                ruleCode: 'formula_low_sodium',
                ruleLabel: 'Faible teneur en sodium (≤ 24mg/100ml)',
                pointsImpact: 4,
                reason: 'Taux de sodium optimal pour les nourrissons.',
                sourceName: 'mpedia.fr',
                sourceUrl: 'https://www.mpedia.fr/art-choix-lait-infantile/',
            );
            $score += 4;
        }

        // MALUS
        // Huile de palme
        if (preg_match('/^[^,]*palme/i', $ingredients) || preg_match('/^[^,]*palm/i', $ingredients)) {
            $appliedRules[] = new AppliedRuleDto(
                ruleCode: 'formula_palm_oil_main',
                ruleLabel: 'Huile de palme en premier ingrédient',
                pointsImpact: -8,
                reason: 'L\'huile de palme contient des contaminants 3-MCPD et glycidol issus du raffinage.',
                sourceName: 'EFSA / Règlement UE 2018/290',
                sourceUrl: 'https://www.efsa.europa.eu/fr/press/news/process-contaminants-vegetable-oils-and-foods',
            );
            $score -= 8;
        }

        // Sucres ajoutés autres que lactose
        if (str_contains($ingredients, 'sirop de glucose') || str_contains($ingredients, 'maltodextrine') || str_contains($ingredients, 'saccharose')) {
            $appliedRules[] = new AppliedRuleDto(
                ruleCode: 'formula_added_sugars',
                ruleLabel: 'Sucres ajoutés (autres que lactose)',
                pointsImpact: -6,
                reason: 'Le lactose seul est préférable pour les nourrissons.',
                sourceName: 'Société Française de Pédiatrie',
                sourceUrl: 'https://www.sfpediatrie.com/',
            );
            $score -= 6;
        }

        // Protéines de soja
        if (str_contains($ingredients, 'soja') || str_contains($ingredients, 'soy')) {
            $appliedRules[] = new AppliedRuleDto(
                ruleCode: 'formula_soy_protein',
                ruleLabel: 'Protéines de soja',
                pointsImpact: -4,
                reason: 'Le soja est déconseillé sauf prescription médicale (terrain allergique).',
                sourceName: 'ANSES',
                sourceUrl: 'https://www.anses.fr/',
            );
            $score -= 4;
        }

        // ℹINFORMATIVE
        if (str_contains(mb_strtolower($product->getName()), 'hydrolysat') || str_contains($ingredients, 'hydrolysé') || str_contains($ingredients, 'hydrolyse')) {
            $appliedRules[] = new AppliedRuleDto(
                ruleCode: 'formula_hydrolysat',
                ruleLabel: 'Protéines hydrolysées (HA)',
                pointsImpact: 0,
                reason: 'Adapté aux nourrissons à risque allergique (sur indication médicale).',
                sourceName: 'Société Française de Pédiatrie',
                sourceUrl: 'https://www.sfpediatrie.com/',
            );
        }

        // Borner le score
        $score = max(self::SCORE_MIN, min(self::SCORE_MAX, $score));

        return new ScoreCalculationResultDto(
            finalScore: $score,
            level: $this->determineLevel($score),
            appliedRules: $appliedRules,
            algoVersion: self::ALGO_VERSION,
            babyAgeMonths: $babyAgeMonths,
        );
    }

    private function determineLevel(int $score): string
    {
        return ScoreLevel::fromScore($score, ScoringAlgorithm::InfantFormula)->value;
    }
}
