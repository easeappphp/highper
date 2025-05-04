<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Serialization;

use MessagePack\MessagePack;
use EaseAppPHP\HighPer\Framework\Config\ConfigProvider;

class MessagePackSerializer implements SerializerInterface
{
    /**
     * @var array The MessagePack options
     */
    protected array $options;

    /**
     * Create a new MessagePack serializer
     *
     * @param ConfigProvider $config
     */
    public function __construct(ConfigProvider $config)
    {
        $this->options = [
            'pack_options' => $config->get('serialization.messagepack.pack_options', 0),
            'unpack_options' => $config->get('serialization.messagepack.unpack_options', 0),
        ];
    }

    /**
     * Serialize data to MessagePack
     *
     * @param mixed $data
     * @return string
     */
    public function serialize(mixed $data): string
    {
        return MessagePack::pack($data, $this->options['pack_options']);
    }

    /**
     * Unserialize MessagePack data
     *
     * @param string $data
     * @return mixed
     */
    public function unserialize(string $data): mixed
    {
        return MessagePack::unpack($data, $this->options['unpack_options']);
    }
}
