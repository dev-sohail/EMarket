<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Cache;
use Throwable;

class CircuitBreaker
{
    public function __construct(
        protected string $key = 'stripe',
        protected int $failureThreshold = 5,
        protected int $openSeconds = 60,
    ) {}

    public function call(callable $fn): mixed
    {
        if (Cache::get("cb:{$this->key}:open")) {
            throw new \RuntimeException('Circuit open for '.$this->key);
        }

        try {
            $result = $fn();
            Cache::forget("cb:{$this->key}:failures");

            return $result;
        } catch (Throwable $e) {
            $failures = (int) Cache::increment("cb:{$this->key}:failures");
            if ($failures >= $this->failureThreshold) {
                Cache::put("cb:{$this->key}:open", true, $this->openSeconds);
                Cache::forget("cb:{$this->key}:failures");
            }
            throw $e;
        }
    }
}
