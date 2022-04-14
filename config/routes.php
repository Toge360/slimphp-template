<?php

use Slim\App;
use App\Middleware\ApiTokenMiddleware;
use App\Middleware\Permission;

return function (App $app) {

  // OHNE TOKE VALIDATION
  // 01 - USER
  $app->get(('/users'), \App\Action\Users\GetUsers::class) 
  ->setName('users');

  $app->get(('/users/{useruuid}'), \App\Action\Users\GetUser::class) 
  ->setName('users');

  /* 
  MIT TOKEN VALIDATION IN MIDDLEWARE

  $app->get(('/users'), \App\Action\Users\GetUsers::class) 
  ->setName('users')
  ->add(ApiTokenMiddleware::class);

  $app->get(('/users/{useruuid}'), \App\Action\Users\GetUser::class) 
  ->setName('users')
  ->add(ApiTokenMiddleware::class); */

};