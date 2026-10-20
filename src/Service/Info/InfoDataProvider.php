<?php

declare(strict_types=1);

namespace App\Service\Info;

use App\Enum\ScoreLevel;
use App\Enum\ScoringAlgorithm;
use App\Repository\ScoringRuleRepository;
use App\Service\Scoring\Evaluator\InfantFormulaScoreCalculator;
use App\Service\Scoring\ScoreCalculator;

final class InfoDataProvider
{
    public function __construct(
        private readonly ScoringRuleRepository $ruleRepository,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getInfoData(): array
    {
        return [
            'rules' => $this->ruleRepository->findActiveByVersion(ScoreCalculator::ALGO_VERSION),
            'algoVersion' => ScoreCalculator::ALGO_VERSION,
            'infantFormulaAlgoVersion' => InfantFormulaScoreCalculator::ALGO_VERSION,
            'scoreScale' => $this->getScoreScale(),
            'infantFormulaRules' => $this->getInfantFormulaRules(),
            'sources' => $this->getSources(),
        ];
    }

    /**
     * @return list<array{level: string, min: int, max: int, label: string, description: string}>
     */
    private function getScoreScale(): array
    {
        return array_map(
            static fn (ScoreLevel $l): array => [
                'level' => $l->value,
                'min' => $l->min(),
                'max' => $l->max(),
                'label' => $l->label(ScoringAlgorithm::Food),
                'description' => $l->description(),
            ],
            ScoreLevel::cases(),
        );
    }

    /**
     * @return list<array{code: string, label: string, points: int, reason: string, source: string, category: string}>
     */
    private function getInfantFormulaRules(): array
    {
        // Miroir exact d'InfantFormulaScoreCalculator (v3.0.0) : un test vérifie
        // que chaque libellé et chaque impact affichés existent dans le calculateur.
        return [
            ['code' => 'formula_no_palm_oil', 'label' => 'Sans huile de palme', 'points' => 8, 'reason' => 'Évite les contaminants 3-MCPD et glycidol issus du raffinage.', 'source' => 'EFSA Scientific Opinion 2016', 'category' => 'bonus'],
            ['code' => 'formula_organic', 'label' => 'Certification Bio', 'points' => 6, 'reason' => 'Production biologique réduisant l\'exposition aux pesticides.', 'source' => 'Règlement UE 2018/848', 'category' => 'bonus'],
            ['code' => 'formula_prebiotics', 'label' => 'Prébiotiques (GOS/FOS)', 'points' => 4, 'reason' => 'Favorisent le microbiote intestinal.', 'source' => 'ANSES / mpedia.fr', 'category' => 'bonus'],
            ['code' => 'formula_probiotics', 'label' => 'Probiotiques', 'points' => 4, 'reason' => 'Souches Bifidobacterium ou Lactobacillus ajoutées.', 'source' => 'Études cliniques', 'category' => 'bonus'],
            ['code' => 'formula_palm_oil_main', 'label' => 'Huile de palme en premier ingrédient', 'points' => -8, 'reason' => 'Contaminants 3-MCPD et glycidol issus du raffinage.', 'source' => 'EFSA / Règlement UE 2018/290', 'category' => 'malus'],
            ['code' => 'formula_palm_oil', 'label' => 'Contient de l\'huile de palme', 'points' => -3, 'reason' => 'Hors premier ingrédient : exposition moindre mais réelle aux contaminants de raffinage.', 'source' => 'EFSA Scientific Opinion 2016', 'category' => 'malus'],
            ['code' => 'formula_added_sugars', 'label' => 'Sucres ajoutés (saccharose / sirop de glucose)', 'points' => -6, 'reason' => 'Habituent au goût sucré : le lactose seul est préférable.', 'source' => 'Société Française de Pédiatrie', 'category' => 'malus'],
            ['code' => 'formula_maltodextrin', 'label' => 'Sucres ajoutés (maltodextrine)', 'points' => -4, 'reason' => 'Glucide ajouté : le lactose seul reste préférable.', 'source' => 'Société Française de Pédiatrie', 'category' => 'malus'],
            ['code' => 'formula_soy_protein', 'label' => 'Protéines de soja', 'points' => -4, 'reason' => 'Déconseillé sauf prescription médicale.', 'source' => 'ANSES', 'category' => 'malus'],
            ['code' => 'formula_ara_present', 'label' => 'ARA (Oméga 6) présent', 'points' => 0, 'reason' => 'Information, sans effet sur le score.', 'source' => 'European Academy of Paediatrics 2020', 'category' => 'info'],
            ['code' => 'formula_hydrolysed', 'label' => 'Protéines hydrolysées (HA)', 'points' => 0, 'reason' => 'Pour les nourrissons à risque allergique, sur indication médicale. Information, sans effet sur le score.', 'source' => 'EFSA 2012 / Société Française de Pédiatrie', 'category' => 'info'],
        ];
    }

    /**
     * @return list<array{name: string, description: string, url: string}>
     */
    private function getSources(): array
    {
        return [
            ['name' => 'ANSES', 'description' => 'Agence française - Avis 0-3 ans (2019)', 'url' => 'https://www.anses.fr/'],
            ['name' => 'PNNS 4', 'description' => 'Programme National Nutrition Santé (2021)', 'url' => 'https://www.mangerbouger.fr/'],
            ['name' => 'OMS', 'description' => 'Organisation mondiale de la santé — sucres (2015)', 'url' => 'https://www.who.int/'],
            ['name' => 'OMS Europe', 'description' => 'Profil nutritionnel des aliments 6-36 mois (2022)', 'url' => 'https://www.who.int/europe/publications/i/item/WHO-EURO-2022-6681-46447-67287'],
            ['name' => 'EFSA', 'description' => 'Autorité européenne de sécurité des aliments', 'url' => 'https://www.efsa.europa.eu/'],
            ['name' => 'Santé publique France', 'description' => 'Agence nationale de santé publique', 'url' => 'https://www.santepubliquefrance.fr/'],
            ['name' => 'Règlement UE 2016/127', 'description' => 'Préparations pour nourrissons (laits infantiles)', 'url' => 'https://eur-lex.europa.eu/legal-content/FR/TXT/?uri=CELEX%3A32016R0127'],
            ['name' => 'Règlement UE 1333/2008', 'description' => 'Additifs alimentaires', 'url' => 'https://eur-lex.europa.eu/legal-content/FR/TXT/?uri=CELEX%3A32008R1333'],
            ['name' => 'Règlement UE 1169/2011 (INCO)', 'description' => 'Étiquetage et allergènes', 'url' => 'https://eur-lex.europa.eu/legal-content/FR/TXT/?uri=CELEX%3A32011R1169'],
            ['name' => 'Règlement UE 2018/848', 'description' => 'Production biologique (label AB)', 'url' => 'https://eur-lex.europa.eu/eli/reg/2018/848/oj'],
            ['name' => 'Directive UE 2006/125/CE', 'description' => 'Aliments pour bébés (sel, composition)', 'url' => 'https://eur-lex.europa.eu/legal-content/FR/TXT/?uri=CELEX%3A32006L0125'],
        ];
    }
}
