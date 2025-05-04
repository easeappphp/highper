<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\MVC\Controller;

use Amp\Http\Server\Request;
use Amp\Http\Server\Response;

interface ControllerInterface
{
    /**
     * Handle the request
     *
     * @param Request $request
     * @return Response
     */
    public function handle(Request $request): Response;
}
