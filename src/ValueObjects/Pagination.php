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
