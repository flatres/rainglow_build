<?php

date_default_timezone_set("Europe/London");

define('LOG_PATH', __DIR__ . '/logs/app.log');

return [
    'settings' => [
        'displayErrorDetails' => true, // set to false in production
        'addContentLengthHeader' => false, // Allow the web server to send the content-length header
        'determineRouteBeforeAppMiddleware' => true,
        // Renderer settings
        'renderer' => [
            'template_path' => __DIR__ . '/../templates/',
        ],

      //  Monolog settings
        'logger' => [
            'name' => 'ada',
            'path' => __DIR__ . '/logs/app.log',
            'level' => \Monolog\Logger::DEBUG,
        ],
    ],
];
