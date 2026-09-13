<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

#[\AllowDynamicProperties]
abstract class TestCase extends BaseTestCase
{
    /**
     * Dynamically set test properties.
     *
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function __set(string $name, mixed $value): void
    {
        $this->$name = $value;
    }

    /**
     * Dynamically retrieve test properties.
     *
     * @param string $name
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        return $this->$name ?? null;
    }

    /**
     * Check if a dynamic test property is set.
     *
     * @param string $name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return isset($this->$name);
    }
}
