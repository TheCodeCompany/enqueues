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
use function Enqueues\enqueues_cache_mode;
use function Enqueues\enqueues_get_settings;
use function Enqueues\flush_enqueues_cache;
use function Enqueues\get_cache_ttl;
use function Enqueues\get_enqueues_build_signature;
use function Enqueues\is_cache_enabled;
use function Enqueues\is_profile_enabled;
use function Enqueues\is_request_memo_enabled;
use function Enqueues\enqueues_profile_reset;

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
		add_action( 'admin_post_enqueues_reset_profile', [ $this, 'handle_reset_profile' ] );

		// Auto-invalidate the persistent cache on the events WordPress can signal. A git-checkout
		// deploy does NOT fire these, so the page also documents flushing on deploy.
		add_action( 'switch_theme', [ $this, 'auto_flush' ] );
		add_action( 'upgrader_process_complete', [ $this, 'auto_flush' ] );

		// Persist the cache profiler's per-request samples at end of request, while profiling is on.
		if ( is_profile_enabled() ) {
			add_action( 'shutdown', '\\Enqueues\\enqueues_profile_persist', 9999 );
		}
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

		// Single source of truth: the cache mode radio ('off' | 'request' | 'persistent').
		$valid = [ 'off', 'request', 'persistent' ];
		$mode  = isset( $input['cache_mode'] ) && in_array( $input['cache_mode'], $valid, true ) ? (string) $input['cache_mode'] : 'request';

		// The ENQUEUES_CACHE_ENABLED constant forces Persistent mode; the radio's Persistent option is
		// disabled in that case, so honour the constant rather than letting a save downgrade it.
		if ( defined( 'ENQUEUES_CACHE_ENABLED' ) && ENQUEUES_CACHE_ENABLED ) {
			$mode = 'persistent';
		}

		// Derive the legacy booleans from the mode so any external reader stays consistent.
		return [
			'cache_mode'       => $mode,
			'request_memo'     => 'off' !== $mode,
			'persistent_cache' => 'persistent' === $mode,
			'cache_ttl'        => $ttl,
			'profile'          => ! empty( $input['profile'] ),
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
	 * Handle the "Reset profiler stats" action.
	 *
	 * @return void
	 */
	public function handle_reset_profile() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'enqueues' ) );
		}

		check_admin_referer( 'enqueues_reset_profile' );

		enqueues_profile_reset();

		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE ) );
		exit;
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
		$mode             = enqueues_cache_mode();
		$ttl              = (int) ( $settings['cache_ttl'] ?? DAY_IN_SECONDS );
		$cache_const      = defined( 'ENQUEUES_CACHE_ENABLED' );
		$profile_on       = ! empty( $settings['profile'] );
		$flushed          = isset( $_GET['enqueues_flushed'] ); // phpcs:ignore WordPress.Security.NonceVerification
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Enqueues', 'enqueues' ); ?></h1>

			<?php if ( $flushed ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Enqueues cache flushed.', 'enqueues' ); ?></p></div>
			<?php endif; ?>

			<p class="description">
				<?php esc_html_e( 'Asset-loading performance controls for the Enqueues framework. Choose a cache mode: Off (recompute every request, like the previous system), Per-request (in-process static memo, safe and never stale), or Persistent (also cache across requests in the object cache).', 'enqueues' ); ?>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( self::PAGE ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Asset cache mode', 'enqueues' ); ?></th>
						<td>
							<fieldset>
								<label style="display:block;margin-bottom:6px">
									<input type="radio" name="<?php echo esc_attr( self::OPTION ); ?>[cache_mode]" value="off" <?php checked( $mode, 'off' ); ?> />
									<strong><?php esc_html_e( 'Off', 'enqueues' ); ?></strong> &mdash; <?php esc_html_e( 'no caching; resolve assets on every request (the previous behaviour).', 'enqueues' ); ?>
								</label>
								<label style="display:block;margin-bottom:6px">
									<input type="radio" name="<?php echo esc_attr( self::OPTION ); ?>[cache_mode]" value="request" <?php checked( $mode, 'request' ); ?> />
									<strong><?php esc_html_e( 'Per-request (static)', 'enqueues' ); ?></strong> &mdash; <?php esc_html_e( 'in-process memo: deduped within each page load, never stale, nothing stored across requests. Recommended default.', 'enqueues' ); ?>
								</label>
								<label style="display:block">
									<input type="radio" name="<?php echo esc_attr( self::OPTION ); ?>[cache_mode]" value="persistent" <?php checked( $mode, 'persistent' ); ?> <?php disabled( $cache_const ); ?> />
									<strong><?php esc_html_e( 'Persistent (object cache)', 'enqueues' ); ?></strong> &mdash; <?php esc_html_e( 'also cache across requests in the object cache (global). Fastest under load; uses the object cache where present (e.g. Memcached), else the database. Needs a deploy-time flush or the enqueues_build_signature filter.', 'enqueues' ); ?>
								</label>
								<?php if ( $cache_const ) : ?>
									<p class="description"><strong><?php esc_html_e( 'The ENQUEUES_CACHE_ENABLED constant forces Persistent mode.', 'enqueues' ); ?></strong></p>
								<?php endif; ?>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="enqueues_cache_ttl"><?php esc_html_e( 'Cache TTL (seconds)', 'enqueues' ); ?></label></th>
						<td>
							<input type="number" min="3600" step="1" id="enqueues_cache_ttl" name="<?php echo esc_attr( self::OPTION ); ?>[cache_ttl]" value="<?php echo esc_attr( (string) $ttl ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'How long persistent cache entries live (minimum 1 hour). This also bounds the worst-case staleness window if a deploy is not followed by a flush.', 'enqueues' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Profiler', 'enqueues' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[profile]" value="1" <?php checked( $profile_on ); ?> />
								<?php esc_html_e( 'Record per-request cache hit/miss timings (quantifies the cache value-add).', 'enqueues' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Off by default and free when off. When on, each request stores a small timing sample shown in the Cache profiler section below. Turn off in normal production once measured.', 'enqueues' ); ?></p>
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
					<tr><td><?php esc_html_e( 'Cache mode', 'enqueues' ); ?></td><td><code><?php echo esc_html( $mode ); ?></code></td></tr>
					<tr><td><?php esc_html_e( 'Request memo active', 'enqueues' ); ?></td><td><code><?php echo is_request_memo_enabled() ? 'on' : 'off'; ?></code></td></tr>
					<tr><td><?php esc_html_e( 'Persistent cache active', 'enqueues' ); ?></td><td><code><?php echo is_cache_enabled() ? 'on' : 'off'; echo $cache_const ? ' (constant)' : ''; ?></code></td></tr>
					<tr><td><?php esc_html_e( 'Cache TTL', 'enqueues' ); ?></td><td><code><?php echo esc_html( (string) get_cache_ttl() ); ?>s</code></td></tr>
					<tr><td><?php esc_html_e( 'Build signature', 'enqueues' ); ?></td><td><code><?php echo esc_html( get_enqueues_build_signature() ); ?></code></td></tr>
				</tbody>
			</table>

			<hr />
			<h2><?php esc_html_e( 'Cache profiler', 'enqueues' ); ?></h2>
			<?php
			$profile_data = get_option( 'enqueues_profile_data', [] );
			$pstats       = is_array( $profile_data ) && isset( $profile_data['stats'] ) && is_array( $profile_data['stats'] ) ? $profile_data['stats'] : [];
			$plog         = is_array( $profile_data ) && isset( $profile_data['log'] ) && is_array( $profile_data['log'] ) ? $profile_data['log'] : [];
			$fmt_us       = static function ( $ns ) {
				return number_format( ( (float) $ns ) / 1000, 1 ) . ' &micro;s';
			};
			if ( ! $profile_on ) :
				?>
				<p class="description"><?php esc_html_e( 'Profiler is off. Tick the Profiler box above, Save, then load some pages to collect samples.', 'enqueues' ); ?></p>
				<?php
			endif;
			if ( empty( $pstats ) ) :
				?>
				<p class="description"><?php esc_html_e( 'No samples recorded yet.', 'enqueues' ); ?></p>
				<?php
			else :
				$tot_hit_n    = 0;
				$tot_miss_n   = 0;
				$tot_saved_ns = 0.0;
				?>
				<table class="widefat striped" style="max-width:960px">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Operation', 'enqueues' ); ?></th>
							<th><?php esc_html_e( 'Hits', 'enqueues' ); ?></th>
							<th><?php esc_html_e( 'Misses', 'enqueues' ); ?></th>
							<th><?php esc_html_e( 'Hit rate', 'enqueues' ); ?></th>
							<th><?php esc_html_e( 'With cache (avg)', 'enqueues' ); ?></th>
							<th><?php esc_html_e( 'Without cache (avg)', 'enqueues' ); ?></th>
							<th><?php esc_html_e( 'Saved / hit', 'enqueues' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $pstats as $bucket => $s ) : ?>
						<?php
						$hit_n    = (int) ( $s['hit_n'] ?? 0 );
						$miss_n   = (int) ( $s['miss_n'] ?? 0 );
						$hit_avg  = $hit_n ? ( (float) $s['hit_ns'] / $hit_n ) : 0.0;
						$miss_avg = $miss_n ? ( (float) $s['miss_ns'] / $miss_n ) : 0.0;
						$saved    = $miss_avg - $hit_avg; // Signed: negative means the cache read costs more than the compute on this backend.
						$rate     = ( $hit_n + $miss_n ) ? ( 100 * $hit_n / ( $hit_n + $miss_n ) ) : 0;
						$tot_hit_n    += $hit_n;
						$tot_miss_n   += $miss_n;
						$tot_saved_ns += $saved * $hit_n;
						?>
						<tr>
							<td><code><?php echo esc_html( $bucket ); ?></code></td>
							<td><?php echo (int) $hit_n; ?></td>
							<td><?php echo (int) $miss_n; ?></td>
							<td><?php echo esc_html( number_format( $rate, 1 ) ); ?>%</td>
							<td><?php echo wp_kses_post( $fmt_us( $hit_avg ) ); ?></td>
							<td><?php echo wp_kses_post( $fmt_us( $miss_avg ) ); ?></td>
							<td><?php echo wp_kses_post( $fmt_us( $saved ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
					<tfoot>
						<tr>
							<th><?php esc_html_e( 'Total', 'enqueues' ); ?></th>
							<th><?php echo (int) $tot_hit_n; ?></th>
							<th><?php echo (int) $tot_miss_n; ?></th>
							<th colspan="3"></th>
							<th><?php echo esc_html( number_format( $tot_saved_ns / 1e6, 2 ) ); ?> ms <?php esc_html_e( 'saved (total)', 'enqueues' ); ?></th>
						</tr>
					</tfoot>
				</table>
				<p class="description">
					<?php esc_html_e( '"Without cache" is the measured filesystem compute (the cost the no-cache system paid every request); "with cache" is the bare cache read. "Saved / hit" is without minus with — a NEGATIVE value means the cache read costs more than recomputing on this backend (a net loss for that operation). Local storage uses DB transients; a production object cache reads faster and shifts these positive.', 'enqueues' ); ?>
				</p>
				<p class="description">
					<?php esc_html_e( 'Caveats: "Without cache (avg)" is sampled only from cache-fill events (cold start / post-deploy / TTL expiry), so its sample count (Misses) is usually small and measured under a cold filesystem — treat the saving as a directional estimate, not an exact per-request delta. For block_version_map, "with cache" is the single shared map read amortised per block (one read serves every block in a request), so its per-block figures are small but sum to the real per-request saving. Totals are a LOWER BOUND under concurrency (each request writes its samples independently, so on multi-worker hosts some are overwritten). Turn the profiler off in normal production once measured.', 'enqueues' ); ?>
				</p>

				<h3><?php esc_html_e( 'Last requests', 'enqueues' ); ?></h3>
				<table class="widefat striped" style="max-width:960px">
					<thead>
						<tr>
							<th><?php esc_html_e( 'When (UTC)', 'enqueues' ); ?></th>
							<th><?php esc_html_e( 'URL', 'enqueues' ); ?></th>
							<th><?php esc_html_e( 'Cache reads (hits)', 'enqueues' ); ?></th>
							<th><?php esc_html_e( 'Compute (misses)', 'enqueues' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( array_reverse( $plog ) as $row ) : ?>
						<tr>
							<td><?php echo esc_html( gmdate( 'H:i:s', (int) ( $row['t'] ?? 0 ) ) ); ?></td>
							<td><code><?php echo esc_html( $row['url'] ?? '' ); ?></code></td>
							<td><?php echo wp_kses_post( $fmt_us( $row['hit_ns'] ?? 0 ) ); ?></td>
							<td><?php echo wp_kses_post( $fmt_us( $row['miss_ns'] ?? 0 ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px">
				<input type="hidden" name="action" value="enqueues_reset_profile" />
				<?php wp_nonce_field( 'enqueues_reset_profile' ); ?>
				<?php submit_button( __( 'Reset profiler stats', 'enqueues' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}
}
