<?php

namespace RetailCrm\Messenger\Beanstalkd\Storage;

interface LockStorageInterface
{
    // "true" if the lock is installed (this means there is no duplicate of this message in the queue)
    public function setLock(string $key): bool;

    public function releaseLock(string $key): bool;
}
