<?php namespace Rapide\VoipNow\Services;


use Rapide\VoipNow\Helpers\Extension;
use Rapide\VoipNow\Helpers\Organization;
use Rapide\VoipNow\Helpers\User;

class VoipService {

	private $authUrl;
	private $apiUrl;
	private $cookieName = "voip_now_cookie";
	private $callerIdPrefix;
	private $soapClientID;
	private $clientSecret;
	private $restClientID;
	private $restClientSecret;
	private $cookie = "/tmp/cookie_%s.txt"; // placeholder filled with unified or system
	private $debug = true;

	public function __construct()
	{
		$this->soapClientID = config('voipnow.client_id');
		$this->clientSecret = config('voipnow.client_secret');
		$this->authUrl = config('voipnow.auth_url');
		$this->restClientID = config('voipnow.client_id');
		$this->restClientSecret = config('voipnow.client_secret');
		$this->apiUrl = config('voipnow.api_url');
		$this->callerIdPrefix = config('voipnow.caller_id_prefix');
	}

	private function debug($line)
	{
		if($this->debug == false)
		{
			return;
		}
		echo date("Y-m-d H:i:s").": ".$line."\n";
	}

	/**
	 * Retrieves a token from the voip server
	 *
	 * @return mixed
	 */

	public function getToken($type = "system")
	{
		$fields = [
			'grant_type' => 'client_credentials',
			'redirect_uri' => 'https://91.208.60.99',
		];

		if($type == "system")
		{
			$fields += array(
				'client_id' => $this->soapClientID,
				'client_secret' => $this->clientSecret,
			);
		}
		else
		{
			$fields += array(
				'client_id' => $this->restClientID,
				'client_secret' => $this->restClientSecret,
			);
		}


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

	public function renewToken($type = "system")
	{
		// load from cookie if ttl is not expired yet
		/* $cookieData = \Cache::get($this->cookieName);
		if(isset($cookieData['ctime']) == true && $cookieData['ctime'] > time() - ($cookieData['expires_in'] - 30)) // 30 seconds safety margin
		{
			return $cookieData['access_token'];
		}

		*/

		// load from server
		if(($tokenData = $this->getToken($type)) != null)
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
		/*
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
	} */

	private function callSoapClient($method = "", $data = array(), &$debug = "")
	{
		$token = $this->renewToken();

		$namespace = "http://4psa.com/HeaderData.xsd/3.0.0";
		$credentials = new \stdClass();
		$credentials->accessToken = $token;

		$header = new \SoapHeader($namespace, "userCredentials", $credentials);

		$context = stream_context_create([
			'ssl' => [
				// set some SSL/TLS specific options
				'verify_peer' => false,
				'verify_peer_name' => false,
				'allow_self_signed' => true
			]
		]);

		$client  = new \SoapClient("https://voip.rapide.nl/soap2/schema/latest/voipnowservice.wsdl", [
			'stream_context' => $context
		]);
		$client->__setSoapHeaders($header);

		try
		{
			return $client->$method($data);
		}
		catch(\Exception $e)
		{
			$debug = $e->getMessage();
			$this->debug($debug);
		}

		return null;
	}

	public function getUsers()
	{
		$result = $this->callSoapClient("getUsers");

		if(isset($result->result) == true && $result->result == "failure")
		{
			return array();
		}

		$users = array();

		foreach($result as $res)
		{
			if(count($res) > 1) {
				foreach ($res as $r) {
					$user = new User($r->id, $r->name, $r->firstName, $r->lastName, $r->login, $r->email, $r->identifier);
					$users[] = $user;
				}
			}
			else{
				foreach ($res as $r) {
					$user = new User($res->id, $res->name, $res->firstName, $res->lastName, $res->login, $res->email, $res->identifier);
					$users[] = $user;
				}
			}
		}

		return $users;
	}

	public function getOrganizations()
	{
		$result = $this->callSoapClient("getOrganizations");

		if(isset($result->result) == true && $result->result == "failure")
		{
			return array();
		}

		$organizations = array();

		foreach($result as $res)
		{
			if(count($res) > 1)
			{
				foreach($res as $r)
				{
					$organization = new Organization($r->ID,$r->name,$r->firstName,$r->lastName, $r->login, $r->email,$r->identifier );
					$organizations[] = $organization;
				}
			}
			else{
				$organization = new Organization($res->ID,$res->name,$res->firstName,$res->lastName, $res->login, $res->email,$res->identifier );
				$organizations[] = $organization;
			}

		}

		return $organizations;
	}


	public function getUserDetails($userID = 0)
	{
		$result = $this->callSoapClient("getUserDetails", array(
			'ID' => $userID,
		));

		if(isset($result->result) == true && $result->result == "failure")
		{
			return null;
		}

		return $result;
	}

	public function deleteUser($userID = 0)
	{
		return $this->callSoapClient("DelUser", array(
			'ID' => $userID,
		));

		if(isset($result->result) == true && $result->result == "success")
		{
			return true;
		}

		return false;
	}


	public function addUser($login = "", $name = "", $password = "", $parentID = 0)
	{
		$result = $this->callSoapClient("addUser", array(
			'login' => $login,
			'name' => $name,
			'password' => $password,
			'parentID' => $parentID,
		));

		if(isset($result->result) == true && $result->result == "success")
		{
			return true;
		}

		return false;
	}

	public function getExtentions()
	{
		$result = $this->callSoapClient("getExtensions");

		if(isset($result->result) == true && $result->result == "failure")
		{
			return array();
		}

		$extensions = array();
		foreach($result as $res)
		{
			if(count($res) > 1)
			{
				foreach($res as $r)
				{
					$extension = new Extension($r->name,$r->firstName,$r->lastName, $r->extendedNumber, $r->email,$r->label, $r->extensionType,$r->identifier );
					$extensions[] = $extension;
				}
			}
			else{
				$extension = new Extension($res->name,$res->firstName,$res->lastName, $res->extendedNumber, $res->email,$res->label, $res->extensionType,$res->identifier );
				$extensions[] = $extension;
			}

		}

		return $extensions;
	}

	public function addExtension($label = "", $password = "", $parentID = 0, $extensionType = "", $templateID = 0)
	{
		$result = $this->callSoapClient("addExtension", array(
			'label' => $label,
			'password' => $password,
			'parentID' => $parentID,
			'extensionType' => $extensionType, // term, phoneQueue, ivr, voicecenter, queuecenter, conference
			'templateID' => $templateID, // ID of extention template (look in GUI)
		));

		if(isset($result->result) == true && $result->result == "success")
		{
			return true;
		}

		return false;
	}

	public function updateExtension($extendedNumber = "", $label = "", $password = "")
	{
		$result = $this->callSoapClient("editExtension", array(
			'extendedNumber' => $extendedNumber,
			'label' => $label,
			'password' => $password,
		));

		if(isset($result->result) == true && $result->result == "success")
		{
			return true;
		}

		return false;
	}

	public function deleteExtension($extendedNumber = "")
	{
		$result = $this->callSoapClient("delExtension", array(
			'extendedNumber' => $extendedNumber
		));

		if(isset($result->result) == true && $result->result == "success")
		{
			return true;
		}

		return false;
	}

	public function getExtensionSettings($extendedNumber = "")
	{
		$result = $this->callSoapClient("getExtensionSettings", array(
			'extendedNumber' => $extendedNumber
		));

		if(isset($result->extendedNumber) == true)
		{
			return $result;
		}

		return null;
	}


	public function getVoicemailSettings($extendedNumber = "")
	{
		$result = $this->callSoapClient("getVoicemailSettings", array(
			'extendedNumber' => $extendedNumber
		));

		if(isset($result->extendedNumber) == true)
		{
			return $result;
		}

		return null;
	}

	public function getCallRulesIn($extendedNumber = "")
	{
		$result = $this->callSoapClient("GetCallRulesIn", array(
			'extendedNumber' => $extendedNumber
		));

		if(isset($result->extendedNumber) == true)
		{
			return $result;
		}

		return array();
	}


	public function deleteCallRulesIn($extendedNumber = "", $id = "")
	{
		$result = $this->callSoapClient("DelCallRulesIn", array(
			'extendedNumber' => $extendedNumber,
			'ID' => $id
		));

		if(isset($result->result) == true && $result->result == "success")
		{
			return $result;
		}

		return null;
	}


	public function addCallRulesIn($extendedNumber = "", $position = 0, $final = false, $ring = 0, $intervalID = 0, $numbers = array())
	{
		$type = 'cascade'; // 'type' => $type // cascade, busy, transfer, hangup, congestion

		$result = $this->callSoapClient("AddCallRulesIn", array(
			'extendedNumber' => $extendedNumber,
			'rule' => array(
				'cascade' => array(
					'intervalID' => $intervalID,
					'position' => $position,
					'ring' => $ring,
					'final' => $final,
					'toNumbers' => $numbers
				)
			)
		));

		if(isset($result->result) == true && $result->result == "success")
		{
			return $result;
		}

		return null;
	}

	/*
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

	*/

	public function callRestClient($url = "", $method = "GET", $data = array(), &$code = 0)
	{
		$token = $this->renewToken("unified");
		// prepend if not full url
		if(strstr($url, "http") == false)
		{
			$url = "https://voip.rapide.nl/uapi/".$url;
		}

		$ch = curl_init();

		curl_setopt($ch,CURLOPT_URL, $url);
		curl_setopt($ch,CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'Authorization: Bearer '.$token,
		));
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch,CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch,CURLOPT_VERBOSE, false);


		// POST and PUT requests
		if($method == "POST" || $method == "PUT")
		{
			if($method == "PUT")
			{
				curl_setopt($ch,CURLOPT_CUSTOMREQUEST, "PUT");
			}
			curl_setopt($ch,CURLOPT_POST, true);
			curl_setopt($ch,CURLOPT_POSTFIELDS, json_encode($data));
		}
		// DELETE requests
		elseif($method == "DELETE")
		{
			curl_setopt($ch,CURLOPT_CUSTOMREQUEST, "DELETE");
		}

		$result = curl_exec($ch);
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		curl_close($ch);

		if($code == 200 || $code == 201)
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

		$response = $this->callRestClient("phoneCalls/@me/simple","POST", [
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


	public function getPhoneCallEvents($extendedNumber = "")
	{
		$result = $this->callRestClient("extensions/@me/".$extendedNumber."/phoneCallEvents");

		if(isset($result['entry']) == true)
		{
			return $result;
		}

		return array();
	}

	private function translateCallEventType($type)
	{
		switch($type)
		{
			case "incomming":
				return 0;
				break;
			case "outgoing":
				return 1;
				break;
			case "hangup":
				return 2;
				break;
			default:
				$this->debug("unsupported even type ".$type);
				break;
		}
	}

	public function addPhoneCallEvent($extendedNumber = "", $type = "", $url = "", $note = "")
	{
		$data = array(
			'type' => $this->translateCallEventType($type),
			'method' => '1', // 1=post, 0=get
			'note' => $note,
			'url' => $url,
			'status' => '1' // 1 for active, 0 for inactive
		);

		$result = $this->callRestClient("extensions/@me/".$extendedNumber."/phoneCallEvents", "POST", $data);
		if($result == false)
		{
			return false;
		}

		return true;
	}


	public function deletePhoneCallEvent($extendedNumber = "", $type = "", $id = "")
	{
		$type = $this->translateCallEventType($type);

		$result = $this->callRestClient("extensions/@me/".$extendedNumber."/phoneCallEvents/".$type."/".$id, "DELETE");

		if($result == false)
		{
			return false;
		}

		return true;
	}

	public function getPresence($extendedNumber = "")
	{
		$result = $this->callRestClient("extensions/@me/".$extendedNumber."/presence", "GET");
		if(isset($result))
		{
			return $result;
		}
		return null;
	}


	public function cdrs($userID = 0, $startDate = 0, $endDate = 0, $url = "")
	{
		$cdrs = array();

		// format date to unified fomat
		$startDateFormatted = urlencode(date("Y-m-d\TH:i:sP", $startDate));
		$endDateFormatted = urlencode(date("Y-m-d\TH:i:sP", $endDate));

		// initial call uses partial url, succeeding calls use full url
		if($url == "") // initial call
		{
			$url = "cdr/".$userID."?count=4000&startDate=".$startDateFormatted."&endDate=".$endDateFormatted;
		}

		$response = $this->callRestClient($url);

		// any records for this user?
		if(isset($response['entry']) == true)
		{
			$cdrs = $response['entry'];
		}
		elseif(isset($response['error']) == true)
		{
			$this->debug($response['error']['message']);
		}

		// any more where that came from?
		if(isset($response['paging']['next']) == true)
		{
			$cdrs = array_merge($cdrs, $this->cdrs($userID, $startDate, $endDate, $response['paging']['next']));
		}

		return $cdrs;
	}





}
