<?php
if (!defined('ABSPATH')) exit;

/**
 * Clase mejorada para manejar todas las operaciones AJAX del plugin de garantías
 * Incluye funcionalidades modernas, validaciones robustas y mejor UX
 */
class WC_Garantias_Ajax {
    
    public static function init() {
        // Hooks para usuarios logueados
        add_action('wp_ajax_wcgarantias_get_products', [__CLASS__, 'get_products_autocomplete']);
        add_action('wp_ajax_wcgarantias_submit_claim', [__CLASS__, 'submit_claim']);
        add_action('wp_ajax_wcgarantias_get_claim_status', [__CLASS__, 'get_claim_status']);
        add_action('wp_ajax_wcgarantias_add_comment', [__CLASS__, 'add_comment']);
        add_action('wp_ajax_wcgarantias_get_comments', [__CLASS__, 'get_comments']);
        add_action('wp_ajax_wcgarantias_upload_file', [__CLASS__, 'upload_file']);
        add_action('wp_ajax_wcgarantias_get_dashboard_data', [__CLASS__, 'get_dashboard_data']);
        
        // Hooks para administradores
        add_action('wp_ajax_wcgarantias_admin_update_status', [__CLASS__, 'admin_update_status']);
        add_action('wp_ajax_wcgarantias_admin_bulk_action', [__CLASS__, 'admin_bulk_action']);
        add_action('wp_ajax_wcgarantias_admin_add_note', [__CLASS__, 'admin_add_note']);
        add_action('wp_ajax_wcgarantias_admin_get_analytics', [__CLASS__, 'admin_get_analytics']);
        add_action('wp_ajax_wcgarantias_admin_assign_claim', [__CLASS__, 'admin_assign_claim']);
        
        // Hooks para usuarios no logueados (tracking público)
        add_action('wp_ajax_nopriv_wcgarantias_track_claim', [__CLASS__, 'track_claim_public']);
        
        // Enqueue scripts
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_scripts']);
    }
    
    /**
     * Enqueue scripts para frontend
     */
    public static function enqueue_scripts() {
        if (!is_account_page()) return;
        
        wp_enqueue_script(
            'wc-garantias-ajax',
            WC_GARANTIAS_URL . 'assets/js/garantias-ajax.js',
            ['jquery'],
            '1.2.0',
            true
        );
        
        wp_localize_script('wc-garantias-ajax', 'wcGarantiasAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcgarantias_nonce'),
            'strings' => [
                'loading' => __('Cargando...', 'wc-garantias'),
                'error' => __('Error al procesar la solicitud', 'wc-garantias'),
                'success' => __('Operación exitosa', 'wc-garantias'),
                'confirm_delete' => __('¿Estás seguro de eliminar este elemento?', 'wc-garantias'),
                'file_too_large' => __('El archivo es demasiado grande', 'wc-garantias'),
                'invalid_file_type' => __('Tipo de archivo no permitido', 'wc-garantias'),
            ],
            'max_file_size' => wp_max_upload_size(),
            'allowed_file_types' => ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'mov', 'avi'],
        ]);
    }
    
    /**
     * Enqueue scripts para admin
     */
    public static function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'wc-garantias') === false && $hook !== 'edit.php') return;
        
        wp_enqueue_script(
            'wc-garantias-admin-ajax',
            WC_GARANTIAS_URL . 'assets/js/garantias-admin-ajax.js',
            ['jquery', 'jquery-ui-sortable'],
            '1.2.0',
            true
        );
        
        wp_localize_script('wc-garantias-admin-ajax', 'wcGarantiasAdminAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcgarantias_admin_nonce'),
            'strings' => [
                'loading' => __('Procesando...', 'wc-garantias'),
                'saved' => __('Guardado correctamente', 'wc-garantias'),
                'error' => __('Error al guardar', 'wc-garantias'),
                'confirm_bulk' => __('¿Aplicar acción a los elementos seleccionados?', 'wc-garantias'),
            ],
        ]);
    }
    
    /**
     * Autocomplete de productos para el formulario de garantías
     */
    public static function get_products_autocomplete() {
        check_ajax_referer('wcgarantias_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Usuario no autenticado']);
        }
        
        $term = sanitize_text_field($_POST['term'] ?? '');
        if (strlen($term) < 2) {
            wp_send_json_error(['message' => 'Término de búsqueda muy corto']);
        }
        
        $customer_id = get_current_user_id();
        $duracion_garantia = get_option('duracion_garantia', 180);
        $fecha_limite = strtotime("-{$duracion_garantia} days");
        
        // Obtener productos comprados por el cliente
        $orders = wc_get_orders([
            'customer_id' => $customer_id,
            'status' => 'completed',
            'limit' => -1,
        ]);
        
        $productos_disponibles = [];
        foreach ($orders as $order) {
            $order_time = strtotime($order->get_date_completed() ? 
                $order->get_date_completed()->date('Y-m-d H:i:s') : 
                $order->get_date_created()->date('Y-m-d H:i:s')
            );
            
            if ($order_time < $fecha_limite) continue;
            
            foreach ($order->get_items() as $item) {
                $product_id = $item->get_product_id();
                $product = wc_get_product($product_id);
                if (!$product) continue;
                
                $product_name = $product->get_name();
                if (stripos($product_name, $term) === false) continue;
                
                // Calcular cantidad disponible para reclamar
                $cantidad_comprada = $item->get_quantity();
                $cantidad_reclamada = self::get_claimed_quantity($customer_id, $product_id);
                $cantidad_disponible = $cantidad_comprada - $cantidad_reclamada;
                
                if ($cantidad_disponible <= 0) continue;
                
                $custom_sku = get_post_meta($product_id, '_alg_ean', true);
                if (is_array($custom_sku)) {
                    $custom_sku = reset($custom_sku);
                }
                
                $productos_disponibles[] = [
                    'id' => $product_id,
                    'label' => sprintf('%s — %s (%d disponibles)', 
                        $product_name, 
                        $custom_sku ?: $product->get_sku(), 
                        $cantidad_disponible
                    ),
                    'name' => $product_name,
                    'sku' => $custom_sku ?: $product->get_sku(),
                    'max_quantity' => $cantidad_disponible,
                    'order_id' => $order->get_id(),
                ];
            }
        }
        
        // Eliminar duplicados y limitar resultados
        $productos_disponibles = array_unique($productos_disponibles, SORT_REGULAR);
        $productos_disponibles = array_slice($productos_disponibles, 0, 20);
        
        wp_send_json_success($productos_disponibles);
    }
    
    /**
     * Envío de reclamo de garantía mejorado con validaciones
     */
    public static function submit_claim() {
        check_ajax_referer('wcgarantias_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Usuario no autenticado']);
        }
        
        try {
            $user_id = get_current_user_id();
            $items_data = $_POST['items'] ?? [];
            
            if (empty($items_data) || !is_array($items_data)) {
                wp_send_json_error(['message' => 'No se proporcionaron items para reclamar']);
            }
            
            $items_procesados = [];
            
            foreach ($items_data as $index => $item) {
                $producto_id = intval($item['producto_id'] ?? 0);
                $cantidad = max(1, intval($item['cantidad'] ?? 1));
                $motivo = sanitize_text_field($item['motivo'] ?? '');
                $motivo_otro = sanitize_text_field($item['motivo_otro'] ?? '');
                $order_id = intval($item['order_id'] ?? 0);
                
                // Validaciones
                if (!$producto_id || !$motivo) {
                    wp_send_json_error(['message' => "Datos incompletos en el item " . ($index + 1)]);
                }
                
                // Verificar que el usuario puede reclamar este producto
                if (!self::can_claim_product($user_id, $producto_id, $cantidad)) {
                    wp_send_json_error(['message' => "No puedes reclamar este producto o la cantidad especificada"]);
                }
                
                // Procesar motivo
                if ($motivo === 'Otro' && !empty($motivo_otro)) {
                    $motivo = 'Otro: ' . $motivo_otro;
                }
                
                $items_procesados[] = [
                    'codigo_item' => 'GRT-ITEM-' . strtoupper(wp_generate_password(8, false, false)),
                    'producto_id' => $producto_id,
                    'cantidad' => $cantidad,
                    'motivo' => $motivo,
                    'foto_url' => '', // Se subirá después si es necesario
                    'video_url' => '', // Se subirá después si es necesario
                    'order_id' => $order_id,
                    'estado' => 'Pendiente',
                    'fecha_creacion' => current_time('mysql'),
                ];
            }
            
            // Crear el post de garantía
            $garantia_post = [
                'post_type' => 'garantia',
                'post_status' => 'publish',
                'post_title' => 'Garantía - ' . $user_id . ' - ' . date('Y-m-d H:i:s'),
                'post_author' => $user_id,
            ];
            
            $post_id = wp_insert_post($garantia_post);
            
            if (is_wp_error($post_id)) {
                wp_send_json_error(['message' => 'Error al crear la garantía']);
            }
            
            // Metadatos
            $codigo_unico = 'GRT-' . date('Ymd') . '-' . strtoupper(wp_generate_password(5, false, false));
            update_post_meta($post_id, '_codigo_unico', $codigo_unico);
            update_post_meta($post_id, '_cliente', $user_id);
            update_post_meta($post_id, '_fecha', current_time('mysql'));
            update_post_meta($post_id, '_estado', 'nueva');
            update_post_meta($post_id, '_items_reclamados', $items_procesados);
            
            // Historial inicial
            $historial = [[
                'estado' => 'nueva',
                'fecha' => current_time('mysql'),
                'nota' => 'Garantía creada por el cliente',
                'usuario' => $user_id,
            ]];
            update_post_meta($post_id, '_historial', $historial);
            
            // Notificar admin
            self::notify_admin_new_claim($post_id, $codigo_unico);
            
            wp_send_json_success([
                'message' => 'Reclamo enviado correctamente',
                'codigo_garantia' => $codigo_unico,
                'garantia_id' => $post_id,
            ]);
            
        } catch (Exception $e) {
            error_log('Error en submit_claim: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Error interno del servidor']);
        }
    }
    
    /**
     * Obtener estado actualizado de una garantía
     */
    public static function get_claim_status() {
        check_ajax_referer('wcgarantias_nonce', 'nonce');
        
        $garantia_id = intval($_POST['garantia_id'] ?? 0);
        if (!$garantia_id) {
            wp_send_json_error(['message' => 'ID de garantía no válido']);
        }
        
        $garantia = get_post($garantia_id);
        if (!$garantia || $garantia->post_type !== 'garantia') {
            wp_send_json_error(['message' => 'Garantía no encontrada']);
        }
        
        // Verificar permisos
        if (!current_user_can('manage_woocommerce') && $garantia->post_author != get_current_user_id()) {
            wp_send_json_error(['message' => 'Sin permisos para ver esta garantía']);
        }
        
        $estados_nombres = [
            'nueva' => 'Pendiente de recibir',
            'en_revision' => 'En revisión',
            'pendiente_envio' => 'Pendiente de envío',
            'recibido' => 'Recibido - En análisis',
            'aprobado_cupon' => 'Aprobado - Cupón Enviado',
            'rechazado' => 'Rechazado',
            'finalizado_cupon' => 'Finalizado - Cupón utilizado',
            'finalizado' => 'Finalizado',
        ];
        
        $estado = get_post_meta($garantia_id, '_estado', true);
        $items = get_post_meta($garantia_id, '_items_reclamados', true);
        $historial = get_post_meta($garantia_id, '_historial', true);
        $comentarios = get_post_meta($garantia_id, '_comentarios', true);
        
        wp_send_json_success([
            'estado' => $estado,
            'estado_nombre' => $estados_nombres[$estado] ?? $estado,
            'items' => $items ?: [],
            'historial' => $historial ?: [],
            'comentarios' => $comentarios ?: [],
            'fecha_actualizacion' => get_post_modified_time('c', false, $garantia),
        ]);
    }
    
    /**
     * Agregar comentario a una garantía
     */
    public static function add_comment() {
        check_ajax_referer('wcgarantias_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Usuario no autenticado']);
        }
        
        $garantia_id = intval($_POST['garantia_id'] ?? 0);
        $comentario = trim(sanitize_textarea_field($_POST['comentario'] ?? ''));
        
        if (!$garantia_id || empty($comentario)) {
            wp_send_json_error(['message' => 'Datos incompletos']);
        }
        
        $garantia = get_post($garantia_id);
        if (!$garantia || $garantia->post_type !== 'garantia') {
            wp_send_json_error(['message' => 'Garantía no encontrada']);
        }
        
        $user_id = get_current_user_id();
        $is_admin = current_user_can('manage_woocommerce');
        
        // Verificar permisos
        if (!$is_admin && $garantia->post_author != $user_id) {
            wp_send_json_error(['message' => 'Sin permisos para comentar en esta garantía']);
        }
        
        $comentarios = get_post_meta($garantia_id, '_comentarios', true) ?: [];
        
        $nuevo_comentario = [
            'id' => uniqid(),
            'usuario_id' => $user_id,
            'usuario_nombre' => wp_get_current_user()->display_name,
            'es_admin' => $is_admin,
            'comentario' => $comentario,
            'fecha' => current_time('mysql'),
            'fecha_legible' => current_time('d/m/Y H:i'),
        ];
        
        $comentarios[] = $nuevo_comentario;
        update_post_meta($garantia_id, '_comentarios', $comentarios);
        
        // Notificar a la otra parte
        if ($is_admin) {
            self::notify_customer_comment($garantia_id, $comentario);
        } else {
            self::notify_admin_comment($garantia_id, $comentario);
        }
        
        wp_send_json_success([
            'message' => 'Comentario agregado correctamente',
            'comentario' => $nuevo_comentario,
        ]);
    }
    
    /**
     * Obtener comentarios de una garantía
     */
    public static function get_comments() {
        check_ajax_referer('wcgarantias_nonce', 'nonce');
        
        $garantia_id = intval($_POST['garantia_id'] ?? 0);
        if (!$garantia_id) {
            wp_send_json_error(['message' => 'ID de garantía no válido']);
        }
        
        $garantia = get_post($garantia_id);
        if (!$garantia || $garantia->post_type !== 'garantia') {
            wp_send_json_error(['message' => 'Garantía no encontrada']);
        }
        
        // Verificar permisos
        if (!current_user_can('manage_woocommerce') && $garantia->post_author != get_current_user_id()) {
            wp_send_json_error(['message' => 'Sin permisos para ver esta garantía']);
        }
        
        $comentarios = get_post_meta($garantia_id, '_comentarios', true) ?: [];
        
        wp_send_json_success(['comentarios' => $comentarios]);
    }
    
    /**
     * Subida de archivos mejorada con validaciones
     */
    public static function upload_file() {
        check_ajax_referer('wcgarantias_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Usuario no autenticado']);
        }
        
        if (empty($_FILES['file'])) {
            wp_send_json_error(['message' => 'No se proporcionó archivo']);
        }
        
        $file = $_FILES['file'];
        $garantia_id = intval($_POST['garantia_id'] ?? 0);
        $item_codigo = sanitize_text_field($_POST['item_codigo'] ?? '');
        $tipo_archivo = sanitize_text_field($_POST['tipo'] ?? 'foto'); // 'foto' o 'video'
        
        // Validaciones
        $max_size = wp_max_upload_size();
        if ($file['size'] > $max_size) {
            wp_send_json_error(['message' => 'Archivo demasiado grande']);
        }
        
        $allowed_types = [
            'foto' => ['jpg', 'jpeg', 'png', 'gif'],
            'video' => ['mp4', 'mov', 'avi', 'wmv'],
        ];
        
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_ext, $allowed_types[$tipo_archivo] ?? [])) {
            wp_send_json_error(['message' => 'Tipo de archivo no permitido']);
        }
        
        // Subir archivo
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        $uploaded = wp_handle_upload($file, ['test_form' => false]);
        
        if (isset($uploaded['error'])) {
            wp_send_json_error(['message' => 'Error al subir archivo: ' . $uploaded['error']]);
        }
        
        // Actualizar item si se proporcionó
        if ($garantia_id && $item_codigo) {
            $items = get_post_meta($garantia_id, '_items_reclamados', true) ?: [];
            foreach ($items as &$item) {
                if ($item['codigo_item'] === $item_codigo) {
                    $item[$tipo_archivo . '_url'] = $uploaded['url'];
                    break;
                }
            }
            update_post_meta($garantia_id, '_items_reclamados', $items);
        }
        
        wp_send_json_success([
            'message' => 'Archivo subido correctamente',
            'url' => $uploaded['url'],
            'filename' => basename($uploaded['file']),
        ]);
    }
    
    /**
     * Obtener datos del dashboard del cliente
     */
    public static function get_dashboard_data() {
        check_ajax_referer('wcgarantias_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Usuario no autenticado']);
        }
        
        $user_id = get_current_user_id();
        
        // Estadísticas del cliente
        $args = [
            'post_type' => 'garantia',
            'post_status' => 'publish',
            'meta_query' => [['key' => '_cliente', 'value' => $user_id]],
            'posts_per_page' => -1,
        ];
        
        $garantias = get_posts($args);
        
        $stats = [
            'total' => count($garantias),
            'pendientes' => 0,
            'aprobadas' => 0,
            'rechazadas' => 0,
            'ultimas' => [],
        ];
        
        foreach ($garantias as $garantia) {
            $estado = get_post_meta($garantia->ID, '_estado', true);
            
            switch ($estado) {
                case 'nueva':
                case 'en_revision':
                case 'pendiente_envio':
                case 'recibido':
                    $stats['pendientes']++;
                    break;
                case 'aprobado_cupon':
                case 'finalizado_cupon':
                case 'finalizado':
                    $stats['aprobadas']++;
                    break;
                case 'rechazado':
                    $stats['rechazadas']++;
                    break;
            }
            
            if (count($stats['ultimas']) < 5) {
                $stats['ultimas'][] = [
                    'id' => $garantia->ID,
                    'codigo' => get_post_meta($garantia->ID, '_codigo_unico', true),
                    'estado' => $estado,
                    'fecha' => get_post_time('d/m/Y', false, $garantia),
                ];
            }
        }
        
        wp_send_json_success($stats);
    }
    
    /**
     * Funciones de utilidad
     */
    
    private static function get_claimed_quantity($customer_id, $product_id) {
        $args = [
            'post_type' => 'garantia',
            'post_status' => 'publish',
            'meta_query' => [
                ['key' => '_cliente', 'value' => $customer_id],
            ],
            'posts_per_page' => -1,
        ];
        
        $garantias = get_posts($args);
        $cantidad_reclamada = 0;
        
        foreach ($garantias as $garantia) {
            $items = get_post_meta($garantia->ID, '_items_reclamados', true) ?: [];
            foreach ($items as $item) {
                if (intval($item['producto_id']) === $product_id) {
                    $cantidad_reclamada += intval($item['cantidad'] ?? 1);
                }
            }
        }
        
        return $cantidad_reclamada;
    }
    
    private static function can_claim_product($customer_id, $product_id, $cantidad_solicitada) {
        $duracion_garantia = get_option('duracion_garantia', 180);
        $fecha_limite = strtotime("-{$duracion_garantia} days");
        
        $orders = wc_get_orders([
            'customer_id' => $customer_id,
            'status' => 'completed',
            'limit' => -1,
        ]);
        
        $cantidad_comprada = 0;
        foreach ($orders as $order) {
            $order_time = strtotime($order->get_date_completed() ? 
                $order->get_date_completed()->date('Y-m-d H:i:s') : 
                $order->get_date_created()->date('Y-m-d H:i:s')
            );
            
            if ($order_time < $fecha_limite) continue;
            
            foreach ($order->get_items() as $item) {
                if ($item->get_product_id() == $product_id) {
                    $cantidad_comprada += $item->get_quantity();
                }
            }
        }
        
        $cantidad_reclamada = self::get_claimed_quantity($customer_id, $product_id);
        $cantidad_disponible = $cantidad_comprada - $cantidad_reclamada;
        
        return $cantidad_disponible >= $cantidad_solicitada;
    }
    
    private static function notify_admin_new_claim($garantia_id, $codigo_unico) {
        $admin_email = get_option('admin_email_garantias', get_option('admin_email'));
        
        $subject = 'Nueva garantía registrada - ' . $codigo_unico;
        $message = "Se ha registrado una nueva garantía.\n\n";
        $message .= "Código: {$codigo_unico}\n";
        $message .= "Ver detalles: " . admin_url("admin.php?page=wc-garantias-ver&garantia_id={$garantia_id}");
        
        wp_mail($admin_email, $subject, $message);
    }
    
    private static function notify_customer_comment($garantia_id, $comentario) {
        $garantia = get_post($garantia_id);
        $customer = get_userdata($garantia->post_author);
        $codigo = get_post_meta($garantia_id, '_codigo_unico', true);
        
        if ($customer && $customer->user_email) {
            $subject = 'Nuevo comentario en tu garantía - ' . $codigo;
            $message = "Hay un nuevo comentario en tu garantía {$codigo}:\n\n";
            $message .= $comentario . "\n\n";
            $message .= "Ver en tu cuenta: " . wc_get_account_endpoint_url('garantias');
            
            wp_mail($customer->user_email, $subject, $message);
        }
    }
    
    private static function notify_admin_comment($garantia_id, $comentario) {
        $admin_email = get_option('admin_email_garantias', get_option('admin_email'));
        $codigo = get_post_meta($garantia_id, '_codigo_unico', true);
        
        $subject = 'Nuevo comentario del cliente - ' . $codigo;
        $message = "El cliente ha agregado un comentario a la garantía {$codigo}:\n\n";
        $message .= $comentario . "\n\n";
        $message .= "Ver detalles: " . admin_url("admin.php?page=wc-garantias-ver&garantia_id={$garantia_id}");
        
        wp_mail($admin_email, $subject, $message);
    }
}

// Inicializar la clase
WC_Garantias_Ajax::init();