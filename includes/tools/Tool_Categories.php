<?php
namespace WP_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Tool_Categories {
    public static function call( $params, $user ) {
        if ( ! taxonomy_exists( 'product_cat' ) ) {
            return array( 'error' => 'woocommerce_missing' );
        }

        $query = sanitize_text_field( $params['query'] ?? '' );
        $parent_id = isset( $params['parent_id'] ) ? intval( $params['parent_id'] ) : 0;
        $per_page = isset( $params['per_page'] ) ? intval( $params['per_page'] ) : 20;

        $args = array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'parent' => $parent_id,
            'number' => $per_page,
        );
        if ( $query !== '' ) {
            $args['search'] = $query;
        }

        $terms = get_terms( $args );
        $results = array();
        foreach ( $terms as $t ) {
            $results[] = array(
                'id' => $t->term_id,
                'name' => $t->name,
                'slug' => $t->slug,
                'count' => $t->count,
                'url' => get_term_link( $t ),
                'parent_id' => $t->parent,
            );
        }

        return array( 'results' => $results );
    }
}
