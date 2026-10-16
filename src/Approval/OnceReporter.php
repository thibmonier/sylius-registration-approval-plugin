<?php

declare(strict_types=1);

namespace Tmonier\SyliusRegistrationApprovalPlugin\Approval;

/** Remembers what was already reported in this process (a warning once per channel, not per request). */
final class OnceReporter
{
    /** @var list<string> */
    private array $seen = [];

    /** True the first time a key is given. */
    public function isNew(string $key): bool
    {
        if (\in_array($key, $this->seen, true)) {
            return false;
        }
        $this->seen = [...$this->seen, $key];

        return true;
    }
}
