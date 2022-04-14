<?php

use Slim\App;
use Slim\Middleware\ErrorMiddleware;

return function (App $app) {


    // Setting up CORS
    // https://www.slimframework.com/docs/v4/cookbook/enable-cors.html// This middleware will append the response header Access-Control-Allow-Methods with all allowed methods
    $app->options ('/{routes:.+}', function ($request, $response, $args) {
        return $response;
    });
    $app->add (function ($request, $handler) {
        $response = $handler->handle ($request);
        return $response
        ->withHeader ('Access-Control-Allow-Origin', '*')
        ->withHeader ('Access-Control-Allow-Headers', 'X-Requested-With,Content-Type,Accept,Origin,Authorization,Token')
        // Optional: Allow Ajax CORS requests with Authorization header
        // ->withHeader ('Access-Control-Allow-Credentials', 'true')
        ->withHeader ('Access-Control-Allow-Methods', 'GET,POST,PUT,PATCH,DELETE,OPTIONS');
    });

    // Parse json, form data and xml
    $app->addBodyParsingMiddleware();

    // Add the Slim built-in routing middleware
    $app->addRoutingMiddleware();

    // Catch exceptions and errors
    $app->add(ErrorMiddleware::class);


};