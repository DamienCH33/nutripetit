<?php

declare(strict_types=1);

namespace App\Service\Product;

use App\Entity\Product;
use App\Service\Scoring\Evaluator\ControversialAdditivesEvaluator;

final class AdditiveExtractor
{
    public const STATUS_CONTROVERSIAL = 'controversial';
    public const STATUS_SWEETENER = 'sweetener';
    public const STATUS_AUTHORIZED = 'authorized';

    /**
     * Détail affiché quand le parent ouvre la section « Additifs » de la fiche.
     *
     * @return list<array{code: string, name: string, status: string, note: string}>
     */
    public function extractAdditives(Product $product): array
    {
        $additivesTags = $product->getAdditives();
        $result = [];
        $seen = [];

        $known = [
            'E100' => 'Curcumine',
            'E101' => 'Riboflavine',
            'E150A' => 'Caramel ordinaire',
            'E150D' => 'Caramel au sulfite d\'ammonium',
            'E160A' => 'Carotènes',
            'E202' => 'Sorbate de potassium',
            'E270' => 'Acide lactique',
            'E290' => 'Dioxyde de carbone',
            'E296' => 'Acide malique',
            'E300' => 'Acide ascorbique (Vitamine C)',
            'E306' => 'Tocophérols (Vitamine E)',
            'E322' => 'Lécithines',
            'E330' => 'Acide citrique',
            'E331' => 'Citrates de sodium',
            'E332' => 'Citrates de potassium',
            'E333' => 'Citrates de calcium',
            'E336' => 'Tartrates de potassium',
            'E412' => 'Gomme de guar',
            'E415' => 'Gomme xanthane',
            'E440' => 'Pectines',
            'E471' => 'Mono- et diglycérides d\'acides gras',
            'E500' => 'Carbonates de sodium',
            'E501' => 'Carbonates de potassium',
            'E503' => 'Carbonates d\'ammonium',
            'E504' => 'Carbonates de magnésium',
            'E575' => 'Glucono-delta-lactone',
            'E950' => 'Acésulfame K (édulcorant)',
            'E951' => 'Aspartame (édulcorant)',
            'E952' => 'Cyclamates (édulcorant)',
            'E954' => 'Saccharine (édulcorant)',
            'E955' => 'Sucralose (édulcorant)',
            'E960' => 'Glycosides de stéviol (édulcorant)',
        ];

        foreach ($additivesTags as $tag) {
            $code = strtoupper(str_replace(['EN:', 'FR:'], '', strtoupper((string) $tag)));

            if (\in_array($code, $seen, true)) {
                continue;
            }
            $seen[] = $code;

            $status = $this->statusOf($code);
            $result[] = [
                'code' => $code,
                'name' => $known[$code] ?? 'Additif non documenté',
                'status' => $status,
                'note' => match ($status) {
                    self::STATUS_CONTROVERSIAL => 'Évaluation préoccupante (EFSA/ANSES) : mieux vaut l\'éviter chez l\'enfant.',
                    self::STATUS_SWEETENER => 'Édulcorant : non autorisé dans les aliments pour bébés (règlement UE 1333/2008).',
                    default => 'Autorisé dans l\'UE (règlement 1333/2008), pas de signalement particulier.',
                },
            ];
        }

        return $result;
    }

    private function statusOf(string $code): string
    {
        if (\in_array(mb_strtolower($code), ControversialAdditivesEvaluator::CONTROVERSIAL_E_CODES, true)) {
            return self::STATUS_CONTROVERSIAL;
        }
        if (1 === preg_match('/^E9[5-6]\d/', $code)) {
            return self::STATUS_SWEETENER;
        }

        return self::STATUS_AUTHORIZED;
    }
}
