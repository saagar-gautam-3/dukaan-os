<?php

declare(strict_types=1);

namespace App\Support;

use Sentry\Event;

final class SentryEventScrubber
{
    public static function scrub(Event $event): ?Event
    {
        $request = $event->getRequest();
        unset($request['cookies'], $request['data'], $request['query_string']);
        if (isset($request['url']) && is_string($request['url'])) {
            $parts = parse_url($request['url']);
            if (is_array($parts)) {
                $request['url'] = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').($parts['path'] ?? '');
            }
        }
        if (isset($request['headers']) && is_array($request['headers'])) {
            foreach (array_keys($request['headers']) as $header) {
                if (in_array(strtolower((string) $header), ['authorization', 'cookie', 'set-cookie'], true)) {
                    unset($request['headers'][$header]);
                }
            }
        }
        $event->setRequest($request);
        $event->setUser(null)->setExtra([]);
        foreach ($event->getContexts() as $context => $data) {
            $event->setContext($context, array_fill_keys(array_keys($data), '[Filtered]') ?: ['_filtered' => '[Filtered]']);
        }
        $event->setTags(array_filter(
            array_intersect_key($event->getTags(), array_flip(['shop_id', 'user_id'])),
            static fn (string $value): bool => ctype_digit($value),
        ));
        $event->setMessage('Unexpected server error');
        foreach ($event->getExceptions() as $exception) {
            $exception->setValue('Unexpected server error');
        }

        return $event;
    }
}
