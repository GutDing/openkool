<?php
/* +----------------------------------------------------------------------+
 * | SMS_Clickatell                                                       |
 * +----------------------------------------------------------------------+
 * | Copyright (c) 2002-2004 Jacques Marneweck                            |
 * +----------------------------------------------------------------------+
 * | This source file is subject to version 3.0 of the PHP license,       |
 * | that is bundled with this package in the file LICENSE, and is        |
 * | available at through the world-wide-web at                           |
 * | http://www.php.net/license/3_0.txt.                                  |
 * | If you did not receive a copy of the PHP license and are unable to   |
 * | obtain it through the world-wide-web, please send a note to          |
 * | license@php.net so we can mail you a copy immediately.               |
 * +----------------------------------------------------------------------+
 * | Authors: Jacques Marneweck <jacques@php.net>                         |
 * +----------------------------------------------------------------------+
 */


/**
 * PHP Interface into Clickatell API
 *
 * @author	Jacques Marneweck <jacques@php.net>
 * @copyright	2002-2004 Jacques Marneweck
 * @version	$Id: Clickatell.php,v 1.3 2007-12-09 14:14:31 idefix Exp $
 * @access	public
 * @package	SMS
 */

class SMS_Clickatell {
	/**
	 * Clickatell API Server
	 * @var string
	 */
	var $_api_server = "https://api.clickatell.com";

	/**
	 * Clickatell API Server Session ID
	 * @var string
	 */
	var $_session_id = null;

	/**
	 * Username from Clickatell used for authentication purposes
	 * @var string
	 */
	var $_username = null;

	/**
	 * Password for Clickatell Usernaem
	 * @var string
	 */
	var $_password = null;

	/**
	 * Clickatell API Server ID
	 * @var string
	 */
	var $_api_id = null;

	/**
	 * Temporary file resource id
	 * @var	resource
	 */
	var $_fp;

	/**
	 * Error codes generated by Clickatell Gateway
	 * @var array
	 */
	var $_errors = array (
		'001' => 'Authentication failed',
		'002' => 'Unknown username or password',
		'003' => 'Session ID expired',
		'004' => 'Account frozen',
		'005' => 'Missing session ID',
		'007' => 'IP lockdown violation',
		'101' => 'Invalid or missing parameters',
		'102' => 'Invalid UDH. (User Data Header)',
		'103' => 'Unknown apismgid (API Message ID)',
		'104' => 'Unknown climsgid (Client Message ID)',
		'105' => 'Invalid Destination Address',
		'106' => 'Invalid Source Address',
		'107' => 'Empty message',
		'108' => 'Invalid or missing api_id',
		'109' => 'Missing message ID',
		'110' => 'Error with email message',
		'111' => 'Invalid Protocol',
		'112' => 'Invalid msg_type',
		'113' => 'Max message parts exceeded',
		'114' => 'Cannot route message',
		'115' => 'Message Expired',
		'116' => 'Invalid Unicode Data',
		'201' => 'Invalid batch ID',
		'202' => 'No batch template',
		'301' => 'No credit left',
		'302' => 'Max allowed credit'
	);

	/**
	 * Message status
	 *
	 * @var array
	 */
	var $_message_status = array (
		'001' => 'Message unknown',
		'002' => 'Message queued',
		'003' => 'Delivered',
		'004' => 'Received by recipient',
		'005' => 'Error with message',
		'006' => 'User cancelled message delivery',
		'007' => 'Error delivering message',
		'008' => 'OK',
		'009' => 'Routing error',
		'010' => 'Message expired',
		'011' => 'Message queued for later delivery',
		'012' => 'Out of credit'
	);

	var $_msg_types = array (
		'SMS_TEXT',
		'SMS_FLASH',
		'SMS_NOKIA_OLOGO',
		'SMS_NOKIA_GLOGO',
		'SMS_NOKIA_PICTURE',
		'SMS_NOKIA_RINGTONE',
		'SMS_NOKIA_RTTL',
		'SMS_NOKIA_CLEAN',
		'SMS_NOKIA_VCARD',
		'SMS_NOKIA_VCAL'
	);

	/**
	 * Authenticate to the Clickatell API Server.
	 *
	 * @return mixed true on sucess
	 * @access public
	 * @since 1.1
	 */
	function auth (&$error) {
		$_url = $this->_api_server . "/http/auth";
		$_post_data = "user=" . $this->_username . "&password=" . $this->_password . "&api_id=" . $this->_api_id;
		$this->_fp = tmpfile();
		$_curl = curl_init();
		curl_setopt($_curl, CURLOPT_URL, $_url);
		curl_setopt($_curl, CURLOPT_TIMEOUT, 20);
		curl_setopt($_curl, CURLOPT_FILE, $this->_fp);
		curl_setopt($_curl, CURLOPT_POSTFIELDS, $_post_data);
		curl_setopt($_curl, CURLOPT_VERBOSE, 0);
		curl_setopt($_curl, CURLOPT_FAILONERROR, 1);
		curl_setopt($_curl, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($_curl, CURLOPT_COOKIEJAR, "/dev/null");
		curl_setopt($_curl, CURLOPT_SSL_VERIFYPEER, FALSE);

		$status = curl_exec($_curl);
		$response['http_code'] = curl_getinfo($_curl, CURLINFO_HTTP_CODE);

		if ($status) {
			$response['error'] = curl_error($_curl);
			$response['errno'] = curl_errno($_curl);
		}

		curl_close($_curl);

		rewind($this->_fp);

		$pairs = "";
		while ($str = fgets($this->_fp, 4096)) {
			$pairs .= $str;
		}
		fclose($this->_fp);

		$response['data'] = $pairs;
		unset($pairs);
		asort($response);
		$sess = split(":", $response['data']);

		$this->_session_id = trim($sess[1]);

		if ($sess[0] == "OK") {
			return (true);
		} else {
			$error = $response['data'];
			return FALSE;
		}
	}

	/**
	 * Delete message queued by Clickatell which has not been passed
	 * onto the SMSC.
	 *
	 * @access	public
	 * @since	1.14
	 * @see		http://www.clickatell.com/downloads/Clickatell_http_2.2.2.pdf
	 */
	function deletemsg ($apimsgid) {
		$_url = $this->_api_server . "/http/delmsg";
		$_post_data = "session_id=" . $this->_session_id . "&apimsgid=" . $apimsgid;

		$this->_fp = tmpfile();
		$_curl = curl_init();
		curl_setopt($_curl, CURLOPT_URL, $_url);
		curl_setopt($_curl, CURLOPT_TIMEOUT, 20);
		curl_setopt($_curl, CURLOPT_FILE, $this->_fp);
		curl_setopt($_curl, CURLOPT_POSTFIELDS, $_post_data);
		curl_setopt($_curl, CURLOPT_VERBOSE, 0);
		curl_setopt($_curl, CURLOPT_FAILONERROR, 1);
		curl_setopt($_curl, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($_curl, CURLOPT_COOKIEJAR, "/dev/null");
		curl_setopt($_curl, CURLOPT_SSL_VERIFYPEER, FALSE);

		$status = curl_exec($_curl);
		$response['http_code'] = curl_getinfo($_curl, CURLINFO_HTTP_CODE);

		if ($status) {
			$response['error'] = curl_error($_curl);
			$response['errno'] = curl_errno($_curl);
		}

		curl_close($_curl);
		rewind($this->_fp);

		$pairs = "";
		while ($str = fgets($this->_fp, 4096)) {
			$pairs .= $str;
		}
		fclose($this->_fp);

		$response['data'] = $pairs;
		unset($pairs);
		asort($response);
		$sess = split(":", $response['data']);

		$deleted = preg_split("/[\s:]+/", $response['data']);
		if ($deleted[0] == "ID") {
			return (array($deleted[1], $deleted[3]));
		} else {
			return trigger_error($response['data'], E_USER_ERROR);
		}
	}

	/**
	 * Query balance of remaining SMS credits
	 *
	 * @access	public
	 * @since	1.9
	 */
	function getbalance () {
		$_url = $this->_api_server . "/http/getbalance";
		$_post_data = "session_id=" . $this->_session_id;

		$this->_fp = tmpfile();
		$_curl = curl_init();
		curl_setopt($_curl, CURLOPT_URL, $_url);
		curl_setopt($_curl, CURLOPT_TIMEOUT, 20);
		curl_setopt($_curl, CURLOPT_FILE, $this->_fp);
		curl_setopt($_curl, CURLOPT_POSTFIELDS, $_post_data);
		curl_setopt($_curl, CURLOPT_VERBOSE, 0);
		curl_setopt($_curl, CURLOPT_FAILONERROR, 1);
		curl_setopt($_curl, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($_curl, CURLOPT_COOKIEJAR, "/dev/null");
		curl_setopt($_curl, CURLOPT_SSL_VERIFYPEER, FALSE);

		$status = curl_exec($_curl);
		$response['http_code'] = curl_getinfo($_curl, CURLINFO_HTTP_CODE);

		if ($status) {
			$response['error'] = curl_error($_curl);
			$response['errno'] = curl_errno($_curl);
		}
		curl_close($_curl);

		rewind($this->_fp);

		$pairs = "";
		while ($str = fgets($this->_fp, 4096)) {
			$pairs .= $str;
		}
		fclose($this->_fp);

		$response['data'] = $pairs;
		asort($response);
		$send = split(":", $response['data']);

		if ($send[0] == "Credit") {
			return trim($send[1]);
		} else {
			return trigger_error($response['data'], E_USER_ERROR);
		}
	}

	/**
	 * Determine the cost of the message which was sent
	 *
	 * @param	string	api_msg_id
	 * @since	1.20
	 */
	function getmsgcharge ($apimsgid) {
		$_url = $this->_api_server . "/http/getmsgcharge";
		$_post_data = "session_id=" . $this->_session_id . "&apimsgid=" . trim($apimsgid);

		$this->_fp = tmpfile();
		$_curl = curl_init();
		curl_setopt($_curl, CURLOPT_URL, $_url);
		curl_setopt($_curl, CURLOPT_TIMEOUT, 20);
		curl_setopt($_curl, CURLOPT_FILE, $this->_fp);
		curl_setopt($_curl, CURLOPT_POSTFIELDS, $_post_data);
		curl_setopt($_curl, CURLOPT_VERBOSE, 0);
		curl_setopt($_curl, CURLOPT_FAILONERROR, 1);
		curl_setopt($_curl, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($_curl, CURLOPT_COOKIEJAR, "/dev/null");
		curl_setopt($_curl, CURLOPT_SSL_VERIFYPEER, FALSE);

		$status = curl_exec($_curl);
		$response['http_code'] = curl_getinfo($_curl, CURLINFO_HTTP_CODE);

		if ($status) {
			$response['error'] = curl_error($_curl);
			$response['errno'] = curl_errno($_curl);
		}
		curl_close($_curl);

		rewind($this->_fp);
		
		$pairs = "";
		while ($str = fgets($this->_fp, 4096)) {
			$pairs .= $str;
		}
		fclose($this->_fp);

		$response['data'] = $pairs;
		asort($response);
		$charge = preg_split("/[\s:]+/", $response['data']);

		if ($charge[2] == "charge") {
			return (array($charge[3], $charge[5]));
		}

		/**
		 * Return charge and message status
		 */
		return (array($charge[3], $charge[5]));
	}

	/**
	 * Initilaise the Clicaktell SMS Class
	 *
	 * @access	public
	 * @since	1.9
	 */
	function init ($_params = array()) {
		if (is_array($_params)) {
			if (!isset($_params['user'])) {
				return trigger_error('Missing parameter user.', E_USER_ERROR);
			}

			if (!isset($_params['pass'])) {
				return trigger_error('Missing parameter pass.', E_USER_ERROR);
			}

			if (!isset($_params['api_id'])) {
				return trigger_error('Missing parameter api_id.', E_USER_ERROR);
			}

			$this->_username = $_params['user'];
			$this->_password = $_params['pass'];
			$this->_api_id = $_params['api_id'];
		} else {
			return trigger_error('You need to specify paramaters for authenticating to Clickatell.', E_USER_ERROR);
		}
	}

	/**
	 * Keep our session to the Clickatell API Server valid.
	 *
	 * @return mixed true on sucess
	 * @access public
	 * @since 1.1
	 */
	function ping () {
		$_url = $this->_api_server . "/http/ping";
		$_post_data = "session_id=" . $this->_session_id;
		$this->_fp = tmpfile();
		$_curl = curl_init();
		curl_setopt($_curl, CURLOPT_URL, $_url);
		curl_setopt($_curl, CURLOPT_TIMEOUT, 20);
		curl_setopt($_curl, CURLOPT_FILE, $this->_fp);
		curl_setopt($_curl, CURLOPT_POSTFIELDS, $_post_data);
		curl_setopt($_curl, CURLOPT_VERBOSE, 0);
		curl_setopt($_curl, CURLOPT_FAILONERROR, 1);
		curl_setopt($_curl, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($_curl, CURLOPT_COOKIEJAR, "/dev/null");
		curl_setopt($_curl, CURLOPT_SSL_VERIFYPEER, FALSE);

		$status = curl_exec($_curl);
		$response['http_code'] = curl_getinfo($_curl, CURLINFO_HTTP_CODE);

		if ($status) {
			$response['error'] = curl_error($_curl);
			$response['errno'] = curl_errno($_curl);
		}
		curl_close($_curl);

		rewind($this->_fp);

		$pairs = "";
		while ($str = fgets($this->_fp, 4096)) {
			$pairs .= $str;
		}
		fclose($this->_fp);

		$response['data'] = $pairs;
		unset($pairs);
		asort($response);
		$sess = split(":", $response['data']);

		if ($sess[0] == "OK") {
			return (true);
		} else {
			return trigger_error($response['data'], E_USER_ERROR);
		}
	}

	/**
	 * Query message status
	 *
	 * @param string spimsgid generated by Clickatell API
	 *
	 * @return string message status
	 * @access public
	 * @since 1.5
	 */

	function querymsg ($apimsgid) {
		$_url = $this->_api_server . "/http/querymsg";
		$_post_data = "session_id=" . $this->_session_id . "&apimsgid=" . $apimsgid;
		$this->_fp = tmpfile();
		$_curl = curl_init();
		curl_setopt($_curl, CURLOPT_URL, $_url);
		curl_setopt($_curl, CURLOPT_TIMEOUT, 20);
		curl_setopt($_curl, CURLOPT_FILE, $this->_fp);
		curl_setopt($_curl, CURLOPT_POSTFIELDS, $_post_data);
		curl_setopt($_curl, CURLOPT_VERBOSE, 0);
		curl_setopt($_curl, CURLOPT_FAILONERROR, 1);
		curl_setopt($_curl, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($_curl, CURLOPT_COOKIEJAR, "/dev/null");
		curl_setopt($_curl, CURLOPT_SSL_VERIFYPEER, FALSE);

		$status = curl_exec($_curl);
		$response['http_code'] = curl_getinfo($_curl, CURLINFO_HTTP_CODE);

		if ($status) {
			$response['error'] = curl_error($_curl);
			$response['errno'] = curl_errno($_curl);
		}
		curl_close($_curl);

		rewind($this->_fp);

		$pairs = "";
		while ($str = fgets($this->_fp, 4096)) {
			$pairs .= $str;
		}
		fclose($this->_fp);

		$response['data'] = $pairs;
		unset($pairs);
		asort($response);
		$status = split(" ", $response['data']);

		if ($status[0] == "ID:") {
			return (trim($status[3]));
		} else {
			return trigger_error($response['data'], E_USER_ERROR);
		}
	}

	/**
	 * Send an SMS Message via the Clickatell API Server
	 *
	 * @param array database result set
	 *
	 * @return mixed true on sucess
	 * @access public
	 * @since 1.2
	 */
	function sendmsg ($_msg) {
		$_url = $this->_api_server . "/http/sendmsg";
		$_post_data = "session_id=" . $this->_session_id . "&to=" . $_msg['to'] . "&text=" . urlencode ($_msg['text']) . "&callback=0&deliv_ack=0&from=" . $_msg['from'] . "&climsgid=" . $_msg['climsgid'];

		if (!in_array($_msg['msg_type'], $this->_msg_types)) {
			return trigger_error("Invalid message type. Message ID is " . $_msg['id'], E_USER_ERROR);
		}

		if ($_msg['msg_type'] != "SMS_TEXT") {
			$_post_data .= $_post_data . "&msg_type=" . $_msg['msg_type'];
		}

		//Set concat parameter if message is longer than 160 characters and thus has to be sent in more than one sms
		$num = strlen($_msg['text']);
		if($num > 160) {
			$concat = 1;
			while($num > 153) {
				$num -= 153;
				$concat++;
			}
			$_post_data .= '&concat='.$concat;
		}

		/**
		 * Check if we are using a queue when sending as each account
		 * with Clickatell is assigned three queues namely 1, 2 and 3.
		 */
		if (isset($_msg['queue']) && is_numeric($_msg['queue'])) {
			if (in_array($_msg['queue'], range(1, 3))) {
				$_post_data .= $_post_data . "&queue=" . $_msg['queue'];
			}
		}

		$req_feat = 0;
		/**
		 * Normal text message
		 */
		if ($_msg['msg_type'] == 'SMS_TEXT') {
			$req_feat += 1;
		}
		/**
		 * We set the sender id is alpha numeric or numeric
		 * then we change the sender from data.
		 */
		if (is_numeric($_msg['from'])) {
			$req_feat += 32;
		} elseif (is_string($_msg['from'])) {
			$req_feat += 16;
		}
		/**
		 * Flash Messaging
		 */
		if ($_msg['msg_type'] == 'SMS_FLASH') {
			$req_feat += 512;
		}
		/**
		 * Delivery Acknowledgments
		 */
		$req_feat += 8192;

		if (!empty($req_feat)) {
			$_post_data .= "&req_feat=" . $req_feat;
		}

		/**
		 * Must we escalate message delivery if message is stuck in
		 * the queue at Clickatell?
		 */
		if (isset($_msg['escalate']) && !empty($_msg['escalate'])) {
			if (is_numeric($_msg['escalate'])) {
				if (in_array($_msg['escalate'], range(1, 2))) {
					$_post_data .= $_post_data . "&escalate=" . $_msg['escalate'];
				}
			}
		}

		$this->_fp = tmpfile();
		$_curl = curl_init();
		curl_setopt($_curl, CURLOPT_URL, $_url);
		curl_setopt($_curl, CURLOPT_TIMEOUT, 20);
		curl_setopt($_curl, CURLOPT_FILE, $this->_fp);
		curl_setopt($_curl, CURLOPT_POSTFIELDS, $_post_data);
		curl_setopt($_curl, CURLOPT_VERBOSE, 0);
		curl_setopt($_curl, CURLOPT_FAILONERROR, 1);
		curl_setopt($_curl, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($_curl, CURLOPT_COOKIEJAR, "/dev/null");
		curl_setopt($_curl, CURLOPT_SSL_VERIFYPEER, FALSE);

		$status = curl_exec($_curl);
		$response['http_code'] = curl_getinfo($_curl, CURLINFO_HTTP_CODE);

		if ($status) {
			$response['error'] = curl_error($_curl);
			$response['errno'] = curl_errno($_curl);
		}
		curl_close($_curl);

		rewind($this->_fp);

		$pairs = "";
		while ($str = fgets($this->_fp, 4096)) {
			$pairs .= $str;
		}
		fclose($this->_fp);

		$response['data'] = $pairs;
		asort($response);
		$send = split(":", $response['data']);

		if ($send[0] == "ID") {
			return array ("1", trim($send[1]));
		} else {
			return trigger_error($response['data'], E_USER_ERROR);
		}
	}
}

/* vim: set noet ts=4 sw=4 ft=php: : */
