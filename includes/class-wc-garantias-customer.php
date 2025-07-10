<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Garantias_Customer {
   public static function init() {
    add_action( 'init', [ __CLASS__, 'add_garantias_endpoint' ] );
    add_filter( 'woocommerce_account_menu_items', [ __CLASS__, 'menu_garantias' ] );
    // Volvemos a la acción original:
    add_action( 'woocommerce_account_garantias_endpoint', [ __CLASS__, 'contenido_garantias_cliente' ] );
}

    public static function add_garantias_endpoint() {
        add_rewrite_endpoint( 'garantias', EP_ROOT | EP_PAGES );
    }

    public static function menu_garantias( $items ) {
        $logout = $items['customer-logout'];
        unset( $items['customer-logout'] );
        $items['garantias'] = 'Mis Garantías';
        $items['customer-logout'] = $logout;
        return $items;
    }

    public static function contenido_garantias_cliente() {
    include WC_GARANTIAS_PATH . 'templates/myaccount-garantias.php';
}
}

WC_Garantias_Customer::init();