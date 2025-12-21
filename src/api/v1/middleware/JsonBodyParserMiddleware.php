<?php

/*
This Middleware ensures that, if an http request arrives in JSON format, it gets properly parsed.
Slim's getParsedBody() method has trouble processing PATCH requests sometimes without this.
*/

namespace Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class JsonBodyParserMiddleware implements MiddlewareInterface
{
  public function process(Request $request, RequestHandlerInterface $handler): Response
  {
    $contentType = $request->getHeaderLine('Content-Type');

    if (strstr($contentType, 'application/json')) {
      $contents = json_decode(file_get_contents('php://input'), true);
      if (json_last_error() === JSON_ERROR_NONE) {
        $request = $request->withParsedBody($contents);
      }
    }

    return $handler->handle($request);
  }
}
