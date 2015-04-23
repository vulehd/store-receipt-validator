<?php
namespace ReceiptValidator\iTunes;

//use Guzzle\Http\Client as GuzzleClient;
use Buzz\Client\Curl;
use Buzz\Browser;
use ReceiptValidator\iTunes\Response;
use ReceiptValidator\RunTimeException;

class Validator
{

    const ENDPOINT_SANDBOX = 'https://sandbox.itunes.apple.com/verifyReceipt';

    const ENDPOINT_PRODUCTION = 'https://buy.itunes.apple.com/verifyReceipt';

    /**
     * endpoint url
     *
     * @var string
     */
    protected $_endpoint;

    /**
     * itunes receipt data, in base64 format
     *
     * @var string
     */
    protected $_receiptData;


    /**
     * itunes shared secret ie. password
     *
     * @var string
     */
    protected $_iStoreSharedSecret = null;

    /**
     * Guzzle http client
     *
     * @var \Guzzle\Http\Client
     */
    protected $_client = null;

    public function __construct($endpoint = self::ENDPOINT_PRODUCTION)
    {
        if ($endpoint != self::ENDPOINT_PRODUCTION && $endpoint != self::ENDPOINT_SANDBOX) {
            throw new RunTimeException("Invalid endpoint '{$endpoint}'");
        }

        $this->_endpoint = $endpoint;
    }

    /**
     * get receipt data
     *
     * @return string
     */
    public function getReceiptData()
    {
        return $this->_receiptData;
    }

    /**
     * set receipt data, either in base64, or in json
     *
     * @param string $receiptData
     * @return \ReceiptValidator\iTunes\Validator;
     */
    function setReceiptData($receiptData)
    {
        if (strpos($receiptData, '{') !== false) {
            $this->_receiptData = base64_encode($receiptData);
        } else {
            $this->_receiptData = $receiptData;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getIStoreSharedSecret()
    {
        return $this->_iStoreSharedSecret;
    }

    /**
     * @param string $iStoreSharedSecret
     */
    public function setIStoreSharedSecret($iStoreSharedSecret)
    {
        $this->_iStoreSharedSecret = $iStoreSharedSecret;

        return $this;
    }

    /**
     * get endpoint
     *
     * @return string
     */
    public function getEndpoint()
    {
        return $this->_endpoint;
    }

    /**
     * set endpoint
     *
     * @param string $endpoint
     * @return\ReceiptValidator\iTunes\Validator;
     */
    function setEndpoint($endpoint)
    {
        $this->_endpoint = $endpoint;

        return $this;
    }

    /**
     * returns the Buzz Browser client
     *
     * @return \Buzz\Browser
     */
    protected function getClient()
    {
        if ($this->_client == null) {
//            $this->_client = new GuzzleClient($this->_endpoint);
			$this->_client = $this->createClient();
        }

        return $this->_client;
    }

	/**
     * returns the Buzz Browser client
     *
     * @return \Buzz\Browser
     */
	protected function createClient()
	{
		$client = new Curl();
		$client->setVerifyPeer(false);
		$browser = new Browser($client);

		return $browser;
	}

    /**
     * encode the request in json
     *
     * @return string
     */
    private function encodeRequest()
    {
        $request = array('receipt-data' => $this->getReceiptData());

        if( !is_null( $test = $this->getIStoreSharedSecret() ) ) {
            $request['password'] = $this->getIStoreSharedSecret();
        }

        return json_encode( $request );
    }

    /**
     * validate the receipt data
     *
     * @param string $receiptData
     * @param string $iStoreSharedSecret
     *
     * @return Response
     */
    public function validate($receiptData = null, $iStoreSharedSecret = null)
    {

        if ($receiptData != null) {
            $this->setReceiptData($receiptData);
        }

        if ($iStoreSharedSecret != null) {
            $this->setIStoreSharedSecret($iStoreSharedSecret);
        }

        $httpResponse = $this->getClient()->post($this->_endpoint, array(), $this->encodeRequest());

        if ($httpResponse->getStatusCode() != 200) {
            throw new RunTimeException('Unable to get response from itunes server');
        }

        $response = new Response(json_decode($httpResponse->getContent(), true));

        // on a 21007 error retry the request in the sandbox environment (if the current environment is Production)
        // these are receipts from apple review team
        if ($this->_endpoint == self::ENDPOINT_PRODUCTION && $response->getResultCode() == Response::RESULT_SANDBOX_RECEIPT_SENT_TO_PRODUCTION) {
            $client = $this->createClient();

//            $httpResponse = $client->post(null, null, $this->encodeRequest(), array('verify' => false))->send();
            $httpResponse = $client->post(self::ENDPOINT_SANDBOX, array(), $this->encodeRequest());

            if ($httpResponse->getStatusCode() != 200) {
                throw new RunTimeException('Unable to get response from itunes server');
            }

            $response = new Response(json_decode($httpResponse->getContent(), true));
        }

        return $response;
    }
}
