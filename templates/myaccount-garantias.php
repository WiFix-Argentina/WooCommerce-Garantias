<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Recopilar productos comprados por el cliente, filtrando por duración de garantía
$customer_id = get_current_user_id();

// SI VIENE UN TIMELINE INDIVIDUAL, SOLO MUESTRA ESO Y SALE
if (isset($_POST['ver_timeline_id'])) {
    $garantia_id = intval($_POST['ver_timeline_id']);
    $garantia_para_timeline = get_post($garantia_id);
    if ($garantia_para_timeline && $garantia_para_timeline->post_author == $customer_id) {
        include WC_GARANTIAS_PATH . 'templates/myaccount-garantias-timeline.php';
    } else {
        echo '<div class="woocommerce-error">No se encontró el reclamo o no tienes permiso para verlo.</div>';
    }
    return;
}

echo '<h2>Mis garantías</h2>';

// Dashboard (solo una vez)
include WC_GARANTIAS_PATH . 'templates/myaccount-garantias-dashboard.php';

// ---- DETALLE DE UN RECLAMO ----
if (isset($_POST['ver_detalle_garantia_id'])) {
    $garantia_id = intval($_POST['ver_detalle_garantia_id']);
    $garantia = get_post($garantia_id);
    if ($garantia && $garantia->post_author == $customer_id) {
        $codigo_unico = get_post_meta($garantia_id, '_codigo_unico', true);
        $fecha_raw = get_post_meta($garantia_id, '_fecha', true);
        $estado = get_post_meta($garantia_id, '_estado', true);
        $estados_nombres = [
            'nueva'              => 'Pendiente de recibir',
            'en_revision'        => 'En revisión',
            'pendiente_envio'    => 'Pendiente de envío',
            'recibido'           => 'Recibido - En análisis',
            'aprobado_cupon'     => 'Aprobado - Cupón Enviado',
            'rechazado'          => 'Rechazado',
            'finalizado_cupon'   => 'Finalizado - Cupón utilizado',
            'finalizado'         => 'Finalizado',
        ];
        $fecha = '';
        if ($fecha_raw) {
            $timestamp = strtotime($fecha_raw);
            $fecha = $timestamp ? date('d/m/Y', $timestamp) : '';
        }

        $items = get_post_meta($garantia_id, '_items_reclamados', true);

        echo '<h3>Detalle de reclamo: ' . esc_html($codigo_unico) . '</h3>';
        echo '<p><strong>Fecha:</strong> ' . esc_html($fecha) . ' &nbsp; <strong>Estado:</strong> ' . esc_html($estados_nombres[$estado] ?? $estado) . '</p>';

        if ($items && is_array($items)) {
            echo '<table class="shop_table shop_table_responsive">';
            echo '<thead><tr>
                <th>Código ítem</th>
                <th>Producto</th>
                <th>Cantidad</th>
                <th>Motivo</th>
                <th>Foto</th>
                <th>Video</th>
                <th>N° Orden</th>
                </tr></thead><tbody>';
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
        }
        echo '<p><a href="' . esc_url(wc_get_account_endpoint_url('garantias')) . '" class="button">&larr; Volver al listado</a></p>';
    } else {
        echo '<div class="woocommerce-error">No se encontró el reclamo o no tienes permiso para verlo.</div>';
    }
    return;
}

// ============ LÓGICA PARA FORMULARIO Y ENVÍO DE RECLAMOS ============

// Recopilar productos comprados por el cliente, filtrando por duración de garantía
$duracion_garantia = get_option('duracion_garantia', 180);
$fecha_limite = strtotime("-{$duracion_garantia} days");

// Obtener pedidos completados
$orders = wc_get_orders([
    'customer_id' => $customer_id,
    'status'      => 'completed',
    'limit'       => -1,
]);

$productos = [];
foreach ( $orders as $order ) {
    $order_time = strtotime($order->get_date_completed() ? $order->get_date_completed()->date('Y-m-d H:i:s') : $order->get_date_created()->date('Y-m-d H:i:s'));
    if ($order_time < $fecha_limite) continue;
    foreach ( $order->get_items() as $item ) {
        $pid = $item->get_product_id();
        $qty = $item->get_quantity();
        $productos[ $pid ] = ( $productos[ $pid ] ?? 0 ) + $qty;
    }
}

$productos_js = [];
foreach ( $productos as $pid => $qty ) {
    $prod = wc_get_product( $pid );
    if ( ! $prod ) continue;
    $custom_sku = get_post_meta( $pid, '_alg_ean', true );
    if ( is_array( $custom_sku ) ) {
        $custom_sku = reset( $custom_sku );
    }

    // Calcular cantidad ya reclamada por este producto
    $cantidad_reclamada = 0;
    $args_gar = [
        'post_type'      => 'garantia',
        'post_status'    => 'publish',
        'meta_query'     => [
            ['key' => '_cliente', 'value' => $customer_id],
            ['key' => '_producto', 'value' => $pid],
        ],
        'posts_per_page' => -1
    ];
    $garantias = get_posts($args_gar);
    foreach ($garantias as $gar) {
        $cantidad_reclamada += intval(get_post_meta($gar->ID, '_cantidad', true));
    }

    $qty_disponible = $qty - $cantidad_reclamada;
    if($qty_disponible < 1) continue; // No mostrar si ya no tiene cantidad disponible

    $label = sprintf( '%s — %s (%s disponibles)', $prod->get_name(), $custom_sku, $qty_disponible );
    $productos_js[] = [
        'label' => $label,
        'value' => $pid,
        'maxqty' => $qty_disponible,
    ];
}

$motivos_txt = get_option( 'motivos_garantia', "Producto defectuoso\nFalla técnica\nFaltan piezas\nOtro" );
$motivos = array_filter( array_map( 'trim', explode( "\n", $motivos_txt ) ) );

// Email del admin para notificaciones post-rechazo
$admin_email_garantias = get_option('admin_email_garantias', 'rosariotechsrl@gmail.com');

// --- PROCESAR ENVÍO DEL FORMULARIO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['producto']) && !empty($productos_js) && !isset($_POST['ver_detalle_garantia_id']) && !isset($_POST['ver_timeline_id'])) {
    $user_id      = get_current_user_id();
    $productos_post = $_POST['producto'];
    $cantidades_post = $_POST['cantidad'];
    $motivos_post = $_POST['motivo'];
    $otros_post = $_POST['motivo_otro'];
    $fotos_files = $_FILES['foto'];
    $videos_files = $_FILES['video'];

    // --- PREPARAR ARRAY DE ITEMS ---
    $items_guardar = [];
    foreach($productos_post as $i => $producto_id){
        $producto_id = sanitize_text_field($producto_id);
        $cantidad = max(1, intval($cantidades_post[$i] ?? 1));
        $motivo_sel = isset($motivos_post[$i]) ? $motivos_post[$i] : '';
        $motivo_otro = isset($otros_post[$i]) ? sanitize_text_field($otros_post[$i]) : '';
        if ($motivo_sel === 'Otro' && !empty($motivo_otro)) {
            $motivo_str = 'Otro: ' . $motivo_otro;
        } else {
            $motivo_str = $motivo_sel;
        }

        $foto_url = '';
        if (!empty($fotos_files['name'][$i])) {
            $file = [
                'name'     => $fotos_files['name'][$i],
                'type'     => $fotos_files['type'][$i],
                'tmp_name' => $fotos_files['tmp_name'][$i],
                'error'    => $fotos_files['error'][$i],
                'size'     => $fotos_files['size'][$i]
            ];
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            $uploaded = wp_handle_upload($file, ['test_form' => false]);
            if (empty($uploaded['error'])) $foto_url = $uploaded['url'];
        }

        $video_url = '';
        if (!empty($videos_files['name'][$i])) {
            $file = [
                'name'     => $videos_files['name'][$i],
                'type'     => $videos_files['type'][$i],
                'tmp_name' => $videos_files['tmp_name'][$i],
                'error'    => $videos_files['error'][$i],
                'size'     => $videos_files['size'][$i]
            ];
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            $uploaded = wp_handle_upload($file, ['test_form' => false]);
            if (empty($uploaded['error'])) $video_url = $uploaded['url'];
        }

        // Buscar el order_id más reciente donde el usuario compró ese producto
        $order_id = null;
        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                if ($item->get_product_id() == $producto_id) {
                    $order_id = $order->get_id();
                    break 2;
                }
            }
        }

        $items_guardar[] = [
            'codigo_item'  => 'GRT-ITEM-' . strtoupper(wp_generate_password(8, false, false)),
            'producto_id'  => $producto_id,
            'cantidad'     => $cantidad,
            'motivo'       => $motivo_str,
            'foto_url'     => $foto_url,
            'video_url'    => $video_url,
            'order_id'     => $order_id,
        ];
    }

    // --- CREAR UN SOLO POST DE GARANTIA ---
    $garantia_post = [
        'post_type'   => 'garantia',
        'post_status' => 'publish',
        'post_title'  => 'Garantía - ' . $user_id . ' - ' . date('Y-m-d H:i:s'),
        'post_author' => $user_id,
    ];

    $post_id = wp_insert_post($garantia_post);

    if ($post_id && !is_wp_error($post_id)) {
        $codigo_unico = 'GRT-' . date('Ymd') . '-' . strtoupper(wp_generate_password(5, false, false));
        update_post_meta($post_id, '_codigo_unico', $codigo_unico);
        update_post_meta($post_id, '_cliente', $user_id);
        update_post_meta($post_id, '_fecha', current_time('mysql'));
        update_post_meta($post_id, '_estado', 'nueva');
        update_post_meta($post_id, '_items_reclamados', $items_guardar);
    }

    echo '<div class="woocommerce-message">¡Reclamo enviado correctamente!</div>';
}

// --- ACCIONES post-rechazo ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['garantia_accion_rechazo'], $_POST['garantia_id'])) {
    $garantia_id = intval($_POST['garantia_id']);
    $accion = sanitize_text_field($_POST['garantia_accion_rechazo']);
    $garantia = get_post($garantia_id);

    if (
        $garantia &&
        $garantia->post_type === 'garantia' &&
        $garantia->post_author == $customer_id &&
        get_post_meta($garantia_id, '_estado', true) === 'rechazado'
        && !get_post_meta($garantia_id, '_accion_post_rechazo', true)
        && in_array($accion, ['destruccion', 'reenvio'])
    ) {
        update_post_meta($garantia_id, '_accion_post_rechazo', $accion);

        // Notificar al admin
        $motivo_rechazo = get_post_meta($garantia_id, '_motivo_rechazo', true);
        $codigo_unico = get_post_meta($garantia_id, '_codigo_unico', true);
        $producto_id = get_post_meta($garantia_id, '_producto', true);
        $prod = wc_get_product($producto_id);
        $nombre_producto = $prod ? $prod->get_name() : 'Producto eliminado';

        $mensaje = "El cliente #" . $customer_id . " ha seleccionado la opción post-rechazo para la garantía $codigo_unico\n\n";
        $mensaje .= "Producto: $nombre_producto\n";
        $mensaje .= "Motivo de rechazo: $motivo_rechazo\n";
        $mensaje .= "Acción solicitada: " . ($accion === 'destruccion' ? 'Destrucción de la pieza' : 'Reenvío a depósito');

        wp_mail(
            $admin_email_garantias,
            'Acción post-rechazo de garantía',
            $mensaje
        );

        echo '<div class="woocommerce-message">¡Tu solicitud post-rechazo fue registrada correctamente!</div>';
    }
}

// --- MOSTRAR RECLAMOS ENVIADOS SOLO DESDE POSTS DE GARANTIA ---
$args = [
    'post_type'      => 'garantia',
    'post_status'    => 'publish',
    'meta_query'     => [
        ['key' => '_cliente', 'value' => $customer_id]
    ],
    'posts_per_page' => 100,
    'orderby'        => 'date',
    'order'          => 'DESC'
];
$garantias = get_posts($args);

$estados_nombres = [
    'nueva'              => 'Pendiente de recibir',
    'en_revision'        => 'En revisión',
    'pendiente_envio'    => 'Pendiente de envío',
    'recibido'           => 'Recibido - En anlisis',
    'aprobado_cupon'     => 'Aprobado - Cupón Enviado',
    'rechazado'          => 'Rechazado',
    'finalizado_cupon'   => 'Finalizado - Cupón utilizado',
    'finalizado'         => 'Finalizado',
];

if ($garantias) {
    echo '<h3>Mis reclamos enviados</h3>';
    echo '<table class="shop_table shop_table_responsive" id="tabla-reclamos">';
    echo '<thead><tr>
        <th>Código</th>
        <th>Fecha</th>
        <th>Estado</th>
        <th>Acciones</th>
        </tr></thead><tbody>';
    foreach ($garantias as $garantia) {
        $codigo_unico = get_post_meta($garantia->ID, '_codigo_unico', true);
        $fecha_raw = get_post_meta($garantia->ID, '_fecha', true);
        $estado = get_post_meta($garantia->ID, '_estado', true);

        $fecha = '';
        if ($fecha_raw) {
            $timestamp = strtotime($fecha_raw);
            $fecha = $timestamp ? date('d/m/Y', $timestamp) : '';
        }

        echo '<tr>';
        echo '<td data-label="Código">' . esc_html($codigo_unico) . '</td>';
        echo '<td data-label="Fecha">' . esc_html($fecha) . '</td>';
        echo '<td data-label="Estado">' . esc_html($estados_nombres[$estado] ?? $estado) . '</td>';
        echo '<td>
            <form method="post" style="margin:0;display:inline;">
                <input type="hidden" name="ver_detalle_garantia_id" value="' . esc_attr($garantia->ID) . '" />
                <button type="submit" class="button" name="ver_detalle_garantia_btn">Ver detalles</button>
            </form>
        </td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}
?>

<!-- Carga jQuery UI Autocomplete -->
<link rel="stylesheet" href="//code.jquery.com/ui/1.13.2/themes/ui-lightness/jquery-ui.css">
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

<style>
#garantiaForm p { margin-bottom: 1.5rem; }
#garantiaForm p label { display: block; margin-bottom: .5rem; }
#tabla-reclamos th, #tabla-reclamos td { padding: 8px 5px; vertical-align: middle; }
#tabla-reclamos th { text-align: left; }
#tabla-reclamos input[type="number"] { width: 70px; }
#tabla-reclamos input[type="text"].producto_autocomplete { width: 100%; min-width: 180px; box-sizing: border-box; }
#tabla-reclamos .motivo_otro { width: 95%; }
#tabla-reclamos .remove-row { color: #a00; font-weight: bold; cursor: pointer; }
.add-row-btn { margin: 10px 0 15px 0; }
#tabla-reclamos select.motivo_select {
    width: 99%;
    min-width: 220px;
    font-size: 15px;
    padding: 7px 8px;
    border-radius: 5px;
    border: 1px solid #ddd;
}
.file-upload-wrap {
    position: relative;
    display: inline-block;
}
.file-upload-input {
    opacity: 0;
    position: absolute;
    left: 0; top: 0;
    width: 60px; height: 34px;
    cursor: pointer;
    z-index: 2;
}
.file-upload-label {
    display: inline-block;
    background: #0071a1;
    color: #fff;
    border: none;
    padding: 6px 18px;
    border-radius: 5px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    min-width: 60px;
    text-align: center;
    z-index: 1;
}
.file-upload-label:active,
.file-upload-label:focus {
    background: #015b80;
}
</style>

<!-- FORMULARIO MODERNO DE GARANTÍAS -->
<div id="garantiaFormContainer" style="display: none; margin-top: 30px;">
    <div style="
        background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        border-radius: 15px;
        padding: 30px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        border: 1px solid #e9ecef;
    ">
        <div style="text-align: center; margin-bottom: 25px;">
            <div style="
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                width: 60px;
                height: 60px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 15px;
                font-size: 24px;
            ">📋</div>
            <h3 style="margin: 0; color: #2c3e50; font-weight: 600;">Nuevo Reclamo de Garantía</h3>
            <p style="margin: 5px 0 0 0; color: #6c757d;">Completa la información para procesar tu solicitud</p>
        </div>

        <form id="garantiaForm" method="post" enctype="multipart/form-data">
            <div id="productos-container" style="display: flex; justify-content: center; width: 100%;">
                <div class="producto-card" style="
                    width: 100%; 
                    max-width: 600px;
                    background: white;
                    border-radius: 12px;
                    padding: 25px;
                    margin-bottom: 20px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
                    border: 2px solid #f1f3f4;
                    transition: border-color 0.3s ease;
                " onmouseover="this.style.borderColor='#667eea'" onmouseout="this.style.borderColor='#f1f3f4'">
                    
                    <div style="display: flex; align-items: center; margin-bottom: 20px;">
                        <div style="
                            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
                            width: 40px;
                            height: 40px;
                            border-radius: 50%;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            margin-right: 15px;
                            color: white;
                            font-weight: bold;
                        ">1</div>
                        <h4 style="margin: 0; color: #2c3e50;">Producto a reclamar</h4>
                        <div style="flex-grow: 1;"></div>
                        <button type="button" class="remove-producto" style="
                            background: #dc3545;
                            color: white;
                            border: none;
                            border-radius: 50%;
                            width: 30px;
                            height: 30px;
                            cursor: pointer;
                            display: none;
                            align-items: center;
                            justify-content: center;
                            font-size: 16px;
                        ">×</button>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 120px; gap: 20px; margin-bottom: 20px;">
                        <div>
                            <label style="
                                display: block;
                                margin-bottom: 8px;
                                color: #495057;
                                font-weight: 500;
                                font-size: 14px;
                            "> Buscar producto</label>
                            <div style="position: relative;">
                                <input type="text" 
                                       class="producto_autocomplete" 
                                       placeholder="Escribe el nombre del producto..." 
                                       style="
                                           width: 100%;
                                           padding: 12px 16px;
                                           border: 2px solid #e9ecef;
                                           border-radius: 8px;
                                           font-size: 14px;
                                           transition: border-color 0.3s ease;
                                           box-sizing: border-box;
                                       "
                                       onfocus="this.style.borderColor='#667eea'"
                                       onblur="this.style.borderColor='#e9ecef'">
                                <input type="hidden" class="producto_hidden" name="producto[]">
                            </div>
                        </div>
                        
                        <div>
                            <label style="
                                display: block;
                                margin-bottom: 8px;
                                color: #495057;
                                font-weight: 500;
                                font-size: 14px;
                            ">📦 Cantidad</label>
                            <input type="number" 
       class="cantidad" 
       name="cantidad[]" 
       min="1" 
       value="1" 
       style="
           width: 100%;
           padding: 12px 16px;
           border: 2px solid #e9ecef;
           border-radius: 8px;
           font-size: 14px;
           transition: border-color 0.3s ease;
           box-sizing: border-box;
       "
       onfocus="this.style.borderColor='#667eea'"
       onblur="this.style.borderColor='#e9ecef'">
<div class="maxqty-label" style="color: #6c757d; font-size: 12px; margin-top: 4px; text-align: center; font-weight: 500;"></div>
                        </div>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="
                            display: block;
                            margin-bottom: 8px;
                            color: #495057;
                            font-weight: 500;
                            font-size: 14px;
                        ">⚠️ Motivo del reclamo</label>
                        <select class="motivo_select" 
        name="motivo[]" 
        style="
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
            background: white;
            box-sizing: border-box;
            height: 48px;
            line-height: 1.4;
        "
                                onfocus="this.style.borderColor='#667eea'"
                                onblur="this.style.borderColor='#e9ecef'"
                                required>
                            <option value="">Seleccione un motivo...</option>
                            <?php foreach ($motivos as $m): ?>
                                <option value="<?php echo esc_attr($m); ?>"><?php echo esc_html($m); ?></option>
                            <?php endforeach; ?>
                        </select>
                        
                        <input type="text" 
                               class="motivo_otro" 
                               name="motivo_otro[]" 
                               placeholder="Especifica el motivo..."
                               style="
                                   width: 100%;
                                   padding: 12px 16px;
                                   border: 2px solid #e9ecef;
                                   border-radius: 8px;
                                   font-size: 14px;
                                   margin-top: 10px;
                                   display: none;
                                   transition: border-color 0.3s ease;
                                   box-sizing: border-box;
                               "
                               onfocus="this.style.borderColor='#667eea'"
                               onblur="this.style.borderColor='#e9ecef'">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
    <div>
        <label style="
            display: block;
            margin-bottom: 8px;
            color: #495057;
            font-weight: 500;
            font-size: 14px;
        ">📸 Foto del problema <span style="color: #dc3545;">*</span></label>
        <div class="file-upload-modern" style="
            border: 2px dashed #e9ecef;
            border-radius: 8px;
            padding: 25px 20px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            background: #f8f9fa;
            min-height: 120px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        " onmouseover="this.style.borderColor='#667eea'; this.style.background='#f0f4ff'" 
           onmouseout="this.style.borderColor='#e9ecef'; this.style.background='#f8f9fa'">
            <div style="font-size: 24px; margin-bottom: 8px;">📷</div>
            <div style="font-size: 14px; color: #6c757d; margin-bottom: 8px;">Arrastra una imagen o haz clic</div>
            <input type="file" 
                   name="foto[]" 
                   accept="image/*" 
                   required
                   style="
                       position: absolute;
                       opacity: 0;
                       width: 100%;
                       height: 100%;
                       cursor: pointer;
                   ">
            <small style="color: #6c757d; font-size: 12px;">JPG, PNG (máx. 5MB)</small>
        </div>
    </div>
    
    <div>
        <label style="
            display: block;
            margin-bottom: 8px;
            color: #495057;
            font-weight: 500;
            font-size: 14px;
        ">🎥 Video (opcional)</label>
        <div class="file-upload-modern" style="
            border: 2px dashed #e9ecef;
            border-radius: 8px;
            padding: 25px 20px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            background: #f8f9fa;
            min-height: 120px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        " onmouseover="this.style.borderColor='#28a745'; this.style.background='#f0fff4'" 
           onmouseout="this.style.borderColor='#e9ecef'; this.style.background='#f8f9fa'">
            <div style="font-size: 24px; margin-bottom: 8px;">🎬</div>
            <div style="font-size: 14px; color: #6c757d; margin-bottom: 8px;">Arrastra un video o haz clic</div>
            <input type="file" 
                   name="video[]" 
                   accept="video/*"
                   style="
                       position: absolute;
                       opacity: 0;
                       width: 100%;
                       height: 100%;
                       cursor: pointer;
                   ">
            <small style="color: #6c757d; font-size: 12px;">MP4, MOV (máx. 50MB)</small>
        </div>
    </div>
</div>

            <div style="text-align: center; margin: 25px 0;">
                <button type="button" 
                        id="add-producto-btn" 
                        style="
                            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
                            color: white;
                            border: none;
                            padding: 12px 24px;
                            border-radius: 25px;
                            font-size: 14px;
                            font-weight: 600;
                            cursor: pointer;
                            transition: all 0.3s ease;
                            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
                        "
                        onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(40, 167, 69, 0.4)'"
                        onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(40, 167, 69, 0.3)'">
                    ➕ Agregar otro producto
                </button>
            </div>

            <div style="
                background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
                border-radius: 10px;
                padding: 15px;
                margin: 20px 0;
                border-left: 4px solid #ffc107;
            ">
                <div style="display: flex; align-items: center;">
                    <div style="font-size: 20px; margin-right: 10px;">💡</div>
                    <div>
                        <strong style="color: #856404;">Consejo:</strong>
<span style="color: #856404; font-size: 14px;">
    Asegúrate de que la foto o video muestre claramente el problema. Esto acelera el proceso.
</span>
                    </div>
                </div>
            </div>

            <div style="text-align: center; padding-top: 20px;">
                <button type="submit" 
                        style="
                            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                            color: white;
                            border: none;
                            padding: 15px 40px;
                            border-radius: 25px;
                            font-size: 16px;
                            font-weight: 600;
                            cursor: pointer;
                            transition: all 0.3s ease;
                            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
                            min-width: 200px;
                        "
                        onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(102, 126, 234, 0.5)'"
                        onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(102, 126, 234, 0.4)'">
                    🚀 Enviar Reclamo
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* Animaciones para el formulario moderno */
.producto-card {
    animation: slideIn 0.5s ease-out;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.file-upload-modern {
    position: relative;
}

.file-upload-modern input[type="file"] {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
}

/* Responsive */
@media (max-width: 768px) {
    .producto-card div[style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
        gap: 15px !important;
    }
    
    #garantiaFormContainer > div {
        padding: 20px !important;
        margin: 15px !important;
    }
    
    .producto-card {
        padding: 20px !important;
    }
}
</style>

<script>
// Definir variables AJAX si no existen
if (typeof wcGarantiasAjax === 'undefined') {
    window.wcGarantiasAjax = {
        ajax_url: '<?php echo admin_url("admin-ajax.php"); ?>',
        nonce: '<?php echo wp_create_nonce("wcgarantias_nonce"); ?>'
    };
}

jQuery(function($){
  var productos = <?php echo wp_json_encode($productos_js); ?>;

  // ... todo tu código del autocomplete ...

  // Solo mostrar "quitar" si hay más de una fila
  $('#tabla-reclamos').on('mouseenter mouseleave','tr',function(){
    var $tbody = $('#tabla-reclamos tbody');
    $tbody.find('.remove-row').toggle($tbody.find('tr').length > 1);
  });

  // AGREGAR AQUÍ EL NUEVO CÓDIGO:
  // Mostrar formulario y hacer scroll
  // Inicializar autocomplete del formulario moderno
$('.producto_autocomplete').each(function() {
    const $input = $(this);
    $input.autocomplete({
        minLength: 2,
        source: function(request, response) {
            $.ajax({
                url: wcGarantiasAjax.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'wcgarantias_get_products',
                    term: request.term,
                    nonce: wcGarantiasAjax.nonce
                },
                success: function(data) {
                    response(data.success ? data.data : []);
                },
                error: function() {
                    response([]);
                }
            });
        },
        select: function(event, ui) {
            event.preventDefault();
            $input.val(ui.item.label);
            const $card = $input.closest('.producto-card');
            $card.find('.producto_hidden').val(ui.item.id);
            $card.find('.cantidad').attr('max', ui.item.max_quantity).val(1);
            $card.find('.maxqty-label').text(`MAX: ${ui.item.max_quantity}`);
            
            // Validar cantidad en tiempo real
            $card.find('.cantidad').off('input change').on('input change', function() {
                const valor = parseInt($(this).val()) || 0;
                const maximo = parseInt($(this).attr('max')) || 999;
                
                if (valor > maximo) {
                    $(this).val(maximo);
                    $(this).css('border-color', '#dc3545');
                    setTimeout(() => {
                        $(this).css('border-color', '#e9ecef');
                    }, 1000);
                } else if (valor < 1) {
                    $(this).val(1);
                }
            });
        }
    });
});

// Inicializar funcionalidades del formulario
$('.motivo_select').on('change', function() {
    const $otroInput = $(this).closest('.producto-card').find('.motivo_otro');
    if ($(this).val() === 'Otro') {
        $otroInput.slideDown(200).prop('required', true);
    } else {
        $otroInput.slideUp(200).prop('required', false);
    }
});

// Feedback visual para archivos
$('input[type="file"]').on('change', function() {
    const $container = $(this).closest('.file-upload-modern');
    const fileName = $(this)[0].files[0]?.name;
    
    if (fileName) {
        $container.find('div:nth-child(2)').text(`✅ ${fileName.substring(0, 20)}...`);
        $container.css({
            'border-color': '#28a745',
            'background': '#f0fff4'
        });
    }
});

// Funcionalidad para agregar más productos
$('#add-producto-btn').on('click', function() {
    const $container = $('#productos-container');
    const $firstCard = $container.find('.producto-card:first');
    const $newCard = $firstCard.clone();
    
    // Limpiar todos los datos del producto clonado
    $newCard.find('input[type="text"], input[type="number"], input[type="hidden"]').val('');
    $newCard.find('select').val('');
    $newCard.find('input[type="file"]').val('');
    $newCard.find('.maxqty-label').text('');
    $newCard.find('.motivo_otro').hide().prop('required', false);
    
    // Resetear el estado visual de archivos
    $newCard.find('.file-upload-modern').each(function() {
        $(this).css({
            'border-color': '#e9ecef',
            'background': '#f8f9fa'
        });
        $(this).find('div:nth-child(2)').text(
            $(this).find('input[accept*="image"]').length > 0 ? 
            'Arrastra una imagen o haz clic' : 
            'Arrastra un video o haz clic'
        );
    });
    
    // Actualizar el número del producto
    const numeroProducto = $container.find('.producto-card').length + 1;
    $newCard.find('div:first-child > div:first-child').text(numeroProducto);
    
    // Mostrar botón de eliminar
    $newCard.find('.remove-producto').show();
    
    // Agregar después del último producto
    $newCard.insertAfter($container.find('.producto-card:last'));
    
    // Reinicializar funcionalidades para el nuevo producto
    initializeProductCard($newCard);
    
    // Scroll suave al nuevo producto
    $('html, body').animate({
        scrollTop: $newCard.offset().top - 100
    }, 500);
});

// Función para eliminar productos
$(document).on('click', '.remove-producto', function() {
    const $card = $(this).closest('.producto-card');
    const totalCards = $('#productos-container .producto-card').length;
    
    if (totalCards > 1) {
        $card.slideUp(300, function() {
            $(this).remove();
            // Renumerar los productos restantes
            $('#productos-container .producto-card').each(function(index) {
                $(this).find('div:first-child > div:first-child').text(index + 1);
            });
        });
    }
});

// Función para inicializar funcionalidades de un producto
function initializeProductCard($card) {
    // Autocomplete para el nuevo producto
    const $input = $card.find('.producto_autocomplete');
    
    $input.autocomplete({
        minLength: 2,
        source: function(request, response) {
            $.ajax({
                url: wcGarantiasAjax.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'wcgarantias_get_products',
                    term: request.term,
                    nonce: wcGarantiasAjax.nonce
                },
                success: function(data) {
                    response(data.success ? data.data : []);
                },
                error: function() {
                    response([]);
                }
            });
        },
        select: function(event, ui) {
            event.preventDefault();
            $input.val(ui.item.label);
            $card.find('.producto_hidden').val(ui.item.id);
            $card.find('.cantidad').attr('max', ui.item.max_quantity).val(1);
            $card.find('.maxqty-label').text(`MAX: ${ui.item.max_quantity}`);
            
            // Validar cantidad en tiempo real
            $card.find('.cantidad').off('input change').on('input change', function() {
                const valor = parseInt($(this).val()) || 0;
                const maximo = parseInt($(this).attr('max')) || 999;
                
                if (valor > maximo) {
                    $(this).val(maximo);
                    $(this).css('border-color', '#dc3545');
                    setTimeout(() => {
                        $(this).css('border-color', '#e9ecef');
                    }, 1000);
                } else if (valor < 1) {
                    $(this).val(1);
                }
            });
        }
    });
    
    // Motivo "Otro" para el nuevo producto
    $card.find('.motivo_select').off('change').on('change', function() {
        const $otroInput = $card.find('.motivo_otro');
        if ($(this).val() === 'Otro') {
            $otroInput.slideDown(200).prop('required', true);
        } else {
            $otroInput.slideUp(200).prop('required', false);
        }
    });
    
    // Feedback visual para archivos del nuevo producto
    $card.find('input[type="file"]').off('change').on('change', function() {
        const $container = $(this).closest('.file-upload-modern');
        const fileName = $(this)[0].files[0]?.name;
        
        if (fileName) {
            $container.find('div:nth-child(2)').text(`✅ ${fileName.substring(0, 20)}...`);
            $container.css({
                'border-color': '#28a745',
                'background': '#f0fff4'
            });
        }
    });
}

// Validación del formulario antes de enviar
$('#garantiaForm').on('submit', function(e) {
    // Validar que hay productos seleccionados
    const productosValidos = $('.producto_hidden').filter(function() {
        return $(this).val() !== '';
    }).length;
    
    if (productosValidos === 0) {
        e.preventDefault();
        alert('Debes seleccionar al menos un producto válido');
        return false;
    }
    
    // Validar que hay fotos
    let todosTienenFoto = true;
    $('.producto_hidden').each(function() {
        if ($(this).val() !== '') {
            const $card = $(this).closest('.producto-card');
            const fotoFile = $card.find('input[name="foto[]"]')[0].files[0];
            if (!fotoFile) {
                todosTienenFoto = false;
            }
        }
    });
    
    if (!todosTienenFoto) {
        e.preventDefault();
        alert('Debes subir una foto para cada producto');
        return false;
    }
    
    // Validar motivos
    let todosConMotivo = true;
    $('.producto_hidden').each(function() {
        if ($(this).val() !== '') {
            const $card = $(this).closest('.producto-card');
            const motivo = $card.find('.motivo_select').val();
            if (!motivo) {
                todosConMotivo = false;
            }
        }
    });
    
    if (!todosConMotivo) {
        e.preventDefault();
        alert('Debes seleccionar un motivo para cada producto');
        return false;
    }
});

  $('a[href="#garantiaForm"]').on('click', function(e) {
    e.preventDefault();
    const $container = $('#garantiaFormContainer');
    if ($container.length) {
        $container.slideDown(400);
        setTimeout(() => {
            $('html, body').animate({
                scrollTop: $container.offset().top - 100
            }, 800);
        }, 450);
    }
});

});
</script>

<?php
// ========== OPCIÓN: Panel de administracin para cambiar el email de notificaciones ==========
add_action('admin_menu', function() {
    add_options_page(
        'Email de Notificaciones Garantías',
        'Email Garantías',
        'manage_options',
        'garantias-email',
        function() {
            if (isset($_POST['guardar_email_garantias']) && check_admin_referer('guardar_email_garantias')) {
                $nuevo_email = sanitize_email($_POST['admin_email_garantias']);
                if (is_email($nuevo_email)) {
                    update_option('admin_email_garantias', $nuevo_email);
                    echo '<div class="updated notice"><p>Email guardado correctamente.</p></div>';
                } else {
                    echo '<div class="error notice"><p>El email ingresado no es válido.</p></div>';
                }
            }
            $email_actual = get_option('admin_email_garantias', 'rosariotechsrl@gmail.com');
            ?>
            <div class="wrap">
                <h1>Email de notificaciones de garantías</h1>
                <form method="post">
                    <?php wp_nonce_field('guardar_email_garantias'); ?>
                    <label for="admin_email_garantias">Email actual:</label>
                    <input type="email" id="admin_email_garantias" name="admin_email_garantias" value="<?php echo esc_attr($email_actual); ?>" style="width:350px;max-width:100%;" required>
                    <p><button type="submit" class="button button-primary" name="guardar_email_garantias" value="1">Guardar Email</button></p>
                </form>
            </div>
            <?php
        }
    );
});
?>