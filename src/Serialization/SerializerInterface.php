<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Serialization;

interface SerializerInterface
{
    /**
     * Serialize data
     *
     * @param mixed $data
     * @return string
     */
    public function serialize(mixed $data): string;

    /**
     * Unserialize data
     *
     * @param string $data
     * @return mixed
     */
    public function unserialize(string $data): mixed;
}
