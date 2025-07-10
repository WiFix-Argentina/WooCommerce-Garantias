<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Garantias_Init {
    public static function init() {
        add_action( 'init', [ __CLASS__, 'register_garantia_cpt' ] );
        add_action( 'publish_garantia', [ __CLASS__, 'notificar_nueva_garantia' ], 10, 2 );
    }

    public static function register_garantia_cpt() {
        $labels = [
            'name' => 'Garantías',
            'singular_name' => 'Garantía',
            'add_new' => 'Añadir Nueva',
            'add_new_item' => 'Añadir Nueva Garantía',
            'edit_item' => 'Editar Garantía',
            'new_item' => 'Nueva Garantía',
            'view_item' => 'Ver Garantía',
            'search_items' => 'Buscar Garantías',
            'not_found' => 'No se encontraron garantías',
            'not_found_in_trash' => 'No se encontraron garantías en la papelera',
        ];

        $args = [
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false, // Ocultamos el menú CPT, usamos el nuestro
            'capability_type' => 'post',
            'supports' => [ 'title' ],
            'has_archive' => false,
        ];

        register_post_type( 'garantia', $args );
    }

    // Notificación por email al admin cuando se crea una nueva garantía
    public static function notificar_nueva_garantia( $ID, $post ) {
        if ( get_post_meta( $ID, '_notificado', true ) ) return;
        $admin_email = get_option( 'admin_email' );
        $cliente = get_post_meta( $ID, '_cliente', true );
        $producto = get_post_meta( $ID, '_producto', true );

        $subject = 'Nueva garantía registrada';
        $message = "Se ha registrado una nueva garantía.\n\nCliente: $cliente\nProducto: $producto\nID: $ID";
        wp_mail( $admin_email, $subject, $message );

        update_post_meta( $ID, '_notificado', 1 );
    }
}