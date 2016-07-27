<?php
namespace MPAPI\Lib;

use GuzzleHttp\Psr7\Response;
use MPAPI\Services\Client;

/**
 *
 * @author Jan Blaha <jan.blaha@mall.cz>
 */
class DataCollector
{
	/**
	 *
	 * @var boolean
	 */
	private $isSeekable;

	/**
	 *
	 * @var integer
	 */
	private $currentPage = 1;

	/**
	 *
	 * @var integer
	 */
	private $pages = 1;

	/**
	 *
	 * @var array
	 */
	private $data = [];

	/**
	 *
	 * @var Client
	 */
	private $client;

	/**
	 *
	 * @param Client $client
	 * @param Response $response
	 */
	public function __construct(Client $client, Response $response)
	{
		$this->client = $client;
		// process response body
		$this->processResponse($response);

		while ($this->isSeekable === true) {
			$this->nextPage();
		}
	}

	/**
	 * Process response data
	 *
	 * @param Response $response
	 */
	private function processResponse(Response $response)
	{
		// parse response body
		$body = json_decode($response->getBody(), true);
		if (isset($body['paging'])) {
			$this->pages = (int)$body['paging']['pages'];
			$this->isSeekable =  $this->pages > 1;
		}
		$this->data = $body['data'];
	}

	/**
	 * Load next page
	 *
	 * @return Response|null
	 */
	private function nextPage()
	{
		$this->currentPage++;
		if ($this->currentPage > $this->pages) {
			$this->isSeekable = false;
		}

		return $this->client->repeatLastRequest(['page' => $this->currentPage]);
	}
}
