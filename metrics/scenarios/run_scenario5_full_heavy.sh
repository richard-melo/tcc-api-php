#!/usr/bin/env bash
# Cenário 5 — Full Heavy
# Combina C1+C2+C3+C4: pacotes pesados + 6 serviços + 13 arquivos + 180 testes.
# Objetivo: estressar TODOS os stages simultaneamente.
# Branch: scenario/full-heavy
#
# Uso: ./metrics/scenarios/run_scenario5_full_heavy.sh

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/_common.sh"

SCENARIO="full-heavy"
BRANCH="scenario/full-heavy"

log_h "Cenário 5 — Full Heavy"
echo "  Branch    : $BRANCH"
echo "  Objetivo  : estressar todos os stages (composer + phpstan + phpcs + phpunit)"

prepare_branch "$BRANCH"

# ── C1: pacotes pesados no composer.json ───────────────────────────────────────
log_h "C1 — Modificando composer.json"
python3 - << 'PY'
import json

path = 'composer.json'
with open(path) as f:
    data = json.load(f)

data.setdefault('require', {}).update({
    'monolog/monolog':   '^3.0',
    'symfony/validator': '^6.4',
    'guzzlehttp/guzzle': '^7.0',
    'ramsey/uuid':       '^4.0',
})

with open(path, 'w') as f:
    json.dump(data, f, indent=4, ensure_ascii=False)
    f.write('\n')

print("  composer.json atualizado com 4 pacotes novos")
PY

rm -f composer.lock
log_ok "composer.lock removido"

# ── C2: 6 classes de serviço (PHPStan) ────────────────────────────────────────
log_h "C2 — Criando 6 classes de serviço"

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
            'title'        => $this->title,
            'sections'     => $this->sections,
            'from'         => $this->dateFrom,
            'to'           => $this->dateTo,
            'count'        => $this->sectionCount(),
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
log_ok "6 serviços criados"

# ── C3: DTOs, Enums, ValueObjects, Helpers ────────────────────────────────────
log_h "C3 — Criando 13 arquivos (DTOs, Enums, ValueObjects, Helpers)"
mkdir -p "$REPO_DIR/src/DTOs" "$REPO_DIR/src/Enums" "$REPO_DIR/src/ValueObjects" "$REPO_DIR/src/Helpers"

cat > "$REPO_DIR/src/DTOs/ExpenseDTO.php" << 'PHPEOF'
<?php

declare(strict_types=1);

namespace App\DTOs;

final class ExpenseDTO
{
    public function __construct(
        public readonly string $description,
        public readonly float $amount,
        public readonly string $category,
        public readonly string $date,
        public readonly int $userId,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'description' => $this->description,
            'amount'      => $this->amount,
            'category'    => $this->category,
            'date'        => $this->date,
            'user_id'     => $this->userId,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            description: (string) ($data['description'] ?? ''),
            amount:      (float)  ($data['amount']      ?? 0.0),
            category:    (string) ($data['category']    ?? ''),
            date:        (string) ($data['date']        ?? ''),
            userId:      (int)    ($data['user_id']     ?? 0),
        );
    }
}
PHPEOF

cat > "$REPO_DIR/src/DTOs/BudgetDTO.php" << 'PHPEOF'
<?php

declare(strict_types=1);

namespace App\DTOs;

final class BudgetDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly string $category,
        public readonly float $amount,
        public readonly int $month,
        public readonly int $year,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'user_id'  => $this->userId,
            'category' => $this->category,
            'amount'   => $this->amount,
            'month'    => $this->month,
            'year'     => $this->year,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            userId:   (int)    ($data['user_id']  ?? 0),
            category: (string) ($data['category'] ?? ''),
            amount:   (float)  ($data['amount']   ?? 0.0),
            month:    (int)    ($data['month']     ?? 1),
            year:     (int)    ($data['year']      ?? (int) date('Y')),
        );
    }
}
PHPEOF

cat > "$REPO_DIR/src/DTOs/UserDTO.php" << 'PHPEOF'
<?php

declare(strict_types=1);

namespace App\DTOs;

final class UserDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'name'       => $this->name,
            'email'      => $this->email,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
PHPEOF

cat > "$REPO_DIR/src/DTOs/SummaryDTO.php" << 'PHPEOF'
<?php

declare(strict_types=1);

namespace App\DTOs;

final class SummaryDTO
{
    public function __construct(
        public readonly int $total,
        public readonly float $sum,
        public readonly float $average,
        public readonly float $max,
        public readonly float $min,
    ) {
    }

    /** @return array<string, int|float> */
    public function toArray(): array
    {
        return [
            'total'   => $this->total,
            'sum'     => $this->sum,
            'average' => $this->average,
            'max'     => $this->max,
            'min'     => $this->min,
        ];
    }

    public function range(): float
    {
        return $this->max - $this->min;
    }
}
PHPEOF

cat > "$REPO_DIR/src/Enums/ExpenseCategory.php" << 'PHPEOF'
<?php

declare(strict_types=1);

namespace App\Enums;

enum ExpenseCategory: string
{
    case Food          = 'food';
    case Transport     = 'transport';
    case Housing       = 'housing';
    case Health        = 'health';
    case Entertainment = 'entertainment';
    case Education     = 'education';
    case Other         = 'other';

    public function label(): string
    {
        return ucfirst($this->name);
    }

    public function isEssential(): bool
    {
        return match ($this) {
            self::Food, self::Housing, self::Health => true,
            default                                 => false,
        };
    }
}
PHPEOF

cat > "$REPO_DIR/src/Enums/AlertLevel.php" << 'PHPEOF'
<?php

declare(strict_types=1);

namespace App\Enums;

enum AlertLevel: string
{
    case None     = 'none';
    case Warning  = 'warning';
    case Exceeded = 'exceeded';

    public function isAlert(): bool
    {
        return $this !== self::None;
    }

    public function color(): string
    {
        return match ($this) {
            self::None     => 'green',
            self::Warning  => 'yellow',
            self::Exceeded => 'red',
        };
    }
}
PHPEOF

cat > "$REPO_DIR/src/Enums/ExportFormat.php" << 'PHPEOF'
<?php

declare(strict_types=1);

namespace App\Enums;

enum ExportFormat: string
{
    case Csv  = 'csv';
    case Json = 'json';
    case Xml  = 'xml';

    public function mimeType(): string
    {
        return match ($this) {
            self::Csv  => 'text/csv',
            self::Json => 'application/json',
            self::Xml  => 'application/xml',
        };
    }

    public function extension(): string
    {
        return $this->value;
    }
}
PHPEOF

cat > "$REPO_DIR/src/ValueObjects/Money.php" << 'PHPEOF'
<?php

declare(strict_types=1);

namespace App\ValueObjects;

final class Money
{
    public function __construct(
        private readonly float $amount,
        private readonly string $currency = 'BRL',
    ) {
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function add(Money $other): self
    {
        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(Money $other): self
    {
        return new self($this->amount - $other->amount, $this->currency);
    }

    public function multiply(float $factor): self
    {
        return new self($this->amount * $factor, $this->currency);
    }

    public function format(): string
    {
        return number_format($this->amount, 2, ',', '.') . ' ' . $this->currency;
    }

    public function isZero(): bool
    {
        return abs($this->amount) < 0.001;
    }

    public function equals(Money $other): bool
    {
        return $this->currency === $other->currency
            && abs($this->amount - $other->amount) < 0.001;
    }
}
PHPEOF

cat > "$REPO_DIR/src/ValueObjects/DateRange.php" << 'PHPEOF'
<?php

declare(strict_types=1);

namespace App\ValueObjects;

final class DateRange
{
    public function __construct(
        private readonly \DateTimeImmutable $from,
        private readonly \DateTimeImmutable $to,
    ) {
        if ($from > $to) {
            throw new \InvalidArgumentException('DateRange: from must be <= to');
        }
    }

    public function getFrom(): \DateTimeImmutable
    {
        return $this->from;
    }

    public function getTo(): \DateTimeImmutable
    {
        return $this->to;
    }

    public function days(): int
    {
        return (int) $this->from->diff($this->to)->days;
    }

    public function contains(\DateTimeImmutable $date): bool
    {
        return $date >= $this->from && $date <= $this->to;
    }

    public function overlaps(DateRange $other): bool
    {
        return $this->from <= $other->to && $this->to >= $other->from;
    }
}
PHPEOF

cat > "$REPO_DIR/src/ValueObjects/Pagination.php" << 'PHPEOF'
<?php

declare(strict_types=1);

namespace App\ValueObjects;

final class Pagination
{
    public function __construct(
        private readonly int $page = 1,
        private readonly int $perPage = 15,
    ) {
        if ($page < 1) {
            throw new \InvalidArgumentException('Page must be >= 1');
        }
        if ($perPage < 1 || $perPage > 200) {
            throw new \InvalidArgumentException('PerPage must be between 1 and 200');
        }
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    public function limit(): int
    {
        return $this->perPage;
    }

    public function nextPage(): self
    {
        return new self($this->page + 1, $this->perPage);
    }

    public function prevPage(): self
    {
        return new self(max(1, $this->page - 1), $this->perPage);
    }
}
PHPEOF

cat > "$REPO_DIR/src/ValueObjects/Period.php" << 'PHPEOF'
<?php

declare(strict_types=1);

namespace App\ValueObjects;

final class Period
{
    public function __construct(
        private readonly int $month,
        private readonly int $year,
    ) {
        if ($month < 1 || $month > 12) {
            throw new \InvalidArgumentException('Month must be between 1 and 12');
        }
    }

    public function getMonth(): int
    {
        return $this->month;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function label(): string
    {
        return sprintf('%04d-%02d', $this->year, $this->month);
    }

    public function daysInMonth(): int
    {
        return (int) date('t', mktime(0, 0, 0, $this->month, 1, $this->year));
    }

    public function startDate(): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('Y-n-j', "{$this->year}-{$this->month}-1");
        if ($date === false) {
            throw new \RuntimeException('Failed to create start date');
        }
        return $date;
    }

    public function endDate(): \DateTimeImmutable
    {
        $days = $this->daysInMonth();
        $date = \DateTimeImmutable::createFromFormat('Y-n-j', "{$this->year}-{$this->month}-{$days}");
        if ($date === false) {
            throw new \RuntimeException('Failed to create end date');
        }
        return $date;
    }
}
PHPEOF

cat > "$REPO_DIR/src/Helpers/ArrayHelper.php" << 'PHPEOF'
<?php

declare(strict_types=1);

namespace App\Helpers;

final class ArrayHelper
{
    /**
     * @param array<mixed> $arr
     * @return array<mixed>
     */
    public static function flatten(array $arr, int $depth = PHP_INT_MAX): array
    {
        $result = [];
        foreach ($arr as $item) {
            if (is_array($item) && $depth > 0) {
                foreach (self::flatten($item, $depth - 1) as $v) {
                    $result[] = $v;
                }
            } else {
                $result[] = $item;
            }
        }
        return $result;
    }

    /**
     * @param array<mixed> $arr
     * @return array<mixed>
     */
    public static function unique(array $arr): array
    {
        return array_values(array_unique($arr));
    }

    /**
     * @param array<mixed> $arr
     * @return array<int, array<mixed>>
     */
    public static function chunk(array $arr, int $size): array
    {
        return array_chunk($arr, max(1, $size));
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return list<mixed>
     */
    public static function pluck(array $items, string $key): array
    {
        return array_values(array_map(
            static fn(array $item): mixed => $item[$key] ?? null,
            $items
        ));
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, array<string, mixed>>
     */
    public static function indexBy(array $items, string $key): array
    {
        $result = [];
        foreach ($items as $item) {
            $result[(string) ($item[$key] ?? '')] = $item;
        }
        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public static function sum(array $items, string $key): float
    {
        return (float) array_sum(array_map(
            static fn(array $item): float => (float) ($item[$key] ?? 0),
            $items
        ));
    }

    /** @param array<mixed> $arr */
    public static function first(array $arr): mixed
    {
        return $arr[array_key_first($arr) ?? 0] ?? null;
    }

    /** @param array<mixed> $arr */
    public static function last(array $arr): mixed
    {
        return $arr[array_key_last($arr) ?? 0] ?? null;
    }
}
PHPEOF

cat > "$REPO_DIR/src/Helpers/StringHelper.php" << 'PHPEOF'
<?php

declare(strict_types=1);

namespace App\Helpers;

final class StringHelper
{
    public static function toSnakeCase(string $s): string
    {
        $result = (string) preg_replace('/[A-Z]/', '_$0', lcfirst($s));
        return strtolower($result);
    }

    public static function toCamelCase(string $s): string
    {
        return lcfirst(str_replace('_', '', ucwords($s, '_')));
    }

    public static function truncate(string $s, int $max, string $suffix = '...'): string
    {
        if (mb_strlen($s) <= $max) {
            return $s;
        }
        return mb_substr($s, 0, $max - mb_strlen($suffix)) . $suffix;
    }

    public static function slug(string $s): string
    {
        $lower   = strtolower($s);
        $ascii   = (string) preg_replace('/[^a-z0-9\s-]/', '', $lower);
        $trimmed = trim($ascii, ' -');
        return (string) preg_replace('/[\s-]+/', '-', $trimmed);
    }

    public static function excerpt(string $s, int $words): string
    {
        $parts = explode(' ', $s);
        if (count($parts) <= $words) {
            return $s;
        }
        return implode(' ', array_slice($parts, 0, $words)) . '...';
    }

    public static function wordCount(string $s): int
    {
        $trimmed = trim($s);
        if ($trimmed === '') {
            return 0;
        }
        return count(preg_split('/\s+/', $trimmed) ?: []);
    }

    public static function contains(string $haystack, string $needle): bool
    {
        return str_contains($haystack, $needle);
    }

    public static function startsWith(string $s, string $prefix): bool
    {
        return str_starts_with($s, $prefix);
    }
}
PHPEOF

cat > "$REPO_DIR/src/Helpers/DateHelper.php" << 'PHPEOF'
<?php

declare(strict_types=1);

namespace App\Helpers;

final class DateHelper
{
    public static function startOfMonth(int $month, int $year): \DateTimeImmutable
    {
        $d = \DateTimeImmutable::createFromFormat('Y-n-j', "{$year}-{$month}-1");
        if ($d === false) {
            throw new \RuntimeException('Invalid month/year');
        }
        return $d;
    }

    public static function endOfMonth(int $month, int $year): \DateTimeImmutable
    {
        $days = self::daysInMonth($month, $year);
        $d    = \DateTimeImmutable::createFromFormat('Y-n-j', "{$year}-{$month}-{$days}");
        if ($d === false) {
            throw new \RuntimeException('Invalid month/year');
        }
        return $d;
    }

    public static function daysInMonth(int $month, int $year): int
    {
        return (int) date('t', mktime(0, 0, 0, $month, 1, $year));
    }

    public static function isWeekend(\DateTimeImmutable $date): bool
    {
        return in_array((int) $date->format('N'), [6, 7], true);
    }

    public static function diffInDays(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) $from->diff($to)->days;
    }

    public static function formatBr(\DateTimeImmutable $date): string
    {
        return $date->format('d/m/Y');
    }

    public static function parseDate(string $s): ?\DateTimeImmutable
    {
        $d = \DateTimeImmutable::createFromFormat('Y-m-d', $s);
        return $d !== false ? $d : null;
    }

    public static function today(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('today');
    }
}
PHPEOF
log_ok "13 arquivos criados (DTOs, Enums, ValueObjects, Helpers)"

# ── C4: 3 arquivos de teste pesados ───────────────────────────────────────────
log_h "C4 — Criando 3 arquivos de teste pesados"

cat > "$REPO_DIR/tests/Functional/HeavyMathTest.php" << 'PHPEOF'
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
PHPEOF

cat > "$REPO_DIR/tests/Functional/HeavyStringTest.php" << 'PHPEOF'
<?php

declare(strict_types=1);

namespace Tests\Functional;

use Tests\Support\FunctionalTestCase;

class HeavyStringTest extends FunctionalTestCase
{
    private string $tmpDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/phpunit_heavy_' . uniqid('', true);
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
        parent::tearDown();
    }

    /**
     * @dataProvider stringTransformProvider
     */
    public function testStringTransformations(
        string $input,
        string $expectedUpper,
        string $expectedLower,
        int $expectedLength,
    ): void {
        usleep(10_000);

        $path    = $this->tmpDir . '/' . uniqid('str_', true) . '.json';
        $payload = json_encode(['input' => $input, 'ts' => microtime(true)]);
        file_put_contents($path, (string) $payload);
        $recovered = json_decode((string) file_get_contents($path), true);

        $this->assertIsArray($recovered);
        $this->assertEquals($input, $recovered['input']);
        $this->assertEquals($expectedUpper, strtoupper($input));
        $this->assertEquals($expectedLower, strtolower($input));
        $this->assertEquals($expectedLength, strlen($input));
    }

    /**
     * @return array<string, array{string, string, string, int}>
     */
    public static function stringTransformProvider(): array
    {
        $words = [
            'apple', 'banana', 'cherry', 'dragonfruit', 'elderberry',
            'fig', 'grape', 'honeydew', 'kiwi', 'lemon',
            'mango', 'nectarine', 'orange', 'papaya', 'quince',
            'raspberry', 'strawberry', 'tangerine', 'ugli', 'watermelon',
            'apricot', 'blueberry', 'cantaloupe', 'date', 'guava',
            'jackfruit', 'kumquat', 'lychee', 'mulberry', 'olive',
            'peach', 'pear', 'plum', 'pomegranate', 'starfruit',
            'avocado', 'coconut', 'durian', 'feijoa', 'gooseberry',
            'huckleberry', 'ackee', 'bilberry', 'boysenberry', 'clementine',
            'damson', 'dewberry', 'elderflower', 'lingonberry', 'loganberry',
        ];
        $cases = [];
        foreach ($words as $word) {
            $cases[$word] = [$word, strtoupper($word), strtolower($word), strlen($word)];
        }
        return $cases;
    }
}
PHPEOF

cat > "$REPO_DIR/tests/Functional/HeavyFlowTest.php" << 'PHPEOF'
<?php

declare(strict_types=1);

namespace Tests\Functional;

use Tests\Support\FunctionalTestCase;

class HeavyFlowTest extends FunctionalTestCase
{
    private string $tmpDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/phpunit_flow_' . uniqid('', true);
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
        parent::tearDown();
    }

    /**
     * @dataProvider expenseFlowProvider
     */
    public function testCompleteExpenseLifecycle(
        string $userSuffix,
        float $amount,
        string $category,
        string $description,
        float $updatedAmount,
    ): void {
        usleep(15_000);

        $token = $this->registerAndLogin(
            "User $userSuffix",
            "heavy_{$userSuffix}@test.com",
            'Password123!'
        );
        $this->assertNotEmpty($token, "Token vazio para $userSuffix");
        $this->withAuth($token);

        $created = $this->callExpense('store', [
            'description'    => $description,
            'amount'         => $amount,
            'category'       => $category,
            'payment_method' => 'pix',
            'expense_date'   => date('Y-m-d'),
        ]);
        $expenseId = $created['id'] ?? 0;
        $this->assertGreaterThan(0, $expenseId, "ID da despesa inválido");
        $this->assertEqualsWithDelta($amount, $created['amount'] ?? -1.0, 0.01);

        $updated = $this->callExpense('update', [
            'description'    => $description . ' (atualizado)',
            'amount'         => $updatedAmount,
            'category'       => $category,
            'payment_method' => 'pix',
            'expense_date'   => date('Y-m-d'),
        ], $expenseId);
        $this->assertEqualsWithDelta($updatedAmount, $updated['amount'] ?? -1.0, 0.01);

        $list = $this->callExpense('index');
        $this->assertIsArray($list);

        $statePath = $this->tmpDir . "/flow_{$userSuffix}.json";
        file_put_contents($statePath, (string) json_encode([
            'user'      => $userSuffix,
            'expense'   => $expenseId,
            'amount'    => $updatedAmount,
            'completed' => true,
        ]));
        $state = json_decode((string) file_get_contents($statePath), true);
        $this->assertIsArray($state);
        $this->assertTrue($state['completed'] ?? false);
    }

    /**
     * @return array<string, array{string, float, string, string, float}>
     */
    public static function expenseFlowProvider(): array
    {
        $categories = ['alimentacao', 'transporte', 'moradia', 'saude', 'lazer'];
        $cases      = [];

        for ($i = 1; $i <= 30; $i++) {
            $cat  = $categories[$i % count($categories)];
            $amt  = round(10.0 + $i * 7.5, 2);
            $upd  = round($amt * 1.1, 2);
            $desc = "Despesa de teste #{$i} para cenario full-heavy";
            $cases["fluxo_{$i}_{$cat}"] = ["u{$i}", $amt, $cat, $desc, $upd];
        }

        return $cases;
    }
}
PHPEOF
log_ok "3 testes pesados criados"

# ── Commit e push ───────────────────────────────────────────────────────────────
log_h "Commit e Push"
commit_push "scenario(full-heavy): combina C1+C2+C3+C4 — estresse total do pipeline"

collect_all "$SCENARIO"

commit_metrics_to_main "$SCENARIO" "$GHA_NEW_LINE" "$JENKINS_NEW_LINE"
