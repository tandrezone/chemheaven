<?php

declare(strict_types=1);

require_once __DIR__ . '/HelpersTest.php';

$tests = array_filter(get_defined_functions()['user'], static fn(string $name): bool => str_starts_with($name, 'test_'));
sort($tests);

$passed = 0;
$failed = 0;

foreach ($tests as $test) {
    try {
        $test();
        echo "PASS {$test}\n";
        $passed++;
    } catch (Throwable $e) {
        echo "FAIL {$test}: {$e->getMessage()}\n";
        $failed++;
    }
}

echo "\n{$passed} passed, {$failed} failed\n";

exit($failed === 0 ? 0 : 1);

