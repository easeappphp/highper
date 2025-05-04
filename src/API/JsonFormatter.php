<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\API;

use Amp\Http\Server\Response;
use EaseAppPHP\HighPer\Framework\Serialization\JsonSerializer;

class JsonFormatter
{
    /**
     * Create a new JSON formatter
     *
     * @param JsonSerializer $serializer
     */
    public function __construct(protected JsonSerializer $serializer)
    {
    }

    /**
     * Format data as a JSON response
     *
     * @param mixed $data
     * @param int $status
     * @param array $headers
     * @return Response
     */
    public function format(mixed $data, int $status, array $headers = []): Response
    {
        $headers['Content-Type'] = 'application/json; charset=utf-8';
        
        $json = $this->serializer->serialize($data);
        
        return new Response($status, $headers, $json);
    }
}
