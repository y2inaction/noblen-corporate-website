<?php
namespace Noblen\Core\Setup;

defined( 'ABSPATH' ) || exit;

/**
 * The Website Setup screen under the Noblen menu.
 *
 * Two deliberate actions: "Prepare" (drafts only) and "Activate" (reviewed items only). Every request is checked for a nonce and
 * the capability, every input is allow-listed against the blueprint, and every output is escaped.
 */
final class SetupPage {

	public const SLUG = 'noblen-setup';

	/**
	 * Hook the screen.
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_action( 'admin_post_noblen_setup_prepare', array( __CLASS__, 'handle_prepare' ) );
		add_action( 'admin_post_noblen_setup_activate', array( __CLASS__, 'handle_activate' ) );
		add_action( 'admin_post_noblen_setup_quickstart', array( __CLASS__, 'handle_quickstart' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/**
	 * Add the screen as a submenu of Noblen.
	 */
	public static function menu(): void {
		add_submenu_page( 'noblen-settings', 'Noblen Settings', 'Settings', Setup::CAP, 'noblen-settings' );
		add_submenu_page( 'noblen-settings', 'Website Setup', 'Website Setup', Setup::CAP, self::SLUG, array( __CLASS__, 'render' ) );
	}

	/**
	 * Load the screen's stylesheet on its own page only.
	 *
	 * @param string $hook Admin page hook.
	 */
	public static function assets( string $hook ): void {
		if ( false === strpos( $hook, self::SLUG ) ) {
			return;
		}
		wp_enqueue_style( 'noblen-admin-setup', plugins_url( 'assets/admin-setup.css', NOBLEN_CORE_FILE ), array(), NOBLEN_CORE_VERSION );
	}

	/**
	 * Check a request: capability first, then the nonce.
	 *
	 * @param string $action Nonce action.
	 * @param string $nonce  Submitted nonce.
	 * @return true|\WP_Error
	 */
	public static function authorize( string $action, string $nonce ) {
		if ( ! Setup::can() ) {
			return new \WP_Error( 'noblen_forbidden', 'You are not allowed to run the website setup.' );
		}
		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			return new \WP_Error( 'noblen_nonce', 'The security check failed. Reload the page and try again.' );
		}
		return true;
	}

	/**
	 * Read activation options from a submitted form, allow-listed against the blueprint.
	 *
	 * @param array<string,mixed> $post Raw (unslashed) form data.
	 * @return array<string,mixed>
	 */
	public static function parse_options( array $post ): array {
		$o     = Setup::default_options();
		$pages = array_keys( Blueprint::pages() );
		$menus = array_keys( Blueprint::menus() );
		foreach ( array( 'publish', 'adopt' ) as $group ) {
			foreach ( (array) ( $post[ $group ] ?? array() ) as $key => $on ) {
				if ( in_array( (string) $key, $pages, true ) && $on ) {
					$o[ $group ][ (string) $key ] = true;
				}
			}
		}
		foreach ( array( 'assign', 'replace_location' ) as $group ) {
			foreach ( (array) ( $post[ $group ] ?? array() ) as $key => $on ) {
				if ( in_array( (string) $key, $menus, true ) && $on ) {
					$o[ $group ][ (string) $key ] = true;
				}
			}
		}
		foreach ( array( 'front_page', 'replace_front_page', 'posts_page', 'replace_posts_page', 'hero_defaults', 'sample_settings' ) as $flag ) {
			$o[ $flag ] = ! empty( $post[ $flag ] );
		}
		return $o;
	}

	/**
	 * Store a result for the next screen load and go back to the screen.
	 *
	 * @param array<string,mixed>|\WP_Error $result Result.
	 */
	private static function finish( $result ): void {
		set_transient(
			'noblen_setup_result_' . get_current_user_id(),
			is_wp_error( $result ) ? array(
				'error' => $result->get_error_message(),
			) : $result,
			10 * MINUTE_IN_SECONDS
		);
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG ) );
		exit;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- the nonce and capability are verified first in authorize().

	/**
	 * Handle "Prepare".
	 */
	public static function handle_prepare(): void {
		$ok = self::authorize( 'noblen_setup_prepare', isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_wpnonce'] ) ) : '' );
		if ( is_wp_error( $ok ) ) {
			wp_die( esc_html( $ok->get_error_message() ), 403 );
		}
		self::finish( Setup::prepare() );
	}

	/**
	 * Handle "Activate".
	 */
	public static function handle_activate(): void {
		$ok = self::authorize( 'noblen_setup_activate', isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_wpnonce'] ) ) : '' );
		if ( is_wp_error( $ok ) ) {
			wp_die( esc_html( $ok->get_error_message() ), 403 );
		}
		if ( empty( $_POST['confirm_review'] ) ) {
			self::finish( new \WP_Error( 'noblen_confirm', 'Tick the confirmation that you have reviewed the pages before activating.' ) );
		}
		$raw = array();
		foreach ( array( 'publish', 'adopt', 'assign', 'replace_location' ) as $g ) {
			$raw[ $g ] = isset( $_POST[ $g ] ) && is_array( $_POST[ $g ] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST[ $g ] ) ) : array();
		}
		foreach ( array( 'front_page', 'replace_front_page', 'posts_page', 'replace_posts_page', 'hero_defaults', 'sample_settings' ) as $f ) {
			$raw[ $f ] = ! empty( $_POST[ $f ] );
		}
		self::finish( Setup::activate( self::parse_options( $raw ) ) );
	}

	/**
	 * Handle "Install the complete website".
	 */
	public static function handle_quickstart(): void {
		$ok = self::authorize( 'noblen_setup_quickstart', isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_wpnonce'] ) ) : '' );
		if ( is_wp_error( $ok ) ) {
			wp_die( esc_html( $ok->get_error_message() ), 403 );
		}
		if ( empty( $_POST['confirm_quickstart'] ) ) {
			self::finish( new \WP_Error( 'noblen_confirm', 'Tick the confirmation to create and publish the website.' ) );
		}
		self::finish( Setup::quickstart() );
	}

	// phpcs:enable WordPress.Security.NonceVerification.Missing

	/**
	 * A one-line pointer on Noblen screens until the setup has been run.
	 */
	public static function notice(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! Setup::can() || ! $screen || ( 'dashboard' !== $screen->id && false === strpos( (string) $screen->id, 'noblen' ) ) || false !== strpos( (string) $screen->id, self::SLUG ) ) {
			return;
		}
		if ( '' !== Setup::state()['prepared_at'] ) {
			return;
		}
		echo '<div class="notice notice-info"><p><strong>Your Noblen website has not been created yet.</strong> Open <a href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ) . '">Noblen, Website Setup</a> to create the pages, menus and navigation in one step. Nothing existing is overwritten.</p></div>';
	}

	/**
	 * A small status badge.
	 *
	 * @param string $class Status class.
	 */
	private static function badge( string $class ): string {
		return '<span class="noblen-badge noblen-badge--' . esc_attr( $class ) . '">' . esc_html( Setup::class_label( $class ) ) . '</span>';
	}

	/**
	 * Render the screen.
	 */
	public static function render(): void {
		if ( ! Setup::can() ) {
			wp_die( 'You are not allowed to run the website setup.', 403 );
		}
		$st     = Setup::status();
		$result = get_transient( 'noblen_setup_result_' . get_current_user_id() );
		if ( false !== $result ) {
			delete_transient( 'noblen_setup_result_' . get_current_user_id() );
		}
		$prepared = '' !== $st['state']['prepared_at'];

		// WordPress moves administration notices to just after the first heading, so that heading is the screen-reader title placed
		// before the banner. Notices then land above the banner, where they stay readable.
		echo '<div class="wrap noblen-setup">';
		echo '<h1 class="screen-reader-text">Website Setup</h1>';
		echo '<div class="noblen-setup__hero"><p class="noblen-setup__title" aria-hidden="true">Website Setup</p><p class="noblen-setup__lead">Prepare the Noblen website structure safely, review it, then activate it.</p></div>';
		echo '<div class="notice notice-info inline"><p><strong>Installing and activating the theme and plugin does not create a finished website.</strong> This screen creates <em>drafts</em> for you to review. Nothing becomes public, no menu is shown and your homepage setting is not touched until you activate in step 2. Existing pages, menus, records and settings are never overwritten or deleted. Business records are drafts only and still need clearance and approval before they can be published.</p></div>';

		self::render_result( $result );

		self::render_quickstart( $prepared );
		echo '<h2>Configuration</h2><ul class="noblen-checks">';
		foreach ( $st['checks'] as $c ) {
			echo '<li class="' . ( $c['ok'] ? 'ok' : 'warn' ) . '"><span>' . ( $c['ok'] ? 'Ready' : ( $c['blocking'] ? 'Blocking' : 'Needs attention' ) ) . '</span> ' . esc_html( (string) $c['label'] );
			if ( '' !== $c['note'] ) {
				echo ' <em>' . esc_html( (string) $c['note'] ) . '</em>';
			}
			echo '</li>';
		}
		echo '</ul>';

		echo '<h2>Legend</h2><p class="noblen-legend">';
		foreach ( array( 'existing', 'generated_draft', 'ready_for_review', 'public' ) as $k ) {
			echo self::badge( $k ) . ' '; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</p><p class="description">A successful preparation is not a finished website: it is the start of your review.</p>';

		// Step 1.
		echo '<div class="noblen-step"><h2>Step 1. Prepare (drafts only)</h2>';
		echo '<p>Creates the pages below as <strong>drafts</strong>, the three menus <strong>empty and unassigned</strong>, and name-only <strong>draft</strong> records. Safe to repeat: items it already created are skipped, and anything of yours that clashes is reported and kept.';
		if ( $prepared ) {
			echo ' Last prepared: ' . esc_html( (string) $st['state']['prepared_at'] ) . ' (UTC). Run it again to finish an interrupted preparation.';
		}
		echo '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="noblen_setup_prepare">';
		wp_nonce_field( 'noblen_setup_prepare' );
		submit_button( $prepared ? 'Run preparation again' : 'Prepare website structure', 'primary', 'submit', false );
		echo '</form></div>';

		self::render_overview( $st );
		self::render_review();

		// Step 2.
		self::render_activation( $st, $prepared );

		self::render_log( $st );
		echo '</div>';
	}

	/**
	 * The one-step installer.
	 *
	 * @param bool $prepared Whether preparation has already run.
	 */
	private static function render_quickstart( bool $prepared ): void {
		echo '<div class="noblen-step noblen-step--quick"><h2>Install the complete website</h2>';
		echo '<p>One step creates every page with professionally written, editable content, builds the Primary and Footer navigation, sets the Home page as the front page and Insights as the posts page (only if you have not chosen others), and fills the <em>empty</em> homepage and business details with clearly marked samples. <strong>The Privacy Policy and Terms pages stay drafts</strong> until they have been legally reviewed. Nothing you already have is overwritten or deleted, and business records stay drafts for the normal approval workflow.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="noblen_setup_quickstart">';
		wp_nonce_field( 'noblen_setup_quickstart' );
		echo '<p><label><input type="checkbox" name="confirm_quickstart" value="1"> I understand this will publish the website pages and use sample business details that I will review and replace.</label></p>';
		submit_button( $prepared ? 'Install or complete the website' : 'Install the complete website', 'primary large', 'submit', false );
		echo '</form></div>';
	}

	/**
	 * The content the owner should review or replace.
	 */
	private static function render_review(): void {
		$rows = Setup::review_list();
		if ( ! $rows ) {
			return;
		}
		echo '<h2>Content to review and replace</h2><p>The pages are complete and ready to read, but the items below use sample or generic information. Replace them with verified information.</p>';
		echo '<table class="widefat striped"><thead><tr><th>Item</th><th>What to review</th><th>Status</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr><td>';
			if ( '' !== $r['edit'] ) {
				echo '<a href="' . esc_url( $r['edit'] ) . '">' . esc_html( $r['item'] ) . '</a>';
			} else {
				echo esc_html( $r['item'] );
			}
			echo '</td><td>' . esc_html( $r['what'] ) . '</td><td>' . esc_html( $r['status'] ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Show the result of the last action.
	 *
	 * @param mixed $result Stored result.
	 */
	private static function render_result( $result ): void {
		if ( ! is_array( $result ) ) {
			return;
		}
		if ( isset( $result['error'] ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( (string) $result['error'] ) . '</p></div>';
			return;
		}
		$stages = array(
			'activate'   => 'Activation',
			'quickstart' => 'Website installation',
		);
		$stage  = $stages[ $result['stage'] ?? '' ] ?? 'Preparation';
		echo '<div class="notice notice-success"><p><strong>' . esc_html( $stage ) . ' finished.</strong> This is a record of what happened, not a sign that the website is complete.</p>';
		if ( isset( $result['counts'] ) ) {
			echo '<p>';
			foreach ( $result['counts'] as $k => $n ) {
				if ( $n ) {
					echo esc_html( ucfirst( (string) $k ) . ': ' . (int) $n ) . '&nbsp;&nbsp; ';
				}
			}
			echo '</p>';
		}
		echo '<ul class="noblen-result">';
		foreach ( (array) $result['items'] as $i ) {
			echo '<li><strong>' . esc_html( (string) $i['label'] ) . '</strong>: ' . esc_html( (string) $i['outcome'] );
			if ( ! empty( $i['note'] ) ) {
				echo ' <em>' . esc_html( (string) $i['note'] ) . '</em>';
			}
			echo '</li>';
		}
		echo '</ul></div>';
	}

	/**
	 * Preview and status tables.
	 *
	 * @param array<string,mixed> $st Status.
	 */
	private static function render_overview( array $st ): void {
		echo '<h2>Pages</h2><table class="widefat striped"><thead><tr><th>Page</th><th>Address</th><th>Purpose</th><th>Status</th></tr></thead><tbody>';
		foreach ( $st['pages'] as $p ) {
			echo '<tr><td>';
			if ( $p['id'] && current_user_can( 'edit_post', $p['id'] ) ) {
				echo '<a href="' . esc_url( (string) get_edit_post_link( $p['id'], 'raw' ) ) . '">' . esc_html( (string) $p['label'] ) . '</a>';
			} else {
				echo esc_html( (string) $p['label'] );
			}
			echo '</td><td><code>/' . esc_html( (string) $p['slug'] ) . '/</code></td><td>' . esc_html( (string) $p['purpose'] ) . '</td><td>' . self::badge( (string) $p['class'] ); // phpcs:ignore WordPress.Security.EscapeOutput
			if ( '' !== $p['note'] ) {
				echo '<br><em>' . esc_html( (string) $p['note'] ) . '</em>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';

		echo '<h2>Record archives</h2><p>Detailed records (business units, industries, services, projects, products) have their own addresses from Noblen Core and fill as records are approved and published. The pages above are the navigation destinations.</p>';
		echo '<table class="widefat striped"><thead><tr><th>Archive</th><th>Content type</th><th>Status</th></tr></thead><tbody>';
		foreach ( $st['archives'] as $a ) {
			echo '<tr><td>' . esc_html( (string) $a['label'] ) . '</td><td><code>' . esc_html( (string) $a['type'] ) . '</code></td><td>' . ( 'public' === $a['class'] ? self::badge( 'public' ) : '<span class="noblen-badge noblen-badge--generated_draft">No published record yet</span>' ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</tbody></table>';

		echo '<h2>Draft records (names only)</h2><p>Every record is a draft with no content, clearance or approval. Publishing one always goes through the normal clearance and approval workflow.</p>';
		echo '<table class="widefat striped"><thead><tr><th>Record</th><th>Type</th><th>Status</th></tr></thead><tbody>';
		foreach ( $st['records'] as $r ) {
			echo '<tr><td>';
			if ( $r['id'] && current_user_can( 'edit_post', $r['id'] ) ) {
				echo '<a href="' . esc_url( (string) get_edit_post_link( $r['id'], 'raw' ) ) . '">' . esc_html( (string) $r['label'] ) . '</a>';
			} else {
				echo esc_html( (string) $r['label'] );
			}
			echo '</td><td><code>' . esc_html( (string) $r['type'] ) . '</code></td><td>' . self::badge( (string) $r['class'] ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</tbody></table>';

		echo '<h2>Menus</h2><table class="widefat striped"><thead><tr><th>Menu</th><th>Location</th><th>Proposed entries</th><th>Status</th></tr></thead><tbody>';
		foreach ( $st['menus'] as $m ) {
			echo '<tr><td>' . esc_html( (string) $m['label'] ) . '</td><td><code>' . esc_html( (string) $m['location'] ) . '</code></td><td>';
			foreach ( $m['items'] as $i ) {
				echo '<span class="noblen-chip ' . ( $i['public'] ? 'is-public' : 'is-waiting' ) . '">' . esc_html( (string) $i['label'] ) . ( $i['public'] ? '' : ' (not public yet)' ) . '</span> ';
			}
			echo '</td><td>' . self::badge( (string) $m['class'] ); // phpcs:ignore WordPress.Security.EscapeOutput
			if ( '' !== $m['note'] ) {
				echo '<br><em>' . esc_html( (string) $m['note'] ) . '</em>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table><p class="description">Entries are added only for destinations the public can reach. Pages the setup did not create are never edited.</p>';

		if ( $st['home'] ) {
			echo '<h2>Homepage sections</h2><p>The theme decides what the homepage shows. A section with nothing approved to show is hidden. Visibility and order are yours to change in the Customizer; the setup does not change them.</p><table class="widefat striped"><thead><tr><th>Section</th><th>Visible setting</th><th>Has approved content</th><th>Will show</th></tr></thead><tbody>';
			foreach ( $st['home'] as $h ) {
				echo '<tr><td>' . esc_html( (string) $h['label'] ) . '</td><td>' . ( $h['visible'] ? 'Yes' : 'Hidden' ) . '</td><td>' . ( $h['has'] ? 'Yes' : 'No' ) . '</td><td>' . ( $h['shows'] ? '<strong>Yes</strong>' : 'No' ) . '</td></tr>';
			}
			echo '</tbody></table>';
		} else {
			echo '<p class="description">Homepage sections are listed when the Noblen Corporate theme is active.</p>';
		}
	}

	/**
	 * Step 2 form.
	 *
	 * @param array<string,mixed> $st       Status.
	 * @param bool                $prepared Whether preparation has run.
	 */
	private static function render_activation( array $st, bool $prepared ): void {
		echo '<div class="noblen-step"><h2>Step 2. Activate (your reviewed choices only)</h2>';
		if ( ! $prepared ) {
			echo '<p>Run step 1 first.</p></div>';
			return;
		}
		echo '<p>Review each page in the editor and replace every <code>[Owner to complete: ...]</code> placeholder with verified content. Then choose what to make public. Only pages that are <strong>ready for owner review</strong> can be published here. You can come back and activate more later.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="noblen_setup_activate">';
		wp_nonce_field( 'noblen_setup_activate' );

		echo '<h3>Publish reviewed pages</h3><ul class="noblen-options">';
		foreach ( $st['pages'] as $key => $p ) {
			$can   = 'ready_for_review' === $p['class'];
			$adopt = 'conflict' === $p['class'] && $p['id'] && 'publish' === get_post_status( $p['id'] );
			echo '<li><label><input type="checkbox" name="publish[' . esc_attr( (string) $key ) . ']" value="1"' . ( $can ? '' : ' disabled' ) . '> Publish <strong>' . esc_html( (string) $p['label'] ) . '</strong></label> ' . self::badge( (string) $p['class'] ); // phpcs:ignore WordPress.Security.EscapeOutput
			if ( $adopt ) {
				echo '<br><label><input type="checkbox" name="adopt[' . esc_attr( (string) $key ) . ']" value="1"> Use my existing published page at <code>/' . esc_html( (string) $p['slug'] ) . '/</code> in navigation (it is not changed)</label>';
			}
			echo '</li>';
		}
		echo '</ul>';

		echo '<h3>Navigation</h3><ul class="noblen-options">';
		foreach ( $st['menus'] as $key => $m ) {
			if ( ! in_array( $m['class'], array( 'generated_draft', 'public' ), true ) ) {
				echo '<li>' . esc_html( (string) $m['label'] ) . ': ' . self::badge( (string) $m['class'] ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput
				continue;
			}
			echo '<li><label><input type="checkbox" name="assign[' . esc_attr( (string) $key ) . ']" value="1"> Assign <strong>' . esc_html( (string) $m['label'] ) . '</strong> to the <code>' . esc_html( (string) $m['location'] ) . '</code> location (entries are added for public destinations only)</label>';
			if ( $m['note'] && 'public' !== $m['class'] ) {
				echo '<br><label><input type="checkbox" name="replace_location[' . esc_attr( (string) $key ) . ']" value="1"> The location already uses another menu: replace it</label>';
			}
			echo '</li>';
		}
		echo '</ul>';

		echo '<h3>Reading settings</h3><ul class="noblen-options">';
		echo '<li><label><input type="checkbox" name="front_page" value="1"> Use the published <strong>Home</strong> page as the front page</label> <label><input type="checkbox" name="replace_front_page" value="1"> replace a different static front page if one is set</label></li>';
		echo '<li><label><input type="checkbox" name="posts_page" value="1"> Use the published <strong>Insights</strong> page as the posts page</label> <label><input type="checkbox" name="replace_posts_page" value="1"> replace a different posts page if one is set</label></li>';
		echo '<li><label><input type="checkbox" name="sample_settings" value="1"> Fill the <em>empty</em> company name, tagline and contact details with clearly marked samples (existing values are kept)</label></li>';
		echo '<li><label><input type="checkbox" name="hero_defaults" value="1"> Fill the <em>empty</em> homepage headline and introduction with "' . esc_html( Blueprint::PROMISE ) . '" and "' . esc_html( Blueprint::COMPANY ) . ', an ' . esc_html( Blueprint::POSITIONING ) . '." (existing text is kept)</label></li>';
		echo '</ul>';

		echo '<p><label><input type="checkbox" name="confirm_review" value="1"> I have reviewed the selected pages and they contain only verified, approved content.</label></p>';
		submit_button( 'Activate selected items', 'primary', 'submit', false );
		echo '</form></div>';
	}

	/**
	 * Recent log lines.
	 *
	 * @param array<string,mixed> $st Status.
	 */
	private static function render_log( array $st ): void {
		$log = array_slice( array_reverse( (array) $st['state']['log'] ), 0, 10 );
		if ( ! $log ) {
			return;
		}
		echo '<h2>Recent activity</h2><ul class="noblen-log">';
		foreach ( $log as $l ) {
			echo '<li><code>' . esc_html( (string) $l['time'] ) . '</code> ' . esc_html( (string) $l['level'] ) . ': ' . esc_html( (string) $l['text'] ) . '</li>';
		}
		echo '</ul>';
	}
}
