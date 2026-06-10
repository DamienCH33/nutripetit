<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\ScoreLevel;
use App\Enum\ScoringAlgorithm;
use App\Repository\ScoringRuleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class InfoController extends AbstractController
{
    public function __construct(
        private readonly ScoringRuleRepository $ruleRepository,
    ) {
    }

    #[Route('/app/infos', name: 'app_pwa_info', methods: ['GET'])]
    public function index(): Response
    {
        $rules = $this->ruleRepository->findActiveByVersion('1.0.0');

        $scoreScale = array_map(
            static fn (ScoreLevel $l): array => [
                'level' => $l->value,
                'min' => $l->min(),
                'max' => $l->max(),
                'label' => $l->label(ScoringAlgorithm::Food),
                'description' => $l->description(),
            ],
            ScoreLevel::cases(),
        );

        $infantFormulaRules = [
            ['code' => 'formula_dha_present', 'label' => 'DHA (Oméga 3) présent', 'points' => 5, 'reason' => 'Le DHA est obligatoire depuis 2020, essentiel au développement cérébral et visuel.', 'source' => 'Règlement UE 2016/127', 'category' => 'bonus'],
            ['code' => 'formula_ara_present', 'label' => 'ARA (Oméga 6) présent', 'points' => 5, 'reason' => 'L\'ARA accompagne le DHA.', 'source' => 'European Academy of Paediatrics 2020', 'category' => 'bonus'],
            ['code' => 'formula_no_palm_oil', 'label' => 'Sans huile de palme', 'points' => 8, 'reason' => 'Évite les contaminants 3-MCPD et glycidol issus du raffinage.', 'source' => 'EFSA Scientific Opinion 2016', 'category' => 'bonus'],
            ['code' => 'formula_organic', 'label' => 'Certification Bio', 'points' => 6, 'reason' => 'Production biologique réduisant l\'exposition aux pesticides.', 'source' => 'Règlement UE 2018/848', 'category' => 'bonus'],
            ['code' => 'formula_prebiotics', 'label' => 'Prébiotiques (GOS/FOS)', 'points' => 4, 'reason' => 'Favorisent le microbiote intestinal.', 'source' => 'ANSES / mpedia.fr', 'category' => 'bonus'],
            ['code' => 'formula_probiotics', 'label' => 'Probiotiques', 'points' => 4, 'reason' => 'Souches Bifidobacterium ou Lactobacillus.', 'source' => 'Études cliniques', 'category' => 'bonus'],
            ['code' => 'formula_low_protein', 'label' => 'Faible teneur en protéines (≤1,34g/100ml)', 'points' => 4, 'reason' => 'Charge rénale moindre, proche du lait maternel.', 'source' => 'SFP', 'category' => 'bonus'],
            ['code' => 'formula_low_sodium', 'label' => 'Faible sodium (≤24mg/100ml)', 'points' => 4, 'reason' => 'Optimal pour les nourrissons.', 'source' => 'mpedia.fr', 'category' => 'bonus'],
            ['code' => 'formula_palm_oil_main', 'label' => 'Huile de palme en premier ingrédient', 'points' => -8, 'reason' => 'Contaminants 3-MCPD et glycidol.', 'source' => 'EFSA / Règlement UE 2018/290', 'category' => 'malus'],
            ['code' => 'formula_added_sugars', 'label' => 'Sucres ajoutés (autres que lactose)', 'points' => -6, 'reason' => 'Le lactose seul est préférable.', 'source' => 'Société Française de Pédiatrie', 'category' => 'malus'],
            ['code' => 'formula_soy_protein', 'label' => 'Protéines de soja', 'points' => -4, 'reason' => 'Déconseillé sauf prescription médicale.', 'source' => 'ANSES', 'category' => 'malus'],
        ];

        $sources = [
            ['name' => 'ANSES', 'description' => 'Agence française - Avis 0-3 ans (2019)', 'url' => 'https://www.anses.fr/'],
            ['name' => 'PNNS 4', 'description' => 'Programme National Nutrition Santé (2021)', 'url' => 'https://www.mangerbouger.fr/'],
            ['name' => 'OMS', 'description' => 'Organisation Mondiale de la Santé', 'url' => 'https://www.who.int/'],
            ['name' => 'EFSA', 'description' => 'Autorité européenne de sécurité des aliments', 'url' => 'https://www.efsa.europa.eu/'],
            ['name' => 'Règlement UE 2016/127', 'description' => 'Préparations pour nourrissons (laits infantiles)', 'url' => 'https://eur-lex.europa.eu/legal-content/FR/TXT/?uri=CELEX%3A32016R0127'],
            ['name' => 'Règlement UE 2018/848', 'description' => 'Production biologique (label AB)', 'url' => 'https://eur-lex.europa.eu/eli/reg/2018/848/oj'],
            ['name' => 'Règlement UE 1169/2011 (INCO)', 'description' => 'Étiquetage et allergènes', 'url' => 'https://eur-lex.europa.eu/legal-content/FR/TXT/?uri=CELEX%3A32011R1169'],
            ['name' => 'Directive UE 2006/125/CE', 'description' => 'Aliments à base de céréales et aliments pour bébés', 'url' => 'https://eur-lex.europa.eu/legal-content/FR/TXT/?uri=CELEX%3A32006L0125'],
        ];

        return $this->render('pages/app/info.html.twig', [
            'rules' => $rules,
            'algoVersion' => '1.0.0',
            'scoreScale' => $scoreScale,
            'infantFormulaRules' => $infantFormulaRules,
            'infantFormulaAlgoVersion' => 'infant_formula_1.0.0',
            'sources' => $sources,
        ]);
    }
}
