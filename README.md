# OpenAPI Exception Extractor Demo POC

This repository demonstrates the usage of [krzysztofmadenski/oas-http-exception-extractor](https://github.com/krzysztofmadenski/oas-http-exception-extractor) - a tool that automatically enhances OpenAPI documentation by analyzing static code for HTTP exception handling.

## Overview

The project showcases how to automate the process of documenting API error responses in OpenAPI/Swagger documentation by statically analyzing exception handling in your code. Instead of manually documenting each possible error response, the tool extracts this information directly from your exception handling logic.

## Features

- Automatic extraction of HTTP exception responses for OpenAPI documentation from Presentation layer. Require good practices in http error throwing. Do not cover exceptions throws by external packages entrypoints and proxy entrypoints. 
- Integration with Symfony's error handling system
- Example controllers demonstrating different use cases:
  - Invokable controller (`InvokableExampleController`)
  - Multiple method controller (`MultipleMethodExampleController`)

## Setup

1. Clone the repository:
```bash
git clone https://github.com/your-username/s15540-example-project-oas-extractor.git
cd s15540-example-project-oas-extractor
```

2. Install dependencies:
```bash
composer install
```

3. Start the development server:
```bash
docker compose up -d
```

## Usage

Once the server is running, you can access:

- The API endpoints at `http://localhost`
- The OpenAPI/Swagger documentation at `http://localhost/api/doc`

The documentation will automatically include error responses based on the exception handling in your controllers, thanks to the `HttpExceptionRouterDescriber` integration.

## How It Works

The project uses `HttpExceptionRouterDescriber` (located in `src/Describer`) to analyze your controllers' exception handling and automatically add corresponding response documentation to your OpenAPI specs.

For example, if your controller has exception handling like:

```php
try {
    // Your logic here
} catch (ValidationException $e) {
    throw new BadRequestHttpException('Invalid input');
} catch (NotFoundException $e) {
    throw new NotFoundHttpException('Resource not found');
}
```

The tool will automatically add 400 and 404 response documentation to your OpenAPI specs without requiring manual documentation.

## Project Structure

- `src/Controller/` - Example controllers demonstrating exception handling
- `src/Describer/` - Contains the HTTP exception extractor integration
- `config/packages/nelmio_api_doc.yaml` - OpenAPI documentation configuration

## Contributing

Feel free to submit issues and enhancement requests!

## License

This project is open-sourced under the MIT license.
