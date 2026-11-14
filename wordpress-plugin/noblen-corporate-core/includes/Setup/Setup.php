<?php
namespace Noblen\Core\Setup;

use Noblen\Core\Content\Types;
use Noblen\Core\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Website Setup: prepare the site structure as drafts, then activate it deliberately.
 *
 * Safety rules, enforced here and tested:
 *  - Everything the setup creates carries a stable key in meta (posts) or term meta (menus). Duplicates are detected by key.
 *  - An existing page, menu, record or setting that the setup did not create is never changed, replaced or deleted. A clash is
 *    reported as a conflict and the existing object is kept.
 *  - Pages are created as drafts. Business records are created as drafts with no content, clearance or approval, and this class
 *    never publishes a record: the clearance and approval workflow is the only way to publish one.
 *  - Stage 1 (prepare) changes nothing public: no page is published, no menu is assigned, no homepage setting is touched.
 *  - Stage 2 (activate) is a separate, explicit action. It publishes only the reviewed pages the administrator selects, adds a
 *    menu entry only when its destination is publicly reachable, assigns a menu to a theme location only when that location is
 *    free (or the administrator chose to replace it), and changes the front page or posts page only when asked.
 *  - Both stages are idempotent and safe to repeat after an interruption.
 */
final class Setup {

	/** Meta key on created posts and menu terms and items. */
	public const META = '_noblen_setup_key';

	/** Option that remembers progress, adopted pages and a short log. */
	public const STATE = 'noblen_core_setup_state';

	/** Capability needed for every setup action. */
	public const CAP = 'noblen_manage_settings';

	/**
	 * Whether the current user may use the setup.
	 */
	public static function can(): bool {
		return current_user_can( self::CAP );
	}

	/**
	 * Stored state.
	 *
	 * @return array<string,mixed>
	 */
	public static function state(): array {
		$s = get_option( self::STATE, array() );
		$s = is_array( $s ) ? $s : array();
		return array_merge(
			array(
				'prepared_at'  => '',
				'activated_at' => '',
				'adopted'      => array(),
				'log'          => array(),
			),
			$s
		);
	}

	/**
	 * Save state.
	 *
	 * @param array<string,mixed> $state State.
	 */
	private static function save_state( array $state ): void {
		$state['log'] = array_slice( (array) $state['log'], -100 );
		update_option( self::STATE, $state, false );
	}

	/**
	 * Add a line to the setup log.
	 *
	 * @param string $level   info, warning or error.
	 * @param string $message Message (no secrets).
	 */
	private static function log( string $level, string $message ): void {
		$state          = self::state();
		$state['log'][] = array(
			'time'  => gmdate( 'c' ),
			'level' => $level,
			'text'  => $message,
		);
		self::save_state( $state );
		if ( 'error' === $level ) {
			error_log( 'Noblen setup: ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Checks of the environment. "blocking" ones stop preparation.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function config_checks(): array {
		$missing_types = array();
		foreach ( Types::all() as $t ) {
			if ( ! post_type_exists( $t ) ) {
				$missing_types[] = $t;
			}
		}
		$registered = function_exists( 'get_registered_nav_menus' ) ? get_registered_nav_menus() : array();
		$locations  = array();
		foreach ( Blueprint::menus() as $m ) {
			if ( ! isset( $registered[ $m['location'] ] ) ) {
				$locations[] = (string) $m['location'];
			}
		}
		return array(
			array(
				'id'       => 'types',
				'label'    => 'Noblen content types are registered',
				'ok'       => array() === $missing_types,
				'blocking' => true,
				'note'     => array() === $missing_types ? '' : 'Missing: ' . implode( ', ', $missing_types ),
			),
			array(
				'id'       => 'theme',
				'label'    => 'The Noblen Corporate theme is active (needed to assign menus)',
				'ok'       => array() === $locations,
				'blocking' => false,
				'note'     => array() === $locations ? '' : 'Menu locations not available: ' . implode( ', ', $locations ) . '. You can prepare now and activate after the theme is active.',
			),
			array(
				'id'       => 'permalinks',
				'label'    => 'Pretty permalinks are on (page addresses such as /about/)',
				'ok'       => '' !== (string) get_option( 'permalink_structure', '' ),
				'blocking' => false,
				'note'     => '' !== (string) get_option( 'permalink_structure', '' ) ? '' : 'Choose a permalink structure under Settings, Permalinks.',
			),
		);
	}

	/* ---------------------------------------------------------------- lookups */

	/**
	 * A post created by the setup, by key and type.
	 *
	 * @param string $key  Stable key.
	 * @param string $type Post type.
	 * @return \WP_Post|null
	 */
	private static function owned_post( string $key, string $type ) {
		$found = get_posts(
			array(
				'post_type'        => $type,
				'post_status'      => array( 'publish', 'pending', 'draft', 'future', 'private', 'trash' ),
				'meta_key'         => self::META, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'       => $key, // phpcs:ignore WordPress.DB.SlowDBQuery
				'posts_per_page'   => 1,
				'suppress_filters' => true,
			)
		);
		return $found ? $found[0] : null;
	}

	/**
	 * Another page that already uses a slug (any status, including trash).
	 *
	 * @param string $slug Slug.
	 * @return \WP_Post|null
	 */
	private static function page_with_slug( string $slug ) {
		global $wpdb;
		$id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_name = %s AND post_status NOT IN ('auto-draft','inherit') ORDER BY ID ASC LIMIT 1", $slug ) ); // phpcs:ignore WordPress.DB
		return $id ? get_post( (int) $id ) : null;
	}

	/**
	 * A record of the type with this exact title, in any non-trashed status.
	 *
	 * @param string $type  Post type.
	 * @param string $title Title.
	 * @return \WP_Post|null
	 */
	private static function record_with_title( string $type, string $title ) {
		$found = get_posts(
			array(
				'post_type'        => $type,
				'post_status'      => array( 'publish', 'pending', 'draft', 'future', 'private' ),
				'title'            => $title,
				'posts_per_page'   => 1,
				'suppress_filters' => true,
			)
		);
		return $found ? $found[0] : null;
	}

	/**
	 * A nav menu created by the setup.
	 *
	 * @param string $key Menu key.
	 * @return int Term ID or 0.
	 */
	private static function owned_menu( string $key ): int {
		$terms = get_terms(
			array(
				'taxonomy'   => 'nav_menu',
				'hide_empty' => false,
				'number'     => 1,
				'fields'     => 'ids',
				'meta_key'   => self::META, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value' => 'menu:' . $key, // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		return ( is_array( $terms ) && $terms ) ? (int) $terms[0] : 0;
	}

	/**
	 * The page that stands for a blueprint page: the one the setup created, or one the administrator adopted.
	 *
	 * @param string $key Page key.
	 * @return \WP_Post|null
	 */
	private static function page_for( string $key ) {
		$own = self::owned_post( 'page:' . $key, 'page' );
		if ( $own ) {
			return $own;
		}
		$adopted = self::state()['adopted'][ $key ] ?? 0;
		$post    = $adopted ? get_post( (int) $adopted ) : null;
		return ( $post && 'page' === $post->post_type && 'trash' !== $post->post_status ) ? $post : null;
	}

	/**
	 * Whether a page's content is complete enough for the public: not empty and with no placeholder left.
	 *
	 * @param \WP_Post $page Page.
	 * @param string   $role Role from the blueprint.
	 */
	public static function page_content_ready( \WP_Post $page, string $role ): bool {
		if ( '' === trim( (string) $page->post_title ) ) {
			return false;
		}
		if ( 'front_page' === $role || 'posts_page' === $role ) {
			return true; // Their public content comes from the plugin or the posts list.
		}
		$content = (string) $page->post_content;
		return '' !== trim( wp_strip_all_tags( $content ) ) && ! preg_match( Blueprint::PLACEHOLDER, $content );
	}

	/**
	 * Whether a destination can be reached by the public right now.
	 *
	 * @param string $dest "home", "page:<key>" or "archive:<key>".
	 */
	public static function destination_public( string $dest ): bool {
		if ( 'home' === $dest ) {
			return true;
		}
		if ( 0 === strpos( $dest, 'page:' ) ) {
			$page = self::page_for( substr( $dest, 5 ) );
			return $page && 'publish' === $page->post_status;
		}
		if ( 0 === strpos( $dest, 'archive:' ) ) {
			$a = Blueprint::archives()[ substr( $dest, 8 ) ] ?? null;
			if ( ! $a ) {
				return false;
			}
			$n = get_posts(
				array(
					'post_type'        => $a['type'],
					'post_status'      => 'publish',
					'posts_per_page'   => 1,
					'fields'           => 'ids',
					'suppress_filters' => true,
				)
			);
			return ! empty( $n );
		}
		return false;
	}

	/* ----------------------------------------------------------------- status */

	/**
	 * Label for a status class shown to the administrator.
	 *
	 * @param string $class Class.
	 */
	public static function class_label( string $class ): string {
		$labels = array(
			'missing'          => 'Not created yet',
			'existing'         => 'Existing content (kept as it is)',
			'conflict'         => 'Conflict (existing content kept)',
			'generated_draft'  => 'Generated draft (needs your content)',
			'ready_for_review' => 'Ready for owner review',
			'public'           => 'Approved and public',
			'trashed'          => 'In the trash',
		);
		return $labels[ $class ] ?? $class;
	}

	/**
	 * Current state of every blueprint item. Read-only.
	 *
	 * @return array<string,mixed>
	 */
	public static function status(): array {
		$pages = array();
		foreach ( Blueprint::pages() as $key => $def ) {
			$own     = self::owned_post( 'page:' . $key, 'page' );
			$adopted = self::page_for( $key );
			$other   = $own ? null : self::page_with_slug( $def['slug'] );
			$row     = array(
				'key'     => $key,
				'label'   => $def['title'],
				'slug'    => $def['slug'],
				'purpose' => $def['purpose'],
				'role'    => $def['role'],
				'id'      => 0,
				'class'   => 'missing',
				'note'    => '',
			);
			if ( $own ) {
				$row['id'] = $own->ID;
				if ( 'trash' === $own->post_status ) {
					$row['class'] = 'trashed';
					$row['note']  = 'Restore it from the trash to continue.';
				} elseif ( 'publish' === $own->post_status ) {
					$row['class'] = 'public';
				} elseif ( self::page_content_ready( $own, $def['role'] ) ) {
					$row['class'] = 'ready_for_review';
				} else {
					$row['class'] = 'generated_draft';
					$row['note']  = 'Replace every [Owner to complete: ...] placeholder with verified content.';
				}
			} elseif ( $adopted ) {
				$row['id']    = $adopted->ID;
				$row['class'] = 'existing';
				$row['note']  = 'You chose to use your existing page. It is not changed.';
			} elseif ( $other ) {
				$row['id']    = $other->ID;
				$row['class'] = 'conflict';
				$row['note']  = sprintf( 'A page with the address /%s/ already exists (status: %s). It is kept and not changed.', $def['slug'], $other->post_status );
			}
			$pages[ $key ] = $row;
		}

		$archives = array();
		foreach ( Blueprint::archives() as $key => $def ) {
			$public           = self::destination_public( 'archive:' . $key );
			$archives[ $key ] = array(
				'key'     => $key,
				'label'   => $def['title'],
				'purpose' => $def['purpose'],
				'type'    => $def['type'],
				'class'   => $public ? 'public' : 'generated_draft',
				'note'    => $public ? '' : 'Shown in navigation after at least one record of this type is approved and published.',
			);
		}

		$records = array();
		foreach ( Blueprint::records() as $type => $titles ) {
			foreach ( $titles as $title ) {
				$key   = 'record:' . $type . ':' . sanitize_title( $title );
				$own   = self::owned_post( $key, $type );
				$other = $own ? null : self::record_with_title( $type, $title );
				$row   = array(
					'key'   => $key,
					'type'  => $type,
					'label' => $title,
					'id'    => 0,
					'class' => 'missing',
					'note'  => '',
				);
				if ( $own ) {
					$row['id'] = $own->ID;
					if ( 'trash' === $own->post_status ) {
						$row['class'] = 'trashed';
					} elseif ( 'publish' === $own->post_status ) {
						$row['class'] = 'public';
					} elseif ( 'pending' === $own->post_status ) {
						$row['class'] = 'ready_for_review';
					} else {
						$row['class'] = 'generated_draft';
						$row['note']  = 'Draft. Needs content, clearance and approval before it can be published.';
					}
				} elseif ( $other ) {
					$row['id']    = $other->ID;
					$row['class'] = 'existing';
					$row['note']  = 'A record with this title already exists. It is kept and not changed.';
				}
				$records[ $key ] = $row;
			}
		}

		$menus = array();
		foreach ( Blueprint::menus() as $key => $def ) {
			$own   = self::owned_menu( $key );
			$other = $own ? false : wp_get_nav_menu_object( $def['name'] );
			$row   = array(
				'key'      => $key,
				'label'    => $def['name'],
				'location' => $def['location'],
				'id'       => $own,
				'class'    => 'missing',
				'note'     => '',
				'items'    => array(),
			);
			if ( $own ) {
				$row['class'] = 'generated_draft';
			} elseif ( $other ) {
				$row['id']    = (int) $other->term_id;
				$row['class'] = 'conflict';
				$row['note']  = 'A menu with this name already exists. It is kept and not changed.';
			}
			foreach ( $def['items'] as $dest ) {
				$row['items'][] = array(
					'dest'   => $dest,
					'label'  => self::dest_label( $dest ),
					'public' => self::destination_public( $dest ),
					'added'  => $own ? (bool) self::owned_item( $key, $dest ) : false,
				);
			}
			$assigned = self::location_menu( (string) $def['location'] );
			if ( $own && $assigned === $own ) {
				$row['class'] = 'public';
				$row['note']  = 'Assigned to the ' . $def['location'] . ' location.';
			} elseif ( $assigned && $assigned !== $own ) {
				$row['note'] = trim( $row['note'] . ' The ' . $def['location'] . ' location already uses another menu; it is kept unless you choose to replace it.' );
			}
			$menus[ $key ] = $row;
		}

		return array(
			'pages'    => $pages,
			'archives' => $archives,
			'records'  => $records,
			'menus'    => $menus,
			'home'     => self::home_status(),
			'checks'   => self::config_checks(),
			'state'    => self::state(),
		);
	}

	/**
	 * Display label of a destination.
	 *
	 * @param string $dest Destination.
	 */
	private static function dest_label( string $dest ): string {
		if ( 'home' === $dest ) {
			return 'Home';
		}
		if ( 0 === strpos( $dest, 'page:' ) ) {
			return Blueprint::pages()[ substr( $dest, 5 ) ]['title'] ?? $dest;
		}
		return Blueprint::archives()[ substr( $dest, 8 ) ]['title'] ?? $dest;
	}

	/**
	 * The menu assigned to a theme location, or 0.
	 *
	 * @param string $location Location.
	 */
	private static function location_menu( string $location ): int {
		$locs = get_nav_menu_locations();
		return isset( $locs[ $location ] ) ? (int) $locs[ $location ] : 0;
	}

	/**
	 * A menu item created by the setup for a destination.
	 *
	 * @param string $menu_key Menu key.
	 * @param string $dest     Destination.
	 * @return \WP_Post|null
	 */
	private static function owned_item( string $menu_key, string $dest ) {
		$found = get_posts(
			array(
				'post_type'        => 'nav_menu_item',
				'post_status'      => 'any',
				'meta_key'         => self::META, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'       => 'item:' . $menu_key . '|' . $dest, // phpcs:ignore WordPress.DB.SlowDBQuery
				'posts_per_page'   => 1,
				'suppress_filters' => true,
			)
		);
		return $found ? $found[0] : null;
	}

	/**
	 * Which homepage sections would show now. Read-only; nothing is changed.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function home_status(): array {
		$types = array(
			'companies'  => Types::COMPANY,
			'industries' => Types::INDUSTRY,
			'solutions'  => Types::SERVICE,
			'projects'   => Types::PROJECT,
			'products'   => Types::PRODUCT,
		);
		$reg   = function_exists( 'noblen_home_registry' ) ? noblen_home_registry() : array();
		$out   = array();
		foreach ( $reg as $id => $label ) {
			$visible = (bool) get_theme_mod( 'noblen_home_show_' . $id, true );
			if ( isset( $types[ $id ] ) ) {
				$has = (bool) get_posts(
					array(
						'post_type'      => $types[ $id ],
						'post_status'    => 'publish',
						'posts_per_page' => 1,
						'fields'         => 'ids',
					)
				);
			} elseif ( 'insights' === $id ) {
				$has = (bool) get_posts(
					array(
						'post_type'      => 'post',
						'post_status'    => 'publish',
						'posts_per_page' => 1,
						'fields'         => 'ids',
					)
				);
			} else {
				$has = false; // Group introduction, partnerships and the call to action depend on owner-entered settings or a later release.
			}
			$out[] = array(
				'id'      => $id,
				'label'   => $label,
				'visible' => $visible,
				'has'     => $has,
				'shows'   => $visible && $has,
			);
		}
		return $out;
	}

	/* ----------------------------------------------------------------- prepare */

	/**
	 * Stage 1: create the proposed structure as drafts. Changes nothing public.
	 *
	 * @return array<string,mixed>|\WP_Error Result with "items" and "counts".
	 */
	public static function prepare() {
		if ( ! self::can() ) {
			return new \WP_Error( 'noblen_forbidden', 'You are not allowed to run the website setup.' );
		}
		foreach ( self::config_checks() as $check ) {
			if ( $check['blocking'] && ! $check['ok'] ) {
				return new \WP_Error( 'noblen_setup_config', 'The setup cannot run: ' . $check['label'] . '. ' . $check['note'] );
			}
		}

		$items = array();

		// Records first: names only, always drafts.
		foreach ( Blueprint::records() as $type => $titles ) {
			foreach ( $titles as $title ) {
				$key       = 'record:' . $type . ':' . sanitize_title( $title );
				$item      = array(
					'key'     => $key,
					'label'   => $title,
					'kind'    => 'record',
					'outcome' => 'skipped',
					'id'      => 0,
					'note'    => '',
				);
				$own       = self::owned_post( $key, $type );
				$other_rec = $own ? null : self::record_with_title( $type, $title );
				if ( $own ) {
					$item['id']      = $own->ID;
					$item['outcome'] = 'existing';
					$item['note']    = 'Already created by the setup.';
				} elseif ( null !== $other_rec ) {
					$item['id']      = $other_rec->ID;
					$item['outcome'] = 'existing';
					$item['note']    = 'A record with this title already exists. Kept as it is.';
				} else {
					$id = wp_insert_post(
						array(
							'post_type'    => $type,
							'post_title'   => $title,
							'post_status'  => 'draft',
							'post_content' => '',
							'meta_input'   => array( self::META => $key ),
						),
						true
					);
					if ( is_wp_error( $id ) || ! $id ) {
						$item['outcome'] = 'error';
						$item['note']    = is_wp_error( $id ) ? $id->get_error_message() : 'The record could not be created.';
						self::log( 'error', 'Record ' . $key . ': ' . $item['note'] );
					} else {
						$item['id']      = (int) $id;
						$item['outcome'] = 'created';
					}
				}
				$items[] = $item;
			}
		}

		// Pages: drafts only.
		foreach ( Blueprint::pages() as $key => $def ) {
			$item       = array(
				'key'     => 'page:' . $key,
				'label'   => $def['title'],
				'kind'    => 'page',
				'outcome' => 'skipped',
				'id'      => 0,
				'note'    => '',
			);
			$own        = self::owned_post( 'page:' . $key, 'page' );
			$other_page = ( $own || self::page_for( $key ) ) ? null : self::page_with_slug( $def['slug'] );
			if ( $own ) {
				$item['id']      = $own->ID;
				$item['outcome'] = 'existing';
				$item['note']    = 'Already created by the setup. Your edits are kept.';
			} elseif ( self::page_for( $key ) ) {
				$item['outcome'] = 'existing';
				$item['note']    = 'You chose to use your existing page.';
			} elseif ( null !== $other_page ) {
				$item['id']      = $other_page->ID;
				$item['outcome'] = 'conflict';
				$item['note']    = sprintf( 'A page at /%s/ already exists (%s). It was kept and not changed.', $def['slug'], $other_page->post_status );
				self::log( 'warning', 'Page conflict: /' . $def['slug'] . '/ already exists.' );
			} else {
				$id = wp_insert_post(
					array(
						'post_type'    => 'page',
						'post_title'   => $def['title'],
						'post_name'    => $def['slug'],
						'post_status'  => 'draft',
						'post_content' => $def['content'],
						'meta_input'   => array( self::META => 'page:' . $key ),
					),
					true
				);
				if ( is_wp_error( $id ) || ! $id ) {
					$item['outcome'] = 'error';
					$item['note']    = is_wp_error( $id ) ? $id->get_error_message() : 'The page could not be created.';
					self::log( 'error', 'Page ' . $key . ': ' . $item['note'] );
				} else {
					$item['id']      = (int) $id;
					$item['outcome'] = 'created';
				}
			}
			$items[] = $item;
		}

		// Menus: created empty and unassigned. Entries are added at activation, only for reachable destinations.
		foreach ( Blueprint::menus() as $key => $def ) {
			$item       = array(
				'key'     => 'menu:' . $key,
				'label'   => $def['name'],
				'kind'    => 'menu',
				'outcome' => 'skipped',
				'id'      => 0,
				'note'    => '',
			);
			$own        = self::owned_menu( $key );
			$other_menu = $own ? false : wp_get_nav_menu_object( $def['name'] );
			if ( $own ) {
				$item['id']      = $own;
				$item['outcome'] = 'existing';
				$item['note']    = 'Already created by the setup.';
			} elseif ( $other_menu ) {
				$item['id']      = (int) $other_menu->term_id;
				$item['outcome'] = 'conflict';
				$item['note']    = 'A menu named "' . $def['name'] . '" already exists. It was kept and not changed.';
				self::log( 'warning', 'Menu conflict: "' . $def['name'] . '" already exists.' );
			} else {
				$id = wp_create_nav_menu( $def['name'] );
				if ( is_wp_error( $id ) || ! $id ) {
					$item['outcome'] = 'error';
					$item['note']    = is_wp_error( $id ) ? $id->get_error_message() : 'The menu could not be created.';
					self::log( 'error', 'Menu ' . $key . ': ' . $item['note'] );
				} else {
					add_term_meta( (int) $id, self::META, 'menu:' . $key, true );
					$item['id']      = (int) $id;
					$item['outcome'] = 'created';
				}
			}
			$items[] = $item;
		}

		$counts = array(
			'created'  => 0,
			'existing' => 0,
			'conflict' => 0,
			'error'    => 0,
			'skipped'  => 0,
		);
		foreach ( $items as $i ) {
			$counts[ $i['outcome'] ] = ( $counts[ $i['outcome'] ] ?? 0 ) + 1;
		}
		$state                = self::state();
		$state['prepared_at'] = gmdate( 'c' );
		self::save_state( $state );
		self::log( 'info', sprintf( 'Prepare: %d created, %d existing, %d conflict, %d error.', $counts['created'], $counts['existing'], $counts['conflict'], $counts['error'] ) );

		return array(
			'stage'  => 'prepare',
			'items'  => $items,
			'counts' => $counts,
		);
	}

	/* ---------------------------------------------------------------- activate */

	/**
	 * Default activation options (everything off).
	 *
	 * @return array<string,mixed>
	 */
	public static function default_options(): array {
		return array(
			'publish'            => array(),
			'adopt'              => array(),
			'assign'             => array(),
			'replace_location'   => array(),
			'front_page'         => false,
			'replace_front_page' => false,
			'posts_page'         => false,
			'replace_posts_page' => false,
			'hero_defaults'      => false,
		);
	}

	/**
	 * Stage 2: activate the reviewed structure. Every change is something the administrator chose.
	 *
	 * @param array<string,mixed> $opts Options from default_options().
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function activate( array $opts ) {
		if ( ! self::can() ) {
			return new \WP_Error( 'noblen_forbidden', 'You are not allowed to run the website setup.' );
		}
		$opts  = array_merge( self::default_options(), $opts );
		$state = self::state();
		if ( '' === $state['prepared_at'] ) {
			return new \WP_Error( 'noblen_setup_not_prepared', 'Prepare the website structure first.' );
		}

		$items = array();
		$add   = static function ( string $kind, string $label, string $outcome, string $note = '', int $id = 0 ) use ( &$items ): void {
			$items[] = array(
				'kind'    => $kind,
				'label'   => $label,
				'outcome' => $outcome,
				'note'    => $note,
				'id'      => $id,
			);
		};

		// 1. Adopt existing pages the administrator chose to use. They are linked, never changed.
		foreach ( (array) $opts['adopt'] as $key => $on ) {
			$def = Blueprint::pages()[ (string) $key ] ?? null;
			if ( ! $on || ! $def || self::owned_post( 'page:' . $key, 'page' ) ) {
				continue;
			}
			$other = self::page_with_slug( $def['slug'] );
			if ( $other && 'publish' === $other->post_status ) {
				$state['adopted'][ $key ] = $other->ID;
				$add( 'page', $def['title'], 'adopted', 'Your existing page is used in navigation and was not changed.', $other->ID );
			} else {
				$add( 'page', $def['title'], 'blocked', 'Only a published existing page can be adopted.' );
			}
		}
		self::save_state( $state );

		// 2. Publish the reviewed pages the administrator selected.
		foreach ( (array) $opts['publish'] as $key => $on ) {
			$def = Blueprint::pages()[ (string) $key ] ?? null;
			if ( ! $on || ! $def ) {
				continue;
			}
			$own = self::owned_post( 'page:' . $key, 'page' );
			if ( ! $own ) {
				$add( 'page', $def['title'], 'blocked', 'Not created by the setup, so it is not published here.' );
			} elseif ( 'publish' === $own->post_status ) {
				$add( 'page', $def['title'], 'existing', 'Already public.', $own->ID );
			} elseif ( ! in_array( $own->post_status, array( 'draft', 'pending' ), true ) ) {
				$add( 'page', $def['title'], 'blocked', 'Status "' . $own->post_status . '" is left alone.', $own->ID );
			} elseif ( ! self::page_content_ready( $own, $def['role'] ) ) {
				$add( 'page', $def['title'], 'blocked', 'Not ready: replace every [Owner to complete: ...] placeholder first.', $own->ID );
			} else {
				$res = wp_update_post(
					array(
						'ID'          => $own->ID,
						'post_status' => 'publish',
					),
					true
				);
				if ( is_wp_error( $res ) || ! $res ) {
					$add( 'page', $def['title'], 'error', is_wp_error( $res ) ? $res->get_error_message() : 'Could not publish.', $own->ID );
					self::log( 'error', 'Publish ' . $key . ' failed.' );
				} else {
					$add( 'page', $def['title'], 'published', 'Published after your review.', $own->ID );
				}
			}
		}

		// 3. Menus: add entries whose destinations are public; assign a menu only when it is free or the administrator chose to replace.
		foreach ( Blueprint::menus() as $key => $def ) {
			$menu_id = self::owned_menu( $key );
			if ( ! $menu_id ) {
				$add( 'menu', $def['name'], 'blocked', 'Not created by the setup (prepare it, or it clashes with an existing menu).' );
				continue;
			}
			$waiting = array();
			foreach ( $def['items'] as $dest ) {
				if ( self::owned_item( $key, $dest ) ) {
					continue;
				}
				if ( ! self::destination_public( $dest ) ) {
					$waiting[] = self::dest_label( $dest );
					continue;
				}
				$added = self::add_menu_item( $menu_id, $key, $dest );
				if ( ! $added ) {
					$add( 'menu', $def['name'] . ': ' . self::dest_label( $dest ), 'error', 'Could not add the entry.' );
				}
			}
			$count = self::count_owned_items( $key, $def['items'] );
			$note  = $waiting ? 'Waiting for public destinations: ' . implode( ', ', $waiting ) . '.' : '';

			$want = ! empty( $opts['assign'][ $key ] );
			if ( ! $want ) {
				$add( 'menu', $def['name'], 'prepared', trim( $count . ' entr' . ( 1 === $count ? 'y' : 'ies' ) . '. Not assigned (not selected). ' . $note ), $menu_id );
				continue;
			}
			if ( 0 === $count ) {
				$add( 'menu', $def['name'], 'blocked', 'No destination is public yet, so the menu is not assigned. ' . $note, $menu_id );
				continue;
			}
			$registered = get_registered_nav_menus();
			if ( ! isset( $registered[ $def['location'] ] ) ) {
				$add( 'menu', $def['name'], 'blocked', 'The theme location "' . $def['location'] . '" is not available. Activate the Noblen Corporate theme.', $menu_id );
				continue;
			}
			$current = self::location_menu( (string) $def['location'] );
			if ( $current && $current !== $menu_id && empty( $opts['replace_location'][ $key ] ) ) {
				$add( 'menu', $def['name'], 'conflict', 'The ' . $def['location'] . ' location already uses another menu. It was kept. Choose "replace" to switch.', $menu_id );
				continue;
			}
			$locs                     = get_nav_menu_locations();
			$locs[ $def['location'] ] = $menu_id;
			set_theme_mod( 'nav_menu_locations', $locs );
			$add( 'menu', $def['name'], 'assigned', trim( 'Assigned to the ' . $def['location'] . ' location. ' . $note ), $menu_id );
		}

		// 4. Front page and posts page: only when asked, and a different existing one only with the replace choice.
		self::apply_reading( $opts, $add );

		// 5. Homepage text: fill empty settings only.
		if ( ! empty( $opts['hero_defaults'] ) ) {
			$cur     = Settings::all();
			$changed = array();
			foreach ( Blueprint::hero_defaults() as $k => $v ) {
				if ( '' === (string) ( $cur[ $k ] ?? '' ) ) {
					$cur[ $k ] = $v;
					$changed[] = $k;
				}
			}
			if ( $changed ) {
				Settings::save( $cur );
				$add( 'settings', 'Homepage text', 'applied', 'Filled the empty setting(s): ' . implode( ', ', $changed ) . '. Existing values were kept.' );
			} else {
				$add( 'settings', 'Homepage text', 'existing', 'The homepage text is already set and was kept.' );
			}
		}

		$state                 = self::state();
		$state['activated_at'] = gmdate( 'c' );
		self::save_state( $state );
		self::log( 'info', 'Activate run: ' . count( $items ) . ' result line(s).' );

		return array(
			'stage' => 'activate',
			'items' => $items,
		);
	}

	/**
	 * Apply the front page and posts page choices.
	 *
	 * @param array<string,mixed> $opts Options.
	 * @param callable            $add  Result recorder.
	 */
	private static function apply_reading( array $opts, callable $add ): void {
		$choices = array(
			'front_page' => array( 'home', 'page_on_front', 'Home page as front page', 'replace_front_page' ),
			'posts_page' => array( 'insights', 'page_for_posts', 'Insights as posts page', 'replace_posts_page' ),
		);
		foreach ( $choices as $opt => $c ) {
			if ( empty( $opts[ $opt ] ) ) {
				continue;
			}
			$page = self::page_for( $c[0] );
			if ( ! $page || 'publish' !== $page->post_status ) {
				$add( 'settings', $c[2], 'blocked', 'The page must be published first.' );
				continue;
			}
			$current = (int) get_option( $c[1], 0 );
			if ( $current === $page->ID ) {
				$add( 'settings', $c[2], 'existing', 'Already set.' );
				continue;
			}
			if ( 'front_page' === $opt && 'page' === get_option( 'show_on_front' ) && $current && empty( $opts[ $c[3] ] ) ) {
				$add( 'settings', $c[2], 'conflict', 'A different static front page is set. It was kept. Choose "replace" to switch.' );
				continue;
			}
			if ( 'posts_page' === $opt && $current && empty( $opts[ $c[3] ] ) ) {
				$add( 'settings', $c[2], 'conflict', 'A different posts page is set. It was kept. Choose "replace" to switch.' );
				continue;
			}
			if ( 'front_page' === $opt ) {
				update_option( 'show_on_front', 'page' );
			}
			update_option( $c[1], $page->ID );
			$add( 'settings', $c[2], 'applied', 'Changed at your request.', $page->ID );
		}
	}

	/**
	 * Add one menu entry. Idempotent through the stable key.
	 *
	 * @param int    $menu_id  Menu term ID.
	 * @param string $menu_key Menu key.
	 * @param string $dest     Destination.
	 */
	private static function add_menu_item( int $menu_id, string $menu_key, string $dest ): bool {
		$args = array(
			'menu-item-status'   => 'publish',
			'menu-item-title'    => self::dest_label( $dest ),
			'menu-item-position' => 0,
		);
		if ( 'home' === $dest ) {
			$args['menu-item-type'] = 'custom';
			$args['menu-item-url']  = home_url( '/' );
		} elseif ( 0 === strpos( $dest, 'page:' ) ) {
			$page = self::page_for( substr( $dest, 5 ) );
			if ( ! $page ) {
				return false;
			}
			$args['menu-item-type']      = 'post_type';
			$args['menu-item-object']    = 'page';
			$args['menu-item-object-id'] = $page->ID;
		} else {
			$a = Blueprint::archives()[ substr( $dest, 8 ) ] ?? null;
			if ( ! $a ) {
				return false;
			}
			$args['menu-item-type']   = 'post_type_archive';
			$args['menu-item-object'] = $a['type'];
			$args['menu-item-url']    = (string) get_post_type_archive_link( $a['type'] );
		}
		$order                      = array_search( $dest, (array) ( Blueprint::menus()[ $menu_key ]['items'] ?? array() ), true );
		$args['menu-item-position'] = false === $order ? 0 : (int) $order + 1;
		$id                         = wp_update_nav_menu_item( $menu_id, 0, $args );
		if ( is_wp_error( $id ) || ! $id ) {
			self::log( 'error', 'Menu entry ' . $menu_key . '|' . $dest . ' failed.' );
			return false;
		}
		add_post_meta( (int) $id, self::META, 'item:' . $menu_key . '|' . $dest, true );
		return true;
	}

	/**
	 * How many of a menu's blueprint entries exist.
	 *
	 * @param string   $menu_key Menu key.
	 * @param string[] $dests    Destinations.
	 */
	private static function count_owned_items( string $menu_key, array $dests ): int {
		$n = 0;
		foreach ( $dests as $d ) {
			if ( self::owned_item( $menu_key, $d ) ) {
				++$n;
			}
		}
		return $n;
	}
}
