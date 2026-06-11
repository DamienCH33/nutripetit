<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\ScoringRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ScoringRuleAppliesToAgeTest extends TestCase
{
    /**
     * @return iterable<string, array{?int, ?int, ?int, bool}>
     */
    public static function cases(): iterable
    {
        // [ageMin, ageMax, babyAge, attendu]
        yield 'sans borne, âge inconnu' => [null, null, null, true];
        yield 'sans borne, âge fourni' => [null, null, 12, true];
        yield 'borne min, âge inconnu' => [6, null, null, false];
        yield 'borne min, âge en dessous' => [6, null, 3, false];
        yield 'borne min, âge pile' => [6, null, 6, true];
        yield 'borne min, âge au dessus' => [6, null, 24, true];
        yield 'borne max, âge au dessus' => [null, 12, 24, false];
        yield 'borne max, âge pile' => [null, 12, 12, true];
        yield 'fourchette, dans la plage' => [6, 12, 9, true];
        yield 'fourchette, trop jeune' => [6, 12, 3, false];
        yield 'fourchette, trop vieux' => [6, 12, 18, false];
    }

    #[DataProvider('cases')]
    public function testAppliesToAge(?int $ageMin, ?int $ageMax, ?int $babyAge, bool $expected): void
    {
        $rule = new ScoringRule('code', 'label', '', '1.0.0', -10, 'source', 'https://example.test');
        $rule->setAgeMinMonths($ageMin);
        $rule->setAgeMaxMonths($ageMax);

        self::assertSame($expected, $rule->appliesToAge($babyAge));
    }
}
