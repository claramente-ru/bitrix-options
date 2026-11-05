<?php

/**
 * Офлайн-проверки модуля claramente.options.
 * Запуск: php tests/run.php
 * Bitrix не нужен: его API заменен заглушками из tests/support, рабочая БД и сеть не используются.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Предупреждения и notice считаются ошибкой теста; подавленные через @ — нет
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if ($severity === E_DEPRECATED || ! (error_reporting() & $severity)) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Автозагрузка в стиле Битрикса: Claramente\Options\Sub\Name => lib/sub/name.php
spl_autoload_register(static function (string $class): void {
    $prefix = 'Claramente\\Options\\';
    if (! str_starts_with($class, $prefix)) {
        return;
    }
    $path = __DIR__ . '/../lib/' . strtolower(str_replace('\\', '/', substr($class, strlen($prefix)))) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

// Заглушки подключаются до тестов, чтобы автозагрузка не подхватила настоящие классы Bitrix-окружения
require __DIR__ . '/support/fakes.php';
require __DIR__ . '/support/helpers.php';

final class TestFailure extends RuntimeException
{
}

$GLOBALS['__tests'] = [];

function test(string $name, callable $fn): void
{
    $GLOBALS['__tests'][] = [$name, $fn];
}

function assertSame(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new TestFailure(
            ($message !== '' ? $message . ': ' : '')
            . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true)
        );
    }
}

function assertTrue(bool $condition, string $message = 'condition is false'): void
{
    if (! $condition) {
        throw new TestFailure($message);
    }
}

foreach (glob(__DIR__ . '/cases/*.php') as $file) {
    require $file;
}

$passed = 0;
$failed = 0;
foreach ($GLOBALS['__tests'] as [$name, $fn]) {
    try {
        $fn();
        $passed++;
        echo "  ok   $name\n";
    } catch (Throwable $e) {
        $failed++;
        echo "  FAIL $name\n       " . $e::class . ': ' . $e->getMessage() . "\n";
    }
}

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
