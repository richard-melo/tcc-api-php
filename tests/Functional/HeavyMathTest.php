<?php

declare(strict_types=1);

namespace Tests\Functional;

use Tests\Support\FunctionalTestCase;

class HeavyMathTest extends FunctionalTestCase
{
    /**
     * @dataProvider mathOperationsProvider
     */
    public function testArithmeticOperations(
        float $a,
        float $b,
        float $expectedSum,
        float $expectedDiff,
        float $expectedProduct,
    ): void {
        usleep(8_000);

        $this->assertEqualsWithDelta($expectedSum, $a + $b, 0.001);
        $this->assertEqualsWithDelta($expectedDiff, $a - $b, 0.001);
        $this->assertEqualsWithDelta($expectedProduct, $a * $b, 0.001);
    }

    /**
     * @return array<string, array{float, float, float, float, float}>
     */
    public static function mathOperationsProvider(): array
    {
        $cases = [];
        for ($i = 1; $i <= 50; $i++) {
            $a = (float) $i;
            $b = (float) ($i * 2);
            $cases["caso_{$i}_inteiros"] = [$a, $b, $a + $b, $a - $b, $a * $b];

            $a2 = round($i * 1.5, 2);
            $b2 = round($i * 0.75, 2);
            $cases["caso_{$i}_decimais"] = [$a2, $b2, $a2 + $b2, $a2 - $b2, $a2 * $b2];
        }
        return $cases;
    }

    /**
     * @dataProvider statisticsProvider
     */
    public function testStatisticsOperations(
        float $sum,
        int $count,
        float $expectedMean,
        float $expectedMin,
        float $expectedMax,
    ): void {
        usleep(8_000);

        $mean = $count > 0 ? $sum / $count : 0.0;
        $this->assertEqualsWithDelta($expectedMean, $mean, 0.01);
        $this->assertLessThanOrEqual($expectedMax, $expectedMax);
        $this->assertGreaterThanOrEqual($expectedMin, $expectedMin);
    }

    /**
     * @return array<string, array{float, int, float, float, float}>
     */
    public static function statisticsProvider(): array
    {
        $cases = [];
        for ($i = 1; $i <= 50; $i++) {
            $count = $i + 1;
            $sum   = (float) ($i * $count);
            $mean  = round($sum / $count, 4);
            $cases["stats_{$i}"] = [$sum, $count, $mean, 0.0, $sum];
        }
        return $cases;
    }
}
