<?php

/**
 * @var object $app
 */

// Slim
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteContext;
use Tuupola\Middleware\HttpBasicAuthentication\AuthenticatorInterface;
use Tuupola\Middleware\HttpBasicAuthentication\PdoAuthenticator;
use Tuupola\Middleware\HttpBasicAuthentication;
// App
use App\Domain1\Users\Sessions;
// use MyApp\Handlers\MyErrorHandler;


// Add body parsing Middleware
$app->addBodyParsingMiddleware ();								// Parse application/json, application/x-www-form-urlencoded, application/xml, text/xml (even PUT)


// Setting up CORS
// https://www.slimframework.com/docs/v4/cookbook/enable-cors.html
// This middleware will append the response header Access-Control-Allow-Methods with all allowed methods
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


// Via this middleware you could access the route and routing results from the resolved route
// https://www.slimframework.com/docs/v3/objects/request.html
$app->add (function (Request $request, RequestHandler $handler) {
	global $myConfig, $myRequest;

	// New Dispatcher & Routing Results https://www.bookstack.cn/read/slimframework-v4/153577
	$routeContext	= RouteContext::fromRequest ($request);
	$route			= $routeContext->getRoute ();
	$routingResults	= $routeContext->getRoutingResults ();
	$routeArguments = $routingResults->getRouteArguments ();
	$allowedMethods = $routingResults->getAllowedMethods ();

	if (empty ($route)) {
		throw new HttpNotFoundException ($request);						// Return NotFound for non existent route
	}

	$myRequest['name']			= $route->getName ();
	// $myRequest['groups']		= $route->getGroups ();
	$myRequest['method']		= $route->getMethods ();
	$myRequest['arguments']		= $route->getArguments ();
	$myRequest['routePath']		= $request->getUri ()->getPath ();
	$myRequest['isPublic']		= strpos ($myRequest['routePath'], '/public/') === false ? false : true;
	$myRequest['client']		= getRequestIp ();
	$myRequest['query']			= $request->getQueryParams ();


	// Debugging activate or deactivate?
	if ($myConfig['debugging']['active'] === true) {                    // Activate debugging globally in config?
		$myRequest['debugging']['active'] = true;
	} else if (isset ($myRequest['httpHeaders']['Debugging'])			// Activate debugging with header variable?
		&& filter_var ($myRequest['httpHeaders']['Debugging'], FILTER_SANITIZE_STRING) === 'true') {
		$myRequest['debugging']['active'] = true;
	} else {
		$myRequest['debugging']['active'] = false;
	}

	logInfo (implode (',', $myRequest['method']) . ' ' . $myRequest['routePath']);		// Log the method and route
	$request = $request->withAttribute('myRequest', $myRequest);	// Add data to your request as [READ-ONLY]

	return $handler->handle ($request);

	/*
	{
		"myRequest": {
			"scriptStart": 1633075959.885609,
			"domain": "mitfit.de",
			"servername": "apislim.mitfit.de",
			"frontend": "manage.mitfit.de",
			"httpHeaders": {
				"Connection": "close",
				"Host": "apislim.mitfit.de",
				"Accept-Language": "de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7",
				"Accept-Encoding": "gzip, deflate, br",
				"Referer": "https:\/\/shop.face-force.de\/",
				"Sec-Fetch-Dest": "empty",
				"Sec-Fetch-Mode": "cors",
				"Sec-Fetch-Site": "cross-site",
				"Origin": "https:\/\/shop.face-force.de",
				"Sec-Ch-Ua-Platform": "\"Windows\"",
				"User-Agent": "Mozilla\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/94.0.4606.61 Safari\/537.36",
				"Sec-Ch-Ua-Mobile": "?0",
				"Accept": "application\/json, text\/plain, *\/*",
				"Sec-Ch-Ua": "\"Chromium\";v=\"94\", \"Google Chrome\";v=\"94\", \";Not A Brand\";v=\"99\"",
				"Cache-Control": "no-cache",
				"Pragma": "no-cache"
			},
			"name": null,
			"method": [
				"GET"
			],
			"arguments": [],
			"routePath": "\/v1\/public\/products",
			"isPublic": true,
			"client": {
				"ip4": "91.8.48.105",
				"ip6": null
			},
			"query": {
				"filter": {
					"branchId": "1"
				}
			},
			"debugging": true,
			"scriptDuration": "0.146 s"
		}
	}
	*/

});
// 		$result = [ 'data' => $row, 'myRequest' => $request->getAttribute('myRequest') ];


/*
// Handle HTTP method PATCH with content type "form-data"
// https://stackoverflow.com/questions/33279153/rest-api-file-ie-images-processing-best-practices
// https://stackoverflow.com/questions/9464935/php-multipart-form-data-put-request
// https://gist.github.com/JhonatanRaul/cb2f9670ad0a8aa2fc32d263f948342a
// https://gist.github.com/devmycloud/df28012101fbc55d8de1737762b70348
$app->add (function (Request $request, RequestHandler $handler) {
	global $myConfig, $myRequest;
	$routeContext	= RouteContext::fromRequest ($request);
	$route			= $routeContext->getRoute ();
	if (strtolower ($request->getMethod ()) === 'patch') {
		// var_export ($request->getHeader ('Content-Type'));
		// $formData = $this->decodeFormData ();
	}
	return $handler->handle ($request);
});
*/


// Add routing Middleware - The RoutingMiddleware should be added after the CORS middleware, so routing is performed first
$app->addRoutingMiddleware ();



// HTTP Authentication Middleware
class CustomAuthenticator implements AuthenticatorInterface {

	public function __invoke (array $arguments): bool {
		global $myRequest;

		$token = $myRequest['httpHeaders']['Token'] ?? null;				// Get the auth token
		// $result = $this->checkToken ($myRequest['usedBackend'], $token);	// Is the token valid (true | false)?
		$result = $this->checkToken ($token);								// Is the token valid (true | false)?
		if ($result) {
			$sessions = new Sessions;										// If valid
			$myRequest['session'] = $sessions->getByToken ($token);			//   get session details
		}
		// var_export ( ['arguments' => $arguments, 'myRequest' => $myRequest, 'result' => $result] );
		// $request = $request->withAttribute ('session', ['a'=>'b']);	// Add the session storage to your request as [READ-ONLY]
		// return $handler->handle ($request);
		// });

		return (bool) $result;
	}

	function checkToken (?string $token): bool {
		global $pdo;

		if (empty ($token)) {
			return false;
		}

		$dbResult = $pdo->getRow ('sessions', 'token', [ 'token' => $token ]);

		return ($dbResult === false) ? false : true;					// Found the token or not
	}

}
$app->add (new Tuupola\Middleware\HttpBasicAuthentication ([
	'secure'  => true,													// Use SSL (or throw RuntimeException)
	'relaxed' => [ 'localhost' ],										// No SSL required for this hosts
	'path' => '/',														// Protect everything with this path (/)
	'ignore' => [														// Exceptions to protected path parameter
		'/v1/login',
		'/v1/forgotPassword',
		'/v1/test',
		'/v1/livewatch',
		'/v1/public',
		'/v1/facebookWebhooks',
		'/v1/facebookEvents',
	],
	'realm' => 'Protected',
	'authenticator' => new CustomAuthenticator (),						// Use our custom authenticator class
	'error' => function (Response $response, array $arguments) {
		$data = [
			'status'  => 'error',
			'message' => $arguments['message']							// eg. "Authentication failed"
		];

		$body = $response->getBody ();
		$body->write ((string) json_encode ($data, JSON_UNESCAPED_SLASHES));

		// return $response->withBody ($body);
		return $response
			->withHeader ('Access-Control-Allow-Origin', '*')
			->withHeader ('Content-Type', 'application/json')
			// ->withStatus (405)										// Has no effect
			->withBody ($body);
	}
]));


// The ErrorMiddleware should always be the outermost middleware
// $app->addErrorMiddleware(true, true, true);
/**
 * @param bool $displayErrorDetails -> Should be set to false in production
 * @param bool $logErrors -> Parameter is passed to the default ErrorHandler
 * @param bool $logErrorDetails -> Display error details in error log
 * which can be replaced by a callable of your choice.
 * Note: This middleware should be added last. It will not handle any exceptions/errors
 * for middleware added after it. If you are adding the pre-packaged ErrorMiddleware set
 * "displayErrorDetails" to "false" AND
 * php.ini setting "display_errors = 0"
 */
// Define Custom Error Handler
$myErrorHandler = function (Request $request, Throwable $exception, bool $displayErrorDetails, bool $logErrors, bool $logErrorDetails, ?LoggerInterface $logger = null) use ($app) {
	// $logger->error ($exception->getMessage ());
	$payload = [
		'errors' => [
			'title'		=> 'Error',
			'detail'	=> $exception->getMessage (),
			'statuss'	=> $exception->getCode (),
		]
	];

	$response = $app->getResponseFactory ()->createResponse ();
	$response
		// ->withStatus (501)
		// ->withHeader ('Content-Type', 'application/json')
		->getBody ()
		->write (json_encode ($payload, JSON_UNESCAPED_UNICODE));

	return $response;
};
// Instantiate Custom Error Handler
// $myErrorHandler  = new MyErrorHandler ($app->getCallableResolver (), $app->getResponseFactory ());
// Add Error Middleware

// 1.
//$errorMiddleware = $app->addErrorMiddleware (true, true, true);
//$errorMiddleware->setDefaultErrorHandler ($myErrorHandler);

// 2.
$errorMiddleware = $app->addErrorMiddleware (true, true, true);
$errorHandler = $errorMiddleware->getDefaultErrorHandler ();												// Get the default error handler
// $errorHandler->registerErrorRenderer ('text/html', MyErrorRenderer::class);
$errorHandler->registerErrorRenderer ('application/json', MyErrorRenderer::class);	// Register my custom error renderer
$errorHandler->forceContentType ('application/json');

// https://akrabat.com/custom-error-rendering-in-slim-4/
// https://stackoverflow.com/questions/62178065/custom-error-handling-without-try-except-in-php-slim-framework
// https://akrabat.com/setting-http-status-code-based-on-exception-in-slim-4/
// https://odan.github.io/2020/05/27/slim4-error-handling.html