<?php
/**
 * WP-CLI Commands for Lean SEO
 *
 * @package Lean_SEO
 * @since 1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Lean_SEO_CLI {

	/**
	 * Batch-generate meta descriptions for posts missing _lean_seo_description.
	 *
	 * Extracts the first meaningful sentence from post content that directly
	 * addresses the post title, capped at 155 characters. Only processes
	 * published posts that have no custom meta description set.
	 *
	 * ## OPTIONS
	 *
	 * [--batch-size=<number>]
	 * : Number of posts to process per batch.
	 * ---
	 * default: 50
	 * ---
	 *
	 * [--limit=<number>]
	 * : Maximum total posts to process. 0 = all.
	 * ---
	 * default: 0
	 * ---
	 *
	 * [--post-type=<type>]
	 * : Post type to process.
	 * ---
	 * default: post
	 * ---
	 *
	 * [--dry-run]
	 * : Preview descriptions without saving.
	 *
	 * ## EXAMPLES
	 *
	 *     # Preview descriptions for first 10 posts
	 *     wp lean-seo generate-descriptions --dry-run --limit=10
	 *
	 *     # Generate all missing descriptions in batches of 100
	 *     wp lean-seo generate-descriptions --batch-size=100
	 *
	 *     # Process only pages
	 *     wp lean-seo generate-descriptions --post-type=page
	 *
	 * @subcommand generate-descriptions
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function generate_descriptions( $args, $assoc_args ) {
		$batch_size = (int) ( $assoc_args['batch-size'] ?? 50 );
		$limit      = (int) ( $assoc_args['limit'] ?? 0 );
		$post_type  = $assoc_args['post-type'] ?? 'post';
		$dry_run    = isset( $assoc_args['dry-run'] );

		if ( ! post_type_exists( $post_type ) ) {
			WP_CLI::error( sprintf( 'Post type "%s" does not exist.', $post_type ) );
		}

		if ( $batch_size < 1 ) {
			$batch_size = 50;
		}

		if ( $dry_run ) {
			WP_CLI::log( '🔍 DRY RUN — no changes will be saved.' );
		}

		// Count posts missing descriptions.
		$total_missing = $this->count_missing( $post_type );
		WP_CLI::log( sprintf( 'Found %d published %s(s) missing meta descriptions.', $total_missing, $post_type ) );

		if ( 0 === $total_missing ) {
			WP_CLI::success( 'All posts already have meta descriptions.' );
			return;
		}

		$to_process = $limit > 0 ? min( $limit, $total_missing ) : $total_missing;
		WP_CLI::log( sprintf( 'Processing %d posts in batches of %d...', $to_process, $batch_size ) );

		$offset    = 0;
		$processed = 0;
		$saved     = 0;
		$skipped   = 0;

		$progress = \WP_CLI\Utils\make_progress_bar( 'Generating descriptions', $to_process );

		while ( $processed < $to_process ) {
			$current_batch = min( $batch_size, $to_process - $processed );

			$posts = get_posts( array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => $current_batch,
				'offset'         => $offset,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Selecting posts by the absence of a meta key is the whole point of this command; it runs once from WP-CLI, in batches, not on a page load.
				'meta_query'     => Lean_SEO_Post_Seo::missing_meta_query( 'description' ),
				'fields'                 => 'all',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			) );

			if ( empty( $posts ) ) {
				break;
			}

			foreach ( $posts as $post ) {
				$description = $this->extract_description( $post );

				if ( empty( $description ) ) {
					$skipped++;
					$processed++;
					$progress->tick();
					WP_CLI::debug( sprintf( '[SKIP] #%d "%s" — could not extract description', $post->ID, $post->post_title ) );
					continue;
				}

				if ( $dry_run ) {
					WP_CLI::log( sprintf(
						"\n  #%d: %s\n  → %s (%d chars)",
						$post->ID,
						$post->post_title,
						$description,
						mb_strlen( $description )
					) );
				} else {
					Lean_SEO_Post_Seo::save( $post, array( 'description' => $description ) );
				}

				$saved++;
				$processed++;
				$progress->tick();
			}

			// Advance the cursor. Posts that received a description drop out
			// of the meta_query result set on the next pass, so only the ones
			// left untouched still need to be skipped over: that is $skipped
			// in a real run, and every processed post in a dry run (where
			// nothing is written back).
			$offset = $dry_run ? $processed : $skipped;

			// Free memory between batches.
			$this->stop_the_insanity();
		}

		$progress->finish();

		WP_CLI::log( '' );
		if ( $dry_run ) {
			WP_CLI::success( sprintf(
				'DRY RUN complete. %d descriptions would be saved, %d skipped.',
				$saved,
				$skipped
			) );
		} else {
			WP_CLI::success( sprintf(
				'Done! %d descriptions saved, %d skipped.',
				$saved,
				$skipped
			) );
		}
	}

	/**
	 * Import titles, descriptions and site identity from another SEO plugin.
	 *
	 * Reads the other plugin's data without changing it. Template variables
	 * (%%title%%, %sitename%, #post_title, …) are resolved against each post;
	 * a value with a variable that cannot be resolved is skipped, and so is a
	 * title that equals what Lean SEO outputs anyway.
	 *
	 * ## OPTIONS
	 *
	 * --from=<plugin>
	 * : Plugin to import from.
	 * ---
	 * options:
	 *   - yoast
	 *   - rankmath
	 *   - aioseo
	 * ---
	 *
	 * [--post-type=<types>]
	 * : Comma-separated post types. Default: every type with the SEO meta box.
	 *
	 * [--identity]
	 * : Also import the site identity: Person or Organization, name, logo and social profiles.
	 *
	 * [--overwrite]
	 * : Replace values Lean SEO already has. By default they are kept.
	 *
	 * [--dry-run]
	 * : Show what would be imported without saving.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lean-seo import --from=yoast --dry-run
	 *     wp lean-seo import --from=rankmath --identity
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function import( $args, $assoc_args ) {
		$source    = $assoc_args['from'];
		$overwrite = isset( $assoc_args['overwrite'] );
		$dry_run   = isset( $assoc_args['dry-run'] );
		$sources   = Lean_SEO_Importer::sources();

		$post_types = isset( $assoc_args['post-type'] )
			? array_filter( array_map( 'trim', explode( ',', $assoc_args['post-type'] ) ) )
			: Lean_SEO_Admin::get_meta_box_post_types();

		foreach ( $post_types as $post_type ) {
			if ( ! post_type_exists( $post_type ) ) {
				WP_CLI::error( sprintf( 'Post type "%s" does not exist.', $post_type ) );
			}
		}

		if ( $dry_run ) {
			WP_CLI::log( 'DRY RUN — nothing will be saved.' );
		}

		$ids = Lean_SEO_Importer::post_ids( $source, $post_types );
		WP_CLI::log( sprintf( 'Found %d posts with %s data.', count( $ids ), $sources[ $source ]['label'] ) );

		$totals = array();
		$rows   = array();

		foreach ( $ids as $post_id ) {
			$result = Lean_SEO_Importer::import_post( $source, $post_id, $overwrite, $dry_run );

			foreach ( $result as $field => $outcome ) {
				$totals[ $field ][ $outcome['status'] ] = ( $totals[ $field ][ $outcome['status'] ] ?? 0 ) + 1;

				if ( 'empty' !== $outcome['status'] ) {
					$rows[] = array(
						'ID'     => $post_id,
						'field'  => $field,
						'status' => $outcome['status'],
						'value'  => $outcome['value'],
					);
				}
			}

			Lean_SEO_Content::forget( $post_id );
		}

		if ( $rows && ( $dry_run || WP_CLI::get_config( 'debug' ) ) ) {
			\WP_CLI\Utils\format_items( 'table', $rows, array( 'ID', 'field', 'status', 'value' ) );
		}

		foreach ( $totals as $field => $statuses ) {
			$parts = array();
			foreach ( $statuses as $status => $count ) {
				$parts[] = sprintf( '%d %s', $count, $status );
			}
			WP_CLI::log( sprintf( '%s: %s', ucfirst( $field ), implode( ', ', $parts ) ) );
		}

		if ( isset( $assoc_args['identity'] ) ) {
			$identity = Lean_SEO_Importer::identity( $source );

			if ( empty( $identity ) ) {
				WP_CLI::log( sprintf( 'Identity: %s has no site identity configured.', $sources[ $source ]['label'] ) );
			} else {
				$written = Lean_SEO_Importer::apply_identity( $identity, $overwrite, $dry_run );
				WP_CLI::log( sprintf( 'Identity: %s', $written ? implode( ', ', $written ) : 'nothing new (already set; use --overwrite to replace)' ) );
			}
		}

		WP_CLI::success( $dry_run ? 'Dry run complete.' : 'Import complete.' );
	}

	/**
	 * Validate the Product schema of every catalog entry.
	 *
	 * Lists each published product with the problems found in its markup:
	 * errors (no Product node at all), warnings (a recommended field is
	 * missing or was left out) and notes.
	 *
	 * ## OPTIONS
	 *
	 * [--post-type=<type>]
	 * : Only this catalog post type. Default: every configured one.
	 *
	 * [--all]
	 * : List complete products too, not only the ones with issues.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp lean-seo validate-products
	 *     wp lean-seo validate-products --post-type=producto --format=csv
	 *
	 * @subcommand validate-products
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function validate_products( $args, $assoc_args ) {
		$post_types = Lean_SEO_Product::post_types();

		if ( empty( $post_types ) ) {
			WP_CLI::error( 'No post type is configured as a product catalog. Choose one under Lean SEO → Settings.' );
		}

		if ( isset( $assoc_args['post-type'] ) ) {
			if ( ! in_array( $assoc_args['post-type'], $post_types, true ) ) {
				WP_CLI::error( sprintf( '"%s" is not configured as a product catalog.', $assoc_args['post-type'] ) );
			}
			$post_types = array( $assoc_args['post-type'] );
		}

		$ids = get_posts( array(
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		$rows   = array();
		$counts = array( 'error' => 0, 'warning' => 0, 'info' => 0, 'ok' => 0 );

		foreach ( $ids as $post_id ) {
			$issues = Lean_SEO_Product::validate( $post_id );
			$status = Lean_SEO_Product_Admin::worst_severity( $issues );
			$counts[ $status ]++;

			Lean_SEO_Content::forget( $post_id );

			if ( 'ok' === $status && ! isset( $assoc_args['all'] ) ) {
				continue;
			}

			$rows[] = array(
				'ID'     => $post_id,
				'title'  => html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' ),
				'status' => $status,
				'issues' => implode( '; ', wp_list_pluck( $issues, 'type' ) ),
			);
		}

		if ( $rows ) {
			\WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $rows, array( 'ID', 'title', 'status', 'issues' ) );
		}

		WP_CLI::success( sprintf(
			'%d products: %d with errors, %d with warnings, %d with notes, %d complete.',
			count( $ids ),
			$counts['error'],
			$counts['warning'],
			$counts['info'],
			$counts['ok']
		) );
	}

	/**
	 * Count published posts missing a description.
	 *
	 * @param string $post_type Post type to count.
	 * @return int
	 */
	private function count_missing( $post_type ) {
		return Lean_SEO_Post_Seo::count_missing( $post_type, 'description' );
	}

	/**
	 * Suggest a meta description for a post.
	 *
	 * Delegates to Lean_SEO_Description so the CLI writes exactly what the
	 * front end would have rendered — before 1.9.0 this command had its own
	 * sentence extractor and the two disagreed.
	 *
	 * @param WP_Post $post The post object.
	 * @return string The generated description, or empty if content is too thin.
	 */
	protected function extract_description( $post ) {
		return Lean_SEO_Description::generate( $post );
	}

	/**
	 * Free memory between batches.
	 *
	 * Clears WP object cache and runs garbage collection.
	 */
	private function stop_the_insanity() {
		global $wpdb, $wp_object_cache;

		$wpdb->queries = array();

		if ( is_object( $wp_object_cache ) ) {
			$wp_object_cache->group_ops      = array();
			$wp_object_cache->stats          = array();
			$wp_object_cache->memcache_debug = array();
			$wp_object_cache->cache          = array();

			if ( method_exists( $wp_object_cache, '__remoteset' ) ) {
				$wp_object_cache->__remoteset();
			}
		}
	}
}
