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
