<?php namespace Rapide\VoipNow\Services;

class VoipService {

	private $authUrl;
	private $apiUrl;
	private $clientID;
	private $clientSecret;
	private $cookieName = "voip_now_cookie";
	private $callerIdPrefix;

	public function __construct()
	{
		$this->clientID = config('voipnow.client_id');
		$this->clientSecret = config('voipnow.client_secret');
		$this->authUrl = config('voipnow.auth_url');
		$this->apiUrl = config('voipnow.api_url');
		$this->callerIdPrefix = config('voipnow.caller_id_prefix');
	}

	/**
	 * Retrieves a token from the voip server
	 *
	 * @return mixed
	 */

	public function getToken()
	{
		$fields = [
			'grant_type' => 'client_credentials',
			'client_id' => $this->clientID,
			'client_secret' => $this->clientSecret,
		];

		$ch = curl_init();

		curl_setopt($ch,CURLOPT_URL, $this->authUrl);
		curl_setopt($ch,CURLOPT_POST, true);
		curl_setopt($ch,CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
		curl_setopt($ch,CURLOPT_POSTFIELDS, http_build_query($fields));
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch,CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch,CURLOPT_VERBOSE, false);

		$result = @json_decode(curl_exec($ch), true);

		curl_close($ch);

		if(isset($result['access_token']) == true)
		{
			return $result;
		}

		return null;
	}

	/**
	 * Checks if the token isn't expired yet, if so, generates a new one and returns it.
	 *
	 * @return mixed
	 */

	public function renewToken()
	{
		// load from cookie if ttl is not expired yet
		$cookieData = \Cache::get($this->cookieName);
		if(isset($cookieData['ctime']) == true && $cookieData['ctime'] > time() - ($cookieData['expires_in'] - 30)) // 30 seconds safety margin
		{
			return $cookieData['access_token'];
		}

		// load from server
		if(($tokenData = $this->getToken()) != null)
		{
			$tokenData['ctime'] = time();

			\Cache::put($this->cookieName, $tokenData, $tokenData['expires_in'] / 60);
			return $tokenData['access_token'];
		}
	}

	/**
	 * Place a http request at the voip server
	 *
	 * @param string $method
	 * @param string $urlSuffix
	 * @param array $data
	 * @param int $code
	 * @return mixed
	 */

	public function callServer($method = "POST", $urlSuffix = "", $data = [], &$code = 0)
	{
		$token = $this->renewToken();

		$url = $this->apiUrl."/".$urlSuffix;

		$ch = curl_init();

		curl_setopt($ch,CURLOPT_URL, $url);
		curl_setopt($ch,CURLOPT_HTTPHEADER, [
			'Content-Type: application/json',
			'Authorization: Bearer '.$token,
		]);
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch,CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch,CURLOPT_VERBOSE, false);
		curl_setopt($ch,CURLOPT_POST, true);

		if($method == "PUT") {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		}

		curl_setopt($ch,CURLOPT_POSTFIELDS, json_encode($data));

		$result = curl_exec($ch);
		var_dump($result);
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		curl_close($ch);

		if($code >= 200 && $code <= 202)
		{
			return json_decode($result, true);
		}

		return null;
	}

	public function callNumber($extendedNumber = "", $sourceNumber, $destinationNumber = "", $destinationName = null)
	{
		if(!$destinationName) {
			$destinationName = $destinationNumber;
		}

		$response = $this->callServer("POST", "phoneCalls/@me/simple", [
			"extension" => $extendedNumber,
			"phoneCallView" => [
				[
					"source" => [
						$sourceNumber,
					],
					"destination"  => [
						$destinationNumber
					],
					"callerId" => $this->callerIdPrefix." ".$destinationName." <".$destinationNumber.">"
				]
			]
		]);


		if(isset($response[0]['id']) == true)
		{
			return $response[0];
		}

		return null;
	}

	public function getExtentions()
	{
		$result = $this->callServer('GET', 'getExtensions');

		if(isset($result->result) == true && $result->result == "failure")
		{
			return array();
		}

		return $result;
	}

	public function transfer($phoneCallViewId, $transferToNumber)
	{
		$response = $this->callServer("PUT", "phoneCalls/@me/@self/".$phoneCallViewId, [
			"action" => "Transfer",
			"sendCallTo" => $transferToNumber,
			"phoneCallViewId" => "01"
		]);

		if(isset($response[0]['id']) == true)
		{
			return $response[0];
		}

		return null;
	}

}
