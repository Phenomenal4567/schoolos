<?php

namespace Tests\Support;

use App\Contracts\PaystackClient;

/**
 * Design ref: 21-schoolos-finance-architecture.md §3, D13.
 *
 * Test-only double for PaystackClient — bound via
 * `$this->app->instance(PaystackClient::class, ...)` per that
 * interface's own doc comment ("tests bind a fake"), never registered
 * in the application itself. Deliberately dumb: verifyTransaction()
 * answers true/false per-reference from an explicit map the test
 * configures up front (verifyAs()), or from $defaultResult when a
 * reference was never registered — there is no real HTTP call, no
 * network dependency, and no implicit "everything verifies" default
 * that could let a test accidentally pass without actually exercising
 * D13's confirm-before-write branch.
 */
class FakePaystackClient implements PaystackClient
{
    /** @var array<string, bool> */
    private array $results = [];

    public function __construct(private bool $defaultResult = false)
    {
    }

    public function verifyAs(string $reference, bool $result): self
    {
        $this->results[$reference] = $result;

        return $this;
    }

    public function verifyTransaction(string $reference): bool
    {
        return $this->results[$reference] ?? $this->defaultResult;
    }
}