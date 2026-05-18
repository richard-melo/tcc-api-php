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
