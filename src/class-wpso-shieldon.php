<?php

/**
 * WP Shieldon Controller.
 *
 * @author Terry Lin
 * @package Shieldon
 * @since 1.0.0
 * @version 1.0.0
 * @license GPLv3
 *
 */

class WPSO_Shieldon_Guardian {

    private $shieldon;

	/**
	 * Constructer.
	 */
	public function __construct() {

		/**
		 * Start a Shieldon instance.
		 */
		$this->shieldon = new \Shieldon\Shieldon();
	}

	/**
	 * Initialize everything the Githuber plugin needs.
	 * 
	 * @document https://shield-on-php.github.io/en/
	 */
	public function init() {

		/**
		 * Set data driver for Shieldon.
		 */
		$driver_type = wpso_get_option( 'data_driver_type', 'shieldon_guardian' );

		switch ( $driver_type ) {

			case 'reids':

				try {

					// Create a Redis instance.
					$redis_instance = new \Redis();
					$redis_instance->connect( '127.0.0.1', 6379 );

					// Use Redis data driver.
					$this->shieldon->setDriver(
						new \Shieldon\Driver\RedisDriver( $redis_instance )
					);

				} catch( \PDOException $e ) {
					echo $e->getMessage();
					return false;
				}

				break;

			case 'file':

				$shieldon_file_dir = wpso_get_upload_dir();

				// Use File data driver.
				$this->shieldon->setDriver(
					new \Shieldon\Driver\FileDriver( $shieldon_file_dir )
				);

				break;

			case 'sqlite':

				try {
					
					// Specific the sqlite file location.
					$sqlite_location = wpso_get_upload_dir() . '/shieldon.sqlite3';

					// Create a PDO instance.
					$pdo_instance = new \PDO( 'sqlite:' . $sqlite_location );

					// Use Sqlite data driver.
					$this->shieldon->setDriver(
						new \Shieldon\Driver\SqliteDriver( $pdo_instance )
					);
	
				} catch( \PDOException $e ) {
					echo $e->getMessage();
					return false;
				}

				break;

			case 'mysql':
			default:

				// Read database settings from wp-config.php
				$db = array(
					'host'    => DB_HOST,
					'dbname'  => DB_NAME,
					'user'    => DB_USER,
					'pass'    => DB_PASSWORD,
					'charset' => DB_CHARSET,
				);
		
				try {

					// Create a PDO instance.
					$pdo_instance = new \PDO(
						'mysql:host=' . $db['host'] . ';dbname=' . $db['dbname'] . ';charset=' . $db['charset'],
						$db['user'],
						$db['pass']
					);

					// Use MySQL data driver.
					$this->shieldon->setDriver(
						new \Shieldon\Driver\SqliteDriver( $pdo_instance )
					);

				} catch( \PDOException $e ) {
					echo $e->getMessage();
					return false;
				}
		}

		// Set Channel, for WordPress multisite network.
		$this->shieldon->setChannel( wpso_get_channel_id() );

		/**
		 * Frequancy check. (settings)
		 */
		$time_unit_quota_s = wpso_get_option( '$time_unit_quota_s', 'shieldon_guardian' );
		$time_unit_quota_m = wpso_get_option( '$time_unit_quota_m', 'shieldon_guardian' );
		$time_unit_quota_h = wpso_get_option( '$time_unit_quota_h', 'shieldon_guardian' );
		$time_unit_quota_d = wpso_get_option( '$time_unit_quota_d', 'shieldon_guardian' );

		$time_unit_quota['s'] = ( is_numeric( $time_unit_quota_s ) && $time_unit_quota_s > 0 ) ? $time_unit_quota_s : 2;
		$time_unit_quota['m'] = ( is_numeric( $time_unit_quota_m ) && $time_unit_quota_m > 0 ) ? $time_unit_quota_m : 10;
		$time_unit_quota['h'] = ( is_numeric( $time_unit_quota_h ) && $time_unit_quota_h > 0 ) ? $time_unit_quota_h : 30;
		$time_unit_quota['d'] = ( is_numeric( $time_unit_quota_d ) && $time_unit_quota_d > 0 ) ? $time_unit_quota_d : 60;

		$this->shieldon->setProperty( 'time_unit_quota', $time_unit_quota );
		$this->shieldon->setProperty( 'lang', wpso_get_lang() );

		$filter_config = array(
			'session'   => ( 'yes' === wpso_get_option( 'enable_filter_session', 'shieldon_filter' ) ) ? true : false,
			'cookie'    => ( 'yes' === wpso_get_option( 'enable_filter_cookie',  'shieldon_filter' ) ) ? true : false,
			'referer'   => ( 'yes' === wpso_get_option( 'enable_filter_referer', 'shieldon_filter' ) ) ? true : false,
			'frequency' => true,
		);

		$this->shieldon->setFilters( $filter_config );

		/**
		 * Load "Ip" component.
		 */
		$component_ip = new \Shieldon\Component\Ip();

		$this->shieldon->setComponent( $component_ip );

		/**
		 * Load "Trusted Bot" component.
		 */
		if ( 'yes' === wpso_get_option( 'enable_component_trustedbot', 'shieldon_components' ) ) {

			// This component will only allow popular search engline.
			// Other bots will go into the checking process.
			$component_trustedbot = new \Shieldon\Component\TrustedBot();

			$this->shieldon->setComponent( $component_trustedbot );
		}

		/**
		 * Load "Header" component.
		 */
		if ( 'yes' === wpso_get_option( 'enable_component_header', 'shieldon_components' ) ) {

			$component_header = new \Shieldon\Component\Header();

			// Deny all vistors without common header information.
			if ( 'yes' === wpso_get_option( 'header_strict_mode', 'shieldon_components' ) ) {
				$component_header->setStrict( true );
			}

			$this->shieldon->setComponent( $component_header );
		}

		/**
		 * Load "User-agent" component.
		 */
		if ( 'yes' === wpso_get_option( 'enable_component_agent', 'shieldon_components' ) ) {

			$component_agent = new \Shieldon\Component\UserAgent();

			// Visitors with empty user-agent information will be blocked.
			if ( 'yes' === wpso_get_option( 'agent_strict_mode', 'shieldon_components' ) ) {
				$component_agent->setStrict( true );
			}

			$this->shieldon->setComponent( $component_agent );
		}

		/**
		 * Load "Rdns" component.
		 */
		if ( 'yes' === wpso_get_option( 'enable_component_rdns', 'shieldon_components' ) ) {

			$component_rdns = new \Shieldon\Component\Rdns();

			// Visitors with empty Rdns record will be blocked.
            // IP resolved hostname (Rdns) and IP address must match.
			if ( 'yes' === wpso_get_option( 'rdns_strict_mode', 'shieldon_components' ) ) {
				$component_rdns->setStrict( true );
			}

			$this->shieldon->setComponent( $component_rdns );
		}

		/**
		 * CAPTCHA
		 */
		if ( 'yes' === wpso_get_option( 'enable_captcha_google', 'shieldon_captcha' ) ) {

			$google_captcha_config['key']    = wpso_get_option( 'google_recaptcha_key', 'shieldon_captcha' );
			$google_captcha_config['secret'] = wpso_get_option( 'google_recaptcha_secret', 'shieldon_captcha' );
			$google_captcha_config['verion'] = wpso_get_option( 'google_recaptcha_version', 'shieldon_captcha' );
			$google_captcha_config['lang']   = wpso_get_option( 'google_recaptcha_version', 'shieldon_captcha' );

			$captcha_google = new \Shieldon\Captcha\Recaptcha( $google_captcha_config );

			$this->shieldon->setCaptcha( $captcha_google );
		}

		if ( 'yes' === wpso_get_option( 'enable_captcha_image', 'shieldon_captcha' ) ) {

			$image_captcha_config['word_length'] = wpso_get_option( 'image_captcha_length', 'shieldon_captcha' );

			$image_captcha_type = wpso_get_option( 'image_captcha_type', 'shieldon_captcha' );

			switch ($image_captcha_type) {
				case 'numeric':
					$image_captcha_config['pool'] = '0123456789';
					break;

				case 'alpha':
					$image_captcha_config['pool'] = '0123456789abcdefghijklmnopqrstuvwxyz';
					break;

				case 'alnum':
				default:
					$image_captcha_config['pool'] = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
			}
			
			$captcha_image = new \Shieldon\Captcha\ImageCaptcha( $image_captcha_config );
			$this->shieldon->setCaptcha( $captcha_image );
		}

		// Start protecting your website!
		$result = $this->shieldon->run();

		if ($result !== $this->shieldon::RESPONSE_ALLOW) {
			if ($this->shieldon->captchaResponse()) {

				// Unban current session.
				$this->shieldon->unban();
			}
			// Output the result page with HTTP status code 200.
			$this->shieldon->output(200);
		}

		// Check cookie generated by JavaScript.
		if ( $filter_config['cookie'] ) {
			add_action( 'wp_print_footer_scripts', array( $this, 'front_print_footer_scripts' ) );
		}
	}

	/**
	 * Print Javascript plaintext in page footer.
	 * 
	 * @return string
	 */
	public function front_print_footer_scripts() {
		echo $this->shieldon->outputJsSnippet();
	}
}
