<?php
// Hook para añadir el campo motivo de rechazo al metabox de garantías
add_action( 'add_meta_boxes', function() {
    add_meta_box(
        'wc_garantia_motivo_rechazo',
        'Motivo del rechazo',
        function($post) {
            $estado = get_post_meta($post->ID, '_estado', true);
            $motivo = get_post_meta($post->ID, '_motivo_rechazo', true);
            // Solo mostrar si el estado es rechazado
            $style = ($estado === 'rechazado') ? '' : 'display:none;';
            ?>
            <div id="motivo-rechazo-box" style="<?php echo $style; ?>">
                <label for="motivo_rechazo"><strong>Motivo del rechazo:</strong></label>
                <textarea style="width:100%;" rows="3" name="motivo_rechazo" id="motivo_rechazo"><?php echo esc_textarea($motivo); ?></textarea>
            </div>
            <script>
            (function($){
                $(document).ready(function() {
                    function toggleMotivo() {
                        if ($('#_estado').val() === 'rechazado') {
                            $('#motivo-rechazo-box').show();
                        } else {
                            $('#motivo-rechazo-box').hide();
                        }
                    }
                    toggleMotivo();
                    $('#_estado').on('change', toggleMotivo);
                });
            })(jQuery);
            </script>
            <?php
        },
        'garantia',
        'side',
        'default'
    );
});

// Guardar el motivo de rechazo
add_action( 'save_post_garantia', function($post_id) {
    if ( isset($_POST['motivo_rechazo']) ) {
        $estado = isset($_POST['_estado']) ? $_POST['_estado'] : get_post_meta($post_id, '_estado', true);
        $motivo = trim($_POST['motivo_rechazo']);
        if ($estado === 'rechazado') {
            if (empty($motivo)) {
                // Impide guardar en vacío
                add_filter('redirect_post_location', function( $location ) {
                    return add_query_arg('error_motivo_rechazo', 1, $location);
                });
            } else {
                update_post_meta( $post_id, '_motivo_rechazo', $motivo );
            }
        } else {
            delete_post_meta( $post_id, '_motivo_rechazo' );
        }
    }
}, 10, 1 );

// Mostrar error si falta motivo
add_action('admin_notices', function() {
    if ( isset($_GET['error_motivo_rechazo']) ) {
        echo '<div class="notice notice-error"><p>Debes ingresar un motivo de rechazo para rechazar la garantía.</p></div>';
    }
});