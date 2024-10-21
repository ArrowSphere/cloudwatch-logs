<?php

declare(strict_types=1);

namespace ArrowSphere\CloudWatchLogs\Formatter;

use Monolog\LogRecord;
use ArrowSphere\CloudWatchLogs\Processor\ArsHeader\ArsRequestIdentifierEnum;
use Monolog\Formatter\JsonFormatter;
use RuntimeException;

/**
 * Class ArsJsonFormatter
 */
final class ArsJsonFormatter extends JsonFormatter
{
    public function format(LogRecord $record): string
    {
        $normalized = $this->normalizeRecord($record);
        if (! is_array($normalized)) {
            throw new RuntimeException('Cannot get a normalized array to format the record logs');
        }

        if ($this->ignoreEmptyContextAndExtra) {
            if (empty($normalized['context'])) {
                unset($normalized['context']);
            }
            if (empty($normalized['extra'])) {
                unset($normalized['extra']);
            }
        }

        $context = $normalized['context'] ?? [];
        $extra = $normalized['extra'] ?? [];

        $arsRecord = [
            'type' => $normalized['level_name'] ?? 'UNKNOWN',
            'message' => $normalized['message'],
            'tags' => $context['tags'] ?? [],
            'entries' => $context['entries'] ?? [],
        ];

        if (isset(
            $extra[ArsRequestIdentifierEnum::ARS_CORRELATION_ID],
            $extra[ArsRequestIdentifierEnum::ARS_REQUEST_ID],
            $extra[ArsRequestIdentifierEnum::ARS_PARENT_ID]
        )) {
            $arsRecord = array_merge($arsRecord, [
                'ars' => [
                    'correlation' => $extra[ArsRequestIdentifierEnum::ARS_CORRELATION_ID],
                    'request' => $extra[ArsRequestIdentifierEnum::ARS_REQUEST_ID],
                    'parent' => $extra[ArsRequestIdentifierEnum::ARS_PARENT_ID],
                ],
            ]);
            unset(
                $extra[ArsRequestIdentifierEnum::ARS_CORRELATION_ID],
                $extra[ArsRequestIdentifierEnum::ARS_REQUEST_ID],
                $extra[ArsRequestIdentifierEnum::ARS_PARENT_ID]
            );
        }

        if (! empty($extra)) {
            $context['extra'] = $extra;
        }

        if (! empty($context)) {
            $arsRecord = array_merge($arsRecord, [
                'context' => $context,
            ]);
        }

        return $this->toJson($arsRecord, true) . ($this->appendNewline ? "\n" : '');
    }
}
