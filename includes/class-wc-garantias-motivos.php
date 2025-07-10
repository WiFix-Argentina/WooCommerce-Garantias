<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WC_Garantias_Motivos {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);
        add_action('admin_init', [__CLASS__, 'save_settings']);
    }

    public static function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            'Motivos de Garantía',
            'Motivos Garantía',
            'manage_woocommerce',
            'garantias-motivos',
            [__CLASS__, 'render_admin_page']
        );
    }

    public static function render_admin_page() {
        ?>
        <div class="wrap">
            <h1>Motivos de Garantía</h1>
            <form method="post" action="">
                <?php wp_nonce_field('guardar_motivos_garantia', 'motivos_garantia_nonce'); ?>
                <textarea name="motivos_garantia" rows="10" style="width: 100%;"><?php
                    echo esc_textarea( get_option('motivos_garantia', "Producto defectuoso\nFalla técnica\nFaltan piezas\nOtro") );
                ?></textarea>
                <p class="description">Escribe un motivo por línea. Estos motivos aparecerán en el formulario que ve el cliente.</p>
                <p>
                    <button class="button button-primary" type="submit" name="guardar_motivos_garantia">Guardar Motivos</button>
                </p>
            </form>
        </div>
        <?php
    }

    public static function save_settings() {
        if (
            isset($_POST['guardar_motivos_garantia']) &&
            isset($_POST['motivos_garantia_nonce']) &&
            wp_verify_nonce($_POST['motivos_garantia_nonce'], 'guardar_motivos_garantia')
        ) {
            update_option('motivos_garantia', sanitize_textarea_field($_POST['motivos_garantia']));
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>Motivos guardados correctamente.</p></div>';
            });
        }
    }
}

WC_Garantias_Motivos::init();