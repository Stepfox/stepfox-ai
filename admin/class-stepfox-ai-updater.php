<?php
/**
 * Lightweight GitHub updater for StepFox AI
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!class_exists('Stepfox_AI_Updater')) {
	class Stepfox_AI_Updater {
		/** @var string */
		private const REPO_OWNER = 'Stepfox';
		/** @var string */
		private const REPO_NAME  = 'stepfox-ai';
		/** @var string */
		private const BRANCH     = 'main';

		/** @var string|null */
		private static $last_source_dir = null;

		public static function init() {
			add_filter('pre_set_site_transient_update_plugins', [__CLASS__, 'check_for_update']);
			add_filter('site_transient_update_plugins', [__CLASS__, 'check_for_update']);
			add_filter('plugins_api', [__CLASS__, 'plugins_api'], 10, 3);
			add_filter('upgrader_source_selection', [__CLASS__, 'fix_github_zip_folder'], 10, 4);
			add_filter('upgrader_package_options', [__CLASS__, 'ensure_clear_destination']);
			add_filter('upgrader_install_package_result', [__CLASS__, 'log_install_result'], 10, 2);
			add_action('upgrader_pre_install', [__CLASS__, 'log_pre_install'], 10, 2);
			add_action('upgrader_post_install', [__CLASS__, 'log_post_install'], 10, 3);
			add_action('load-update-core.php', [__CLASS__, 'maybe_bust_cache']);
			add_action('load-plugins.php', [__CLASS__, 'maybe_bust_cache']);
		}

		public static function check_for_update($transient) {
			if (empty($transient) || !is_object($transient)) {
				return $transient;
			}

			$plugin_file     = trailingslashit(dirname(dirname(__FILE__))) . 'stepfox-ai.php';
			$plugin_basename = plugin_basename($plugin_file);
			$current_version = defined('STEPFOX_AI_VERSION') ? STEPFOX_AI_VERSION : self::read_local_version($plugin_file);

			$remote_version = self::get_remote_version();
			if ($remote_version && version_compare($remote_version, $current_version, '>')) {
				$update              = new stdClass();
				$update->slug        = 'stepfox-ai';
				$update->plugin      = $plugin_basename;
				$update->new_version = $remote_version;
				$update->url         = 'https://github.com/' . self::REPO_OWNER . '/' . self::REPO_NAME;
				$update->package     = self::get_download_zip_url();

				$transient->response[$plugin_basename] = $update;
				if (isset($transient->no_update[$plugin_basename])) {
					unset($transient->no_update[$plugin_basename]);
				}
			} else {
				if (isset($transient->response[$plugin_basename])) {
					unset($transient->response[$plugin_basename]);
				}
				$no_update              = new stdClass();
				$no_update->slug        = 'stepfox-ai';
				$no_update->plugin      = $plugin_basename;
				$no_update->new_version = $current_version;
				$no_update->url         = 'https://github.com/' . self::REPO_OWNER . '/' . self::REPO_NAME;
				if (!isset($transient->no_update) || !is_array($transient->no_update)) {
					$transient->no_update = array();
				}
				$transient->no_update[$plugin_basename] = $no_update;
			}

			return $transient;
		}

		public static function plugins_api($result, $action, $args) {
			if ($action !== 'plugin_information' || empty($args) || empty($args->slug) || $args->slug !== 'stepfox-ai') {
				return $result;
			}

			$remote_version = self::get_remote_version();
			$sections       = self::get_remote_sections();

			$info = new stdClass();
			$info->name          = 'StepFox AI';
			$info->slug          = 'stepfox-ai';
			$info->version       = $remote_version ?: (defined('STEPFOX_AI_VERSION') ? STEPFOX_AI_VERSION : '1.0.0');
			$info->requires      = '6.0';
			$info->tested        = '6.7';
			$info->requires_php  = '7.4';
			$info->author        = '<a href="https://stepfoxthemes.com">Stepfox</a>';
			$info->homepage      = 'https://github.com/' . self::REPO_OWNER . '/' . self::REPO_NAME;
			$info->download_link = self::get_download_zip_url();
			$info->sections      = $sections;

			return $info;
		}

		public static function fix_github_zip_folder($source, $remote_source, $upgrader, $hook_extra) {
			$source_basename   = basename($source);
			$plugin_dir_name   = 'stepfox-ai';
			$source_has_plugin = file_exists(trailingslashit($source) . 'stepfox-ai.php');
			$source_has_nested = is_dir(trailingslashit($source) . $plugin_dir_name) && file_exists(trailingslashit($source) . $plugin_dir_name . '/stepfox-ai.php');

			if ($source_has_nested) {
				$fixed = trailingslashit($source) . $plugin_dir_name;
				self::$last_source_dir = $fixed;
				return $fixed;
			}

			if ($source_has_plugin && $source_basename !== $plugin_dir_name) {
				$new_source = trailingslashit(dirname($source)) . $plugin_dir_name;
				if (is_dir($new_source)) { self::rrmdir($new_source); }
				if (!@rename($source, $new_source)) {
					@mkdir($new_source, 0755, true);
					self::rcopy($source, $new_source);
					self::rrmdir($source);
				}
				self::$last_source_dir = is_dir($new_source) ? $new_source : $source;
				return self::$last_source_dir;
			}

			if (strpos($source_basename, self::REPO_NAME) !== false && $source_basename !== $plugin_dir_name) {
				$new_source = trailingslashit(dirname($source)) . $plugin_dir_name;
				if (is_dir($new_source)) { self::rrmdir($new_source); }
				if (!@rename($source, $new_source)) {
					@mkdir($new_source, 0755, true);
					self::rcopy($source, $new_source);
					self::rrmdir($source);
				}
				self::$last_source_dir = is_dir($new_source) ? $new_source : $source;
				return self::$last_source_dir;
			}

			self::$last_source_dir = $source;
			return $source;
		}

		public static function ensure_clear_destination($options) {
			if (!is_array($options)) {
				return $options;
			}
			$hook_extra   = isset($options['hook_extra']) && is_array($options['hook_extra']) ? $options['hook_extra'] : [];
			$is_our_plugin = false;
			if (isset($hook_extra['plugin'])) {
				$our_basename = plugin_basename(trailingslashit(dirname(dirname(__FILE__))) . 'stepfox-ai.php');
				$is_our_plugin = ($hook_extra['plugin'] === $our_basename);
			}
			if ($is_our_plugin) {
				$options['clear_destination'] = true;
				$options['abort_if_destination_exists'] = false;
				$plugins_dir = defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : (trailingslashit(WP_CONTENT_DIR) . 'plugins');
				$options['destination']      = $plugins_dir;
				$options['destination_name'] = 'stepfox-ai';
			}
			return $options;
		}

		private static function get_remote_version() {
			$cache_key = 'stepfox_ai_remote_version';
			$cached    = get_site_transient($cache_key);
			if (!self::is_force_check() && is_string($cached) && $cached !== '') {
				return $cached;
			}

			$candidates = [
				'https://raw.githubusercontent.com/' . self::REPO_OWNER . '/' . self::REPO_NAME . '/' . self::BRANCH . '/stepfox-ai.php',
				'https://raw.githubusercontent.com/' . self::REPO_OWNER . '/' . self::REPO_NAME . '/' . self::BRANCH . '/stepfox-ai/stepfox-ai.php',
			];
			$body = null;
			foreach ($candidates as $raw_url) {
				$response = wp_remote_get($raw_url, [
					'timeout'    => 10,
					'user-agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url('/'),
				]);
				if (is_wp_error($response)) { continue; }
				if (wp_remote_retrieve_response_code($response) !== 200) { continue; }
				$tmp = wp_remote_retrieve_body($response);
				if (is_string($tmp) && $tmp !== '') { $body = $tmp; break; }
			}
			if (!is_string($body) || $body === '') { return null; }
			if (preg_match('/^\s*\*\s*Version:\s*([^\r\n]+)/mi', $body, $m)) {
				$version = trim($m[1]);
			} elseif (preg_match("/define\s*\(\s*'STEPFOX_AI_VERSION'\s*,\s*'([^']+)'\s*\)/", $body, $m)) {
				$version = trim($m[1]);
			} else {
				$version = null;
			}
			if ($version) { set_site_transient($cache_key, $version, 5 * MINUTE_IN_SECONDS); }
			return $version;
		}

		private static function read_local_version($plugin_file) {
			$data = get_file_data($plugin_file, ['Version' => 'Version']);
			return !empty($data['Version']) ? $data['Version'] : '0.0.0';
		}

		private static function get_remote_sections() {
			$sections = [ 'description' => 'AI Console Runner block for generating code with OpenAI (server-side secured).' ];
			$readme_raw = 'https://raw.githubusercontent.com/' . self::REPO_OWNER . '/' . self::REPO_NAME . '/' . self::BRANCH . '/readme.txt';
			$response = wp_remote_get($readme_raw, [ 'timeout' => 10, 'user-agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url('/') ]);
			if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
				$body = (string) wp_remote_retrieve_body($response);
				if ($body) { $sections['description'] = wp_kses_post(wpautop($body)); }
			}
			return $sections;
		}

		private static function get_download_zip_url() {
			return 'https://codeload.github.com/' . self::REPO_OWNER . '/' . self::REPO_NAME . '/zip/refs/heads/' . self::BRANCH;
		}

		public static function maybe_bust_cache() {
			if (self::is_force_check()) { delete_site_transient('stepfox_ai_remote_version'); }
		}
		private static function is_force_check() {
			return (is_admin() && isset($_GET['force-check'])) || (defined('WP_CLI') && WP_CLI);
		}

		private static function rrmdir($dir) {
			if (!is_dir($dir)) { return; }
			$items = scandir($dir);
			if ($items === false) { return; }
			foreach ($items as $item) {
				if ($item === '.' || $item === '..') { continue; }
				$path = $dir . DIRECTORY_SEPARATOR . $item;
				if (is_dir($path)) { self::rrmdir($path); } else { @unlink($path); }
			}
			@rmdir($dir);
		}
		private static function rcopy($src, $dst) {
			if (is_file($src)) { @copy($src, $dst); return; }
			if (!is_dir($src)) { return; }
			if (!is_dir($dst)) { @mkdir($dst, 0755, true); }
			$items = scandir($src);
			if ($items === false) { return; }
			foreach ($items as $item) {
				if ($item === '.' || $item === '..') { continue; }
				$from = $src . DIRECTORY_SEPARATOR . $item;
				$to   = $dst . DIRECTORY_SEPARATOR . $item;
				if (is_dir($from)) { self::rcopy($from, $to); } else { @copy($from, $to); }
			}
		}

		public static function log_install_result($result, $hook_extra) {
			if (is_wp_error($result)) {
				self::debug_log('install_package_result: WP_Error ' . $result->get_error_code() . ' - ' . $result->get_error_message());
				if ($result->get_error_code() === 'incompatible_archive_no_plugins' && self::$last_source_dir && is_dir(self::$last_source_dir)) {
					$destination = trailingslashit(WP_PLUGIN_DIR) . 'stepfox-ai';
					self::debug_log('manual_install: attempting copy from ' . self::$last_source_dir . ' to ' . $destination);
					if (is_dir($destination)) { self::rrmdir($destination); }
					@mkdir($destination, 0755, true);
					self::rcopy(self::$last_source_dir, $destination);
					if (file_exists($destination . '/stepfox-ai.php')) {
						self::debug_log('manual_install: success');
						return [ 'source' => self::$last_source_dir, 'destination' => $destination, 'destination_name' => 'stepfox-ai', 'feedback' => 'manual-install' ];
					}
					self::debug_log('manual_install: failed - plugin main not found after copy');
				}
			} else {
				self::debug_log('install_package_result: ' . wp_json_encode($result));
			}
			return $result;
		}
		public static function log_pre_install($bool, $hook_extra) { self::debug_log('pre_install: ' . wp_json_encode($hook_extra)); return $bool; }
		public static function log_post_install($bool, $hook_extra, $result) { self::debug_log('post_install: ' . (is_wp_error($result) ? ('WP_Error ' . $result->get_error_code() . ' - ' . $result->get_error_message()) : wp_json_encode($result))); return $bool; }
		public static function debug_log($message) { error_log('[Stepfox AI Updater] ' . (is_string($message) ? $message : wp_json_encode($message))); }
	}
}


