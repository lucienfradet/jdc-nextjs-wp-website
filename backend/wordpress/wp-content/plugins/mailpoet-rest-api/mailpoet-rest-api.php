<?php
/**
 * Plugin Name: MailPoet REST API
 * Description: Exposes MailPoet functionality through WordPress REST API
 * Version: 1.0.0
 * Author: Lucien Cusson-Fradet
 * Text Domain: mailpoet-rest-api
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
	exit;
}

class MailPoet_REST_API {

	/**
	 * Initialize the plugin
	 */
	public function __construct() {
		// Register REST API endpoints when REST API is initialized
		add_action('rest_api_init', array($this, 'register_routes'));

		// Add CORS headers for the REST API
		add_action('rest_api_init', array($this, 'add_cors_headers'));

		// Add admin menu and settings
		add_action('admin_menu', array($this, 'add_admin_menu'));
		add_action('admin_init', array($this, 'register_settings'));
	}

	/**
	 * Add settings page to admin menu
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'tools.php',                      // Parent slug
			'MailPoet REST API Settings',     // Page title
			'MailPoet REST API',              // Menu title
			'manage_options',                 // Capability
			'mailpoet-rest-api',              // Menu slug
			array($this, 'render_settings_page') // Callback function
		);
	}

	/**
	 * Register plugin settings
	 */
	public function register_settings() {
		register_setting('mailpoet_rest_api_settings', 'mailpoet_rest_api_allowed_origins', array(
			'sanitize_callback' => array($this, 'sanitize_origins'),
			'default' => array()
		));

		register_setting('mailpoet_rest_api_settings', 'mailpoet_rest_api_key', array(
			'default' => wp_generate_password(32, false)
		));

		add_settings_section(
			'mailpoet_rest_api_main_section',
			'API Settings',
			function() {
				echo '<p>Configure the MailPoet REST API settings</p>';
			},
			'mailpoet-rest-api'
		);

		add_settings_field(
			'mailpoet_rest_api_allowed_origins',
			'Allowed Origins (CORS)',
			array($this, 'render_origins_field'),
			'mailpoet-rest-api',
			'mailpoet_rest_api_main_section'
		);

		add_settings_field(
			'mailpoet_rest_api_key',
			'API Key',
			array($this, 'render_api_key_field'),
			'mailpoet-rest-api',
			'mailpoet_rest_api_main_section'
		);
	}

	/**
	 * Sanitize origins field
	 */
	public function sanitize_origins($value) {
		if (empty($value)) {
			return array();
		}

		if (is_string($value)) {
			// Split by newlines and sanitize each URL
			$origins = explode("\n", $value);
			$origins = array_map('trim', $origins);
			$origins = array_filter($origins);
			return array_map('esc_url_raw', $origins);
		}

		if (is_array($value)) {
			return array_map('esc_url_raw', $value);
		}

		return array();
	}

	/**
	 * Render origins field
	 */
	public function render_origins_field() {
		$origins = get_option('mailpoet_rest_api_allowed_origins', array());

		if (is_array($origins)) {
			$origins_str = implode("\n", $origins);
		} else {
			$origins_str = $origins;
		}

		echo '<textarea name="mailpoet_rest_api_allowed_origins" rows="4" cols="50" class="large-text">' . esc_textarea($origins_str) . '</textarea>';
		echo '<p class="description">Enter one origin per line (e.g., https://your-nextjs-app.com)</p>';
	}

	/**
	 * Render API key field
	 */
	public function render_api_key_field() {
		$api_key = get_option('mailpoet_rest_api_key', '');

		if (empty($api_key)) {
			$api_key = wp_generate_password(32, false);
			update_option('mailpoet_rest_api_key', $api_key);
		}

		echo '<input type="text" name="mailpoet_rest_api_key" value="' . esc_attr($api_key) . '" class="large-text" />';
		echo '<p class="description">This key is required for ALL API requests.<br>Use it with the <code>X-MailPoet-API-Key</code> header.</p>';
	}

	/**
	 * Render the settings page
	 */
	public function render_settings_page() {
		if (!current_user_can('manage_options')) {
			return;
		}
?>
	<div class="wrap">
	    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
	    <form action="options.php" method="post">
<?php
		settings_fields('mailpoet_rest_api_settings');
		do_settings_sections('mailpoet-rest-api');
		submit_button('Save Settings');
?>
	    </form>

	    <h2>API Documentation</h2>
	    <div class="card">
		<h3>Authentication Required for All Endpoints</h3>
		<p>All endpoints require the <code>X-MailPoet-API-Key</code> header with your API key.</p>

		<h3>Subscribe Endpoint</h3>
		<p><code>POST /wp-json/mailpoet/v1/subscribers</code></p>
		<p>Required parameters: <code>email</code></p>
		<p>Optional parameters: <code>first_name</code>, <code>last_name</code>, <code>list_ids</code> (array)</p>

		<h3>Unsubscribe Endpoint</h3>
		<p><code>POST /wp-json/mailpoet/v1/unsubscribe</code></p>
		<p>Required parameters: <code>email</code></p>
		<p>Optional parameters: <code>list_ids</code> (array), <code>token</code></p>

		<h3>Lists Endpoint</h3>
		<p><code>GET /wp-json/mailpoet/v1/lists</code></p>
	    </div>
	</div>
<?php
	}

	/**
	 * Add CORS headers
	 */
	public function add_cors_headers() {
		// Get the allowed origins from plugin settings
		$origins = get_option('mailpoet_rest_api_allowed_origins', array());

		if (empty($origins)) {
			// Default to the site URL if no origins are set
			$origins = array(get_site_url());
		}

		// Register the filter to add CORS headers
		add_filter('rest_pre_serve_request', function($served, $result, $request, $server) use ($origins) {
			$origin = get_http_origin();

			if ($origin && (in_array($origin, $origins) || in_array('*', $origins))) {
				header('Access-Control-Allow-Origin: ' . esc_url_raw($origin));
				header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
				header('Access-Control-Allow-Credentials: true');
				header('Access-Control-Allow-Headers: Authorization, Content-Type, X-MailPoet-API-Key');

				// Handle preflight requests
				if ('OPTIONS' === $_SERVER['REQUEST_METHOD']) {
					status_header(200);
					exit();
				}
			}

			return $served;
		}, 10, 4);
	}

	/**
	 * Register REST API routes
	 */
	public function register_routes() {
		// Register subscribers endpoints
		register_rest_route('mailpoet/v1', '/subscribers', array(
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array($this, 'get_subscribers'),
				'permission_callback' => array($this, 'check_api_key'),
			),
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array($this, 'add_subscriber'),
				'permission_callback' => array($this, 'check_api_key'), // Now requiring API key for all endpoints
				'args' => array(
					'email' => array(
						'required' => true,
						'validate_callback' => function($param) {
							return is_email($param);
						}
		),
					'list_ids' => array(
						'required' => false,
						'default' => array(1), // Default to the first list
					),
					'first_name' => array(
						'required' => false,
					),
					'last_name' => array(
						'required' => false,
					)
				)
			)
		));

		// Register unsubscribe endpoint
		register_rest_route('mailpoet/v1', '/unsubscribe', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array($this, 'unsubscribe'),
			'permission_callback' => array($this, 'check_api_key'), // Now requiring API key for all endpoints
			'args' => array(
				'email' => array(
					'required' => true,
					'validate_callback' => function($param) {
						return is_email($param);
					}
		),
				'list_ids' => array(
					'required' => false,
				),
				'token' => array(
					'required' => false,
				)
			)
		));

		// Register lists endpoint
		register_rest_route('mailpoet/v1', '/lists', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array($this, 'get_lists'),
			'permission_callback' => array($this, 'check_api_key'),
		));

		register_rest_route('mailpoet/v1', '/subscriber-status', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array($this, 'get_subscriber_status'),
			'permission_callback' => array($this, 'check_api_key'),
			'args' => array(
				'email' => array(
					'required' => true,
					'validate_callback' => function($param) {
						return is_email($param);
					}
		),
			)
		));
	}

	/**
	 * Check if a valid API key is provided
	 */
	public function check_api_key($request) {
		// Get the API key from the request header
		$api_key = $request->get_header('X-MailPoet-API-Key');

		// Get the stored API key from WordPress options
		$stored_api_key = get_option('mailpoet_rest_api_key', '');

		// If no API key is set yet, allow access for initial setup
		if (empty($stored_api_key)) {
			return true;
		}

		// If API keys match, allow access
		if ($api_key === $stored_api_key) {
			return true;
		}

		// Otherwise, deny access
		return new WP_Error(
			'rest_forbidden',
			__('Invalid or missing API key.', 'mailpoet-rest-api'),
			array('status' => 401)
		);
	}

	/**
	 * Check if MailPoet is active
	 */
	private function is_mailpoet_active() {
		// First check: Look for MailPoet's main class
		if (class_exists('\MailPoet\API\API')) {
			return true;
		}

		// Second check: Look for the plugin main file
		if (is_plugin_active('mailpoet/mailpoet.php')) {
			return true;
		}

		// Third check: Look for older MailPoet versions
		if (class_exists('\WYSIJA')) {
			return true;
		}

		return false;
	}

	/**
	 * Get all subscribers
	 */
	public function get_subscribers($request) {
		// Check if MailPoet is installed and active
		if (!$this->is_mailpoet_active()) {
			return new WP_Error(
				'mailpoet_not_active',
				__('MailPoet is not active.', 'mailpoet-rest-api'),
				array('status' => 500)
			);
		}

		try {
			// Get the MailPoet API instance
			$mailpoet_api = \MailPoet\API\API::MP('v1');

			// Get subscribers
			$subscribers = $mailpoet_api->getSubscribers(array(
				'limit' => $request->get_param('limit') ?: 50,
				'offset' => $request->get_param('offset') ?: 0,
			));

			return rest_ensure_response($subscribers);
		} catch (Exception $e) {
			return new WP_Error(
				'mailpoet_api_error',
				$e->getMessage(),
				array('status' => 500)
			);
		}
	}

	/**
	 * Add a new subscriber or resubscribe an existing one
	 */
	public function add_subscriber($request) {
		// Check if MailPoet is installed and active
		if (!$this->is_mailpoet_active()) {
			return new WP_Error(
				'mailpoet_not_active',
				__('MailPoet is not active.', 'mailpoet-rest-api'),
				array('status' => 500)
			);
		}

		try {
			// Get the MailPoet API instance
			$mailpoet_api = \MailPoet\API\API::MP('v1');

			// Get email from request
			$email = $request->get_param('email');

			// Check if subscriber exists already
			$existing_subscriber = null;
			try {
				$existing_subscriber = $mailpoet_api->getSubscriber($email);
			} catch (Exception $e) {
				// Subscriber doesn't exist, which is fine for new signups
			}

			// If subscriber exists and is unsubscribed, we need to resubscribe them
			if ($existing_subscriber) {
				// Check their status
				if ($existing_subscriber['status'] === 'unsubscribed') {
					// Method 1: Direct database update to change status
					global $wpdb;
					$table_name = $wpdb->prefix . 'mailpoet_subscribers';

					$updated = $wpdb->update(
						$table_name,
						array('status' => 'subscribed'),
						array('id' => $existing_subscriber['id']),
						array('%s'),
						array('%d')
					);

					// Get list IDs
					$list_ids = $request->get_param('list_ids') ?: array(1);

					// Method 2: Try to subscribe to lists
					try {
						// Check if we have the proper method for subscribing to lists
						// This part might vary depending on your MailPoet version
						if (method_exists($mailpoet_api, 'subscribeToLists')) {
							$mailpoet_api->subscribeToLists($existing_subscriber['id'], $list_ids);
						}
					} catch (Exception $e) {
						// Ignore this error, the direct DB update should work
					}

					// Return success response for resubscription
					return rest_ensure_response(array(
						'success' => true,
						'subscriber' => $existing_subscriber,
						'message' => __('Successfully resubscribed.', 'mailpoet-rest-api'),
						'was_existing' => true
					));
				} else {
					// They're already subscribed
					return rest_ensure_response(array(
						'success' => false,
						'message' => __('This email is already subscribed.', 'mailpoet-rest-api')
					));
				}
			}

			// For new subscribers, proceed normally
			// Prepare subscriber data
			$subscriber_data = array(
				'email' => $email,
				'first_name' => $request->get_param('first_name') ?: '',
				'last_name' => $request->get_param('last_name') ?: '',
			);

			// Get list IDs
			$list_ids = $request->get_param('list_ids') ?: array(1); // Default to the first list

			// Add subscriber with confirmation disabled (double opt-in handled separately if needed)
			$subscriber = $mailpoet_api->addSubscriber($subscriber_data, $list_ids);

			return rest_ensure_response(array(
				'success' => true,
				'subscriber' => $subscriber,
				'message' => __('Successfully subscribed.', 'mailpoet-rest-api'),
				'was_existing' => false
			));
		} catch (Exception $e) {
			// Special handling for "already subscribed" case
			if (strpos($e->getMessage(), 'already exists') !== false) {
				return rest_ensure_response(array(
					'success' => false,
					'message' => __('This email is already subscribed.', 'mailpoet-rest-api')
				));
			}

			return new WP_Error(
				'mailpoet_api_error',
				$e->getMessage(),
				array('status' => 500)
			);
		}
	}

	/**
	 * Unsubscribe a subscriber (simplified version)
	 */
	public function unsubscribe($request) {
		// Check if MailPoet is installed and active
		if (!$this->is_mailpoet_active()) {
			return new WP_Error(
				'mailpoet_not_active',
				__('MailPoet is not active.', 'mailpoet-rest-api'),
				array('status' => 500)
			);
		}

		try {
			// Get the email
			$email = $request->get_param('email');

			// Check if email is valid
			if (!is_email($email)) {
				return new WP_Error(
					'invalid_email',
					__('Invalid email address.', 'mailpoet-rest-api'),
					array('status' => 400)
				);
			}

			// Try to find the subscriber using API
			$mailpoet_api = \MailPoet\API\API::MP('v1');
			$subscriber = null;

			try {
				$subscriber = $mailpoet_api->getSubscriber($email);
			} catch (Exception $e) {
				// Subscriber not found, return success anyway to avoid email fishing
				return rest_ensure_response(array(
					'success' => true,
					'message' => __('If this email exists in our system, it has been unsubscribed.', 'mailpoet-rest-api')
				));
			}

			// Method 1: Direct database update - Most reliable
			global $wpdb;
			$table_name = $wpdb->prefix . 'mailpoet_subscribers';

			$wpdb->update(
				$table_name,
				array('status' => 'unsubscribed'),
				array('id' => $subscriber['id']),
				array('%s'),
				array('%d')
			);

			// Method 2: Try to unsubscribe from lists if available
			try {
				// Get all available lists
				$available_lists = $mailpoet_api->getLists();

				foreach ($available_lists as $list) {
					if ($list['type'] !== 'wordpress_users') {
						try {
							// Try to use unsubscribeFromList if it exists
							if (method_exists($mailpoet_api, 'unsubscribeFromList')) {
								$mailpoet_api->unsubscribeFromList($subscriber['id'], $list['id']);
							}
						} catch (Exception $e) {
							// Ignore errors here
						}
					}
				}
			} catch (Exception $e) {
				// Ignore errors from list methods
			}

			return rest_ensure_response(array(
				'success' => true,
				'message' => __('Successfully unsubscribed.', 'mailpoet-rest-api'),
				'subscriber_id' => $subscriber['id']
			));

		} catch (Exception $e) {
			return new WP_Error(
				'mailpoet_api_error',
				$e->getMessage(),
				array('status' => 500)
			);
		}
	}

	/**
	 * Get a subscriber's status (add this to your plugin)
	 */
	public function get_subscriber_status($request) {
		// Check if MailPoet is installed and active
		if (!$this->is_mailpoet_active()) {
			return new WP_Error(
				'mailpoet_not_active',
				__('MailPoet is not active.', 'mailpoet-rest-api'),
				array('status' => 500)
			);
		}

		try {
			// Get the email
			$email = $request->get_param('email');

			// Try to find the subscriber
			$mailpoet_api = \MailPoet\API\API::MP('v1');

			try {
				$subscriber = $mailpoet_api->getSubscriber($email);

				// Also check the database directly
				global $wpdb;
				$table_name = $wpdb->prefix . 'mailpoet_subscribers';
				$db_status = $wpdb->get_var($wpdb->prepare(
					"SELECT status FROM $table_name WHERE email = %s",
					$email
				));

				return rest_ensure_response(array(
					'success' => true,
					'email' => $email,
					'api_status' => $subscriber['status'],
					'db_status' => $db_status,
					'subscriber_id' => $subscriber['id']
				));
			} catch (Exception $e) {
				return new WP_Error(
					'subscriber_not_found',
					__('Subscriber not found.', 'mailpoet-rest-api'),
					array('status' => 404)
				);
			}
		} catch (Exception $e) {
			return new WP_Error(
				'mailpoet_api_error',
				$e->getMessage(),
				array('status' => 500)
			);
		}
	}

	/**
	 * Get all lists
	 */
	public function get_lists() {
		// Check if MailPoet is installed and active
		if (!$this->is_mailpoet_active()) {
			return new WP_Error(
				'mailpoet_not_active',
				__('MailPoet is not active.', 'mailpoet-rest-api'),
				array('status' => 500)
			);
		}

		try {
			// Get the MailPoet API instance
			$mailpoet_api = \MailPoet\API\API::MP('v1');

			// Get lists
			$lists = $mailpoet_api->getLists();

			return rest_ensure_response($lists);
		} catch (Exception $e) {
			return new WP_Error(
				'mailpoet_api_error',
				$e->getMessage(),
				array('status' => 500)
			);
		}
	}
}

// Initialize the plugin
$mailpoet_rest_api = new MailPoet_REST_API();
