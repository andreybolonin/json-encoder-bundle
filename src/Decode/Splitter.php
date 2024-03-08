<?php

declare(strict_types=1);

namespace Mtarld\JsonEncoderBundle\Decode;

use Mtarld\JsonEncoderBundle\Exception\UnexpectedValueException;
use Mtarld\JsonEncoderBundle\Stream\StreamReaderInterface;

/**
 * Splits collections to retrieve the offset and length of each element.
 *
 * @internal
 */
final class Splitter
{
    private const NESTING_CHARS = ['{' => true, '[' => true];
    private const UNNESTING_CHARS = ['}' => true, ']' => true];

    private static ?Lexer $lexer = null;

    /**
     * @var array{key: array<string, string>}
     */
    private static array $cache = [
        'key' => [],
    ];

    /**
     * @param StreamReaderInterface|resource $stream
     */
    public static function splitList(mixed $stream, int $offset = 0, ?int $length = null): ?\Iterator
    {
        $lexer = self::$lexer ??= new Lexer();
        $tokens = $lexer->getTokens($stream, $offset, $length);

        if ('null' === $tokens->current()[0] && 1 === iterator_count($tokens)) {
            return null;
        }

        return self::createListBoundaries($tokens, $stream);
    }

    /**
     * @param StreamReaderInterface|resource $stream
     */
    public static function splitDict(mixed $stream, int $offset = 0, ?int $length = null): ?\Iterator
    {
        $lexer = self::$lexer ??= new Lexer();
        $tokens = $lexer->getTokens($stream, $offset, $length);

        if ('null' === $tokens->current()[0] && 1 === iterator_count($tokens)) {
            return null;
        }

        return self::createDictBoundaries($tokens, $stream);
    }

    /**
     * @param \Iterator<array{0: string, 1: int}> $tokens
     *
     * @return \Iterator<array{0: int, 1: int}>
     */
    private static function createListBoundaries(\Iterator $tokens): \Iterator
    {
        $level = 0;

        foreach ($tokens as $i => $token) {
            if (0 === $i) {
                continue;
            }

            $value = $token[0];
            $position = $token[1];
            $offset = $offset ?? $position;

            if (isset(self::NESTING_CHARS[$value])) {
                ++$level;

                continue;
            }

            if (isset(self::UNNESTING_CHARS[$value])) {
                --$level;

                continue;
            }

            if (0 !== $level) {
                continue;
            }

            if (',' === $value) {
                if (($length = $position - $offset) > 0) {
                    yield [$offset, $length];
                }

                $offset = null;
            }
        }

        if (-1 !== $level || !isset($value, $offset, $position) || ']' !== $value) {
            throw new UnexpectedValueException('JSON is not valid.');
        }

        if (($length = $position - $offset) > 0) {
            yield [$offset, $length];
        }
    }

    /**
     * @param \Iterator<array{0: string, 1: int}> $tokens
     * @param resource                            $resource
     *
     * @return \Iterator<string, array{0: int, 1: int}>
     */
    private static function createDictBoundaries(\Iterator $tokens, mixed $resource): \Iterator
    {
        $level = 0;
        $offset = 0;
        $firstValueToken = false;
        $key = null;

        foreach ($tokens as $i => $token) {
            if (0 === $i) {
                continue;
            }

            $value = $token[0];
            $position = $token[1];

            if ($firstValueToken) {
                $firstValueToken = false;
                $offset = $position;
            }

            if (isset(self::NESTING_CHARS[$value])) {
                ++$level;

                continue;
            }

            if (isset(self::UNNESTING_CHARS[$value])) {
                --$level;

                continue;
            }

            if (0 !== $level) {
                continue;
            }

            if (':' === $value) {
                $firstValueToken = true;

                continue;
            }

            if (',' === $value) {
                if (null !== $key && ($length = $position - $offset) > 0) {
                    yield $key => [$offset, $length];
                }

                $key = null;

                continue;
            }

            if (null === $key) {
                $key = self::$cache['key'][$value] ??= json_decode($value);
            }
        }

        if (-1 !== $level || !isset($value, $position) || '}' !== $value) {
            throw new UnexpectedValueException('JSON is not valid.');
        }

        if (null !== $key && ($length = $position - $offset) > 0) {
            yield $key => [$offset, $length];
        }
    }
}
