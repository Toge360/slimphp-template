<?php

/* The Action does only these things:
Collects input from the HTTP request (if needed)
Invokes the Domain with those inputs (if required) and retains the result
Builds an HTTP response (typically with the Domain invocation results). 

All other logic, including all forms of input validation, error handling, and so on, are therefore pushed out of the Action 
and into the Domain (for domain logic concerns) or the response renderer (for presentation logic concerns). */

namespace App\Action\User;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use App\Domain\Users\UserService as Service; // the Domain

final class GetUsers {

  private $service;
  
  public function __construct(Service $service){
    $this->service = $service;
  }

  public function __invoke(
    ServerRequestInterface $request, 
    ResponseInterface $response,
    array $args = [] // arguments ( id in "/users/{id}")
): ResponseInterface {

    // Invoke the Domain
    $result = $this->service->getUsers();

    $result = array(
      'data' => $wrap
    );

    // Build the HTTP response
    $response->getBody()->write((string)json_encode($result));
    $response = $response->withHeader('Content-Type', 'application/json');
    $response = $response->withStatus(200);
    
    return $response;
        
  }
}