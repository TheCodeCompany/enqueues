<?php
/**
 * Admin settings page for the Enqueues framework.
 *
 * Exposes the asset-loading performance controls (request-level memoisation and the persistent
 * object-cache layer) as toggles under Settings -> Enqueues, plus a manual "Flush cache" action.
 *
 * File Path: src/php/Controller/SettingsController.php
 *
 * @package Enqueues
 */

namespace Enqueues\Controller;

use Enqueues\Base\Main\Controller;
use function Enqueues\enqueues_get_settings;
use function Enqueues\flush_enqueues_cache;
use function Enqueues\get_cache_ttl;
use function Enqueues\get_enqueues_build_signature;
use function Enqueues\is_cache_enabled;
use function Enqueues\is_request_memo_enabled;

/**
 * Registers the Settings -> Enqueues page and persists the framework's performance settings.
 */
class SettingsController extends Controller {

	/**
	 * Option name the settings array is stored under.
	 */
	const OPTION = 'enqueues_settings';

	/**
	 * Settings group / page slug.
	 */
	const PAGE = 'enqueues';

	/**
	 * Boot the controller.
	 *
	 * @return void
	 */
	public function set_up() {

		// Prevent duplicate initialization.
		if ( ! $this->initialize() ) {
			return;
		}

		add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_post_enqueues_flush_cache', [ $this, 'handle_flush_cache' ] );

		// Auto-invalidate the persistent cache on the events WordPress can signal. A git-checkout
		// deploy does NOT fire these, so the page also documents flushing on deploy.
		add_action( 'switch_theme', [ $this, 'auto_flush' ] );
		add_action( 'upgrader_process_complete', [ $this, 'auto_flush' ] );
	}

	/**
	 * Register the Settings -> Enqueues page.
	 *
	 * @return void
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'Enqueues', 'enqueues' ),
			__( 'Enqueues', 'enqueues' ),
			'manage_options',
			self::PAGE,
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Register the settings option and its sanitiser.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			self::PAGE,
			self::OPTION,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize' ],
				'default'           => [],
			]
		);
	}

	/**
	 * Sanitise submitted settings.
	 *
	 * @param mixed $input Raw submitted value.
	 *
	 * @return array
	 */
	public function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : [];

		// Clamp TTL to a sane minimum. TTL bounds the persistent cache's residual staleness window
		// (a CSS-only or template-only change that does not move the build signature), so a very
		// small or zero TTL is unsafe.
		$ttl = isset( $input['cache_ttl'] ) ? (int) $input['cache_ttl'] : DAY_IN_SECONDS;
		if ( $ttl < HOUR_IN_SECONDS ) {
			$ttl = HOUR_IN_SECONDS;
		}

		// When the ENQUEUES_CACHE_ENABLED constant is set the persistent_cache checkbox is disabled
		// and therefore not POSTed; preserve the previously-stored value instead of clearing it.
		if ( defined( 'ENQUEUES_CACHE_ENABLED' ) ) {
			$existing         = enqueues_get_settings();
			$persistent_cache = ! empty( $existing['persistent_cache'] );
		} else {
			$persistent_cache = ! empty( $input['persistent_cache'] );
		}

		return [
			'request_memo'     => ! empty( $input['request_memo'] ),
			'persistent_cache' => $persistent_cache,
			'cache_ttl'        => $ttl,
		];
	}

	/**
	 * Handle the manual "Flush cache" action.
	 *
	 * @return void
	 */
	public function handle_flush_cache() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'enqueues' ) );
		}

		check_admin_referer( 'enqueues_flush_cache' );

		flush_enqueues_cache();

		wp_safe_redirect( add_query_arg( 'enqueues_flushed', '1', admin_url( 'options-general.php?page=' . self::PAGE ) ) );
		exit;
	}

	/**
	 * Flush wrapper for WordPress lifecycle hooks.
	 *
	 * @return void
	 */
	public function auto_flush() {
		flush_enqueues_cache();
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_page() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings        = enqueues_get_settings();
		$memo_on          = ! empty( $settings['request_memo'] );
		$cache_on         = ! empty( $settings['persistent_cache'] );
		$ttl              = (int) ( $settings['cache_ttl'] ?? DAY_IN_SECONDS );
		$cache_const      = defined( 'ENQUEUES_CACHE_ENABLED' );
		$flushed          = isset( $_GET['enqueues_flushed'] ); // phpcs:ignore WordPress.Security.NonceVerification
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Enqueues', 'enqueues' ); ?></h1>

			<?php if ( $flushed ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Enqueues cache flushed.', 'enqueues' ); ?></p></div>
			<?php endif; ?>

			<p class="description">
				<?php esc_html_e( 'Asset-loading performance controls for the Enqueues framework. The request memo is in-process and safe to leave on. The persistent cache stores resolved asset metadata across requests in the object cache.', 'enqueues' ); ?>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( self::PAGE ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Request memo (O1)', 'enqueues' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[request_memo]" value="1" <?php checked( $memo_on ); ?> />
								<?php esc_html_e( 'Memoise asset lookups for the duration of each request.', 'enqueues' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'In-process only; cannot serve stale data across requests. Recommended on.', 'enqueues' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Persistent cache (O2)', 'enqueues' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[persistent_cache]" value="1" <?php checked( $cache_on ); ?> <?php disabled( $cache_const ); ?> />
								<?php esc_html_e( 'Cache resolved asset metadata across requests in the object cache.', 'enqueues' ); ?>
							</label>
							<?php if ( $cache_const ) : ?>
								<p class="description"><strong><?php esc_html_e( 'Overridden by the ENQUEUES_CACHE_ENABLED constant; this checkbox is ignored.', 'enqueues' ); ?></strong></p>
							<?php endif; ?>
							<p class="description"><?php esc_html_e( 'Caches resolved asset metadata across requests, keyed by a build signature derived from compiled .asset.php hashes. A CSS-only or template-only change may not move the signature, so flush after each deploy (button below) or set the enqueues_build_signature filter to your deploy hash. Leave OFF unless you have a deploy-time flush in place.', 'enqueues' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="enqueues_cache_ttl"><?php esc_html_e( 'Cache TTL (seconds)', 'enqueues' ); ?></label></th>
						<td>
							<input type="number" min="3600" step="1" id="enqueues_cache_ttl" name="<?php echo esc_attr( self::OPTION ); ?>[cache_ttl]" value="<?php echo esc_attr( (string) $ttl ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'How long persistent cache entries live (minimum 1 hour). This also bounds the worst-case staleness window if a deploy is not followed by a flush.', 'enqueues' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<hr />
			<h2><?php esc_html_e( 'Maintenance', 'enqueues' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="enqueues_flush_cache" />
				<?php wp_nonce_field( 'enqueues_flush_cache' ); ?>
				<?php submit_button( __( 'Flush cache now', 'enqueues' ), 'secondary', 'submit', false ); ?>
				<p class="description"><?php esc_html_e( 'Run this after a deploy to discard any stale persistent cache entries.', 'enqueues' ); ?></p>
			</form>

			<hr />
			<h2><?php esc_html_e( 'Status', 'enqueues' ); ?></h2>
			<table class="widefat striped" style="max-width:640px">
				<tbody>
					<tr><td><?php esc_html_e( 'Request memo active', 'enqueues' ); ?></td><td><code><?php echo is_request_memo_enabled() ? 'on' : 'off'; ?></code></td></tr>
					<tr><td><?php esc_html_e( 'Persistent cache active', 'enqueues' ); ?></td><td><code><?php echo is_cache_enabled() ? 'on' : 'off'; echo $cache_const ? ' (constant)' : ''; ?></code></td></tr>
					<tr><td><?php esc_html_e( 'Cache TTL', 'enqueues' ); ?></td><td><code><?php echo esc_html( (string) get_cache_ttl() ); ?>s</code></td></tr>
					<tr><td><?php esc_html_e( 'Build signature', 'enqueues' ); ?></td><td><code><?php echo esc_html( get_enqueues_build_signature() ); ?></code></td></tr>
				</tbody>
			</table>
		</div>
		<?php
	}
}
