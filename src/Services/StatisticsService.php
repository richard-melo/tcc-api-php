<?php

declare(strict_types=1);

namespace App\Services;

class StatisticsService
{
    /** @param array<int, int|float> $values */
    public function mean(array $values): float
    {
        $n = count($values);
        if ($n === 0) {
            return 0.0;
        }
        return (float) array_sum($values) / $n;
    }

    /** @param array<int, int|float> $values */
    public function median(array $values): float
    {
        if (count($values) === 0) {
            return 0.0;
        }
        $sorted = $values;
        sort($sorted);
        $n   = count($sorted);
        $mid = (int) ($n / 2);
        if ($n % 2 === 1) {
            return (float) $sorted[$mid];
        }
        return ((float) $sorted[$mid - 1] + (float) $sorted[$mid]) / 2.0;
    }

    /** @param array<int, int|float> $values */
    public function variance(array $values): float
    {
        $n = count($values);
        if ($n < 2) {
            return 0.0;
        }
        $mean = $this->mean($values);
        $sum  = 0.0;
        foreach ($values as $v) {
            $diff  = (float) $v - $mean;
            $sum  += $diff * $diff;
        }
        return $sum / (float) $n;
    }

    /** @param array<int, int|float> $values */
    public function standardDeviation(array $values): float
    {
        return sqrt($this->variance($values));
    }

    /** @param array<int, int|float> $values */
    public function percentile(array $values, float $p): float
    {
        if (count($values) === 0 || $p < 0.0 || $p > 100.0) {
            return 0.0;
        }
        $sorted = $values;
        sort($sorted);
        $index = ($p / 100.0) * (float) (count($sorted) - 1);
        $lower = (int) floor($index);
        $upper = (int) ceil($index);
        if ($lower === $upper) {
            return (float) $sorted[$lower];
        }
        $w = $index - (float) $lower;
        return (float) $sorted[$lower] * (1.0 - $w) + (float) $sorted[$upper] * $w;
    }

    /**
     * @param array<int, int|float> $values
     * @return list<float>
     */
    public function normalize(array $values): array
    {
        if (count($values) === 0) {
            return [];
        }
        $min   = (float) min($values);
        $max   = (float) max($values);
        $range = $max - $min;
        if ($range === 0.0) {
            return array_fill(0, count($values), 0.0);
        }
        return array_values(array_map(
            static fn(int|float $v): float => ((float) $v - $min) / $range,
            $values
        ));
    }

    /**
     * @param array<int, int|float> $values
     * @return list<float>
     */
    public function zscore(array $values): array
    {
        $n = count($values);
        if ($n < 2) {
            return array_fill(0, $n, 0.0);
        }
        $mean = $this->mean($values);
        $std  = $this->standardDeviation($values);
        if ($std === 0.0) {
            return array_fill(0, $n, 0.0);
        }
        return array_values(array_map(
            static fn(int|float $v): float => ((float) $v - $mean) / $std,
            $values
        ));
    }

    /**
     * @param array<int, int|float> $values
     * @return array<string, float>
     */
    public function describe(array $values): array
    {
        if (count($values) === 0) {
            return [
                'count' => 0.0, 'mean' => 0.0, 'std' => 0.0,
                'min'   => 0.0, 'p25'  => 0.0, 'p50' => 0.0,
                'p75'   => 0.0, 'max'  => 0.0,
            ];
        }
        $sorted = $values;
        sort($sorted);
        return [
            'count' => (float) count($values),
            'mean'  => $this->mean($values),
            'std'   => $this->standardDeviation($values),
            'min'   => (float) $sorted[0],
            'p25'   => $this->percentile($values, 25.0),
            'p50'   => $this->percentile($values, 50.0),
            'p75'   => $this->percentile($values, 75.0),
            'max'   => (float) $sorted[count($sorted) - 1],
        ];
    }
}
