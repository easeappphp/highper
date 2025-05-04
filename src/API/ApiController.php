<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\API;

use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\Http\Status;
use EaseAppPHP\HighPer\Framework\MVC\Controller\BaseController;
use OpenTelemetry\API\Trace\TracerInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

abstract class ApiController extends BaseController
{
    /**
     * @var TracerInterface The tracer
     */
    protected TracerInterface $tracer;

    /**
     * Create a new API controller
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->tracer = $container->get('tracer');
    }

    /**
     * Create a successful response
     *
     * @param mixed $data
     * @param int $status
     * @param array $headers
     * @return Response
     */
    protected function success(mixed $data = null, int $status = Status::OK, array $headers = []): Response
    {
        return $this->json([
            'success' => true,
            'data' => $data,
        ], $status, $headers);
    }

    /**
     * Create an error response
     *
     * @param string $message
     * @param mixed $errors
     * @param int $status
     * @param array $headers
     * @return Response
     */
    protected function error(string $message, mixed $errors = null, int $status = Status::BAD_REQUEST, array $headers = []): Response
    {
        return $this->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status, $headers);
    }

    /**
     * Create a not found response
     *
     * @param string $message
     * @param mixed $errors
     * @param array $headers
     * @return Response
     */
    protected function notFound(string $message = 'Resource not found', mixed $errors = null, array $headers = []): Response
    {
        return $this->error($message, $errors, Status::NOT_FOUND, $headers);
    }

    /**
     * Create a validation error response
     *
     * @param mixed $errors
     * @param string $message
     * @param array $headers
     * @return Response
     */
    protected function validationError(mixed $errors, string $message = 'Validation failed', array $headers = []): Response
    {
        return $this->error($message, $errors, Status::UNPROCESSABLE_ENTITY, $headers);
    }

    /**
     * Get the request body as JSON, with optional validation
     *
     * @param Request $request
     * @param array $rules
     * @return array|Response
     */
    protected function getJsonData(Request $request, array $rules = []): array|Response
    {
        try {
            $data = $this->getJsonBody($request);
            
            if ($data === null) {
                return $this->error('Invalid JSON payload');
            }
            
            // If validation rules are provided, validate the data
            if (!empty($rules)) {
                $validator = $this->container->get('validator');
                $validation = $validator->validate($data, $rules);
                
                if ($validation->fails()) {
                    return $this->validationError($validation->errors());
                }
            }
            
            return $data;
        } catch (\Throwable $e) {
            $this->logger->error('Error parsing JSON request: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            
            return $this->error('Error parsing request body');
        }
    }

    /**
     * Create a response with MessagePack format
     *
     * @param mixed $data
     * @param int $status
     * @param array $headers
     * @return Response
     */
    protected function messagePackResponse(mixed $data, int $status = Status::OK, array $headers = []): Response
    {
        return $this->messagePack($data, $status, $headers);
    }

    /**
     * Create a no content response
     *
     * @param array $headers
     * @return Response
     */
    protected function noContent(array $headers = []): Response
    {
        return new Response(Status::NO_CONTENT, $headers);
    }

    /**
     * Create a created response
     *
     * @param mixed $data
     * @param string|null $location
     * @param array $headers
     * @return Response
     */
    protected function created(mixed $data = null, ?string $location = null, array $headers = []): Response
    {
        if ($location !== null) {
            $headers['Location'] = $location;
        }
        
        return $this->success($data, Status::CREATED, $headers);
    }

    /**
     * Create an accepted response
     *
     * @param mixed $data
     * @param array $headers
     * @return Response
     */
    protected function accepted(mixed $data = null, array $headers = []): Response
    {
        return $this->success($data, Status::ACCEPTED, $headers);
    }

    /**
     * Create a paginated response
     *
     * @param array $items
     * @param int $total
     * @param int $page
     * @param int $perPage
     * @param array $headers
     * @return Response
     */
    protected function paginate(array $items, int $total, int $page, int $perPage, array $headers = []): Response
    {
        $lastPage = max(1, ceil($total / $perPage));
        
        return $this->success([
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'last_page' => $lastPage,
                'total' => $total,
                'from' => ($page - 1) * $perPage + 1,
                'to' => min($page * $perPage, $total),
            ],
        ], Status::OK, $headers);
    }
}