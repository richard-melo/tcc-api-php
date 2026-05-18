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
