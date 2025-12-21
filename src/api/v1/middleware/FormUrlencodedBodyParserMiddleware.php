<?php
/*
This Middleware parses incoming request bodies which are in the x-www-form-urlencoded format,
which is the easiest way to send data in Postman, and parses them to something that Slim can understand.
While PHP seems to manage ok with this, Slim's getParsedBody() method struggles with it.
*/

namespace Middleware;

use Psr\Http\Message\ResponseInterface as Response  ;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class FormUrlencodedBodyParserMiddleware implements MiddlewareInterface
{
  public function process(Request $request, RequestHandlerInterface $handler): Response
  {
    $contentType = $request->getHeaderLine('Content-Type');

    if (strstr($contentType, 'application/x-www-form-urlencoded')) {
      parse_str(file_get_contents('php://input'), $contents);
      $request = $request->withParsedBody($contents);
    }

    return $handler->handle($request);
  }
}
