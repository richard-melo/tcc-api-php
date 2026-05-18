#!/usr/bin/env bash
# Cenário 2 — Heavy PHPStan
# Cria 6 classes de serviço com tipagem complexa para estressar a análise estática.
# Branch: scenario/heavy-phpstan
#
# Uso: ./metrics/scenarios/run_scenario2_heavy_phpstan.sh

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/_common.sh"

SCENARIO="heavy-phpstan"
BRANCH="scenario/heavy-phpstan"

log_h "Cenário 2 — Heavy PHPStan"
echo "  Branch    : $BRANCH"
echo "  Objetivo  : estressar PHPStan com +6 classes de serviço (tipagem forte)"

prepare_branch "$BRANCH"

# ── Cria as 6 classes de serviço ───────────────────────────────────────────────
log_h "Criando classes de serviço"

# ── StatisticsService ──────────────────────────────────────────────────────────
cat > "$REPO_DIR/src/Services/StatisticsService.php" << 'PHPEOF'
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
PHPEOF
log_ok "StatisticsService.php"

# ── NotificationService ────────────────────────────────────────────────────────
cat > "$REPO_DIR/src/Services/NotificationService.php" << 'PHPEOF'
<?php

declare(strict_types=1);

namespace App\Services;

class NotificationService
{
    /** @var array<int, array<string, string>> */
    private array $history = [];

    /** @var array<int, array<string, string>> */
    private array $queue = [];

    public function send(string $channel, string $message, string $level = 'info'): void
    {
        $this->history[] = [
            'channel'   => $channel,
            'message'   => $this->formatMessage($message, $level),
            'level'     => $level,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param array<int, array<string, string>> $messages
     */
    public function sendBatch(array $messages): int
    {
        $count = 0;
        foreach ($messages as $msg) {
            $channel = $msg['channel'] ?? 'default';
            $text    = $msg['message'] ?? '';
            $level   = $msg['level']   ?? 'info';
            if ($text !== '') {
                $this->send($channel, $text, $level);
                $count++;
            }
        }
        return $count;
    }

    public function queue(string $channel, string $message): void
    {
        $this->queue[] = ['channel' => $channel, 'message' => $message, 'level' => 'info'];
    }

    public function flush(): int
    {
        $count = count($this->queue);
        foreach ($this->queue as $item) {
            $this->send($item['channel'], $item['message'], $item['level']);
        }
        $this->queue = [];
        return $count;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function getHistory(): array
    {
        return $this->history;
    }

    public function clearHistory(): void
    {
        $this->history = [];
    }

    public function formatMessage(string $message, string $level): string
    {
        $prefix = match ($level) {
            'error'   => '[ERROR]',
            'warning' => '[WARN ]',
            'info'    => '[INFO ]',
            'debug'   => '[DEBUG]',
            default   => '[LOG  ]',
        };
        return "$prefix $message";
    }

    public function countByLevel(string $level): int
    {
        return count(array_filter(
            $this->history,
            static fn(array $entry): bool => $entry['level'] === $level
        ));
    }
}
PHPEOF
log_ok "NotificationService.php"

# ── CacheManager ───────────────────────────────────────────────────────────────
cat > "$REPO_DIR/src/Services/CacheManager.php" << 'PHPEOF'
<?php

declare(strict_types=1);

namespace App\Services;

class CacheManager
{
    /** @var array<string, mixed> */
    private array $store = [];

    /** @var array<string, int> */
    private array $expiry = [];

    public function set(string $key, mixed $value, int $ttl = 3600): void
    {
        $this->store[$key]  = $value;
        $this->expiry[$key] = time() + $ttl;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) {
            return $default;
        }
        return $this->store[$key];
    }

    public function has(string $key): bool
    {
        if (!array_key_exists($key, $this->store)) {
            return false;
        }
        if (time() > ($this->expiry[$key] ?? 0)) {
            unset($this->store[$key], $this->expiry[$key]);
            return false;
        }
        return true;
    }

    public function delete(string $key): bool
    {
        if (!array_key_exists($key, $this->store)) {
            return false;
        }
        unset($this->store[$key], $this->expiry[$key]);
        return true;
    }

    public function flush(): void
    {
        $this->store  = [];
        $this->expiry = [];
    }

    public function increment(string $key, int $by = 1): int
    {
        $current = $this->has($key) ? (int) $this->store[$key] : 0;
        $new     = $current + $by;
        $this->set($key, $new);
        return $new;
    }

    public function decrement(string $key, int $by = 1): int
    {
        return $this->increment($key, -$by);
    }

    /**
     * @param callable(): mixed $callback
     */
    public function remember(string $key, callable $callback, int $ttl = 3600): mixed
    {
        if ($this->has($key)) {
            return $this->get($key);
        }
        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }
}
PHPEOF
log_ok "CacheManager.php"

# ── DataTransformer ────────────────────────────────────────────────────────────
cat > "$REPO_DIR/src/Services/DataTransformer.php" << 'PHPEOF'
<?php

declare(strict_types=1);

namespace App\Services;

class DataTransformer
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function keysToSnakeCase(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $snake = strtolower((string) preg_replace('/[A-Z]/', '_$0', lcfirst($key)));
            $result[$snake] = $value;
        }
        return $result;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function keysToCamelCase(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $camel = lcfirst(str_replace('_', '', ucwords($key, '_')));
            $result[$camel] = $value;
        }
        return $result;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function flatten(array $data, string $prefix = '', string $separator = '.'): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $fullKey = $prefix !== '' ? $prefix . $separator . $key : $key;
            if (is_array($value)) {
                $result += $this->flatten($value, $fullKey, $separator);
            } else {
                $result[$fullKey] = $value;
            }
        }
        return $result;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    public function pick(array $data, array $keys): array
    {
        return array_intersect_key($data, array_flip($keys));
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    public function omit(array $data, array $keys): array
    {
        return array_diff_key($data, array_flip($keys));
    }

    /**
     * @param array<string, mixed> $data
     * @param callable(mixed): mixed $fn
     * @return array<string, mixed>
     */
    public function mapValues(array $data, callable $fn): array
    {
        return array_map($fn, $data);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, list<array<string, mixed>>>
     */
    public function groupBy(array $items, string $key): array
    {
        $result = [];
        foreach ($items as $item) {
            $group = (string) ($item[$key] ?? '');
            $result[$group][] = $item;
        }
        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    public function unique(array $items, string $key): array
    {
        $seen   = [];
        $result = [];
        foreach ($items as $item) {
            $val = (string) ($item[$key] ?? '');
            if (!isset($seen[$val])) {
                $seen[$val] = true;
                $result[]   = $item;
            }
        }
        return $result;
    }
}
PHPEOF
log_ok "DataTransformer.php"

# ── ReportGenerator ────────────────────────────────────────────────────────────
cat > "$REPO_DIR/src/Services/ReportGenerator.php" << 'PHPEOF'
<?php

declare(strict_types=1);

namespace App\Services;

class ReportGenerator
{
    private string $title = '';

    /** @var array<int, array<string, mixed>> */
    private array $sections = [];

    private ?string $dateFrom = null;
    private ?string $dateTo   = null;

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function addSection(string $heading, array $data): self
    {
        $this->sections[] = ['heading' => $heading, 'data' => $data];
        return $this;
    }

    public function setDateRange(string $from, string $to): self
    {
        $this->dateFrom = $from;
        $this->dateTo   = $to;
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function generate(): array
    {
        return [
            'title'    => $this->title,
            'sections' => $this->sections,
            'from'     => $this->dateFrom,
            'to'       => $this->dateTo,
            'count'    => $this->sectionCount(),
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function generateSummary(): string
    {
        $lines = ["Report: {$this->title}"];
        if ($this->dateFrom !== null && $this->dateTo !== null) {
            $lines[] = "Period: {$this->dateFrom} to {$this->dateTo}";
        }
        $lines[] = "Sections: {$this->sectionCount()}";
        foreach ($this->sections as $section) {
            $heading = (string) ($section['heading'] ?? '');
            $count   = is_array($section['data']) ? count($section['data']) : 0;
            $lines[] = "  - $heading ($count items)";
        }
        return implode("\n", $lines);
    }

    public function reset(): self
    {
        $this->title    = '';
        $this->sections = [];
        $this->dateFrom = null;
        $this->dateTo   = null;
        return $this;
    }

    public function sectionCount(): int
    {
        return count($this->sections);
    }

    public function hasData(): bool
    {
        return $this->title !== '' && count($this->sections) > 0;
    }
}
PHPEOF
log_ok "ReportGenerator.php"

# ── AnalyticsService ───────────────────────────────────────────────────────────
cat > "$REPO_DIR/src/Services/AnalyticsService.php" << 'PHPEOF'
<?php

declare(strict_types=1);

namespace App\Services;

class AnalyticsService
{
    /** @var array<int, array<string, int|string>> */
    private array $events = [];

    public function track(string $event, string $category = 'general'): void
    {
        $this->events[] = [
            'event'    => $event,
            'category' => $category,
            'ts'       => time(),
        ];
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    public function getEvents(?string $category = null): array
    {
        if ($category === null) {
            return $this->events;
        }
        return array_values(array_filter(
            $this->events,
            static fn(array $e): bool => $e['category'] === $category
        ));
    }

    /**
     * @return array<string, int>
     */
    public function countByEvent(): array
    {
        $result = [];
        foreach ($this->events as $e) {
            $name = (string) $e['event'];
            $result[$name] = ($result[$name] ?? 0) + 1;
        }
        return $result;
    }

    /**
     * @return array<string, int>
     */
    public function countByCategory(): array
    {
        $result = [];
        foreach ($this->events as $e) {
            $cat = (string) $e['category'];
            $result[$cat] = ($result[$cat] ?? 0) + 1;
        }
        return $result;
    }

    /**
     * @return array<string, int>
     */
    public function topEvents(int $n = 5): array
    {
        $counts = $this->countByEvent();
        arsort($counts);
        return array_slice($counts, 0, $n, true);
    }

    /**
     * @return array<int, int>
     */
    public function trend(string $event): array
    {
        $result = [];
        foreach ($this->events as $e) {
            if ($e['event'] !== $event) {
                continue;
            }
            $day = (int) date('Ymd', (int) $e['ts']);
            $result[$day] = ($result[$day] ?? 0) + 1;
        }
        ksort($result);
        return $result;
    }

    public function clear(): void
    {
        $this->events = [];
    }

    public function totalCount(): int
    {
        return count($this->events);
    }
}
PHPEOF
log_ok "AnalyticsService.php"

# ── 3. Commit e push ───────────────────────────────────────────────────────────
log_h "Commit e Push"
commit_push "scenario(heavy-phpstan): adiciona 6 classes de serviço com tipagem forte"

# ── 4. Coleta métricas ─────────────────────────────────────────────────────────
collect_all "$SCENARIO"

cd "$REPO_DIR"
git checkout main
log_ok "De volta para main. Branch '$BRANCH' mantida no GitHub."
