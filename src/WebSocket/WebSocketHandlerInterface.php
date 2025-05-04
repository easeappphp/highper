<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\WebSocket;

use Amp\Websocket\Message;

interface WebSocketHandlerInterface
{
    /**
     * Handle a new WebSocket connection
     *
     * @param WebSocketConnection $connection
     * @return void
     */
    public function onConnect(WebSocketConnection $connection): void;

    /**
     * Handle a WebSocket message
     *
     * @param WebSocketConnection $connection
     * @param Message $message
     * @return void
     */
    public function onMessage(WebSocketConnection $connection, Message $message): void;

    /**
     * Handle a WebSocket disconnection
     *
     * @param WebSocketConnection $connection
     * @return void
     */
    public function onDisconnect(WebSocketConnection $connection): void;
}
