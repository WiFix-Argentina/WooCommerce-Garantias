<?php

/*
Plugin Name: WooCommerce Garantías
Description: Gestión avanzada de garantías para WooCommerce con generación de cupones, panel de cliente y administración.
Version: 1.1.0
Author: WiFix Development
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WC_GARANTIAS_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_GARANTIAS_URL', plugin_dir_url( __FILE__ ) );

// ARCHIVOS A INCLUIR
$includes = [
    'includes/class-wc-garantias-init.php',
    'includes/class-wc-garantias-customer.php',
    'includes/class-wc-garantias-admin.php',
    'includes/class-wc-garantias-emails.php',
    'includes/class-wc-garantias-ajax.php',
    'includes/class-wc-garantias-timeline.php',
    'includes/class-wc-garantias-integrations.php',
    'includes/class-wc-garantias-dashboard.php',
    'includes/class-wc-garantias-motivos.php',
    'includes/class-wc-garantias-admin-badge.php' ,
];

foreach ( $includes as $file ) {
    $filepath = WC_GARANTIAS_PATH . $file;
    if ( file_exists( $filepath ) ) {
        require_once $filepath;
    } else {
        error_log( '[WooGarantias] No se encontró el archivo: ' . $filepath );
    }
}

// Iniciar el plugin principal
if ( class_exists( 'WC_Garantias_Init' ) ) {
    add_action( 'plugins_loaded', array( 'WC_Garantias_Init', 'init' ) );
} else {
    error_log( '[WooGarantias] La clase WC_Garantias_Init no existe.' );
}

// ¡Asegura el panel de garantías SIEMPRE!
if ( class_exists( 'WC_Garantias_Admin' ) ) {
    WC_Garantias_Admin::init();
}

// ¡Asegura el panel de garantías en el dashboard del usuario!
if ( class_exists( 'WC_Garantias_Customer' ) ) {
    WC_Garantias_Customer::init();
}

// ¡Asegura las funcionalidades AJAX!
if ( class_exists( 'WC_Garantias_Ajax' ) ) {
    WC_Garantias_Ajax::init();
}

// Historial de estados de garantías
require_once WC_GARANTIAS_PATH . 'includes/class-wc-garantias-historial.php';

/**
 * Encola el CSS responsive de Garantías (solo en Mi Cuenta).
 */
function wcgarantias_enqueue_responsive_css() {
    if ( ! is_account_page() ) {
        return;
    }

    // Usa las constantes definidas al inicio del plugin
    $css_path = WC_GARANTIAS_PATH . 'assets/css/garantias-responsive.css';
    $css_url  = WC_GARANTIAS_URL  . 'assets/css/garantias-responsive.css';

    if ( file_exists( $css_path ) ) {
        wp_enqueue_style(
            'wc-garantias-responsive',    // handle único
            $css_url,                     // URL pública
            array( 'woodmart-style' ),    // depende del CSS principal de Woodmart
            filemtime( $css_path )        // versión por timestamp
        );
    }
}
add_action( 'wp_enqueue_scripts', 'wcgarantias_enqueue_responsive_css', 100 );

add_action('add_meta_boxes', function() {
    add_meta_box(
        'garantia_items_reclamados',
        'Ítems Reclamados',
        function($post) {
            $items = get_post_meta($post->ID, '_items_reclamados', true);
            if ($items && is_array($items)) {
                echo '<table class="widefat striped"><thead>
                        <tr>
                            <th>Código ítem</th>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Motivo</th>
                            <th>Foto</th>
                            <th>Video</th>
                            <th>N° Orden</th>
                        </tr>
                    </thead><tbody>';
                foreach ($items as $item) {
                    $prod = wc_get_product($item['producto_id']);
                    echo '<tr>';
                    echo '<td>' . esc_html($item['codigo_item']) . '</td>';
                    echo '<td>' . ($prod ? esc_html($prod->get_name()) : 'Producto eliminado') . '</td>';
                    echo '<td>' . esc_html($item['cantidad']) . '</td>';
                    echo '<td>' . esc_html($item['motivo']) . '</td>';
                    echo '<td>';
                    if (!empty($item['foto_url'])) {
                        echo '<a href="' . esc_url($item['foto_url']) . '" target="_blank">Ver foto</a>';
                    }
                    echo '</td>';
                    echo '<td>';
                    if (!empty($item['video_url'])) {
                        echo '<a href="' . esc_url($item['video_url']) . '" target="_blank">Ver video</a>';
                    }
                    echo '</td>';
                    echo '<td>' . esc_html($item['order_id']) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            } else {
                echo "<em>No hay ítems reclamados en este reclamo.</em>";
            }
        },
        'garantia',
        'normal',
        'high'
    );
});

add_filter('manage_garantia_posts_columns', function($columns) {
    $columns['items_count'] = 'Ítems reclamados';
    return $columns;
});

add_action('manage_garantia_posts_custom_column', function($column, $post_id) {
    if ($column === 'items_count') {
        $items = get_post_meta($post_id, '_items_reclamados', true);
        echo is_array($items) ? count($items) : 0;
    }
}, 10, 2);