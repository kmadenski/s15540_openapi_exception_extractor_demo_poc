<?php

namespace App\Describer;

use Nelmio\ApiDocBundle\RouteDescriber\RouteDescriberInterface;
use OasHttpExceptionExtractor\ExceptionExtractor;
use OasHttpExceptionExtractor\Parser\ExceptionsDTO;
use OpenApi\Annotations\MediaType;
use OpenApi\Annotations\OpenApi;
use OpenApi\Annotations\Operation;
use OpenApi\Annotations\PathItem;
use OpenApi\Annotations\Property;
use OpenApi\Annotations\Response;
use OpenApi\Annotations\Schema;
use OpenApi\Generator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Routing\Route;

class HttpExceptionRouterDescriber implements RouteDescriberInterface
{

    public function __construct(
        private readonly ContainerInterface $container
    )
    {
    }

    public function describe(OpenApi $api, Route $route, \ReflectionMethod $reflectionMethod)
    {
        $path = $this->getControllerFilePathWithContainer($route);
        $extractor = new ExceptionExtractor();
        $methodExceptions = $extractor->extract($path)->getMethodExceptions($reflectionMethod->getName());

        foreach ($methodExceptions->exceptions as $exceptionClass) {
            $httpCode = $this->getHttpStatusCodeFromExceptionClass($exceptionClass);
            $this->addResponseToOpenApi($api, $route, $httpCode);
        }
    }
    private function addResponseToOpenApi(OpenApi $api, Route $route, int $httpCode): void
    {
        // Retrieve the path and HTTP methods from the route
        $path = $route->getPath();
        $methods = $route->getMethods();

        if (empty($methods)) {
            // Default to GET if no methods are specified
            $methods = ['GET'];
        }

        foreach ($methods as $method) {
            $method = strtolower($method);

            $pathItem = $this->findPathItem($api, $path);
            $operation = $this->findOperation($pathItem, $method);
            if(Generator::isDefault($operation->responses)){
                $operation->responses = [];
            }
            // Avoid duplicating responses
            if (!isset($operation->responses[(string)$httpCode])) {
                // Create the response
                $response = new class(([
                    'response' => (string)$httpCode,
                    'description' => $path,
                ])) extends Response{};

                $operation->responses[] = $response;
            }
        }
    }
    private function findPathItem(OpenApi $api, string $path): ?PathItem
    {
        foreach ($api->paths as $pathItem) {
            if ($pathItem->path === $path) {
                return $pathItem;
            }
        }

        return null;
    }
    private function findOperation(PathItem $pathItem, string $method): ?Operation
    {
        $operation = $pathItem->$method ?? null;

        return $operation instanceof Operation ? $operation : null;
    }

    private function getControllerFilePathWithContainer(Route $route): string
    {
        $controller = $route->getDefault('_controller');

        if (!$controller) {
            throw new \Exception("The route does not have a '_controller' attribute.");
        }

        if (is_string($controller)) {
            if (strpos($controller, '::') !== false) {
                list($class, $method) = explode('::', $controller, 2);
            } else {
                // Assume it's a service ID
                if (!$this->container->has($controller)) {
                    throw new \Exception("Service '$controller' not found in the container.");
                }

                $service = $this->container->get($controller);

                if (!is_object($service)) {
                    throw new \Exception("Service '$controller' is not a valid object.");
                }

                $class = get_class($service);
            }
        } elseif (is_array($controller) && count($controller) === 2) {
            $object = $controller[0];
            $method = $controller[1];

            if (is_object($object)) {
                $class = get_class($object);
            } else {
                throw new \Exception("Controller array does not contain a valid object.");
            }
        } else {
            throw new \Exception("Unsupported controller format.");
        }

        if (!class_exists($class)) {
            throw new \Exception("Controller class '$class' does not exist.");
        }

        $reflector = new \ReflectionClass($class);
        $filePath = $reflector->getFileName();

        if (!$filePath) {
            throw new \Exception("Unable to determine the file path for controller class '$class'.");
        }

        return $filePath;
    }

    private function getHttpStatusCodeFromExceptionClass(string $exceptionClass, array $constructorArgs = []): ?int
    {
        if (!is_subclass_of($exceptionClass, HttpExceptionInterface::class)) {
            throw new \InvalidArgumentException("Class $exceptionClass must implement HttpExceptionInterface.");
        }

        $reflection = new \ReflectionClass($exceptionClass);

        // Check if the class is instantiable
        if (!$reflection->isInstantiable()) {
            throw new \InvalidArgumentException("Class $exceptionClass is not instantiable.");
        }

        // Create an instance of the exception with provided constructor arguments
        $instance = $reflection->newInstanceArgs($constructorArgs);

        // Retrieve the status code
        return $instance->getStatusCode();
    }
}