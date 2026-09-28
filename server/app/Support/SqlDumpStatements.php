<?php

namespace App\Support;

use Generator;
use RuntimeException;

/** Split the bundled phpMyAdmin dump; no routines or DELIMITER directives are supported. */
final class SqlDumpStatements
{
    public static function read(string $sql): Generator
    {
        $start = 0;
        $quote = null;
        $comment = null;
        $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $sql[$i + 1] ?? '';
            if ($comment === 'line') {
                if ($char === "\n") {
                    $comment = null;
                }
                continue;
            }
            if ($comment === 'block') {
                if ($char === '*' && $next === '/') {
                    $comment = null;
                    $i++;
                }
                continue;
            }
            if ($quote !== null) {
                if ($char === '\\') {
                    $i++;
                } elseif ($char === $quote) {
                    if ($next === $quote) {
                        $i++;
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }
            if (in_array($char, ["'", '"', '`'], true)) {
                $quote = $char;
            } elseif ($char === '/' && $next === '*') {
                $comment = 'block';
                $i++;
            } elseif ($char === '#' || ($char === '-' && $next === '-' && ctype_space($sql[$i + 2] ?? ' '))) {
                $comment = 'line';
            } elseif ($char === ';') {
                $statement = trim(substr($sql, $start, $i - $start));
                if ($statement !== '') {
                    yield $statement;
                }
                $start = $i + 1;
            }
        }
        if ($quote !== null || $comment === 'block') {
            throw new RuntimeException('Unterminated string or comment in demo SQL.');
        }
        $last = trim(substr($sql, $start));
        if ($last !== '') {
            yield $last;
        }
    }
}
