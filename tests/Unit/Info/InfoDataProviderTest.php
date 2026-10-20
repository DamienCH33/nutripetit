<?php

declare(strict_types=1);

namespace App\Tests\Unit\Info;

use App\Repository\ScoringRuleRepository;
use App\Service\Info\InfoDataProvider;
use App\Service\Scoring\Evaluator\InfantFormulaScoreCalculator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * La page « Comment on note ? » affichait encore des bonus retirés de l'algo
 * laits v3 (DHA, faible protéine, faible sodium). Ce test lit le calculateur
 * et vérifie que chaque règle affichée y existe avec le même impact.
 */
final class InfoDataProviderTest extends TestCase
{
    public function testInfantFormulaRulesShownMatchTheCalculator(): void
    {
        $source = (string) file_get_contents((string) new ReflectionClass(InfantFormulaScoreCalculator::class)->getFileName());
        preg_match_all("/ruleLabel: '((?:[^'\\\\]|\\\\.)*)',\\s*pointsImpact: (-?\\d+)/", $source, $m, \PREG_SET_ORDER);
        $calculator = [];
        foreach ($m as [, $label, $points]) {
            $calculator[stripslashes($label)] = (int) $points;
        }
        self::assertNotEmpty($calculator);

        $shown = new InfoDataProvider($this->createStub(ScoringRuleRepository::class))->getInfoData()['infantFormulaRules'];
        self::assertIsArray($shown);

        $displayed = [];
        foreach ($shown as $rule) {
            $displayed[$rule['label']] = $rule['points'];
        }

        ksort($calculator);
        ksort($displayed);
        self::assertSame($calculator, $displayed);
    }
}
