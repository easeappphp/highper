<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\MVC\Controller;

use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\Http\Status;
use EaseAppPHP\HighPer\Framework\API\JsonFormatter;
use EaseAppPHP\HighPer\Framework\API\MessagePackFormatter;
use EaseAppPHP\HighPer\Framework\MVC\View\ViewFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

abstract class BaseController implements ControllerInterface
{
    /**
     * @var ContainerInterface The service container
     */
    protected ContainerInterface $container;
    
    /**
     * @var LoggerInterface The logger
     */
    protected LoggerInterface $logger;
    
    /**
     * @var JsonFormatter The JSON formatter
     */
    protected JsonFormatter $jsonFormatter;
    
    /**
     * @var MessagePackFormatter The MessagePack formatter
     */
    protected MessagePackFormatter $messagePackFormatter;
    
    /**
     * @var ViewFactory The view factory
     */
    protected ViewFactory $viewFactory;

    /**
     * Create a new controller instance
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->logger = $container->get(LoggerInterface::class);
        $this->jsonFormatter = $container->get(JsonFormatter::class);
        $this->messagePackFormatter = $container->get(MessagePackFormatter::class);
        $this->viewFactory = $container->get(ViewFactory::class);
    }

    /**
     * Create a JSON response
     *
     * @param mixed $data
     * @param int $status
     * @param array $headers
     * @return Response
     */
    protected function json(mixed $data, int $status = Status::OK, array $headers = []): Response
    {
        return $this->jsonFormatter->format($data, $status, $headers);
    }

    /**
     * Create a MessagePack response
     *
     * @param mixed $data
     * @param int $status
     * @param array $headers
     * @return Response
     */
    protected function messagePack(mixed $data, int $status = Status::OK, array $headers = []): Response
    {
        return $this->messagePackFormatter->format($data, $status, $headers);
    }

    /**
     * Create a view response
     *
     * @param string $template
     * @param array $data
     * @param int $status
     * @param array $headers
     * @return Response
     */
    protected function view(string $template, array $data = [], int $status = Status::OK, array $headers = []): Response
    {
        $view = $this->viewFactory->make($template, $data);
        
        $headers['Content-Type'] = 'text/html; charset=utf-8';
        
        return new Response($status, $headers, $view->render());
    }

    /**
     * Redirect to another URL
     *
     * @param string $url
     * @param int $status
     * @return Response
     */
    protected function redirect(string $url, int $status = Status::FOUND): Response
    {
        return new Response($status, [
            'Location' => $url
        ]);
    }

    /**
     * Get a parameter from the request
     *
     * @param Request $request
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    protected function getParam(Request $request, string $name, mixed $default = null): mixed
    {
        $params = $request->getAttribute('routeParams', []);
        
        return $params[$name] ?? $default;
    }

    /**
     * Get the request body as a string
     *
     * @param Request $request
     * @return string
     */
    protected function getBody(Request $request): string
    {
        return $request->getBody()->buffer();
    }

    /**
     * Get the request body as JSON
     *
     * @param Request $request
     * @return array|null
     */
    protected function getJsonBody(Request $request): ?array
    {
        $body = $this->getBody($request);
        
        return json_decode($body, true);
    }
}
