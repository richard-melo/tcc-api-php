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
