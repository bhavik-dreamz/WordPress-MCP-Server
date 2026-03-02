<?php
namespace WP_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Tool_CPT {
    /**
     * Search custom post types
     * Params: query, post_type (required), per_page, page, meta_filters
     */
    public static function call( $params, $user ) {
        $query = sanitize_text_field( $params['query'] ?? '' );
        $post_type = sanitize_text_field( $params['post_type'] ?? '' );
        $per_page = isset( $params['per_page'] ) ? intval( $params['per_page'] ) : 10;
        $page = isset( $params['page'] ) ? intval( $params['page'] ) : 1;
        $meta_filters = $params['meta_filters'] ?? null;

        if ( empty( $post_type ) ) {
            return array( 'error' => 'post_type required' );
        }

        $allowed = get_option( 'wp_mcp_allowed_cpts', array() );
        if ( ! empty( $allowed ) && ! in_array( $post_type, (array) $allowed, true ) ) {
            return array( 'error' => 'post_type not allowed' );
        }

        $available = get_post_types( array( 'public' => true ) );
        if ( ! in_array( $post_type, $available, true ) ) {
            return array( 'error' => 'invalid_post_type' );
        }

        $args = array(
            's' => $query,
            'post_type' => $post_type,
            'posts_per_page' => $per_page,
            'paged' => max( 1, $page ),
            'post_status' => 'publish',
        );

        if ( is_array( $meta_filters ) ) {
            $meta_query = array( 'relation' => 'AND' );
            foreach ( $meta_filters as $mf ) {
                if ( empty( $mf['key'] ) ) {
                    continue;
                }
                $meta_query[] = array(
                    'key' => sanitize_text_field( $mf['key'] ),
                    'value' => isset( $mf['value'] ) ? sanitize_text_field( $mf['value'] ) : '',
                    'compare' => '=',
                );
            }
            if ( count( $meta_query ) > 0 ) {
                $args['meta_query'] = $meta_query;
            }
        }

        $wpq = new \WP_Query( $args );
        $results = array();
        foreach ( $wpq->posts as $p ) {
            $meta = get_post_meta( $p->ID );
            $results[] = array(
                'id' => $p->ID,
                'title' => get_the_title( $p ),
                'excerpt' => wp_trim_words( $p->post_excerpt ?: $p->post_content, 55 ),
                'url' => get_permalink( $p ),
                'meta' => $meta,
                'status' => $p->post_status,
            );
        }

        return array( 'results' => $results, 'total' => (int) $wpq->found_posts );
    }
}
