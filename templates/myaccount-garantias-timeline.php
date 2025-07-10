<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if (!isset($garantia_id)) {
    echo '<div class="woocommerce-error">No se encontró el reclamo seleccionado.</div>';
    return;
}

$garantia = get_post($garantia_id);
if (!$garantia) {
    echo '<div class="woocommerce-error">No se encontró el reclamo seleccionado.</div>';
    return;
}

// Datos principales
$codigo_unico = get_post_meta($garantia_id, '_codigo_unico', true);
$producto_id = get_post_meta($garantia_id, '_producto', true);
$prod = wc_get_product($producto_id);
$nombre_producto = $prod ? $prod->get_name() : 'Producto eliminado';
$estado = get_post_meta($garantia_id, '_estado', true);
$motivo_rechazo = get_post_meta($garantia_id, '_motivo_rechazo', true);
$fecha = get_post_meta($garantia_id, '_fecha', true);

// Timeline de eventos (puedes adaptar la estructura a tu sistema)
$eventos = [
    [
        'titulo' => 'Solicitado',
        'fecha' => get_post_meta($garantia_id, '_fecha', true),
        'icono' => 'fa-file-alt',
        'estado' => 'done',
    ],
];
if ($estado === 'recibido' || $estado === 'en_revision' || $estado === 'aprobado_cupon' || $estado === 'rechazado' || $estado === 'finalizado' || $estado === 'finalizado_cupon') {
    $eventos[] = [
        'titulo' => 'Recibido',
        'fecha' => get_post_meta($garantia_id, '_fecha_recibido', true), // Asegúrate de guardar esta fecha
        'icono' => 'fa-box',
        'estado' => 'done',
    ];
}
if ($estado === 'rechazado') {
    $eventos[] = [
        'titulo' => 'Rechazado',
        'fecha' => get_post_meta($garantia_id, '_fecha_rechazado', true), // Asegúrate de guardar esta fecha
        'icono' => 'fa-times-circle',
        'estado' => 'rejected',
        'detalle' => $motivo_rechazo,
    ];
} elseif ($estado === 'aprobado_cupon' || $estado === 'finalizado_cupon') {
    $eventos[] = [
        'titulo' => 'Aprobado',
        'fecha' => get_post_meta($garantia_id, '_fecha_aprobado', true), // Asegúrate de guardar esta fecha
        'icono' => 'fa-check-circle',
        'estado' => 'aprobado',
    ];
} elseif ($estado === 'en_revision') {
    $eventos[] = [
        'titulo' => 'En revisión',
        'fecha' => get_post_meta($garantia_id, '_fecha_revision', true),
        'icono' => 'fa-search',
        'estado' => 'revision',
    ];
} elseif ($estado === 'pendiente_envio') {
    $eventos[] = [
        'titulo' => 'Pendiente de envío',
        'fecha' => get_post_meta($garantia_id, '_fecha_pendiente_envio', true),
        'icono' => 'fa-truck',
        'estado' => 'pendiente',
    ];
}

// Estado visual
$estados_nombres = [
    'nueva'              => ['label' => 'Pendiente de recibir', 'class' => 'status-pendiente'],
    'en_revision'        => ['label' => 'En revisin', 'class' => 'status-revision'],
    'pendiente_envio'    => ['label' => 'Pendiente de envío', 'class' => 'status-pendiente'],
    'recibido'           => ['label' => 'Recibido - En análisis', 'class' => 'status-recibido'],
    'aprobado_cupon'     => ['label' => 'Aprobado - Cupn Enviado', 'class' => 'status-aprobado'],
    'rechazado'          => ['label' => 'Rechazado', 'class' => 'status-rechazado'],
    'finalizado_cupon'   => ['label' => 'Finalizado - Cupón utilizado', 'class' => 'status-finalizado'],
    'finalizado'         => ['label' => 'Finalizado', 'class' => 'status-finalizado'],
];
$estado_label = isset($estados_nombres[$estado]) ? $estados_nombres[$estado]['label'] : ucfirst($estado);
$estado_class = isset($estados_nombres[$estado]) ? $estados_nombres[$estado]['class'] : 'status-default';
?>

<!-- FontAwesome CDN (puedes usar SVGs propios si prefieres) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<style>
.timeline-warranty {
  max-width: 560px;
  margin: 40px auto 0 auto;
  background: #fff;
  border-radius: 14px;
  box-shadow: 0 4px 32px rgba(0,0,0,0.10);
  padding: 28px 32px 38px 32px;
  font-family: 'Inter', Arial, sans-serif;
}
.timeline-header {
  display: flex;
  align-items: center;
  gap: 1.5em;
  margin-bottom: 32px;
  flex-wrap: wrap;
}
.product-name {
  font-size: 1.2em;
  font-weight: 600;
  color: #222;
}
.claim-code {
  font-size: .98em;
  color: #666;
  background: #f4f6fa;
  border-radius: 7px;
  padding: 2px 11px;
}
.claim-status {
  padding: 2px 14px;
  border-radius: 12px;
  font-weight: bold;
  font-size: .97em;
}
.status-rechazado { background: #fbeaea; color: #d63638; }
.status-aprobado { background: #eafded; color: #27ae60; }
.status-recibido { background: #eaf6fb; color: #3498db; }
.status-pendiente { background: #f8f9fa; color: #888; }
.status-revision { background: #f6f8fa; color: #e67e22; }
.status-finalizado { background: #ececec; color: #222; }
.timeline-steps {
  display: flex;
  flex-direction: column;
  gap: 0;
  border-left: 3px solid #eaf0fa;
  margin-left: 27px;
  position: relative;
}
.timeline-step {
  display: flex;
  align-items: flex-start;
  margin-bottom: 32px;
  position: relative;
  min-height: 54px;
}
.timeline-step:last-child { margin-bottom: 0; }
.step-icon {
  width: 38px;
  height: 38px;
  background: #f8fafc;
  border-radius: 50%;
  border: 3px solid #eaf0fa;
  color: #bbb;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5em;
  position: absolute;
  left: -48px;
  top: 0;
  transition: border-color .2s, color .2s, background .2s;
}
.timeline-step.done .step-icon {
  border-color: #3498db;
  color: #3498db;
  background: #eaf6fb;
}
.timeline-step.rejected .step-icon {
  border-color: #d63638;
  color: #d63638;
  background: #fbeaea;
}
.timeline-step.aprobado .step-icon {
  border-color: #27ae60;
  color: #27ae60;
  background: #eafded;
}
.timeline-step.revision .step-icon {
  border-color: #e67e22;
  color: #e67e22;
  background: #fcf5e6;
}
.step-info {
  margin-left: 16px;
}
.step-title {
  font-weight: 600;
  font-size: 1.08em;
}
.step-date {
  font-size: .97em;
  color: #888;
}
.step-note {
  margin-top: 2px;
  font-size: .96em;
  color: #d63638;
  font-style: italic;
}
.timeline-actions {
  margin-top: 32px;
  text-align: right;
}
.timeline-actions form {
  display: inline-block;
  margin: 0 6px 0 0;
}
.timeline-actions button, .timeline-actions a.button {
  background: #f8f8f8;
  border: 1px solid #e4e4e4;
  color: #222;
  border-radius: 5px;
  padding: 8px 22px;
  cursor: pointer;
  font-weight: 600;
  font-size: 1em;
  transition: background .18s;
}
.timeline-actions button:hover, .timeline-actions a.button:hover {
  background: #f2f2f2;
}
</style>

<div class="timeline-warranty">
  <div class="timeline-header">
    <div class="product-name"><?php echo esc_html($nombre_producto); ?></div>
    <div class="claim-code">Código: <?php echo esc_html($codigo_unico); ?></div>
    <div class="claim-status <?php echo esc_attr($estado_class); ?>">
      <?php echo esc_html($estado_label); ?>
    </div>
  </div>
  <div class="timeline-steps">
    <?php foreach ($eventos as $evento):
      $step_class = $evento['estado'];
      ?>
      <div class="timeline-step <?php echo esc_attr($step_class); ?>">
        <div class="step-icon">
          <i class="fas <?php echo esc_attr($evento['icono']); ?>"></i>
        </div>
        <div class="step-info">
          <div class="step-title"><?php echo esc_html($evento['titulo']); ?></div>
          <?php if (!empty($evento['fecha'])): ?>
            <div class="step-date"><?php echo date('d/m/Y H:i', strtotime($evento['fecha'])); ?></div>
          <?php endif; ?>
          <?php if (!empty($evento['detalle'])): ?>
            <div class="step-note">Motivo: <?php echo esc_html($evento['detalle']); ?></div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="timeline-actions">
    <form method="post" style="display:inline;">
      <button type="submit" class="button">Volver a la lista</button>
    </form>
  </div>
</div>