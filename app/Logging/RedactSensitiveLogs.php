<?php

namespace App\Logging;

use Monolog\Logger;
use Monolog\LogRecord;

class RedactSensitiveLogs
{
    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(function (LogRecord $record): LogRecord {
            return $record->with(
                context: $this->redact($record->context),
                extra: $this->redact($record->extra),
            );
        });
    }

    private function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string) $key);

            if (
                str_contains($lowerKey, 'password') ||
                str_contains($lowerKey, 'secret') ||
                str_contains($lowerKey, 'token') ||
                str_contains($lowerKey, 'api_key') ||
                str_contains($lowerKey, 'authorization')
            ) {
                $data[$key] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->redact($value);
            }
        }

        return $data;
    }
}