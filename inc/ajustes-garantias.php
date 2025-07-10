<?php
// Archivo incluido desde el plugin principal

add_action('admin_menu', function() {
    add_submenu_page(
        'woocommerce',
        'Ajustes de Garantías',
        'Garantías',
        'manage_woocommerce',
        'ajustes-garantias',
        function() {
            ?>
            <div class="wrap">
                <h1>Ajustes de Garantías</h1>
                <form method="post" action="options.php">
                    <?php
                        settings_fields('garantias_opciones');
                        do_settings_sections('garantias_opciones');
                        ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Duración de la garantía (días)</th>
                            <td>
                                <input type="number" name="duracion_garantia" min="1"
                                       value="<?php echo esc_attr(get_option('duracion_garantia', 180)); ?>" class="small-text" /> días
                                <p class="description">Solo podrán reclamar productos comprados dentro de este plazo.</p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(); ?>
                </form>
            </div>
        <?php
        }
    );
});

add_action('admin_init', function() {
    register_setting('garantias_opciones', 'duracion_garantia', [
        'type' => 'integer',
        'sanitize_callback' => 'absint',
        'default' => 180,
    ]);
});