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
