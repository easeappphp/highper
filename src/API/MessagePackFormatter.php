<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\API;

use Amp\Http\Server\Response;
use EaseAppPHP\HighPer\Framework\Serialization\MessagePackSerializer;

class MessagePackFormatter
{
    /**
     * Create a new MessagePack formatter
     *
     * @param MessagePackSerializer $serializer
     */
    public function __construct(protected MessagePackSerializer $serializer)
    {
    }

    /**
     * Format data as a MessagePack response
     *
     * @param mixed $data
     * @param int $status
     * @param array $headers
     * @return Response
     */
    public function format(mixed $data, int $status, array $headers = []): Response
    {
        $headers['Content-Type'] = 'application/msgpack';
        
        $msgPack = $this->serializer->serialize($data);
        
        return new Response($status, $headers, $msgPack);
    }
}
