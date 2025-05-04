<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Serialization;

use EaseAppPHP\HighPer\Framework\Config\ConfigProvider;

class JsonSerializer implements SerializerInterface
{
    /**
     * @var array The JSON options
     */
    protected array $options;

    /**
     * Create a new JSON serializer
     *
     * @param ConfigProvider $config
     */
    public function __construct(ConfigProvider $config)
    {
        $this->options = [
            'flags' => $config->get('serialization.json.flags', JSON_PRESERVE_ZERO_FRACTION),
            'depth' => $config->get('serialization.json.depth', 512),
        ];
    }

    /**
     * Serialize data to JSON
     *
     * @param mixed $data
     * @return string
     * @throws \JsonException
     */
    public function serialize(mixed $data): string
    {
        return json_encode($data, $this->options['flags'] | JSON_THROW_ON_ERROR, $this->options['depth']);
    }

    /**
     * Unserialize JSON data
     *
     * @param string $data
     * @return mixed
     * @throws \JsonException
     */
    public function unserialize(string $data): mixed
    {
        return json_decode($data, true, $this->options['depth'], JSON_THROW_ON_ERROR);
    }
}
