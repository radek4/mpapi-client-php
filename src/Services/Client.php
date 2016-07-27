<?php
namespace MPAPI\Services;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\ClientException;
use Psr\Log\LoggerInterface;
use MPAPI\Lib\Logger;
use MPAPI\Lib\ClientIdParser;
use MPAPI\Exceptions\ClientIdException;
use MPAPI\Lib\Handlers\ExceptionHandler;
use GuzzleHttp\Psr7\Request;

/**
 * Marketplace API client
 *
 * @author Jan Blaha <jan.blaha@mall.cz>
 */
class Client
{

	/**
	 *
	 * @var string
	 */
	const ENVIRONMENT_TEST = 'test';

	/**
	 *
	 * @var string
	 */
	const ENVIRONMENT_PRODUCTION = 'prod';

	/**
	 *
	 * @var string
	 */
	const CONFIG_FILE = '/../config/config.ini';

	/**
	 *
	 * @var string
	 */
	const API_URL_PATTERN = '%s?client_id=%s';

	/**
	 *
	 * @var string
	 */
	const LOGGER_REQUEST = 'Request %s %s';

	/**
	 *
	 * @var string
	 */
	const LOGGER_RESPONSE = 'Response for %s %s';

	/**
	 *
	 * @var string
	 */
	private $clientId;

	/**
	 *
	 * @var LoggerInterface $logger
	 */
	private $logger;

	/**
	 *
	 * @var string
	 */
	private $environment;

	/**
	 *
	 * @var GuzzleHttp\Client $httpClient
	 */
	private $httpClient;

	/**
	 *
	 * @var array
	 */
	private $config;

	/**
	 *
	 * @var array
	 */
	private $errors = [];

	/**
	 * @var array
	 */
	private $allowedEnvironment = [
		self::ENVIRONMENT_TEST,
		self::ENVIRONMENT_PRODUCTION
	];

	/**
	 *
	 * @var Request
	 */
	private $lastRequest;

	/**
	 *
	 * @param string $clientId
	 */
	public function __construct($clientId)
	{
		if (empty($clientId)) {
			throw new ClientIdException(ClientIdException::MSG_MISSING_CLIENT_ID);
		}
		$this->clientId = $clientId;
		$this->environment = self::ENVIRONMENT_TEST;

		// set default exception handler
		$this->setExceptionHandler(new ExceptionHandler($this->getLogger()));
	}

	/**
	 * Setter for logger
	 *
	 * @param LoggerInterface $logger
	 * @return Client
	 */
	public function setLogger(LoggerInterface $logger)
	{
		$this->logger = $logger;
		return $this;
	}

	/**
	 *
	 * @return LoggerInterface
	 */
	public function getLogger()
	{
		if (!$this->logger instanceof LoggerInterface) {
			$this->logger = new Logger();
		}
		return $this->logger;
	}

	/**
	 * Set custom user handler
	 *
	 * @param $errorHandler
	 * @param $errorTypes
	 * @return \MPAPI\Services\Client
	 */
	public function setErrorHandler($handler, $errorTypes)
	{
		set_error_handler($handler, $errorTypes);
		return $this;
	}

	/**
	 *
	 * @param object $handler
	 * @return \MPAPI\Services\Client
	 */
	public function setExceptionHandler($handler)
	{
		set_exception_handler($handler);
		return $this;
	}

	/**
	 *
	 * @param string $path
	 * @param string $method
	 * @param array $body
	 * @param array $args
	 * @return Response|null
	 */
	public function sendRequest($path, $method, array $body = [], array $args = [])
	{
		$response = null;
		$query = [];
		try {
			// log request parameters
			$this->getLogger()->info(sprintf(self::LOGGER_REQUEST, $method, $path), $body);
			// save request params into history
			$this->lastRequest = [
				'path' => $path,
				'method' => $method,
				'body' => $body,
				'args' => $args
			];

			// set query params
			$query['client_id'] = $this->clientId;
			$query = array_merge($query, $args);

			/* @var Response $response */
			$response = $this->getHttpClient()->request($method, $path, [
				'json' => $body,
				'query' => [
					$query
				]
			]);

			// log response
			$this->getLogger()->info(sprintf(self::LOGGER_RESPONSE, $method, $path), json_decode($response->getBody(), true));
		} catch (ClientIdException $e) {
			$this->getLogger()->error(sprintf(self::LOGGER_RESPONSE, $method, $path), ['message' => $e->getMessage()]);
			throw $e;
		} catch (ClientException $e) {
			$this->getLogger()->error($e->getMessage(), [
				'method' => $method,
				'path' => $path,
				'body' => $body,
				'client_id' => $this->clientId
			]);
			throw $e;
		}
		return $response;
	}

	/**
	 * Repeat last request
	 *
	 * @param array $args
	 * @return Response|null
	 */
	public function repeatLastRequest(array $args = [])
	{
		$args = array_merge($this->lastRequest['args'], $args);
		return $this->sendRequest(
			$this->lastRequest['path'],
			$this->lastRequest['method'],
			$this->lastRequest['body'],
			$args
		);
	}

	/**
	 * Get configuration
	 *
	 * @param string $environment
	 * @return string
	 */
	private function getConfig($environment)
	{
		$retval = null;
		if (file_exists(__DIR__ . self::CONFIG_FILE)) {
			$this->config = parse_ini_file(__DIR__ . self::CONFIG_FILE, true);
		}

		if (!in_array($environment, $this->allowedEnvironment)) {
			throw new ClientIdException(sprintf(ClientIdException::MSG_UNKNOWN_ENVIRONMENT, $environment));
		}

		if (isset($this->config[$environment])) {
			$retval = $this->config[$environment];
		}
		return $retval;
	}

	/**
	 * Get client for network communication
	 *
	 * @return HttpClient
	 */
	private function getHttpClient()
	{
		if (!$this->httpClient instanceof Client) {
			$config = $this->getConfig($this->getEnvironment());
			/* @var GuzzleHttp\Client */
			$this->httpClient = new HttpClient([
				'base_uri' => $config['url'],
				'timeout' => 0,
				'allow_redirects' => false
			]);
		}
		return $this->httpClient;
	}

	/**
	 * Get environment from client id
	 *
	 * @return string
	 */
	private function getEnvironment()
	{
		$parser = new ClientIdParser($this->clientId);
		return $parser->getEnvironment();
	}
}
