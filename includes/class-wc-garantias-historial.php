<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WC_Garantias_Historial {
    public static function init() {
        add_action('updated_post_meta', [__CLASS__, 'guardar_evento_historial'], 10, 4);
        add_action('added_post_meta', [__CLASS__, 'guardar_evento_historial'], 10, 4);
    }

    public static function guardar_evento_historial($meta_id, $object_id, $meta_key, $_meta_value) {
        // Solo para garantías y solo si la meta es _estado
        $post = get_post($object_id);
        if ( ! $post || $post->post_type !== 'garantia' ) return;
        if ( $meta_key !== '_estado' ) return;

        $estado_nuevo = $_meta_value;
        $historial = get_post_meta($object_id, '_historial', true);
        if ( ! is_array($historial) ) $historial = [];

        // Verifica si el último evento es el mismo estado
        $ultimo = end($historial);
        if ($ultimo && $ultimo['estado'] === $estado_nuevo) return;

        $historial[] = [
            'estado' => $estado_nuevo,
            'fecha'  => current_time('mysql'),
            'nota'   => '' // Puedes agregar aquí un comentario si lo deseas
        ];
        update_post_meta($object_id, '_historial', $historial);
    }
}

WC_Garantias_Historial::init();