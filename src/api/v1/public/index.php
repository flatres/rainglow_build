<?php

// header('Access-Control-Allow-Origin: *');
// header('Access-Control-Allow-Headers: origin, x-requested-with, Content-Type, Authorization, Accept');
// header('Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS, PATCH');

$userId = null;

$showErrors = true;

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Slim\Factory\AppFactory;
use DI\Container;
use DI\ContainerBuilder;
use Psr\Log\LoggerInterface;


use \Middleware\JsonBodyParserMiddleware;
use \Middleware\FormUrlencodedBodyParserMiddleware;


const FILESTORE_PATH = __DIR__ . '/filestore/';
// const FILESTORE_URL = 'filestore/';

require __DIR__ . '/../../vendor/autoload.php';

session_start();
session_write_close();

// load environmental variables
$dotenv = \Dotenv\Dotenv::createImmutable(dirname(__FILE__) . '/../');
$dotenv->load();


//create a new container - not a Slim one.
$container = new Container();

// Instantiate the app
$settings = require __DIR__ . '/../settings.php';


//$app = new \Slim\App($settings); old way to set up the app

//sets the container to the app (which we'll create later)
AppFactory::setContainer($container);

//add settings to the container
$container->set('settings', function() use ($settings) {
  return $settings['settings'];
});

$app = AppFactory::create();
$app->setBasePath('/api/v1/public');
$app->addRoutingMiddleWare();


$app->add(new JsonBodyParserMiddleware());
$app->add(new FormUrlencodedBodyParserMiddleware());

// Set up dependencies
require __DIR__ . '/../dependencies.php';

// Register middleware
require __DIR__ . '/../middleware.php';


//load helpers
foreach (glob(dirname(__FILE__) . '/../helpers/*.php') as $filename) {
  include $filename;
}

// Register routes

foreach (glob(dirname(__FILE__) . '/../.routes/*/_routes.php') as $filename) {
  include $filename;
}
foreach (glob(dirname(__FILE__) . '/../.routes/*/*/_routes.php') as $filename) {
  include $filename;
}

// Define Custom Error Handler
$customErrorHandler = function (
    $request,
    Throwable $exception,
    bool $displayErrorDetails,
    bool $logErrors,
    bool $logErrorDetails,
    ?LoggerInterface $logger = null
) use ($app) {
    // $logger->error($exception->getMessage());

    $payload = [
      'error' => $exception->getMessage(),
      'trace' => $exception->getTrace()
    ];

    $response = $app->getResponseFactory()->createResponse();
    $response->getBody()->write(
        json_encode($payload, JSON_UNESCAPED_UNICODE)
    );
    $response = $response->withHeader('Access-Control-Allow-Origin', '*');
    return $response->withStatus(200);
};

if ($showErrors && $_ENV['ENV'] == 'STAGE') {
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);
} else {
  ini_set('display_errors', 0);
  ini_set('display_startup_errors', 0);
}

// Add Error Middleware
// $errorMiddleware = $app->addErrorMiddleware(true, true, true);
// $errorMiddleware->setDefaultErrorHandler($customErrorHandler);

$app->run();
