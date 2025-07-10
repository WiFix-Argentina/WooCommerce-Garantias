/**
 * WooCommerce Garantías - Frontend AJAX
 * Funcionalidades modernas sin recargar página
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Variables globales
    const garantiasAjax = {
        
        /**
         * Inicializar todas las funcionalidades
         */
        init: function() {
            this.initAutocomplete();
            this.initFormSubmit();
            this.initStatusRefresh();
            this.initComments();
            this.initDashboard();
            this.initToastNotifications();
        },
        
        /**
         * Sistema de notificaciones toast
         */
        initToastNotifications: function() {
            if ($('#garantias-toast-container').length === 0) {
                $('body').append('<div id="garantias-toast-container" style="position:fixed;top:20px;right:20px;z-index:9999;"></div>');
            }
        },
        
        showToast: function(message, type = 'success') {
            const toast = $(`
                <div class="garantias-toast garantias-toast-${type}" style="
                    background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : type === 'warning' ? '#ffc107' : '#17a2b8'};
                    color: white;
                    padding: 15px 20px;
                    margin-bottom: 10px;
                    border-radius: 5px;
                    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                    opacity: 0;
                    transform: translateX(100%);
                    transition: all 0.3s ease;
                    max-width: 350px;
                    word-wrap: break-word;
                ">
                    ${message}
                </div>
            `);
            
            $('#garantias-toast-container').append(toast);
            
            // Animación de entrada
            setTimeout(() => {
                toast.css({opacity: 1, transform: 'translateX(0)'});
            }, 100);
            
            // Auto remove después de 5 segundos
            setTimeout(() => {
                toast.css({opacity: 0, transform: 'translateX(100%)'});
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        },
        
        /**
         * Mejorar autocomplete de productos
         */
        initAutocomplete: function() {
            $('.producto_autocomplete').each(function() {
                const $input = $(this);
                const $hiddenInput = $input.siblings('.producto_hidden');
                
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
                            beforeSend: function() {
                                $input.addClass('loading');
                            },
                            success: function(data) {
                                $input.removeClass('loading');
                                if (data.success) {
                                    response(data.data);
                                } else {
                                    garantiasAjax.showToast(data.data.message || 'Error en búsqueda', 'error');
                                    response([]);
                                }
                            },
                            error: function() {
                                $input.removeClass('loading');
                                garantiasAjax.showToast('Error de conexión', 'error');
                                response([]);
                            }
                        });
                    },
                    select: function(event, ui) {
                        event.preventDefault();
                        $input.val(ui.item.label);
                        $hiddenInput.val(ui.item.id);
                        
                        // Actualizar cantidad máxima
                        const $cantidadInput = $input.closest('tr').find('.cantidad');
                        const $maxLabel = $input.closest('tr').find('.maxqty-label');
                        
                        $cantidadInput.attr('max', ui.item.max_quantity).val(1);
                        $maxLabel.text(`(Máx: ${ui.item.max_quantity})`);
                        
                        return false;
                    },
                    focus: function(event, ui) {
                        event.preventDefault();
                        $input.val(ui.item.label);
                        return false;
                    }
                });
            });
        },
        
        /**
         * Envío de formulario con AJAX
         */
        initFormSubmit: function() {
            $('#garantiaForm').on('submit', function(e) {
                e.preventDefault();
                
                const $form = $(this);
                const $submitBtn = $form.find('button[type="submit"]');
                
                // Validar formulario
                if (!garantiasAjax.validateForm($form)) {
                    return false;
                }
                
                // Preparar datos
                const formData = new FormData();
                formData.append('action', 'wcgarantias_submit_claim');
                formData.append('nonce', wcGarantiasAjax.nonce);
                
                // Recopilar items
                const items = [];
                $form.find('.reclamo-row').each(function(index) {
                    const $row = $(this);
                    const productoId = $row.find('.producto_hidden').val();
                    
                    if (productoId) {
                        items.push({
                            producto_id: productoId,
                            cantidad: $row.find('.cantidad').val(),
                            motivo: $row.find('.motivo_select').val(),
                            motivo_otro: $row.find('.motivo_otro').val(),
                            order_id: $row.find('.producto_autocomplete').data('order-id') || 0
                        });
                        
                        // Agregar archivos si existen
                        const fotoFile = $row.find('input[name="foto[]"]')[0].files[0];
                        const videoFile = $row.find('input[name="video[]"]')[0].files[0];
                        
                        if (fotoFile) {
                            formData.append(`foto_${index}`, fotoFile);
                        }
                        if (videoFile) {
                            formData.append(`video_${index}`, videoFile);
                        }
                    }
                });
                
                formData.append('items', JSON.stringify(items));
                
                // Enviar
                $submitBtn.prop('disabled', true).text('Enviando...');
                
                $.ajax({
                    url: wcGarantiasAjax.ajax_url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            garantiasAjax.showToast('¡Reclamo enviado correctamente!', 'success');
                            
                            // Mostrar código de garantía
                            const codigo = response.data.codigo_garantia;
                            garantiasAjax.showToast(`Código de seguimiento: ${codigo}`, 'info');
                            
                            // Limpiar formulario
                            $form[0].reset();
                            $form.find('.producto_hidden').val('');
                            $form.find('.maxqty-label').text('');
                            
                            // Recargar lista de reclamos
                            setTimeout(() => {
                                location.reload();
                            }, 2000);
                            
                        } else {
                            garantiasAjax.showToast(response.data.message || 'Error al enviar reclamo', 'error');
                        }
                    },
                    error: function() {
                        garantiasAjax.showToast('Error de conexión', 'error');
                    },
                    complete: function() {
                        $submitBtn.prop('disabled', false).text('ENVIAR RECLAMO');
                    }
                });
            });
        },
        
        /**
         * Validar formulario antes de enviar
         */
        validateForm: function($form) {
            let isValid = true;
            const errors = [];
            
            // Verificar que hay al menos un producto
            const validRows = $form.find('.reclamo-row').filter(function() {
                return $(this).find('.producto_hidden').val() !== '';
            });
            
            if (validRows.length === 0) {
                errors.push('Debes seleccionar al menos un producto');
                isValid = false;
            }
            
            // Verificar cada fila válida
            validRows.each(function() {
                const $row = $(this);
                const motivo = $row.find('.motivo_select').val();
                const cantidad = parseInt($row.find('.cantidad').val());
                const maxCantidad = parseInt($row.find('.cantidad').attr('max'));
                
                if (!motivo) {
                    errors.push('Debes seleccionar un motivo para cada producto');
                    isValid = false;
                }
                
                if (cantidad > maxCantidad) {
                    errors.push(`La cantidad no puede ser mayor a ${maxCantidad}`);
                    isValid = false;
                }
                
                // Verificar foto obligatoria
                const fotoFile = $row.find('input[name="foto[]"]')[0].files[0];
                if (!fotoFile) {
                    errors.push('La foto es obligatoria para cada producto');
                    isValid = false;
                }
            });
            
            // Mostrar errores
            if (!isValid) {
                errors.forEach(error => {
                    garantiasAjax.showToast(error, 'error');
                });
            }
            
            return isValid;
        },
        
        /**
         * Refrescar estado de garantías automáticamente
         */
        initStatusRefresh: function() {
            // Refrescar cada 30 segundos si estamos en la página de garantías
            if ($('.garantias-status-refresh').length > 0) {
                setInterval(() => {
                    garantiasAjax.refreshClaimStatuses();
                }, 30000);
            }
        },
        
        refreshClaimStatuses: function() {
            $('.garantia-row').each(function() {
                const $row = $(this);
                const garantiaId = $row.data('garantia-id');
                
                if (garantiaId) {
                    $.ajax({
                        url: wcGarantiasAjax.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'wcgarantias_get_claim_status',
                            garantia_id: garantiaId,
                            nonce: wcGarantiasAjax.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                const $estadoCell = $row.find('.estado-cell');
                                const estadoActual = $estadoCell.text().trim();
                                const estadoNuevo = response.data.estado_nombre;
                                
                                if (estadoActual !== estadoNuevo) {
                                    $estadoCell.text(estadoNuevo).addClass('estado-actualizado');
                                    setTimeout(() => {
                                        $estadoCell.removeClass('estado-actualizado');
                                    }, 3000);
                                }
                            }
                        }
                    });
                }
            });
        },
        
        /**
         * Sistema de comentarios en tiempo real
         */
        initComments: function() {
            // Enviar comentario
            $(document).on('submit', '.comentario-form', function(e) {
                e.preventDefault();
                
                const $form = $(this);
                const $textarea = $form.find('textarea');
                const $submitBtn = $form.find('button[type="submit"]');
                const garantiaId = $form.data('garantia-id');
                const comentario = $textarea.val().trim();
                
                if (!comentario) {
                    garantiasAjax.showToast('Escribe un comentario', 'error');
                    return;
                }
                
                $submitBtn.prop('disabled', true).text('Enviando...');
                
                $.ajax({
                    url: wcGarantiasAjax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wcgarantias_add_comment',
                        garantia_id: garantiaId,
                        comentario: comentario,
                        nonce: wcGarantiasAjax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            garantiasAjax.showToast('Comentario agregado', 'success');
                            $textarea.val('');
                            garantiasAjax.loadComments(garantiaId);
                        } else {
                            garantiasAjax.showToast(response.data.message || 'Error al agregar comentario', 'error');
                        }
                    },
                    error: function() {
                        garantiasAjax.showToast('Error de conexión', 'error');
                    },
                    complete: function() {
                        $submitBtn.prop('disabled', false).text('Enviar');
                    }
                });
            });
            
            // Cargar comentarios al abrir detalle
            $(document).on('click', '.ver-comentarios-btn', function() {
                const garantiaId = $(this).data('garantia-id');
                garantiasAjax.loadComments(garantiaId);
            });
        },
        
        loadComments: function(garantiaId) {
            const $container = $(`.comentarios-container[data-garantia-id="${garantiaId}"]`);
            
            $.ajax({
                url: wcGarantiasAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wcgarantias_get_comments',
                    garantia_id: garantiaId,
                    nonce: wcGarantiasAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        garantiasAjax.renderComments($container, response.data.comentarios);
                    }
                }
            });
        },
        
        renderComments: function($container, comentarios) {
            $container.empty();
            
            if (comentarios.length === 0) {
                $container.html('<p><em>No hay comentarios aún.</em></p>');
                return;
            }
            
            comentarios.forEach(comentario => {
                const adminClass = comentario.es_admin ? 'comentario-admin' : 'comentario-cliente';
                const autorLabel = comentario.es_admin ? '👨‍💼 Admin' : '👤 Cliente';
                
                const comentarioHtml = `
                    <div class="comentario ${adminClass}" style="
                        margin-bottom: 15px;
                        padding: 12px;
                        border-left: 4px solid ${comentario.es_admin ? '#007cba' : '#28a745'};
                        background: ${comentario.es_admin ? '#f8f9fa' : '#f0f8f0'};
                        border-radius: 0 5px 5px 0;
                    ">
                        <div class="comentario-header" style="
                            display: flex;
                            justify-content: space-between;
                            margin-bottom: 8px;
                            font-size: 0.9em;
                            color: #666;
                        ">
                            <span><strong>${autorLabel} ${comentario.usuario_nombre}</strong></span>
                            <span>${comentario.fecha_legible}</span>
                        </div>
                        <div class="comentario-texto">${comentario.comentario}</div>
                    </div>
                `;
                
                $container.append(comentarioHtml);
            });
        },
        
        /**
         * Dashboard con estadísticas en tiempo real
         */
        initDashboard: function() {
            if ($('#garantias-dashboard').length > 0) {
                garantiasAjax.loadDashboardData();
                
                // Actualizar cada 60 segundos
                setInterval(() => {
                    garantiasAjax.loadDashboardData();
                }, 60000);
            }
        },
        
        loadDashboardData: function() {
            $.ajax({
                url: wcGarantiasAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wcgarantias_get_dashboard_data',
                    nonce: wcGarantiasAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        garantiasAjax.renderDashboard(response.data);
                    }
                },
                error: function() {
                    // Fallar silenciosamente para no molestar al usuario
                    console.log('Error cargando dashboard de garantías');
                }
            });
        },
        
        renderDashboard: function(data) {
            const dashboardHtml = `
                <div class="garantias-dashboard-stats" style="
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 20px;
                    margin-bottom: 30px;
                ">
                    <div class="stat-card" style="
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        color: white;
                        padding: 20px;
                        border-radius: 10px;
                        text-align: center;
                        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                    ">
                        <div class="stat-number" style="font-size: 2.5em; font-weight: bold; margin-bottom: 5px;">
                            ${data.total}
                        </div>
                        <div class="stat-label">Total Garantías</div>
                    </div>
                    
                    <div class="stat-card" style="
                        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
                        color: white;
                        padding: 20px;
                        border-radius: 10px;
                        text-align: center;
                        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                    ">
                        <div class="stat-number" style="font-size: 2.5em; font-weight: bold; margin-bottom: 5px;">
                            ${data.pendientes}
                        </div>
                        <div class="stat-label">Pendientes</div>
                    </div>
                    
                    <div class="stat-card" style="
                        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
                        color: white;
                        padding: 20px;
                        border-radius: 10px;
                        text-align: center;
                        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                    ">
                        <div class="stat-number" style="font-size: 2.5em; font-weight: bold; margin-bottom: 5px;">
                            ${data.aprobadas}
                        </div>
                        <div class="stat-label">Aprobadas</div>
                    </div>
                    
                    <div class="stat-card" style="
                        background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
                        color: white;
                        padding: 20px;
                        border-radius: 10px;
                        text-align: center;
                        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                    ">
                        <div class="stat-number" style="font-size: 2.5em; font-weight: bold; margin-bottom: 5px;">
                            ${data.rechazadas}
                        </div>
                        <div class="stat-label">Rechazadas</div>
                    </div>
                </div>
                
                ${data.ultimas.length > 0 ? `
                    <div class="garantias-recientes">
                        <h4 style="margin-bottom: 15px; color: #333;">Últimas Garantías</h4>
                        <div class="garantias-recientes-list">
                            ${data.ultimas.map(garantia => `
                                <div class="garantia-reciente" style="
                                    display: flex;
                                    justify-content: space-between;
                                    align-items: center;
                                    padding: 12px;
                                    margin-bottom: 8px;
                                    background: #f8f9fa;
                                    border-radius: 5px;
                                    border-left: 4px solid #007cba;
                                ">
                                    <div>
                                        <strong>${garantia.codigo}</strong>
                                        <br>
                                        <small style="color: #666;">${garantia.fecha}</small>
                                    </div>
                                    <span class="estado-badge" style="
                                        background: #e9ecef;
                                        color: #495057;
                                        padding: 4px 8px;
                                        border-radius: 12px;
                                        font-size: 0.8em;
                                    ">${garantia.estado}</span>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                ` : ''}
            `;
            
            $('#garantias-dashboard').html(dashboardHtml);
        }
    };
    
    // CSS para animaciones
    const styles = `
        <style>
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .estado-actualizado {
            background-color: #fff3cd !important;
            transition: background-color 0.3s ease;
        }
        
        .producto_autocomplete.loading {
            background-image: url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHZpZXdCb3g9IjAgMCAyMCAyMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KICAgIDxjaXJjbGUgY3g9IjEwIiBjeT0iMTAiIHI9IjMiIGZpbGw9Im5vbmUiIHN0cm9rZT0iIzk5OSIgc3Ryb2tlLXdpZHRoPSIyIj4KICAgICAgICA8YW5pbWF0ZSBhdHRyaWJ1dGVOYW1lPSJyIiB2YWx1ZXM9IjM7NjszIiBkdXI9IjFzIiByZXBlYXRDb3VudD0iaW5kZWZpbml0ZSIvPgogICAgPC9jaXJjbGU+Cjwvc3ZnPg==');
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 20px 20px;
        }
        
        /* Responsivo */
        @media (max-width: 768px) {
            .garantias-dashboard-stats {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 15px !important;
            }
            
            .stat-card {
                padding: 15px !important;
            }
            
            .stat-number {
                font-size: 2em !important;
            }
        }
        
        @media (max-width: 480px) {
            .garantias-dashboard-stats {
                grid-template-columns: 1fr !important;
            }
        }
        </style>
    `;
    
    $('head').append(styles);
    
    // Inicializar cuando el DOM esté listo
    garantiasAjax.init();
});