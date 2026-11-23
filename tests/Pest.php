<?php

/*
| Integration tests run against a real WordPress (see tests/bootstrap.php); every
| test is rolled back afterwards by Tests\TestCase.
*/

pest()->extend(Tests\TestCase::class)->in('Integration');

/** Path to the theme, for tests that read its files. */
function theme_path(string $path = ''): string
{
    return get_stylesheet_directory() . ($path ? '/' . ltrim($path, '/') : '');
}
