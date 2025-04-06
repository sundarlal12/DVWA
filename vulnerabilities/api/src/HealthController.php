<?php

# Start the app with:
#
# php -S localhost:8000 -t public

namespace Src;

use OpenApi\Attributes as OAT;

class HealthController
{
	private $command = null;
	private $requestMethod = "GET";

	public function __construct($requestMethod, $version, $command) {
		$this->requestMethod = $requestMethod;
		$this->command = $command;
	}

    #[OAT\Post(
		tags: ["health"],
        path: '/vulnerabilities/api/v2/health/echo',
        operationId: 'echo',
		description: 'Echo, echo, cho, cho, o o ....',
        parameters: [
                new OAT\RequestBody (
					description: 'Your words.',
                    content: new OAT\MediaType(
                        mediaType: 'application/json',
                        schema: new OAT\Schema(ref: Words::class)
                    )
                ),

        ],
        responses: [
            new OAT\Response(
                response: 200,
                description: 'Successful operation.',
            ),
        ]
    )   
    ]
	
 /**
  * Processes incoming JSON via HTTP and returns an appropriate response.
  *
  * @example
  * // Assuming the input JSON is: {"words": "Hello, world!"}
  * fetch('http://yourapi/endpoint', {
  *   method: 'POST',
  *   headers: { 'Content-Type': 'application/json' },
  *   body: JSON.stringify({ words: "Hello, world!" })
  * })
  * .then(response => response.json())
  * .then(data => console.log(data)); // Logs: { reply: "Hello, world!" }
  *
  * @param {Object} inputData - JSON-decoded input data from the request body.
  * @returns {Object} response - JSON response with status and a reply or error message.
  * @description
  *   - Parses incoming data from 'php://input', expecting a JSON object.
  *   - Checks if the 'words' property exists in the input data.
  *   - Constructs an HTTP response with status and body based on the presence of 'words'.
  *   - Returns 200 OK with an echo reply if 'words' exist or 500 Internal Server Error otherwise.
  */
	private function echo() {
		$input = (array) json_decode(file_get_contents('php://input'), TRUE);
		if (array_key_exists ("words", $input)) {
			$words = $input['words'];

			$response['status_code_header'] = 'HTTP/1.1 200 OK';
			$response['body'] = json_encode (array ("reply" => $words));
		} else {
			$response['status_code_header'] = 'HTTP/1.1 500 Internal Server Error';
			$response['body'] = json_encode (array ("status" => "Words not specified"));
		}
		return $response;
	}

    #[OAT\Post(
		tags: ["health"],
        path: '/vulnerabilities/api/v2/health/connectivity',
        operationId: 'checkConnectivity',
		description: 'The server occasionally loses connectivity to other systems and so this can be used to check connectivity status.',
        parameters: [
                new OAT\RequestBody (
					description: 'Remote host.',
                    content: new OAT\MediaType(
                        mediaType: 'application/json',
                        schema: new OAT\Schema(ref: Target::class)
                    )
                ),

        ],
        responses: [
            new OAT\Response(
                response: 200,
                description: 'Successful operation.',
            ),
        ]
    )   
    ]
	
 /**
  * Checks the connectivity to a specified target by sending ping requests.
  * 
  * @example
  * // Sample input through HTTP request payload:
  * // { "target": "8.8.8.8" }
  * const response = checkConnectivity();
  * console.log(response); // Outputs: { "status_code_header": "HTTP/1.1 200 OK", "body": "{\"status\":\"OK\"}" }
  * 
  * @returns {Object} A response object containing HTTP status code and body with connectivity status.
  * @description
  *   - The function extracts the target address from the HTTP request input for connectivity testing.
  *   - Utilizes the `exec` function to perform a ping operation to the target address.
  *   - Returns connection success or failure status based on ping result.
  *   - Provides specific error response if no target is specified in the input request.
  */
	private function checkConnectivity() {
		$input = (array) json_decode(file_get_contents('php://input'), TRUE);
		if (array_key_exists ("target", $input)) {
			$target = $input['target'];

			exec ("ping -c 4 " . $target, $output, $ret_var);

			if ($ret_var == 0) {
				$response['status_code_header'] = 'HTTP/1.1 200 OK';
				$response['body'] = json_encode (array ("status" => "OK"));
			} else {
				$response['status_code_header'] = 'HTTP/1.1 500 Internal Server Error';
				$response['body'] = json_encode (array ("status" => "Connection failed"));
			}
		} else {
			$response['status_code_header'] = 'HTTP/1.1 500 Internal Server Error';
			$response['body'] = json_encode (array ("status" => "Target not specified"));
		}
		return $response;
	}

    #[OAT\Get(
		tags: ["health"],
        path: '/vulnerabilities/api/v2/health/status',
        operationId: 'getHealthStatus',
		description: 'Get the health of the system.',
        responses: [
            new OAT\Response(
                response: 200,
                description: 'Successful operation.',
            ),
        ]
    )   
    ]
	
	private function getStatus() {
		$response['status_code_header'] = 'HTTP/1.1 200 OK';
		$response['body'] = json_encode (array ("status" => "OK"));
		return $response;
	}

    #[OAT\Get(
		tags: ["health"],
        path: '/vulnerabilities/api/v2/health/ping',
        operationId: 'ping',
		description: 'Simple ping/pong to check connectivity.',
        responses: [
            new OAT\Response(
                response: 200,
                description: 'Successful operation.',
            ),
        ]
    )   
    ]
	private function ping() {
		$response['status_code_header'] = 'HTTP/1.1 200 OK';
		$response['body'] = json_encode (array ("Ping" => "Pong"));
		return $response;
	}

 /**
  * Process HTTP requests based on the request method and command.
  * It handles different request methods like POST, GET, and OPTIONS,
  * executing specific functions for each valid command or invoking
  * a generic controller for unsupported commands.
  *
  * @returns {void} Outputs the HTTP response based on the request method and command.
  *
  * @description
  * - The function distinguishes actions based on the HTTP method (`POST`, `GET`, `OPTIONS`) and a command associated with that method.
  * - The `POST` method is associated with actions like echoing data or checking connectivity.
  * - The `GET` method includes retrieving status or performing a ping operation.
  * - If the command or the request method is not supported, a GenericController is used to process unsupported or unrecognized requests.
  * - After processing, it sets the appropriate HTTP response header and body based on the response from the executed function.
  */
	public function processRequest() {
		switch ($this->requestMethod) {
			case 'POST':
				switch ($this->command) {
					case "echo":
						$response = $this->echo();
						break;
					case "connectivity":
						$response = $this->checkConnectivity();
						break;
					default:
						$gc = new GenericController("notFound");
						$gc->processRequest();
						exit();
				};
				break;
			case 'GET':
				switch ($this->command) {
					case "status":
						$response = $this->getStatus();
						break;
					case "ping":
						$response = $this->ping();
						break;
					default:
						$gc = new GenericController("notFound");
						$gc->processRequest();
						exit();
				};
				break;
			case 'OPTIONS':
				$gc = new GenericController("options");
				$gc->processRequest();
				break;
			default:
				$gc = new GenericController("notSupported");
				$gc->processRequest();
				break;
		}
		header($response['status_code_header']);
		if ($response['body']) {
			echo $response['body'];
		}
	}
}

#[OAT\Schema(required: ['target'])]
final class Target {
    #[OAT\Property(example: "digi.ninja")]
    public string $target;
}

#[OAT\Schema(required: ['words'])]
final class Words {
    #[OAT\Property(example: "Hello World")]
    public string $words;
}

