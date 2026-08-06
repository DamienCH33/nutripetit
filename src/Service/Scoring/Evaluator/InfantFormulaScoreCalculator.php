<?php

declare(strict_types=1);

namespace App\Service\Scoring\Evaluator;

use App\Dto\AppliedRuleDto;
use App\Dto\ScoreCalculationResultDto;
use App\Entity\Product;
use App\Enum\ScoreLevel;
use App\Enum\ScoringAlgorithm;

/**
 * Calcule le score d'un lait infantile.
 *
 * Principe : tout lait commercialisé en UE est garanti sûr et nutritionnellement
 * adapté par le Règlement délégué (UE) 2016/127. On ne note donc PAS la conformité
 * (identique pour tous) mais uniquement la QUALITÉ OPTIONNELLE, lisible de façon
 * fiable dans la liste d'ingrédients et les labels.
 *
 * Choix de conception majeur : les macronutriments chiffrés (protéines, sodium,
 * fer…) NE sont PAS scorés. Les valeurs Open Food Facts des laits sont exprimées
 * en poudre (~×8 vs reconstitué) alors que les seuils réglementaires sont en
 * /100 ml reconstitué : les comparer produit des scores faux. Ces valeurs sont
 * affichées à titre informatif (via une table de référence Ciqual), jamais scorées.
 *
 * v3.0.0 :
 *  - DHA retiré des bonus : obligatoire pour tous depuis le 22/02/2020
 *    (2016/127, 20-50 mg/100 kcal) → non discriminant.
 *  - Règles chiffrées (faible protéine / faible sodium) retirées : données OFF poudre non fiables.
 *  - Malus sucres gradué (saccharose / sirop de glucose plus pénalisés que maltodextrine).
 *  - Malus intermédiaire "huile de palme présente mais pas en 1er ingrédient".
 *  - ARA en informatif (0 pt) : statut optionnel non confirmé dans l'annexe → prudence.
 *  - Base 60, plage réelle ~50-82, échelle recalée dans ScoreLevel.
 */
final class InfantFormulaScoreCalculator
{
    public const ALGO_VERSION = 'infant_formula_3.0.0';
    private const SCORE_BASE = 60;
    private const SCORE_MIN = 60;
    private const SCORE_MAX = 100;

    public function calculate(Product $product, ?int $babyAgeMonths = null): ScoreCalculationResultDto
    {
        $appliedRules = [];
        $score = self::SCORE_BASE;

        $ingredients = mb_strtolower((string) $product->getIngredientsRaw());
        $labels = $product->getOffRawData()['labels_tags'] ?? [];
        if (!\is_array($labels)) {
            $labels = [];
        }

        // BONUS
        // Sans huile de palme (+8) — EFSA 2016 : évite contaminants 3-MCPD / glycidol.
        // L'huile de palme est autorisée : son absence est un vrai choix différenciant.
        $hasPalm = str_contains($ingredients, 'palme') || str_contains($ingredients, 'palm');
        if ('' !== $ingredients && !$hasPalm) {
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

        // Bio (+6) — Règlement UE 2018/848 : réduit l'exposition aux pesticides.
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

        // Prébiotiques GOS/FOS (+4) — ANSES : ingrédients optionnels (non obligatoires).
        if (
            1 === preg_match('/\b(gos|fos)\b/', $ingredients)
            || str_contains($ingredients, 'oligosaccharide')
            || str_contains($ingredients, 'galacto-oligo')
            || str_contains($ingredients, 'fructo-oligo')
        ) {
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

        // Probiotiques (+4) — optionnel, non imposé par la réglementation.
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

        // MALUS
        // Huile de palme en 1er ingrédient (-8) OU présente ailleurs (-3) — EFSA 2016.
        // elseif : jamais de cumul (un produit "palme en tête" n'est pas aussi
        // pénalisé une seconde fois pour "palme présente").
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
        } elseif ($hasPalm) {
            $appliedRules[] = new AppliedRuleDto(
                ruleCode: 'formula_palm_oil_present',
                ruleLabel: 'Contient de l\'huile de palme',
                pointsImpact: -3,
                reason: 'Présence d\'huile de palme (hors 1er ingrédient) : exposition moindre mais réelle aux contaminants de raffinage.',
                sourceName: 'EFSA Scientific Opinion 2016',
                sourceUrl: 'https://www.efsa.europa.eu/fr/press/news/process-contaminants-vegetable-oils-and-foods',
            );
            $score -= 3;
        }

        // Sucres ajoutés gradués — le règlement privilégie le lactose ("lactose uniquement"), SFP.
        // Saccharose / sirop de glucose (-6) plus pénalisés que la maltodextrine (-4).
        // elseif : on applique le malus le plus lourd applicable, sans cumul.
        if (str_contains($ingredients, 'saccharose') || str_contains($ingredients, 'sirop de glucose')) {
            $appliedRules[] = new AppliedRuleDto(
                ruleCode: 'formula_added_sugars_high',
                ruleLabel: 'Sucres ajoutés (saccharose / sirop de glucose)',
                pointsImpact: -6,
                reason: 'Le saccharose et le sirop de glucose habituent au goût sucré : le lactose seul est préférable.',
                sourceName: 'Société Française de Pédiatrie',
                sourceUrl: 'https://www.sfpediatrie.com/',
            );
            $score -= 6;
        } elseif (str_contains($ingredients, 'maltodextrine')) {
            $appliedRules[] = new AppliedRuleDto(
                ruleCode: 'formula_added_sugars_malto',
                ruleLabel: 'Sucres ajoutés (maltodextrine)',
                pointsImpact: -4,
                reason: 'La maltodextrine est un glucide ajouté : le lactose seul reste préférable pour les nourrissons.',
                sourceName: 'Société Française de Pédiatrie',
                sourceUrl: 'https://www.sfpediatrie.com/',
            );
            $score -= 4;
        }

        // Protéines de soja (-4) — ANSES : déconseillé sauf prescription (terrain allergique).
        // Vise les préparations À BASE de soja, pas la lécithine de soja (émulsifiant).
        if (1 === preg_match('/prot[ée]ines? de soja|isolat de soja|farine de soja|base de soja|soy protein|soya protein/u', $ingredients)) {
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

        // INFORMATIF (0 pt)
        // ARA — présent mais non bonifié : statut optionnel non confirmé dans l'annexe I → prudence.
        if (1 === preg_match('/\bara\b/', $ingredients) || str_contains($ingredients, 'arachidonic') || str_contains($ingredients, 'arachidonique')) {
            $appliedRules[] = new AppliedRuleDto(
                ruleCode: 'formula_ara_present',
                ruleLabel: 'ARA (Oméga 6) présent',
                pointsImpact: 0,
                reason: 'L\'ARA accompagne le DHA dans certaines formules. Information, sans impact sur le score.',
                sourceName: 'European Academy of Paediatrics 2020',
                sourceUrl: 'https://academic.oup.com/ajcn/article/111/1/10/5701474',
            );
        }

        // Hydrolysats (HA) — EFSA 2012 : bénéfice préventif non démontré → mention neutre, pas de bonus.
        if (str_contains(mb_strtolower($product->getName()), 'hydrolysat') || str_contains($ingredients, 'hydrolysé') || str_contains($ingredients, 'hydrolyse')) {
            $appliedRules[] = new AppliedRuleDto(
                ruleCode: 'formula_hydrolysat',
                ruleLabel: 'Protéines hydrolysées (HA)',
                pointsImpact: 0,
                reason: 'Adapté aux nourrissons à risque allergique (sur indication médicale). Bénéfice préventif non démontré (EFSA 2012).',
                sourceName: 'EFSA 2012 / Société Française de Pédiatrie',
                sourceUrl: 'https://www.sfpediatrie.com/',
            );
        }

        // Borner le score.
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
