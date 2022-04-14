<?php
declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;
use App\Domain\Token\Service\TokenValidation; // the Domain to check token
use Slim\Routing\RouteContext;

class Permission {
    /**
     * Example middleware invokable class
     *
     * @param  ServerRequest  $request PSR-7 request
     * @param  RequestHandler $handler PSR-15 request handler
     *
     * @return Response
     */


    public function __construct(TokenValidation $tokenValidation){
        $this->tokenValidation = $tokenValidation;
      }


    private function getBearerToken($headers) {

        // HEADER: Get the access token from the header
        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                return $matches[1];
            }
        }
        return null;
    }




    public function __invoke(Request $request, RequestHandler $handler,): Response {



        /* Authorisation via BEARER Token
        Setup Postman with Authorisation Type Bearer Token */

        /* https://www.predic8.de/bearer-token-autorisierung-api-security.htm */
        $headers = $request->getHeaders();

        $bearer = null;
        if (isset($headers['Authorization'])) {
            $bearer = $this->getBearerToken($headers['Authorization'][0]); // How to properly use Bearer tokens?: https://stackoverflow.com/a/40582472/5997781
        }

        $response = $handler->handle($request);
        $existingContent = (string) $response->getBody();
    
        $response = new Response();
        $response->getBody()->write($existingContent);


        // Invoke the Token-Domain with bearer and retain the result
        $tokenResult = $this->tokenValidation->getToken($bearer);

        if ($tokenResult["valid"]) {
            
            // valid
            //$response->getBody()->write((string)json_encode($tokenResult));

            $response = $response->withHeader('Content-Type', 'application/json');
            $response = $response->withStatus(200);
            return $response;

        } else {

            // invalid

            $result = array(
                'success' => false,
                'reason' => $tokenResult['reason'],
            );

            $response = new Response();
            $response->getBody()->write((string)json_encode($result));

            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');//Forbidden

        }

    }
}