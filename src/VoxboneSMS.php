<?php
/**
 * @author      Clint Davis <os-dev@clint.davis.to>
 * @copyright   Copyright (c) Clint Davis
 * @license     http://opensource.org/licenses/BSD-3-Clause 3-clause BSD
 *
 * @link        https://github.com/ClintDavis/voxbone-sms-php
 */

namespace ClintDavis\VoxboneSMS;

class VoxboneSMS
{

	private $baseURL;
	private $username;
	private $password;

	private $charset;

	private $messageFull;

	private $mb_strlen;

	public $fragRef;

	public $fragments;

	public $charPerFragment;

	public $messageFragments = [];

	public $to;
	public $from;

	public $to_raw;
	public $from_raw;

	/*
	*	Class constructor
	 */
	function __construct($username, $password, $baseURL = 'https://sms.voxbone.com:4443/sms/v1/') {
		$this->username = $username;
		$this->password = $password;
		$this->baseURL = $baseURL;
	}

	public function SMS($to, $from, $message, $send = true) {
		$this->to = $to;
		$this->from = $from;

		$this->to_raw = substr($this->to, 1);
		$this->from_raw = substr($this->from, 1);

		$this->generateFragRef();

		$this->message($message);

		if ($send) {
			$this->send();
		}
	}


	public function message($message) {
		$this->messageFull = $message;

		$this->detectEncodeing();
		$this->detectFragmentation();
		$this->fragmentMessage();

	}

	/*
	*	Produce charactor set naming and string length calculations
	 */

	public function detectEncodeing() {

		$this->mb_strlen = mb_strlen($this->messageFull);

		// Multibite string lengths are longer becuase of the emoji
		if($this->mb_strlen < strlen($this->messageFull)) {
			$this->charset = "UCS2"; // Emoji support
		} else {
			switch (mb_detect_encoding($this->messageFull, "UTF-7,ISO-8859-1")) {
				case 'UTF-7': // Standard 128 characters
					$this->charset = "7-Bit GSM";
					break;

				case 'ISO-8859-1': // ~ is a example of a non 7-bit GSM
					$this->charset = "Latin-1";
					break;
			}
		}

	}


	/*
	*	Based on voxbone developer docs: https://developers.voxbone.com/how-to/fragment-sms/
	 */

	public function detectFragmentation() {

		switch ($this->charset) {
			case '7-Bit GSM':
				if ($this->mb_strlen <= 160) {
					$this->charPerFragment = 160;
					$this->fragments = 1;
				} else {
					$this->charPerFragment = 153;
					$this->fragments = ceil($this->mb_strlen / $this->charPerFragment);
				}
				break;
			case 'Latin-1':
				if ($this->mb_strlen <= 140) {
					$this->charPerFragment = 140;
					$this->fragments = 1;
				} else {
					$this->charPerFragment = 134;
					$this->fragments = ceil($this->mb_strlen / $this->charPerFragment);
				}
				break;
			case 'UCS2':
				if ($this->mb_strlen <= 70) {
					$this->charPerFragment = 70;
					$this->fragments = 1;
				} else {
					$this->charPerFragment = 65;
					$this->fragments = ceil($this->mb_strlen / $this->charPerFragment);
				}
				break;
		}

	}


	/*
	*	Seperate out message based on fragmentation segments
	 */

	private function fragmentMessage() {

		for ($i=0; $i < $this->fragments; $i++) {
			$this->messageFragments[] = mb_substr($this->messageFull, $i * $this->charPerFragment, $this->charPerFragment);
		}

	}


	/*
	*	Based on developer documentation: https://developers.voxbone.com/how-to/fragment-sms/
	*	Todo: User option to set 8bit or 16bit frag ref on their account setttings.
	 */

	private function generateFragRef() {
		$this->fragRef = rand(0,255);
	}


	/*
	*	Send SMS to voxbone
	 */

	public function send($deliveryReport = 'all') {


		$client = new \GuzzleHttp\Client([
			// Base URI is used with relative requests
			'base_uri' => $this->baseURL,
			// You can set any number of default request options.
			'timeout'  => 20,
			// Set default username and password for voxbone from the .env file
			'auth' => [$this->username, $this->password, 'digest']
		]);


		$response = $client->request('POST', $this->to_raw, [
			'json' => [
				'from' 	=> $this->from,
				'msg' 	=> $this->messageFull,
				'frag' 	=> null,
				'delivery_report' => $deliveryReport
		]]);


		/*
		*	Seams that voxbones does not require the message to be fragmented.
		* However we still want reporting on this for builling purposes.
		*/

		// foreach ($this->messageFragments as $key => $msgFragment) {
		// 	$response = $client->request('POST', $this->to_raw, [
		// 		'json' => [
		// 			'from' 	=> $this->from,
		// 			'msg' 	=> $msgFragment,
		// 			'frag' 	=> [
		// 	      'frag_ref' 		=> $this->fragRef,
		// 	      'frag_total' 	=> $this->fragments,
		// 	      'frag_num' 		=> $key+1, // Prevent indexed by zero
		// 			],
		// 			'delivery_report' => 'all'
		// 	]]);
		// }


	}


}
