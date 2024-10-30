<?php

// Subpackage namespace
namespace LittleBizzy\CloudFlare\Admin;

// Aliased namespaces
use \LittleBizzy\CloudFlare\Core;
use \LittleBizzy\CloudFlare\API;
use \LittleBizzy\CloudFlare\Helpers;

/**
 * Submit class
 *
 * @package CloudFlare
 * @subpackage Admin
 */
final class Submit {



	// Properties
	// ---------------------------------------------------------------------------------------------------



	/**
	 * Single class instance
	 */
	private static $instance;



	// Initialization
	// ---------------------------------------------------------------------------------------------------



	/**
	 * Create or retrieve instance
	 */
	public static function instance() {

		// Check instance
		if (!isset(self::$instance))
			self::$instance = new self;

		// Done
		return self::$instance;
	}



	/**
	 * Constructor
	 */
	private function __construct() {}



	// Methods
	// ---------------------------------------------------------------------------------------------------



	/**
	 * API credentials
	 */
	public function credentials(&$args) {


		/* nonce */

		// Check nonce
		if (empty($_POST['hd-credentials-nonce']) || !wp_verify_nonce($_POST['hd-credentials-nonce'], 'cloudflare_credentials')) {
			$args['notices']['error'][] = 'Invalid form security code, please try again.';
			return;
		}


		/* Key */

		// Check constant
		if (defined('CLOUDFLARE_API_KEY')) {
			$key = CLOUDFLARE_API_KEY;

		// Check submit
		} elseif (isset($_POST['tx-credentials-key'])) {

			// Form value
			$test = trim($_POST['tx-credentials-key']);
			if (empty($test)) {
				$args['notices']['error'][] = 'Missing Cloudflare API Key value';

			// Done
			} else {
				$key = $test;
				Core\Data::instance()->save(['key' => $key]);
			}
		}


		/* email */

		// Check constant
		if (defined('CLOUDFLARE_API_EMAIL')) {
			$email = CLOUDFLARE_API_EMAIL;

		// Check submit
		} elseif (isset($_POST['tx-credentials-email'])) {

			// Form value
			$test = trim($_POST['tx-credentials-email']);
			if (empty($test)) {
				$args['notices']['error'][] = 'Missing Cloudflare API email';

			// Validate
			} elseif (!is_email($test)) {
				$args['notices']['error'][] = 'The email <strong>'.esc_html($test).'<strong> is not valid';

			// Done
			} else {
				$email = $test;
				Core\Data::instance()->save(['email' => $email]);
			}
		}


		/* API request */

		// Check values for API validation
		if (isset($key) && isset($email)) {

			// Check API data
			if (empty($key) || empty($email)) {
				$args['notices']['error'][] = 'Missing API Key or email value';

			// Continue
			} else {

				// Update zone data
				$this->updateZone($key, $email, $args);
			}
		}
	}



	/**
	 * Change Development Mode status
	 */
	public function devMode(&$args, $toolbar) {


		/* nonce */

		// Check toolbar
		if ($toolbar) {

			// Toolbar nonce param
			$toolbarNonce = isset($_GET[Helpers\Plugin::instance()->prefix.'_nonce'])? $_GET[Helpers\Plugin::instance()->prefix.'_nonce'] : false;
			if (empty($toolbarNonce) || !wp_verify_nonce($toolbarNonce, 'cloudflare_toolbar')) {
				$args['notices']['error'][] = 'Invalid action security code, please try again.';
				return;
			}

		// Check form nonce
		} elseif (empty($_POST['hd-devmode-nonce']) || !wp_verify_nonce($_POST['hd-devmode-nonce'], 'cloudflare_devmode')) {
			$args['notices']['error'][] = 'Invalid form security code, please try again.';
			return;
		}


		/* API settings */

		// Init data
		$data = Core\Data::instance();

		// key and email
		$key = defined('CLOUDFLARE_API_KEY')? CLOUDFLARE_API_KEY : $data->key;
		$email = defined('CLOUDFLARE_API_EMAIL')? CLOUDFLARE_API_EMAIL : $data->email;

		// Check API data
		if (empty($key) || empty($email)) {
			$args['notices']['error'][] = 'Missing API Key or email value';

		// Check API
		} elseif (false !== $this->updateZone($key, $email, $args, false)) {

			// Reload data
			$data->load();

			// Check zone data
			if (empty($data->zone['id'])) {
				$args['notices']['error'][] = 'Missing API zone detected';

			// Zone Ok
			} else {


				/* Dev mode */

				// Determine action
				$enable = $toolbar? true : (empty($_POST['hd-devmode-action'])? false : ('on' == $_POST['hd-devmode-action']));

				// Enable or disable Dev mode
				$response = API\CloudFlare::instance($key, $email)->setDevMode($data->zone['id'], $enable);
				if (is_wp_error($response)) {
					$message = $this->apiErrorMessage($response);
					$args['notices']['error'][] = 'CloudFlare API request error'.(empty($message)? '' : ': <strong>'.esc_html($message).'</strong>');

				// Success
				} else {
					$data->zone['development_mode'] = $response['result']['time_remaining'];
					$data->save(['zone' => $data->zone, 'dev_mode_at' => time()]);
					$args['notices']['success'][] = 'Updated <strong>development mode</strong> status via CloudFlare API';
				}
			}
		}
	}



	/**
	 * Purge all files
	 */
	public function purge(&$args, $toolbar) {


		/* nonce */

		// Check toolbar
		if ($toolbar) {

			// Toolbar nonce param
			$toolbarNonce = isset($_GET[Helpers\Plugin::instance()->prefix.'_nonce'])? $_GET[Helpers\Plugin::instance()->prefix.'_nonce'] : false;
			if (empty($toolbarNonce) || !wp_verify_nonce($toolbarNonce, 'cloudflare_toolbar')) {
				$args['notices']['error'][] = 'Invalid action security code, please try again.';
				return;
			}

		// Check form nonce
		} elseif (empty($_POST['hd-purge-nonce']) || !wp_verify_nonce($_POST['hd-purge-nonce'], 'cloudflare_purge')) {
			$args['notices']['error'][] = 'Invalid form security code, please try again.';
			return;
		}


		/* API settings */

		// Init data
		$data = Core\Data::instance();

		// key and email
		$key = defined('CLOUDFLARE_API_KEY')? CLOUDFLARE_API_KEY : $data->key;
		$email = defined('CLOUDFLARE_API_EMAIL')? CLOUDFLARE_API_EMAIL : $data->email;

		// Check API data
		if (empty($key) || empty($email)) {
			$args['notices']['error'][] = 'Missing API Key or email value';

		// Check API
		} elseif (false !== $this->updateZone($key, $email, $args, false)) {

			// Reload data
			$data->load();

			// Check zone data
			if (empty($data->zone['id'])) {
				$args['notices']['error'][] = 'Missing API zone detected';

			// Zone Ok
			} else {


				/* Purge */

				$response = API\CloudFlare::instance($key, $email)->purgeZone($data->zone['id']);
				if (is_wp_error($response)) {
					$message = $this->apiErrorMessage($response);
					$args['notices']['error'][] = 'CloudFlare API request error'.(empty($message)? '' : ': <strong>'.esc_html($message).'</strong>');

				// Success
				} else {
					$args['notices']['success'][] = 'Purged all files successfully via CloudFlare API';
				}
			}
		}
	}



	// Internal
	// ---------------------------------------------------------------------------------------------------



	/**
	 * Update API zone from key and email values
	 */
	private function updateZone($key, $email, &$args, $notifySuccess = true) {

		// Initialize
		$zone = false;

		// Perform the API calls
		$result = $this->checkDomain($key, $email);
		if (is_wp_error($result)) {

			// Add argument
			$message = $this->apiErrorMessage($result);
			$args['notices']['error'][] = 'CloudFlare API request error'.(empty($message)? '' : ': <strong>'.esc_html($message).'</strong>');

		// Missing domain
		} elseif (false === $result) {
			$args['notices']['error'][] = 'Current domain does not match the CloudFlare API zones';

		// Found
		} else {

			// Retrieve zone
			$zone = Core\Data::instance()->sanitizeZone($result);

			// Check notification
			if ($notifySuccess)
				$args['notices']['success'][] = 'Updated domain info via CloudFlare API';
		}

		// Update data
		Core\Data::instance()->save(['zone' => $zone]);

		// Done
		return $zone;
	}



	/**
	 * Check current domain calling the API
	 */
	private function checkDomain($key, $email) {

		// Initialize
		$page = $maxPages = 1;

		// Enum page
		while ($page <= $maxPages) {

			// Perform the API call
			$response = API\CloudFlare::instance($key, $email)->getZones($page);
			if (is_wp_error($response))
				return $response;

			// Check domains
			if (false !== ($zone = $this->matchZone($response['result'])))
				return $zone;

			// Max pages check
			if (1 == $page)
				$maxPages = empty($response['result_info']['total_pages'])? 0 : (int) $response['result_info']['total_pages'];

			// Next page
			$page++;
		}

		// Done
		return false;
	}



	/**
	 * Compare zones with current domain
	 */
	private function matchZone($result) {

		//Check array
		if (empty($result) || !is_array($result))
			return false;

		// Current domain
		$domain = strtolower(trim(Core\Data::instance()->domain));

		// Enum zones
		foreach ($result as $zone) {

			// Check zone name
			$name = strtolower(trim($zone['name']));
			if ('' === $name || false === strpos($domain, $name))
				continue;

			// Check same alue
			if ($domain == $name)
				return $zone;

			// Check length
			$length = strlen($name);
			if ($length > strlen($domain))
				continue;

			// Ends with the zone name
			if (substr($domain, -$length) === $name)
				return $zone;
		}

		// Not found
		return false;
	}



	/**
	 * Extracts API error message
	 */
	private function apiErrorMessage($wpError) {

		// Check response
		$response = $wpError->get_error_message();
		if (!empty($response) && is_array($response) || !empty($response['body'])) {
			$body = @json_decode($response['body'], true);
			if (!empty($body['errors']) && is_array($body['errors']) && !empty($body['errors'][0]['message']))
				return $body['errors'][0]['message'];
		}

		// Not found
		return false;
	}



}