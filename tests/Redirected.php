<?php

namespace Tests;

/** Thrown in place of a redirect so a test can see where it was going. */
final class Redirected extends \RuntimeException
{
    public function __construct(public readonly string $location)
    {
        parent::__construct("Redirected to {$location}");
    }
}
