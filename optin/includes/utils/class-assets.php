<?php // phpcs:ignore

namespace OPTN\Includes\Utils;

use WP_Query;

/**
 * Searches and formats stock images for the campaign editor's media picker.
 *
 * Uses the configured image catalog to return labeled, paginated choices that can
 * be inserted into saved campaign content.
 */
class Assets {

	/**
	 * Search the stock image catalog and return one page of editor choices.
	 *
	 * @param string $s     Search phrase used to match image names.
	 * @param int    $page  Zero-based page number to retrieve.
	 * @param int    $limit Maximum number of image records in the result.
	 * @return array<int, array<string, string>> Matching image choices and metadata.
	 */
	public static function get_images( $s, $page, $limit ) {
		$data = array();

		$args = array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'post_status'    => 'inherit',
			'posts_per_page' => $limit,
			'paged'          => $page + 1,
		);

		if ( ! empty( $s ) ) {
			$args['s'] = $s;
		}

		$query = new WP_Query( $args );
		$posts = $query->posts;

		foreach ( $posts as $post ) {
			$image = wp_get_attachment_image_src( $post->ID, 'full' );
			if ( ! $image ) {
				continue;
			}
			// $author     = get_the_author_meta( 'display_name', $post->post_author );
			// $author_url = get_the_author_meta( 'user_url', $post->post_author );
			$data[] = array(
				'id'         => $post->ID,
				'width'      => $image[1],
				'height'     => $image[2],
				'full'       => $image[0],
				'thumb'      => wp_get_attachment_image_url( $post->ID, 'thumb' ),
				'author'     => '',
				'author_url' => '',
			);
		}

		// $provider     = get_bloginfo( 'name' );
		// $provider_url = get_bloginfo( 'url' );

		return array(
			'data'         => array(
				'page'  => $page,
				'limit' => $limit,
				'total' => $query->found_posts,
				'data'  => $data,
			),

			'provider'     => 'own',
			'provider_url' => '',
		);
	}
}
