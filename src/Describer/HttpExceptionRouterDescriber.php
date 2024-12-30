<?php

namespace App\Describer;

use Nelmio\ApiDocBundle\RouteDescriber\RouteDescriberInterface;
use OasHttpExceptionExtractor\ExceptionExtractor;
use OpenApi\Annotations\OpenApi;
use OpenApi\Annotations\Operation;
use OpenApi\Annotations\PathItem;
use OpenApi\Annotations\Response;
use OpenApi\Generator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Routing\Route;

/**
 * Describes HTTP exception responses in OpenAPI documentation.
 * 
 * This class analyzes controller methods for potential HTTP exceptions
 * and adds corresponding response documentation to the OpenAPI spec.
 */
readonly class HttpExceptionRouterDescriber implements RouteDescriberInterface
{
    public function __construct(
        private ContainerInterface $container
    ) {
    }

    /**
     * Describes HTTP exception responses for a route.
     *
     * @param OpenApi $api The OpenAPI documentation being built
     * @param Route $route The route being documented
     * @param \ReflectionMethod $reflectionMethod The controller method being documented
     */
    public function describe(OpenApi $api, Route $route, \ReflectionMethod $reflectionMethod): void
    {
        $controllerPath = $this->getControllerFilePathWithContainer($route);
        $extractor = new ExceptionExtractor();
        $methodExceptions = $extractor->extract($controllerPath)->getMethodExceptions($reflectionMethod->getName());

        foreach ($methodExceptions->exceptions as $exceptionClass) {
            $httpCode = $this->getHttpStatusCodeFromExceptionClass($exceptionClass);
            $this->addResponseToOpenApi($api, $route, $httpCode, $exceptionClass);
        }
    }
    /**
     * Adds an HTTP exception response to the OpenAPI documentation.
     *
     * @param OpenApi $api The OpenAPI documentation being built
     * @param Route $route The route being documented
     * @param int $httpCode The HTTP status code for the exception
     * @param string $exceptionClass The exception class being documented
     */
    private function addResponseToOpenApi(OpenApi $api, Route $route, int $httpCode, string $exceptionClass): void
    {
        $path = $route->getPath();
        $methods = $route->getMethods() ?: ['GET'];

        foreach ($methods as $method) {
            $method = strtolower($method);
            $pathItem = $this->findPathItem($api, $path);
            $operation = $this->findOperation($pathItem, $method);

            if (Generator::isDefault($operation->responses)) {
                $operation->responses = [];
            }

            if (!isset($operation->responses[(string)$httpCode])) {
                $shortName = (new \ReflectionClass($exceptionClass))->getShortName();
                $description = sprintf('%s (%d)', $shortName, $httpCode);

                $response = new class(['response' => (string)$httpCode, 'description' => $description]) extends Response {};
                $operation->responses[] = $response;
            }
        }
    }
    /**
     * Finds a PathItem in the OpenAPI documentation for a given path.
     */
    private function findPathItem(OpenApi $api, string $path): ?PathItem
    {
        foreach ($api->paths as $pathItem) {
            if ($pathItem->path === $path) {
                return $pathItem;
            }
        }
        return null;
    }

    /**
     * Finds an Operation in a PathItem for a given HTTP method.
     */
    private function findOperation(PathItem $pathItem, string $method): ?Operation
    {
        $operation = $pathItem->$method ?? null;
        return $operation instanceof Operation ? $operation : null;
    }

    /**
     * Extracts the controller file path from a route.
     *
     * @throws \Exception When the controller cannot be resolved
     */
    private function getControllerFilePathWithContainer(Route $route): string
    {
        $controller = $route->getDefault('_controller');
        if (!$controller) {
            throw new \Exception("The route does not have a '_controller' attribute.");
        }

        $class = $this->resolveControllerClass($controller);
        $reflector = new \ReflectionClass($class);
        $filePath = $reflector->getFileName();

        if (!$filePath) {
            throw new \Exception("Unable to determine the file path for controller class '$class'.");
        }

        return $filePath;
    }

    /**
     * Resolves the controller class from various controller formats.
     *
     * @throws \Exception When the controller format is invalid or the class cannot be resolved
     */
    private function resolveControllerClass(mixed $controller): string
    {
        if (is_string($controller)) {
            return $this->resolveStringController($controller);
        }

        if (is_array($controller) && count($controller) === 2 && is_object($controller[0])) {
            return get_class($controller[0]);
        }

        throw new \Exception("Unsupported controller format.");
    }

    /**
     * Resolves a string controller to its class name.
     *
     * @throws \Exception When the controller service cannot be resolved
     */
    private function resolveStringController(string $controller): string
    {
        if (str_contains($controller, '::')) {
            [$class] = explode('::', $controller, 2);
            return $class;
        }

        if (!$this->container->has($controller)) {
            throw new \Exception("Service '$controller' not found in the container.");
        }

        $service = $this->container->get($controller);
        if (!is_object($service)) {
            throw new \Exception("Service '$controller' is not a valid object.");
        }

        return get_class($service);
    }

    /**
     * Gets the HTTP status code from an exception class.
     *
     * @throws \InvalidArgumentException When the exception class is invalid
     */
    private function getHttpStatusCodeFromExceptionClass(string $exceptionClass): int
    {
        if (!is_subclass_of($exceptionClass, HttpExceptionInterface::class)) {
            throw new \InvalidArgumentException("Class $exceptionClass must implement HttpExceptionInterface.");
        }

        $reflection = new \ReflectionClass($exceptionClass);
        if (!$reflection->isInstantiable()) {
            throw new \InvalidArgumentException("Class $exceptionClass is not instantiable.");
        }

        $instance = $reflection->newInstance();
        return $instance->getStatusCode();
    }
}
