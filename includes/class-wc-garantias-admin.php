<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WC_Garantias_Admin {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'add_admin_menu' ] );
        add_action( 'admin_notices', [ __CLASS__, 'show_admin_notice' ] );
    }

    public static function add_admin_menu() {
        add_menu_page(
            __( 'Garantías', 'woocommerce-garantias' ),
            __( 'Garantías', 'woocommerce-garantias' ),
            'manage_woocommerce',
            'wc-garantias',
            [ __CLASS__, 'admin_page_content' ],
            'dashicons-shield',
            56
        );
        add_submenu_page(
            'wc-garantias',
            'Motivos de Garantía',
            'Motivos',
            'manage_woocommerce',
            'wc-garantias-motivos',
            [ __CLASS__, 'motivos_page_content' ]
        );
        add_submenu_page(
            'wc-garantias',
            'Configuración',
            'Configuración',
            'manage_woocommerce',
            'wc-garantias-config',
            [ __CLASS__, 'config_page_content' ]
        );
        add_submenu_page(
            null,
            'Ver Garantía',
            'Ver Garantía',
            'manage_woocommerce',
            'wc-garantias-ver',
            [ __CLASS__, 'ver_garantia_page' ]
        );
    }

    public static function show_admin_notice() {
        if (isset($_GET['error_motivo_rechazo'])) {
            echo '<div class="notice notice-error"><p>Debes ingresar un motivo de rechazo para rechazar la garantía.</p></div>';
        }
    }

    public static function admin_page_content() {
        $garantias_ids = [];
        if (!empty($_POST)) {
            $garantias_ids = get_posts([
                'post_type' => 'garantia',
                'post_status' => 'publish',
                'fields' => 'ids',
                'posts_per_page' => 1000,
            ]);
        }
        foreach ( $garantias_ids as $garantia_id ) {
            if (isset($_POST["eliminar_garantia_{$garantia_id}"]) && wp_verify_nonce($_POST["_wpnonce_eliminar_{$garantia_id}"], 'eliminar_garantia_'.$garantia_id)) {
                wp_delete_post($garantia_id, true);
                wp_redirect(admin_url('admin.php?page=wc-garantias'));
                exit;
            }
            if ( isset($_POST["guardar_comentario_{$garantia_id}"]) && wp_verify_nonce($_POST["_wpnonce_coment_{$garantia_id}"], 'guardar_comentario_'.$garantia_id) ) {
                update_post_meta( $garantia_id, '_comentario_interno', sanitize_textarea_field($_POST['comentario']) );
                wp_redirect( admin_url('admin.php?page=wc-garantias') );
                exit;
            }
        }

        $estado_filtro = isset( $_GET['estado'] ) ? sanitize_text_field( $_GET['estado'] ) : '';
        $busqueda_filtro = isset($_GET['busqueda']) ? trim(sanitize_text_field($_GET['busqueda'])) : '';

        $estados = [
            'nueva'              => 'Pendiente de recibir',
            'en_revision'        => 'En revisión',
            'pendiente_envio'    => 'Pendiente de envío',
            'recibido'           => 'Recibido - En análisis',
            'aprobado_cupon'     => 'Aprobado - Cupón Enviado',
            'rechazado'          => 'Rechazado',
            'finalizado_cupon'   => 'Finalizado - Cupón utilizado',
            'finalizado'         => 'Finalizado',
        ];

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Panel de Garantías', 'woocommerce-garantias' ); ?></h1>
            <form method="get">
                <input type="hidden" name="page" value="wc-garantias" />
                <input type="text" name="busqueda" placeholder="Buscar cliente, teléfono, N° orden o código de garantía" value="<?php echo esc_attr( $busqueda_filtro ); ?>" />
                <select name="estado">
                    <option value="">Todos los estados</option>
                    <?php foreach ($estados as $k => $v): ?>
                        <option value="<?php echo esc_attr($k); ?>" <?php selected($estado_filtro,$k); ?>><?php echo esc_html($v); ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="button button-primary" type="submit">Filtrar</button>
                <a class="button" href="<?php echo esc_url( admin_url('admin.php?page=wc-garantias&export=csv') ); ?>">Exportar CSV</a>
            </form>
            <br />
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nombre de cliente</th>
                        <th>Teléfono</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Cantidad de items</th>
                        <th>Comentario interno</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                if ( isset($_GET['export']) && $_GET['export'] === 'csv' ) {
                    self::export_csv();
                    exit;
                }

                $meta_query = [];
                if ( $estado_filtro ) {
                    $meta_query[] = [ 'key'=>'_estado', 'value'=>$estado_filtro ];
                }

                if ($busqueda_filtro) {
                    $user_ids = [];

                    $q1 = new WP_User_Query([
                        'search'         => '*' . $busqueda_filtro . '*',
                        'search_columns' => ['user_login', 'user_email', 'display_name'],
                        'fields'         => 'ID'
                    ]);
                    if (!empty($q1->results)) {
                        $user_ids = array_merge($user_ids, $q1->results);
                    }

                    $q2 = new WP_User_Query([
                        'fields'     => 'ID',
                        'meta_query' => [
                            'relation' => 'OR',
                            [
                                'key'     => 'billing_phone',
                                'value'   => $busqueda_filtro,
                                'compare' => 'LIKE'
                            ],
                            [
                                'key'     => 'phone',
                                'value'   => $busqueda_filtro,
                                'compare' => 'LIKE'
                            ]
                        ]
                    ]);
                    if (!empty($q2->results)) {
                        $user_ids = array_merge($user_ids, $q2->results);
                    }
                    $user_ids = array_unique($user_ids);

                    $meta_or = [
                        'relation' => 'OR',
                        [ 'key' => '_order_id', 'value' => $busqueda_filtro, 'compare' => 'LIKE' ],
                        [ 'key' => '_codigo_unico', 'value' => $busqueda_filtro, 'compare' => 'LIKE' ]
                    ];
                    if (is_numeric($busqueda_filtro)) {
                        $meta_or[] = [ 'key' => '_cliente', 'value' => $busqueda_filtro ];
                    }
                    if (!empty($user_ids)) {
                        $meta_or[] = [
                            'key'     => '_cliente',
                            'value'   => $user_ids,
                            'compare' => 'IN'
                        ];
                    }
                    if (
                        empty($user_ids)
                        && !is_numeric($busqueda_filtro)
                        && !preg_match('/^grt-/i', $busqueda_filtro)
                    ) {
                        $meta_or[] = [ 'key'=>'_cliente', 'value'=>'__no_exist__' ];
                    }
                    $meta_query[] = $meta_or;
                }

                $args = [
                    'post_type'      => 'garantia',
                    'post_status'    => 'publish',
                    'posts_per_page' => 30,
                    'orderby'        => 'date',
                    'order'          => 'DESC',
                    'meta_query'     => $meta_query,
                ];
                $garantias = get_posts($args);
                if ($garantias) {
                    foreach ($garantias as $garantia) {
                        $codigo_unico = get_post_meta($garantia->ID, '_codigo_unico', true);
                        $cliente_id = get_post_meta($garantia->ID, '_cliente', true);
                        $nombre_cliente = 'Usuario eliminado';
                        $telefono = '-';
                        if ($cliente_id) {
                            $user_info = get_userdata($cliente_id);
                            if ($user_info) {
                                $nombre_cliente = $user_info->display_name ?: $user_info->user_login;
                                $telefono = get_user_meta($cliente_id, 'billing_phone', true);
                                if (!$telefono) $telefono = get_user_meta($cliente_id, 'phone', true);
                                if (!$telefono) $telefono = '-';
                            }
                        }
                        $fecha = esc_html(get_the_date('d/m/Y', $garantia));
                        $estado = get_post_meta($garantia->ID, '_estado', true);
                        $items = get_post_meta($garantia->ID, '_items_reclamados', true);
if ( !is_array($items) ) $items = [];
// Suma la cantidad (no las filas)
$cantidad_items = 0;
if (is_array($items) && count($items)) {
    foreach ($items as $item) {
        $cantidad_items += isset($item['cantidad']) ? intval($item['cantidad']) : 1;
    }
} else {
    // Compatibilidad con reclamos viejos
    $cantidad_items = intval(get_post_meta($garantia->ID, '_cantidad', true));
    if (!$cantidad_items) $cantidad_items = 1;
}
                        $comentario = get_post_meta($garantia->ID, '_comentario_interno', true);
                        $ver_url = admin_url('admin.php?page=wc-garantias-ver&garantia_id=' . $garantia->ID);

                        echo '<tr>';
                        echo '<td>' . esc_html($codigo_unico) . '</td>';
                        echo '<td>' . esc_html($nombre_cliente) . '</td>';
                        echo '<td>' . esc_html($telefono) . '</td>';
                        echo '<td>' . $fecha . '</td>';
                        echo '<td>' . esc_html($estados[$estado] ?? $estado) . '</td>';
                        echo '<td>' . esc_html($cantidad_items) . '</td>';
                        echo '<td>';
                        echo '<form method="post" action="">';
                        wp_nonce_field('guardar_comentario_'.$garantia->ID, '_wpnonce_coment_'.$garantia->ID);
                        echo '<input type="hidden" name="garantia_id" value="'.esc_attr($garantia->ID).'" />';
                        echo '<textarea name="comentario" rows="2" style="width:90%;">'.esc_textarea($comentario).'</textarea><br>';
                        echo '<input type="submit" class="button" value="Guardar" name="guardar_comentario_'.$garantia->ID.'" />';
                        echo '</form>';
                        echo '</td>';
                        echo '<td>
                                <a class="button" href="'.esc_url($ver_url).'">Ver Garantía</a>
                                <form method="post" action="" style="display:inline;" onsubmit="return confirm(\'¿Ests seguro de que deseas eliminar esta garantía? Esta acción no se puede deshacer.\');">
                                    '.wp_nonce_field('eliminar_garantia_'.$garantia->ID, '_wpnonce_eliminar_'.$garantia->ID, true, false).'
                                    <input type="hidden" name="garantia_id" value="'.esc_attr($garantia->ID).'" />
                                    <button type="submit" class="button button-danger" name="eliminar_garantia_'.$garantia->ID.'" style="margin-top:5px;background:#d63638;color:#fff;">Eliminar</button>
                                </form>
                              </td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="8">No se encontraron garantías.</td></tr>';
                }
                ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function motivos_page_content() {
    if (
        isset($_POST['guardar_motivos_garantia']) &&
        isset($_POST['motivos_garantia_nonce']) &&
        wp_verify_nonce($_POST['motivos_garantia_nonce'], 'guardar_motivos_garantia')
    ) {
        update_option('motivos_garantia', sanitize_textarea_field($_POST['motivos_garantia']));
        update_option('motivos_rechazo_garantia', sanitize_textarea_field($_POST['motivos_rechazo_garantia']));
        echo '<div class="notice notice-success is-dismissible"><p>Motivos guardados correctamente.</p></div>';
    }
    ?>
    <div class="wrap">
        <h1>Motivos de Garanta</h1>
        <form method="post" action="">
            <?php wp_nonce_field('guardar_motivos_garantia', 'motivos_garantia_nonce'); ?>
            <h2>Motivos de reclamo (cliente)</h2>
            <textarea name="motivos_garantia" rows="6" style="width: 100%;"><?php
                echo esc_textarea( get_option('motivos_garantia', "Producto defectuoso\nFalla técnica\nFaltan piezas\nOtro") );
            ?></textarea>
            <p class="description">Escribe un motivo por línea. Estos motivos los ve el cliente al reclamar una garantía.</p>
            <h2 style="margin-top:32px;">Motivos de rechazo (admin)</h2>
            <textarea name="motivos_rechazo_garantia" rows="6" style="width: 100%;"><?php
                echo esc_textarea( get_option('motivos_rechazo_garantia', "Fuera de plazo\nProducto dañado\nNo corresponde a la compra\nOtro") );
            ?></textarea>
            <p class="description">Escribe un motivo por lnea. Estos motivos aparecen al rechazar una garantía.</p>
            <p>
                <button class="button button-primary" type="submit" name="guardar_motivos_garantia">Guardar Motivos</button>
            </p>
        </form>
    </div>
    <?php
}

    public static function config_page_content() {
    if (
        isset($_POST['guardar_config_garantias']) &&
        check_admin_referer('guardar_config_garantias')
    ) {
        $nuevo_email = sanitize_email($_POST['admin_email_garantias']);
        if (is_email($nuevo_email)) {
            update_option('admin_email_garantias', $nuevo_email);
        }
        update_option('garantia_mail_aprobado_asunto', sanitize_text_field($_POST['garantia_mail_aprobado_asunto']));
        update_option('duracion_garantia', intval($_POST['duracion_garantia']));
        update_option('garantia_mail_aprobado_cuerpo', sanitize_textarea_field($_POST['garantia_mail_aprobado_cuerpo']));
        update_option('garantia_mail_rechazado_asunto', sanitize_text_field($_POST['garantia_mail_rechazado_asunto']));
        update_option('garantia_mail_rechazado_cuerpo', sanitize_textarea_field($_POST['garantia_mail_rechazado_cuerpo']));
        update_option('garantia_mail_postrechazo_asunto', sanitize_text_field($_POST['garantia_mail_postrechazo_asunto']));
        update_option('garantia_mail_postrechazo_cuerpo', sanitize_textarea_field($_POST['garantia_mail_postrechazo_cuerpo']));
        wp_redirect(admin_url('admin.php?page=wc-garantias-config&config_guardada=1'));
        exit;
    }

    $email_actual = get_option('admin_email_garantias', 'rosariotechsrl@gmail.com');
    $duracion_garantia = get_option('duracion_garantia', 180);
    $aprobado_asunto = get_option('garantia_mail_aprobado_asunto', 'Cupn por Garantía Aprobada');
    $aprobado_cuerpo = get_option('garantia_mail_aprobado_cuerpo', 'Hola {cliente}, tu garanta fue aprobada. Tienes un cupón de ${importe} para tu próxima compra. Código: {cupon}');
    $rechazado_asunto = get_option('garantia_mail_rechazado_asunto', 'Garantía Rechazada');
    $rechazado_cuerpo = get_option('garantia_mail_rechazado_cuerpo', 'Hola {cliente}, tu garantía {codigo} fue rechazada. Motivo: {motivo}');
    $postrechazo_asunto = get_option('garantia_mail_postrechazo_asunto', 'Acción post-rechazo de garantía');
    $postrechazo_cuerpo = get_option('garantia_mail_postrechazo_cuerpo', 'El cliente #{cliente_id} ha seleccionado la opción post-rechazo para la garanta {codigo}. Producto: {producto}. Motivo de rechazo: {motivo}. Acción solicitada: {accion}.');
    ?>
    <div class="wrap">
        <h1>Configuración de Garantías</h1>
        <form method="post">
            <?php wp_nonce_field('guardar_config_garantias'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="admin_email_garantias">Email de notificaciones</label></th>
                    <td>
                        <input type="email" id="admin_email_garantias" name="admin_email_garantias" value="<?php echo esc_attr($email_actual); ?>" style="width:350px;max-width:100%;" required>
                        <p class="description">Este email recibirá notificaciones por acciones post-rechazo.</p>
                    </td>
                </tr>
                <tr>
    <th scope="row"><label for="duracion_garantia">Duración de la garantía (días)</label></th>
    <td>
        <input type="number" id="duracion_garantia" name="duracion_garantia" value="<?php echo esc_attr($duracion_garantia); ?>" min="1" style="width:100px;" required>
        <p class="description">Cantidad de das desde la compra en los que el producto es elegible para reclamo de garantía.</p>
    </td>
</tr>
            </table>
            <h2>Emails automticos</h2>
            <h3>Garanta Aprobada (Cupón)</h3>
            <p>Asunto:<br>
                <input type="text" name="garantia_mail_aprobado_asunto" value="<?php echo esc_attr($aprobado_asunto); ?>" style="width:60%;">
            </p>
            <p>
                Mensaje:<br>
                <textarea name="garantia_mail_aprobado_cuerpo" rows="4" style="width:60%;"><?php echo esc_textarea($aprobado_cuerpo); ?></textarea>
                <br><small>Variables disponibles: {cliente}, {importe}, {cupon}</small>
            </p>
            <h3>Garantía Rechazada</h3>
            <p>Asunto:<br>
                <input type="text" name="garantia_mail_rechazado_asunto" value="<?php echo esc_attr($rechazado_asunto); ?>" style="width:60%;">
            </p>
            <p>
                Mensaje:<br>
                <textarea name="garantia_mail_rechazado_cuerpo" rows="4" style="width:60%;"><?php echo esc_textarea($rechazado_cuerpo); ?></textarea>
                <br><small>Variables disponibles: {cliente}, {codigo}, {motivo}</small>
            </p>
            <h3>Post-Rechazo (para el administrador)</h3>
            <p>Asunto:<br>
                <input type="text" name="garantia_mail_postrechazo_asunto" value="<?php echo esc_attr($postrechazo_asunto); ?>" style="width:60%;">
            </p>
            <p>
                Mensaje:<br>
                <textarea name="garantia_mail_postrechazo_cuerpo" rows="4" style="width:60%;"><?php echo esc_textarea($postrechazo_cuerpo); ?></textarea>
                <br><small>Variables disponibles: {cliente_id}, {codigo}, {producto}, {motivo}, {accion}</small>
            </p>
            <p>
                <button type="submit" class="button button-primary" name="guardar_config_garantias" value="1">Guardar Configuracin</button>
            </p>
        </form>
    </div>
    <?php
}

    public static function ver_garantia_page() {
        if (!isset($_GET['garantia_id'])) {
            echo "<div class='notice notice-error'><p>No se encontró la garantía.</p></div>";
            return;
        }
        $garantia_id = intval($_GET['garantia_id']);
        $garantia = get_post($garantia_id);

        if (!$garantia) {
            echo "<div class='notice notice-error'><p>No se encontró la garanta.</p></div>";
            return;
        }
        // --- NUEVO: Procesar acciones sobre cada item (incluye subida de archivos si es rechazo) ---
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['garantia_id'], $_POST['item_codigo'], $_POST['accion_item']) &&
    intval($_POST['garantia_id']) === $garantia_id
) {
    $item_codigo = sanitize_text_field($_POST['item_codigo']);
    $accion      = sanitize_text_field($_POST['accion_item']);

    // PROCESO ESPECIAL PARA RECHAZO: permite subir foto/video
    $uploads = [];
    if ($accion === 'rechazado') {
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        foreach (['foto_funciona', 'video_funciona'] as $input_name) {
            if (!empty($_FILES[$input_name]['name'])) {
                $uploaded = wp_handle_upload($_FILES[$input_name], ['test_form' => false]);
                if (!isset($uploaded['error'])) {
                    $uploads[$input_name] = $uploaded['url'];
                }
            }
        }
    }

    $items = get_post_meta($garantia_id, '_items_reclamados', true);
    if (is_array($items)) {
    foreach ($items as &$item) {
        if (($item['codigo_item'] ?? '') === $item_codigo) {
            $item['estado'] = $accion;
            // Si es rechazo, guarda las URLs
            if ($accion === 'rechazado') {
                if (isset($uploads['foto_funciona'])) {
                    $item['foto_funciona'] = $uploads['foto_funciona'];
                }
                if (isset($uploads['video_funciona'])) {
                    $item['video_funciona'] = $uploads['video_funciona'];
                }
                // ---------- ENVÍO DE MAIL AL CLIENTE ----------
                $cliente_id = get_post_meta($garantia_id, '_cliente', true);
                $user = get_userdata($cliente_id);
                $user_email = $user ? $user->user_email : '';
                $nombre_cliente = $user ? $user->display_name : '';
                $motivo = $_POST['motivo_rechazo'] ?? '';
                if (empty($motivo) && isset($_POST['otro_motivo'])) {
                    $motivo = $_POST['otro_motivo'];
                }
                $codigo_unico = get_post_meta($garantia_id, '_codigo_unico', true);

                $asunto = get_option('garantia_mail_rechazado_asunto', 'Garantía Rechazada');
                $cuerpo = get_option('garantia_mail_rechazado_cuerpo', 'Hola {cliente}, tu garantía {codigo} fue rechazada. Motivo: {motivo}');
                $cuerpo = str_replace(
                    ['{cliente}', '{codigo}', '{motivo}'],
                    [$nombre_cliente, $codigo_unico . ' - ' . $item_codigo, $motivo],
                    $cuerpo
                );
                if ($user_email) {
                    wp_mail($user_email, $asunto, $cuerpo);
                }
            }
            break;
        }
    }
    update_post_meta($garantia_id, '_items_reclamados', $items);
}
    wp_redirect(admin_url('admin.php?page=wc-garantias-ver&garantia_id=' . $garantia_id));
    exit;
}
// --- FIN NUEVO ---

        $estados = [
            'nueva'              => 'Pendiente de recibir',
            'en_revision'        => 'En revisin',
            'pendiente_envio'    => 'Pendiente de envío',
            'recibido'           => 'Recibido - En anlisis',
            'aprobado_cupon'     => 'Aprobado - Cupón Enviado',
            'rechazado'          => 'Rechazado',
            'finalizado_cupon'   => 'Finalizado - Cupón utilizado',
            'finalizado'         => 'Finalizado',
        ];

        // Procesa cambios de estado y motivo de rechazo
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['garantia_id']) && intval($_POST['garantia_id']) === $garantia_id) {
            if (isset($_POST['recibido'])) {
                update_post_meta($garantia_id, '_estado', 'recibido');
                wp_redirect(admin_url('admin.php?page=wc-garantias-ver&garantia_id=' . $garantia_id));
                exit;
            }
            if (isset($_POST['aceptada'])) {
                update_post_meta($garantia_id, '_estado', 'aprobado_cupon');
                // Aquí va la lgica de cupón si la tienes (copiala aqu)
                if (!get_post_meta($garantia_id, '_cupon_generado', true)) {
                    $cliente_id   = get_post_meta($garantia_id, '_cliente', true);
                    $producto_id  = get_post_meta($garantia_id, '_producto', true);
                    $order_id     = get_post_meta($garantia_id, '_order_id', true);

                    $precio_actual = false;
                    $producto = wc_get_product($producto_id);
                    if ($producto && $producto->exists()) {
                        $precio_actual = floatval($producto->get_price());
                    }
                    $precio_pagado = false;
                    if ($order_id) {
                        $order = wc_get_order($order_id);
                        if ($order) {
                            foreach ($order->get_items() as $item) {
                                if ($item->get_product_id() == $producto_id) {
                                    $precio_pagado = floatval($item->get_total() / $item->get_quantity());
                                    break;
                                }
                            }
                        }
                    }
                    $importe = $precio_actual ? $precio_actual : $precio_pagado;
                    if ($importe && $cliente_id) {
                        $user = get_userdata($cliente_id);
                        $user_email = $user ? $user->user_email : '';
                        if ($user_email) {
                            $codigo_cupon = 'GARANTIA-' . strtoupper(wp_generate_password(8, false, false));
                            $cupon = array(
                                'post_title'   => $codigo_cupon,
                                'post_content' => '',
                                'post_status'  => 'publish',
                                'post_author'  => 1,
                                'post_type'    => 'shop_coupon'
                            );
                            $cupon_id = wp_insert_post($cupon);
                            if ($cupon_id) {
                                update_post_meta($cupon_id, 'discount_type', 'fixed_cart');
                                update_post_meta($cupon_id, 'coupon_amount', $importe);
                                update_post_meta($cupon_id, 'individual_use', 'yes');
                                update_post_meta($cupon_id, 'usage_limit', 1);
                                update_post_meta($cupon_id, 'usage_limit_per_user', 1);
                                update_post_meta($cupon_id, 'customer_email', $user_email);
                                update_post_meta($cupon_id, 'exclude_sale_items', 'yes');
                                update_post_meta($cupon_id, 'customer_user', [$cliente_id]);
                                update_post_meta($garantia_id, '_cupon_generado', $codigo_cupon);
                                update_user_meta($cliente_id, '_cupon_garantia_pendiente', $codigo_cupon);

                                $asunto = get_option('garantia_mail_aprobado_asunto', 'Cupn por Garantía Aprobada');
$cuerpo = get_option('garantia_mail_aprobado_cuerpo', 'Hola {cliente}, tu garantía fue aprobada. Tienes un cupón de ${importe} para tu próxima compra. Código: {cupon}');
$cuerpo = str_replace(
    ['{cliente}', '{importe}', '{cupon}'],
    [$nombre_cliente, number_format($importe,2), $codigo_cupon],
    $cuerpo
);
wp_mail($user_email, $asunto, $cuerpo);
                            }
                        }
                    }
                }
                wp_redirect(admin_url('admin.php?page=wc-garantias-ver&garantia_id=' . $garantia_id));
                exit;
            }
            if (isset($_POST['rechazada'])) {
                $motivo_rechazo = isset($_POST['motivo_rechazo']) ? trim(sanitize_textarea_field($_POST['motivo_rechazo'])) : '';
                if (empty($motivo_rechazo)) {
                    wp_redirect(admin_url('admin.php?page=wc-garantias-ver&garantia_id=' . $garantia_id . '&error_motivo_rechazo=1'));
                    exit;
                } else {
                    update_post_meta($garantia_id, '_estado', 'rechazado');
                    update_post_meta($garantia_id, '_motivo_rechazo', $motivo_rechazo);
                    wp_redirect(admin_url('admin.php?page=wc-garantias-ver&garantia_id=' . $garantia_id));
                    exit;
                }
            }
        }

        $codigo_unico = get_post_meta($garantia_id, '_codigo_unico', true);
        $cliente_id = get_post_meta($garantia_id, '_cliente', true);
        $nombre_cliente = 'Usuario eliminado';
        $telefono = '';
        if ($cliente_id) {
            $user_info = get_userdata($cliente_id);
            if ($user_info) {
                $nombre_cliente = $user_info->display_name ?: $user_info->user_login;
                $telefono = get_user_meta($cliente_id, 'billing_phone', true);
                if (!$telefono) $telefono = get_user_meta($cliente_id, 'phone', true);
                if (!$telefono) $telefono = '-';
            }
        }
        $fecha = esc_html(get_the_date('d/m/Y', $garantia));
        $estado = get_post_meta($garantia_id, '_estado', true);
        $motivo_rechazo = get_post_meta($garantia_id, '_motivo_rechazo', true);

        // Usar el nuevo meta _items_reclamados, si existe
$items = get_post_meta($garantia_id, '_items_reclamados', true);
if (!is_array($items) || count($items) === 0) {
    // Para compatibilidad con reclamos viejos, usa los campos antiguos
    $producto_id = get_post_meta($garantia_id, '_producto', true);
    $cantidad = get_post_meta($garantia_id, '_cantidad', true);
    $motivo = get_post_meta($garantia_id, '_motivos', true);
    $foto_url = get_post_meta($garantia_id, '_foto_url', true);
    $video_url = get_post_meta($garantia_id, '_video_url', true);
    $order_id = get_post_meta($garantia_id, '_order_id', true);
    $items = [];
    if ($producto_id) {
        $items[] = [
            'producto_id' => $producto_id,
            'cantidad'    => $cantidad ? $cantidad : 1,
            'motivo'      => $motivo,
            'foto_url'    => $foto_url,
            'video_url'   => $video_url,
            'order_id'    => $order_id,
        ];
    }
}

        $cantidad_total_reclamada = 0;
$garantias_cliente = get_posts([
    'post_type' => 'garantia',
    'post_status' => 'publish',
    'meta_query' => [
        ['key' => '_cliente', 'value' => $cliente_id]
    ],
    'posts_per_page' => -1
]);
foreach ($garantias_cliente as $g) {
    $items = get_post_meta($g->ID, '_items_reclamados', true);
    if (is_array($items) && count($items)) {
        foreach ($items as $item) {
            $cantidad_total_reclamada += isset($item['cantidad']) ? intval($item['cantidad']) : 1;
        }
    } else {
        // Compatibilidad con garantas viejas
        $cantidad = get_post_meta($g->ID, '_cantidad', true);
        $cantidad_total_reclamada += intval($cantidad ? $cantidad : 1);
    }
}

        echo "<div class='wrap'>";
        echo "<h1>Garantía: <span style='color:#007cba;'>".esc_html($codigo_unico)."</span></h1>";
        echo "<p><strong>Cliente:</strong> " . esc_html($nombre_cliente) . " <br><strong>Telfono:</strong> " . esc_html($telefono) . "</p>";
        echo "<p><strong>Fecha de reclamo:</strong> " . $fecha . "</p>";
        echo "<p><strong>Estado:</strong> <span style='font-weight:bold;'>" . esc_html($estados[$estado] ?? $estado) . "</span></p>";

        echo "<hr>";
        
        // Recuperar motivos de rechazo configurables (admin)
$motivos_rechazo = explode("\n", get_option('motivos_rechazo_garantia', "Fuera de plazo\nProducto dañado\nNo corresponde a la compra\nOtro"));
$motivos_rechazo = array_filter(array_map('trim', $motivos_rechazo));

// FORMULARIO para seleccin mltiple y tabla de ítems
echo "<form method='post'>";
echo "<input type='hidden' name='garantia_id' value='" . esc_attr($garantia_id) . "'>";
echo "<h2>Items reclamados</h2>";
echo "<table class='widefat'><thead><tr>
    <th><input type='checkbox' id='select_all_items'></th>
    <th>Código</th>
    <th>Producto</th>
    <th>Cantidad</th>
    <th>Motivo</th>
    <th>Foto</th>
    <th>Video</th>
    <th>Prueba funcionamiento</th>
    <th>Fecha de compra</th>
    <th>Nº Orden</th>
    <th>Estado</th>
    <th>Acciones</th>
</tr></thead><tbody>";
if (is_array($items) && count($items)) {
    foreach ($items as $item) {
        $codigo_item = $item['codigo_item'] ?? '';
        $producto_id = $item['producto_id'] ?? '';
        $cantidad    = $item['cantidad'] ?? 1;
        $motivo      = $item['motivo'] ?? '';
        $foto_url    = $item['foto_url'] ?? '';
        $video_url   = $item['video_url'] ?? '';
        $order_id    = $item['order_id'] ?? '';
        $estado_item = $item['estado'] ?? 'Pendiente';
        $foto_funciona = $item['foto_funciona'] ?? '';
        $video_funciona = $item['video_funciona'] ?? '';

        $producto_nombre = $producto_id ? (wc_get_product($producto_id) ? wc_get_product($producto_id)->get_name() : 'Producto eliminado') : '-';
        $order_fecha = '';
        if ($order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $order_fecha = $order->get_date_created() ? $order->get_date_created()->date_i18n('d/m/Y') : '';
            }
        }

        echo "<tr>";
        // Checkbox solo si Pendiente
        echo "<td><input type='checkbox' class='item_checkbox' name='bulk_items[]' value='" . esc_attr($codigo_item) . "'" . ($estado_item !== 'Pendiente' ? ' disabled' : '') . "></td>";
        echo "<td>" . esc_html($codigo_item) . "</td>";
        echo "<td>" . esc_html($producto_nombre) . "</td>";
        echo "<td>" . esc_html($cantidad) . "</td>";
        echo "<td>" . esc_html($motivo) . "</td>";
        // Foto original del reclamo
        echo "<td>";
        if ($foto_url) { echo "<a href='" . esc_url($foto_url) . "' target='_blank'>Ver foto</a>"; }
        echo "</td>";
        // Video original del reclamo
        echo "<td>";
        if ($video_url) { echo "<a href='" . esc_url($video_url) . "' target='_blank'>Ver video</a>"; }
        echo "</td>";
        // Prueba de funcionamiento (nuevo)
        echo "<td>";
        if ($foto_funciona) {
            echo "<a href='" . esc_url($foto_funciona) . "' target='_blank'>Ver foto funcionamiento</a><br>";
        }
        if ($video_funciona) {
            echo "<a href='" . esc_url($video_funciona) . "' target='_blank'>Ver video funcionamiento</a>";
        }
        echo "</td>";
        echo "<td>" . esc_html($order_fecha) . "</td>";
        echo "<td>" . esc_html($order_id) . "</td>";
        echo "<td>" . esc_html($estado_item) . "</td>";
        // ACCIONES (igual que tienes ahora)
        echo "<td>";
        if ($estado_item === 'Pendiente') {
            echo "<form method='post' style='display:inline;'>";
            echo "<input type='hidden' name='garantia_id' value='" . esc_attr($garantia_id) . "'>";
            echo "<input type='hidden' name='item_codigo' value='" . esc_attr($codigo_item) . "'>";
            echo "<button type='submit' name='accion_item' value='recibido' class='button'>Recibir</button>";
            echo "<button type='submit' name='accion_item' value='aceptado' class='button'>Aceptar</button>";
            echo "</form>";
        } elseif ($estado_item === 'recibido' || $estado_item === 'aceptado') {
            echo "<form method='post' style='display:inline;'>";
            echo "<input type='hidden' name='garantia_id' value='" . esc_attr($garantia_id) . "'>";
            echo "<input type='hidden' name='item_codigo' value='" . esc_attr($codigo_item) . "'>";
            echo "<button type='submit' name='accion_item' value='aprobado' class='button button-primary'>Aprobar garantía</button>";
            echo "</form>";
            echo "<button type='button' class='button button-danger btn-rechazar-garantia' data-codigo='".esc_attr($codigo_item)."' style='margin-left:4px;'>Rechazar garantía</button>";
        } elseif ($estado_item === 'aprobado') {
            echo "<span style='color: green; font-weight: bold;'>Aprobado</span>";
        } elseif ($estado_item === 'rechazado') {
            echo "<span style='color: red; font-weight: bold;'>Rechazado</span>";
        } else {
            echo "<span style='color: #007cba; font-weight: bold;'>" . esc_html($estado_item) . "</span>";
        }
        echo "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='12'>No hay items</td></tr>";
}
echo "</tbody></table>";
echo "<div style='margin-top:10px;'>
    <button type='submit' name='bulk_action' value='recibido' class='button' id='bulk_recibir' disabled>Recibir seleccionados</button>
    <button type='submit' name='bulk_action' value='aceptado' class='button' id='bulk_aceptar' disabled>Aceptar seleccionados</button>
</div>";

// == MODAL DE RECHAZO (antes del cierre del form) ==
echo '
<div id="modal-rechazo-garantia" style="display:none;position:fixed;left:0;top:0;width:100vw;height:100vh;z-index:9999;background:rgba(0,0,0,0.4);">
  <div style="background:#fff;padding:22px 18px 18px 18px;max-width:400px;margin:90px auto;border-radius:6px;position:relative;">
    <h3 style="margin-top:0;">Motivo de rechazo</h3>
    <form id="form-rechazo-garantia" method="post" enctype="multipart/form-data">
      <input type="hidden" name="garantia_id" value="'.esc_attr($garantia_id).'" />
      <input type="hidden" name="item_codigo" id="modal_item_codigo" value="" />
      <input type="hidden" name="accion_item" value="rechazado" />
      <label>Selecciona un motivo:</label>
      <select name="motivo_rechazo" id="modal_motivo_rechazo" required style="width:100%;">';
foreach ($motivos_rechazo as $motivo) {
    echo '<option value="'.esc_attr($motivo).'">'.esc_html($motivo).'</option>';
}
echo '  </select>
      <div id="otro_motivo_wrap" style="margin-top:10px;display:none;">
        <input type="text" name="otro_motivo" id="modal_otro_motivo" placeholder="Especifica el motivo" style="width:100%;" />
      </div>
      <div style="margin-top:10px;">
        <label>Adjuntar foto demostrando funcionamiento (opcional):</label>
        <input type="file" name="foto_funciona" accept="image/*" />
      </div>
      <div style="margin-top:10px;">
        <label>Adjuntar video demostrando funcionamiento (opcional):</label>
        <input type="file" name="video_funciona" accept="video/*" />
      </div>
      <div style="margin-top:14px;text-align:right;">
        <button type="button" class="button" id="cancelar_rechazo">Cancelar</button>
        <button type="submit" class="button button-primary">Rechazar</button>
      </div>
    </form>
  </div>
</div>';

echo "</form>";

echo <<<EOT
<script>
document.addEventListener('DOMContentLoaded', function() {
    // JS para bulk actions
    var checkboxes = document.querySelectorAll('.item_checkbox');
    var selectAll = document.getElementById('select_all_items');
    var bulkRecibir = document.getElementById('bulk_recibir');
    var bulkAceptar = document.getElementById('bulk_aceptar');

    function updateBulkButtons() {
        var anyChecked = false;
        checkboxes.forEach(cb => { if(cb.checked) anyChecked = true; });
        bulkRecibir.disabled = !anyChecked;
        bulkAceptar.disabled = !anyChecked;
    }
    checkboxes.forEach(cb => { cb.addEventListener('change', updateBulkButtons); });
    if(selectAll){
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => { cb.checked = selectAll.checked; });
            updateBulkButtons();
        });
    }

    // MODAL DE RECHAZO
    document.querySelectorAll('.btn-rechazar-garantia').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            document.getElementById('modal-rechazo-garantia').style.display = 'block';
            document.getElementById('modal_item_codigo').value = btn.getAttribute('data-codigo');
            document.getElementById('modal_motivo_rechazo').selectedIndex = 0;
            document.getElementById('otro_motivo_wrap').style.display = 'none';
            document.getElementById('modal_otro_motivo').value = '';
        });
    });
    document.getElementById('cancelar_rechazo').onclick = function(){
        document.getElementById('modal-rechazo-garantia').style.display = 'none';
    };
    document.getElementById('modal_motivo_rechazo').addEventListener('change', function(){
        if(this.value.toLowerCase() === 'otro') {
            document.getElementById('otro_motivo_wrap').style.display = 'block';
            document.getElementById('modal_otro_motivo').required = true;
        } else {
            document.getElementById('otro_motivo_wrap').style.display = 'none';
            document.getElementById('modal_otro_motivo').required = false;
        }
    });
    document.getElementById('form-rechazo-garantia').addEventListener('submit', function(e){
        var select = document.getElementById('modal_motivo_rechazo');
        if(select.value.toLowerCase() === 'otro') {
            var otro = document.getElementById('modal_otro_motivo').value.trim();
            if(otro.length > 0) {
                select.value = otro;
            }
        }
    });
});
</script>
EOT;

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['garantia_id'], $_POST['bulk_items'], $_POST['bulk_action']) &&
    intval($_POST['garantia_id']) === $garantia_id
) {
    $bulk_items = array_map('sanitize_text_field', (array)$_POST['bulk_items']);
    $accion     = sanitize_text_field($_POST['bulk_action']);

    $items = get_post_meta($garantia_id, '_items_reclamados', true);
    if (is_array($items)) {
        foreach ($items as &$item) {
            if (in_array($item['codigo_item'] ?? '', $bulk_items, true)) {
                $item['estado'] = $accion;
            }
        }
        update_post_meta($garantia_id, '_items_reclamados', $items);
    }
    wp_redirect(admin_url('admin.php?page=wc-garantias-ver&garantia_id=' . $garantia_id));
    exit;
}

echo "<p><strong>Total de items reclamados por este cliente:</strong> " . intval($cantidad_total_reclamada) . "</p>";

// AGREGAR: calcular y mostrar % de reclamo
// 1. Obtener todos los pedidos completados del cliente
$orders = wc_get_orders([
    'customer_id' => $cliente_id,
    'status'      => 'completed',
    'limit'       => -1,
]);
$total_items_comprados = 0;
foreach ($orders as $order) {
    foreach ($order->get_items() as $item) {
        $total_items_comprados += $item->get_quantity();
    }
}

// 2. Calcular el porcentaje
if ($total_items_comprados > 0) {
    $porcentaje = ($cantidad_total_reclamada / $total_items_comprados) * 100;
    $porcentaje = number_format($porcentaje, 2, ',', '.');
    echo "<p><strong>Tasa de reclamo:</strong> $porcentaje%</p>";
} else {
    echo "<p><strong>Tasa de reclamo:</strong> N/A</p>";
}

$hay_item_para_aprobar = false;
if (is_array($items) && count($items)) {
    foreach ($items as $item) {
        if (
            isset($item['estado']) &&
            ($item['estado'] === 'recibido' || $item['estado'] === 'aceptado')
        ) {
            $hay_item_para_aprobar = true;
            break;
        }
    }
}

        echo "<p style='margin-top:2em;'><a class='button' href='" . esc_url(admin_url('admin.php?page=wc-garantias')) . "'>&larr; Volver al listado</a></p>";
        echo "</div>";
    }

    public static function export_csv() {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment;filename=garantias.csv');
        $garantias = get_posts( [
            'post_type'=>'garantia',
            'post_status'=>'publish',
            'posts_per_page'=>-1
        ] );
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Código','Nombre de cliente','Teléfono','Fecha','Estado','Cantidad de items','Comentario']);
        foreach ($garantias as $garantia) {
            $codigo_unico = get_post_meta($garantia->ID, '_codigo_unico', true);
            $cliente_id = get_post_meta($garantia->ID, '_cliente', true);
            $nombre_cliente = 'Usuario eliminado';
            $telefono = '-';
            if ($cliente_id) {
                $user_info = get_userdata($cliente_id);
                if ($user_info) {
                    $nombre_cliente = $user_info->display_name ?: $user_info->user_login;
                    $telefono = get_user_meta($cliente_id, 'billing_phone', true);
                    if (!$telefono) $telefono = get_user_meta($cliente_id, 'phone', true);
                    if (!$telefono) $telefono = '-';
                }
            }
            $fecha = get_the_date('d/m/Y', $garantia);
            $estado = get_post_meta($garantia->ID, '_estado', true);
            $cantidad_items = get_post_meta($garantia->ID, '_cantidad', true);
            if (!$cantidad_items) $cantidad_items = 1;
            $comentario = get_post_meta($garantia->ID, '_comentario_interno', true);
            fputcsv($out, [
                $codigo_unico,
                $nombre_cliente,
                $telefono,
                $fecha,
                $estado,
                $cantidad_items,
                $comentario
            ]);
        }
        fclose($out);
    }
}

// Aplica el cupn de garantía automáticamente al carrito y checkout del usuario (solo si no fue usado)
foreach(['woocommerce_before_cart', 'woocommerce_before_checkout_form'] as $hook) {
    add_action($hook, function() {
        if ( is_user_logged_in() ) {
            $user_id = get_current_user_id();
            $cupon = get_user_meta($user_id, '_cupon_garantia_pendiente', true);
            if ($cupon && !WC()->cart->has_discount($cupon)) {
                WC()->cart->add_discount($cupon);
            }
        }
    });
}
?>