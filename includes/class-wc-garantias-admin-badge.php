<?php
// Badge de notificación para el menú de garantías en el admin

add_filter('add_menu_classes', function($menu) {
    // Contar garantías en estado "nueva"
    $args = [
        'post_type'      => 'garantia',
        'post_status'    => 'publish',
        'meta_query'     => [
            [
                'key'     => '_estado',
                'value'   => 'nueva',
                'compare' => '=',
            ]
        ],
        'fields'         => 'ids',
        'posts_per_page' => -1
    ];
    $query = new WP_Query($args);
    $count = $query->found_posts;

    if ($count > 0) {
        foreach ($menu as $k => $item) {
            // Cambia aquí el slug
            if ($item[2] === 'wc-garantias') {
                // Badge igual al de WP (rojo)
                $menu[$k][0] .= ' <span class="update-plugins count-' . $count . '" style="background:#d63638;color:#fff;border-radius:10px;padding:0 7px;font-size:12px;font-weight:bold;vertical-align:top;margin-left:6px;">' . $count . '</span>';
                break;
            }
        }
    }
    return $menu;
});