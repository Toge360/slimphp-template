<?php
declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;
use App\Domain\Token\Service\TokenValidation; // the class to check the token
use Slim\Routing\RouteContext;

class ApiTokenMiddleware {
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

    public function __invoke(Request $request, RequestHandler $handler): Response {

        /* Authorisation via BEARER Token
        Setup Postman with Authorisation Type Bearer Token */

        /* https://www.slimframework.com/docs/v3/objects/request.html */
        $headers = $request->getHeaders(); // https://www.predic8.de/bearer-token-autorisierung-api-security.htm
        $method = $request->getMethod();
        $uri = $request->getUri();
        $path = $uri->getPath();
        $query = $uri->getQuery();

        /* https://www.slimframework.com/docs/v4/cookbook/retrieving-current-route.html */
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        // return NotFound for non existent route
        if (empty($route)) {
            throw new HttpNotFoundException($request);
        }

        $name = $route->getName();
        $groups = $route->getGroups();
        $methods = $route->getMethods();
        $arguments = $route->getArguments();

        $permissionPlugin = null; // welches plugin erfordert berechtigungen
        if (isset($arguments['permissionPlugin'])) {
            $permissionPlugin = $arguments['permissionPlugin'];
        }

        // get the companyUuid from route
        $companyuuid = null;
        if (isset($arguments['companyuuid'])) {
            $companyuuid = $arguments['companyuuid'];
        }

        $token = null;
        if (isset($headers['Authorization'])) {
            $token = $this->getBearerToken($headers['Authorization'][0]); // How to properly use Bearer tokens?: https://stackoverflow.com/a/40582472/5997781
        }

        // Gültigkeit des Tokens checken (expires)
        // $permissionPlugin: in $arguments['permissionPlugin'] we got an id of that plugin. With that Plugin we can check, if user has permissions to do that
        // $companyuuid to check, if user has permissions for that company
        $tokenResult = $this->tokenValidation->validateToken($token,$permissionPlugin,$companyuuid);

        if ($tokenResult["token"]["valid"]) {

            // token is valid

            //print_r($tokenResult);

            if (isset($tokenResult["permissions"]) 
            AND $method == "GET" AND $tokenResult["permissions"]["read"] != "1" AND $tokenResult["permissions"]["master"] != "1"
            OR $method == "POST" AND $tokenResult["permissions"]["write"] != "1" AND $tokenResult["permissions"]["master"] != "1"
            OR $method == "PUT" AND $tokenResult["permissions"]["write"] != "1" AND $tokenResult["permissions"]["master"] != "1"
            OR $method == "PATCH" AND $tokenResult["permissions"]["write"] != "1" AND $tokenResult["permissions"]["master"] != "1"
            OR $method == "DELETE" AND $tokenResult["permissions"]["delete"] != "1" AND $tokenResult["permissions"]["master"] != "1") {

                // NO PERMISSIONS
                
                $statusCode = 403; // invalid

                $result = array(
                    'success' => false,
                    'reason' => 'no permissions',
                );
    
                $response = new Response();
                $response->getBody()->write((string)json_encode($result));
    
                return $response->withStatus($statusCode)->withHeader('Content-Type', 'application/json');//Forbidden

            } else {

                // permissions okay

                $response = $handler->handle($request);
                $existingContent = (string) $response->getBody();
                $routingStatus = $response->getStatusCode(); // get here the "original status" of the request (mostly 200, but in some cases 400 etc.)

                if (!$routingStatus || $routingStatus == '' || !isset($routingStatus)) {
                    $routingStatus = 200;
                }

                $response = new Response();

                $newContent = json_decode($existingContent);
                $newContent->auth=$tokenResult;
                $newContent = json_encode($newContent);
                $response->getBody()->write($newContent); // adding tokenresult

                /* Fixed Problem: Always 200 Status
                In some cases, i wanted to set the status in the route return to another than 200.
                It turns out, that the middleware always returns a 200 when user is authenticated.
                Above i get the status of the original request and use it below as the middleware status code. */

                $response = $response->withHeader('Content-Type', 'application/json');
                $response = $response->withStatus($routingStatus);
                return $response;

            }

        } else {

            // invalid
            $statusCode = 403;

            $result = array(
                'success' => false,
                'reason' => $tokenResult['token']['reason'],
            );

            $response = new Response();
            $response->getBody()->write((string)json_encode($result));

            if ($result['reason'] == 'session expired') {
                $statusCode = 401;
            }

            return $response->withStatus($statusCode)->withHeader('Content-Type', 'application/json');//Forbidden

        }

    }
}