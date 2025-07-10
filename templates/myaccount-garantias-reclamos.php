<?php
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

$user_id = get_current_user_id();
$args = [
    'post_type'      => 'garantia',
    'post_status'    => 'publish',
    'meta_query'     => [
        ['key' => '_cliente', 'value' => $user_id]
    ],
    'posts_per_page' => 100,
    'orderby'        => 'date',
    'order'          => 'DESC'
];
$garantias = get_posts($args);
?>

<h3>Nuevo reclamo de garantía</h3>
<form id="garantiaForm" method="post" enctype="multipart/form-data">
  <label for="producto">Producto</label>
  <input type="text" id="producto" name="producto" placeholder="Escribe para buscar..." required>

  <label for="cantidad">Cantidad</label>
  <input type="number" id="cantidad" name="cantidad" min="1" value="1" required>

  <label for="motivo">Motivo</label>
  <select id="motivo" name="motivo" required>
    <option value="">Seleccione un motivo...</option>
    <!-- opciones -->
  </select>

  <label for="foto">Foto del producto</label>
  <input type="file" id="foto" name="foto" accept="image/*" required>

  <label for="video">Video del producto (opcional)</label>
  <input type="file" id="video" name="video" accept="video/*">

  <button type="submit" class="button">Enviar reclamo</button>
</form>

<?php if ( $garantias ) : ?>
  <h3>Mis reclamos enviados</h3>
  <table id="tabla-reclamos" class="shop_table shop_table_responsive">
    <thead>
      <tr>
        <th>Código</th>
        <th>Producto</th>
        <th>Cantidad</th>
        <th>Motivo</th>
        <th>Foto</th>
        <th>Video</th>
        <th>Fecha</th>
        <th>N° Orden</th>
        <th>Estado</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ( $garantias as $garantia ) :
        $codigo_unico = get_post_meta( $garantia->ID, '_codigo_unico', true );
        $producto_id  = get_post_meta( $garantia->ID, '_producto', true );
        $prod         = wc_get_product( $producto_id );
        $cantidad     = get_post_meta( $garantia->ID, '_cantidad', true );
        $motivo       = get_post_meta( $garantia->ID, '_motivos', true );
        $foto_url     = get_post_meta( $garantia->ID, '_foto_url', true );
        $video_url    = get_post_meta( $garantia->ID, '_video_url', true );
        $fecha_raw    = get_post_meta( $garantia->ID, '_fecha', true );
        $order_id     = get_post_meta( $garantia->ID, '_order_id', true );
        $estado       = get_post_meta( $garantia->ID, '_estado', true );

        // Formatear fecha a d/m/Y
        $fecha = '';
        if ( $fecha_raw ) {
            $ts    = strtotime( $fecha_raw );
            $fecha = $ts ? date( 'd/m/Y', $ts ) : '';
        }
      ?>
        <tr>
          <td data-label="Código"><?php echo esc_html( $codigo_unico ); ?></td>
          <td data-label="Producto"><?php echo $prod ? esc_html( $prod->get_name() ) : 'Producto eliminado'; ?></td>
          <td data-label="Cantidad"><?php echo esc_html( $cantidad ); ?></td>
          <td data-label="Motivo"><?php echo esc_html( $motivo ); ?></td>
          <td data-label="Foto">
            <?php if ( $foto_url ) : ?>
              <a href="<?php echo esc_url( $foto_url ); ?>" target="_blank">Ver foto</a>
            <?php endif; ?>
          </td>
          <td data-label="Video">
            <?php if ( $video_url ) : ?>
              <a href="<?php echo esc_url( $video_url ); ?>" target="_blank">Ver video</a>
            <?php endif; ?>
          </td>
          <td data-label="Fecha"><?php echo esc_html( $fecha ); ?></td>
          <td data-label="N° Orden"><?php echo esc_html( $order_id ); ?></td>
          <td data-label="Estado"><?php echo esc_html( $estados_nombres[ $estado ] ?? $estado ); ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>