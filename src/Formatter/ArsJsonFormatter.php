<?php

declare(strict_types=1);

namespace ArrowSphere\CloudWatchLogs\Formatter;

use ArrowSphere\CloudWatchLogs\Processor\ArsHeader\ArsRequestIdentifierEnum;
use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;
use stdClass;

/**
 * Class ArsJsonFormatter
 *
 * @phpstan-type NormalizedRecord array{
 *     level: int,
 *     level_name?: string,
 *     message: string,
 *     datetime: string,
 *     context: array{
 *         tags: list<string>,
 *         entries: mixed,
 *         extra?: array<string, mixed>
 *     }|stdClass,
 *     extra: array<string, mixed>|stdClass,
 * }|array<void>
 */
final class ArsJsonFormatter extends JsonFormatter
{
    public function format(LogRecord $record): string
    {
        /** @var NormalizedRecord $normalized */
        $normalized = $this->normalizeRecord($record);

        if ($this->ignoreEmptyContextAndExtra) {
            if (empty($normalized['context'])) {
                unset($normalized['context']);
            }
            if (empty($normalized['extra'])) {
                unset($normalized['extra']);
            }
        }

        if (isset($normalized['context']) && ! is_array($normalized['context'])) {
            $normalized['context'] = [];
        }

        if (isset($normalized['extra']) && ! is_array($normalized['extra'])) {
            $normalized['extra'] = [];
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
