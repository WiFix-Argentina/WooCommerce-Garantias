<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Dashboard mejorado de garantías del cliente
?>

<div id="garantias-dashboard" class="garantias-dashboard-container">
    <!-- Indicador de carga inicial -->
    <div class="dashboard-loading" style="text-align: center; padding: 40px;">
        <div style="display: inline-block; width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid #007cba; border-radius: 50%; animation: spin 1s linear infinite;"></div>
        <p style="margin-top: 15px; color: #666;">Cargando estadísticas...</p>
    </div>
</div>

<div class="garantias-tips" style="
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 8px;
    padding: 12px 15px;
    margin: 12px 0;
    border-left: 4px solid #007cba;
">
    <h4 style="margin-top: 0; color: #007cba;">💡 Consejos para acelerar tu reclamo</h4>
    <ul style="margin-bottom: 0; padding-left: 20px;">
        <li><strong>Foto clara:</strong> Asegúrate de que la foto muestre claramente el problema del producto</li>
        <li><strong>Descripción detallada:</strong> Explica exactamente qué est fallando</li>
        <li><strong>Conserva el empaque:</strong> Si es posible, guarda la caja original</li>
        <li><strong>Responde rápido:</strong> Revisa tu email y responde a nuestras consultas</li>
    </ul>
</div>

<!-- Sección de cupones disponibles -->
<?php
$user_id = get_current_user_id();
$cupon_pendiente = get_user_meta($user_id, '_cupon_garantia_pendiente', true);

// Verificar que el cupón realmente existe
$cupon_valido = false;
if ($cupon_pendiente) {
    $cupon_post = get_page_by_title($cupon_pendiente, OBJECT, 'shop_coupon');
    if ($cupon_post && $cupon_post->post_status === 'publish') {
        $cupon_valido = true;
        // Obtener valor del cupón
        $cupon_valor = get_post_meta($cupon_post->ID, 'coupon_amount', true);
    } else {
        // Limpiar cupón inexistente
        delete_user_meta($user_id, '_cupon_garantia_pendiente');
        $cupon_pendiente = false;
    }
}

if ($cupon_pendiente && $cupon_valido) :
?>
<div class="cupon-disponible" style="
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
    border-radius: 10px;
    padding: 20px;
    margin: 20px 0;
    text-align: center;
    box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
">
    <h4 style="margin-top: 0; color: white;">🎉 ¡Tienes un cupón disponible por $<?php echo number_format($cupon_valor, 0, ',', '.'); ?>!</h4>
    <div style="
        background: rgba(255,255,255,0.2);
        border-radius: 8px;
        padding: 15px;
        margin: 15px 0;
        font-size: 1.2em;
        font-weight: bold;
        letter-spacing: 2px;
    ">
        <?php echo esc_html($cupon_pendiente); ?>
    </div>
    <p style="margin-bottom: 10px;">
        Este cupón se aplicará automáticamente en tu próxima compra.
    </p>
    <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>" 
       class="button" 
       style="background: white; color: #28a745; border: none; padding: 10px 20px; border-radius: 5px; font-weight: bold; text-decoration: none;">
        Ir a comprar
    </a>
</div>
<?php endif; ?>

<!-- Sección de acceso rápido -->
<div class="acceso-rapido" style="
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
    margin: 20px 0;
">
    <a href="#garantiaForm" class="quick-action-card" style="
        display: block;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px;
        border-radius: 10px;
        text-decoration: none;
        text-align: center;
        transition: transform 0.2s ease;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    " onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
        <div style="font-size: 2em; margin-bottom: 10px; height: 48px; display: flex; align-items: center; justify-content: center;">📋</div>
        <h4 style="margin: 0; color: white;">Nuevo Reclamo</h4>
        <p style="margin: 5px 0 0 0; opacity: 0.9; font-size: 0.9em;">
            Reportar un problema con un producto
        </p>
    </a>
    
    <a href="<?php echo esc_url(wc_get_account_endpoint_url('orders')); ?>" class="quick-action-card" style="
        display: block;
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        color: white;
        padding: 20px;
        border-radius: 10px;
        text-decoration: none;
        text-align: center;
        transition: transform 0.2s ease;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    " onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
        <div style="font-size: 2em; margin-bottom: 10px; height: 48px; display: flex; align-items: center; justify-content: center;">📦</div>
        <h4 style="margin: 0; color: white;">Mis Pedidos</h4>
        <p style="margin: 5px 0 0 0; opacity: 0.9; font-size: 0.9em;">
            Ver historial de compras
        </p>
    </a>
</div>

<!-- Información sobre la garantía -->
<div class="info-garantia" style="
    background: #f8f9fa;
    border-radius: 6px;
    padding: 12px 15px;
    margin: 12px 0;
    border: 1px solid #dee2e6;
">
    <h4 style="margin-top: 0; color: #495057;">ℹ️ Información importante</h4>
    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 25px; text-align: left;">
    <div>
        <strong>Plazo de garantía:</strong><br>
        <span style="color: #007cba;"><?php echo esc_html(get_option('duracion_garantia', 180)); ?> días</span><br>
        <small>desde la compra</small>
    </div>
    <div>
        <strong>Tiempo de respuesta:</strong><br>
        <span style="color: #007cba;">24-48 horas</span><br>
        <small>días hábiles</small>
    </div>
    <div>
        <strong>Métodos de contacto:</strong><br>
        <span style="color: #007cba;">Email y esta plataforma</span>
    </div>
</div>

<style>
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Responsivo para móviles */
@media (max-width: 768px) {
    .garantias-dashboard-container .stat-card {
        padding: 15px !important;
    }
    
    .garantias-dashboard-container .stat-number {
        font-size: 2em !important;
    }
    
    .acceso-rapido {
        grid-template-columns: 1fr !important;
    }
    
    .info-garantia div[style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
        gap: 15px !important;
    }
}

/* Ocultar dashboard en móvil si hay problemas de espacio */
@media (max-width: 480px) {
    .garantias-tips,
    .info-garantia {
        padding: 15px;
        margin: 15px 0;
    }
    
    .cupon-disponible {
        padding: 15px;
        margin: 15px 0;
    }
}
</style>

<script>
// Auto-refresh del dashboard cada 60 segundos
jQuery(document).ready(function($) {
    // El JavaScript principal se encarga de cargar los datos
    
    // Smooth scroll para el enlace "Nuevo Reclamo"
    $('a[href="#garantiaForm"]').on('click', function(e) {
        e.preventDefault();
        const target = $('#garantiaForm');
        if (target.length) {
            $('html, body').animate({
                scrollTop: target.offset().top - 100
            }, 800);
        }
    });
});
</script>