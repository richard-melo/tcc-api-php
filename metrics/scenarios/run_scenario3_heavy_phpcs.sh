#!/usr/bin/env bash
# Cenário 3 — Heavy PHPCS
# Cria 13 arquivos PHP pequenos (DTOs, Enums, ValueObjects, Helpers) para estressar o PHPCS.
# Branch: scenario/heavy-phpcs
#
# Uso: ./metrics/scenarios/run_scenario3_heavy_phpcs.sh

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/_common.sh"

SCENARIO="heavy-phpcs"
BRANCH="scenario/heavy-phpcs"

log_h "Cenário 3 — Heavy PHPCS"
echo "  Branch    : $BRANCH"
echo "  Objetivo  : estressar PHPCS com 13 arquivos adicionais em src/"

prepare_branch "$BRANCH"
mkdir -p "$REPO_DIR/src/DTOs" "$REPO_DIR/src/Enums" "$REPO_DIR/src/ValueObjects" "$REPO_DIR/src/Helpers"

log_h "Criando DTOs"

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
log_ok "4 DTOs criados"

log_h "Criando Enums"

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
log_ok "3 Enums criados"

log_h "Criando Value Objects"

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
log_ok "4 Value Objects criados"

log_h "Criando Helpers"

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
        $lower    = strtolower($s);
        $ascii    = (string) preg_replace('/[^a-z0-9\s-]/', '', $lower);
        $trimmed  = trim($ascii, ' -');
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
log_ok "3 Helpers criados"

# ── Commit e push ───────────────────────────────────────────────────────────────
log_h "Commit e Push"
commit_push "scenario(heavy-phpcs): adiciona 13 arquivos (DTOs, Enums, ValueObjects, Helpers)"

collect_all "$SCENARIO"

cd "$REPO_DIR"
git checkout main
log_ok "De volta para main. Branch '$BRANCH' mantida no GitHub."
