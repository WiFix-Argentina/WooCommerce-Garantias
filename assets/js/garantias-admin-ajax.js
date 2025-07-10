/**
 * WooCommerce Garantías - Admin AJAX
 * Panel de administración mejorado
 */

jQuery(document).ready(function($) {
    'use strict';
    
    const garantiasAdmin = {
        
        init: function() {
            this.initQuickActions();
            this.initBulkActions();
            this.initInlineEdit();
            this.initAutoRefresh();
            this.initStatusUpdates();
        },
        
        /**
         * Acciones rápidas en cada fila
         */
        initQuickActions: function() {
            // Cambio rápido de estado
            $(document).on('change', '.quick-status-change', function() {
                const $select = $(this);
                const garantiaId = $select.data('garantia-id');
                const nuevoEstado = $select.val();
                const $row = $select.closest('tr');
                
                if (!garantiaId || !nuevoEstado) return;
                
                garantiasAdmin.showRowLoading($row);
                
                $.ajax({
                    url: wcGarantiasAdminAjax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wcgarantias_admin_update_status',
                        garantia_id: garantiaId,
                        estado: nuevoEstado,
                        nonce: wcGarantiasAdminAjax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            garantiasAdmin.showNotification('Estado actualizado correctamente', 'success');
                            $row.addClass('row-updated');
                            setTimeout(() => $row.removeClass('row-updated'), 2000);
                        } else {
                            garantiasAdmin.showNotification(response.data.message || 'Error al actualizar', 'error');
                            $select.val($select.data('original-value')); // Revertir
                        }
                    },
                    error: function() {
                        garantiasAdmin.showNotification('Error de conexión', 'error');
                        $select.val($select.data('original-value')); // Revertir
                    },
                    complete: function() {
                        garantiasAdmin.hideRowLoading($row);
                    }
                });
            });
            
            // Guardar original value para revertir en caso de error
            $('.quick-status-change').each(function() {
                $(this).data('original-value', $(this).val());
            });
        },
        
        /**
         * Acciones en lote
         */
        initBulkActions: function() {
            // Seleccionar todos
            $('#select-all-garantias').on('change', function() {
                $('.garantia-checkbox').prop('checked', this.checked);
                garantiasAdmin.updateBulkActionButton();
            });
            
            // Checkbox individual
            $(document).on('change', '.garantia-checkbox', function() {
                garantiasAdmin.updateBulkActionButton();
                
                // Actualizar "seleccionar todos"
                const totalCheckboxes = $('.garantia-checkbox').length;
                const checkedCheckboxes = $('.garantia-checkbox:checked').length;
                
                $('#select-all-garantias').prop('indeterminate', 
                    checkedCheckboxes > 0 && checkedCheckboxes < totalCheckboxes
                );
                $('#select-all-garantias').prop('checked', 
                    checkedCheckboxes === totalCheckboxes
                );
            });
            
            // Ejecutar acción en lote
            $('#bulk-action-btn').on('click', function() {
                const accion = $('#bulk-action-select').val();
                const garantiasIds = $('.garantia-checkbox:checked').map(function() {
                    return $(this).val();
                }).get();
                
                if (!accion || garantiasIds.length === 0) {
                    garantiasAdmin.showNotification('Selecciona una acción y al menos una garantía', 'warning');
                    return;
                }
                
                if (!confirm(wcGarantiasAdminAjax.strings.confirm_bulk)) {
                    return;
                }
                
                garantiasAdmin.executeBulkAction(accion, garantiasIds);
            });
        },
        
        updateBulkActionButton: function() {
            const checkedCount = $('.garantia-checkbox:checked').length;
            const $btn = $('#bulk-action-btn');
            
            if (checkedCount > 0) {
                $btn.prop('disabled', false).text(`Aplicar a ${checkedCount} elementos`);
            } else {
                $btn.prop('disabled', true).text('Aplicar acción');
            }
        },
        
        executeBulkAction: function(accion, garantiasIds) {
            const $btn = $('#bulk-action-btn');
            $btn.prop('disabled', true).text('Procesando...');
            
            $.ajax({
                url: wcGarantiasAdminAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wcgarantias_admin_bulk_action',
                    bulk_action: accion,
                    garantias_ids: garantiasIds,
                    nonce: wcGarantiasAdminAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        garantiasAdmin.showNotification(`Acción aplicada a ${garantiasIds.length} elementos`, 'success');
                        location.reload(); // Recargar para mostrar cambios
                    } else {
                        garantiasAdmin.showNotification(response.data.message || 'Error en acción en lote', 'error');
                    }
                },
                error: function() {
                    garantiasAdmin.showNotification('Error de conexión', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Aplicar acción');
                }
            });
        },
        
        /**
         * Edición inline de comentarios
         */
        initInlineEdit: function() {
            // Hacer comentarios editables
            $(document).on('click', '.comentario-editable', function() {
                const $div = $(this);
                const garantiaId = $div.data('garantia-id');
                const comentarioActual = $div.text().trim();
                
                if ($div.hasClass('editing')) return;
                
                $div.addClass('editing');
                const $textarea = $(`<textarea class="comentario-textarea" style="width:100%;height:60px;">${comentarioActual}</textarea>`);
                const $btnSave = $('<button type="button" class="button button-small save-comentario" style="margin-top:5px;margin-right:5px;">Guardar</button>');
                const $btnCancel = $('<button type="button" class="button button-small cancel-comentario">Cancelar</button>');
                
                $div.html('').append($textarea).append($btnSave).append($btnCancel);
                $textarea.focus();
                
                // Guardar comentario
                $btnSave.on('click', function() {
                    const nuevoComentario = $textarea.val().trim();
                    
                    $.ajax({
                        url: wcGarantiasAdminAjax.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'wcgarantias_admin_add_note',
                            garantia_id: garantiaId,
                            nota: nuevoComentario,
                            nonce: wcGarantiasAdminAjax.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                $div.removeClass('editing').text(nuevoComentario);
                                garantiasAdmin.showNotification('Comentario guardado', 'success');
                            } else {
                                garantiasAdmin.showNotification('Error al guardar', 'error');
                            }
                        },
                        error: function() {
                            garantiasAdmin.showNotification('Error de conexión', 'error');
                        }
                    });
                });
                
                // Cancelar edición
                $btnCancel.on('click', function() {
                    $div.removeClass('editing').text(comentarioActual);
                });
            });
        },
        
        /**
         * Auto-refresh de la lista
         */
        initAutoRefresh: function() {
            // Auto-refresh cada 60 segundos
            setInterval(() => {
                garantiasAdmin.refreshNewClaimsCount();
            }, 60000);
        },
        
        refreshNewClaimsCount: function() {
            $.ajax({
                url: wcGarantiasAdminAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wcgarantias_admin_get_new_count',
                    nonce: wcGarantiasAdminAjax.nonce
                },
                success: function(response) {
                    if (response.success && response.data.new_count > 0) {
                        // Actualizar badge del menú
                        const $menuBadge = $('#adminmenu a[href*="wc-garantias"] .update-plugins');
                        if ($menuBadge.length > 0) {
                            $menuBadge.text(response.data.new_count);
                        }
                        
                        // Mostrar notificación si hay nuevas
                        if (response.data.new_count > 0) {
                            garantiasAdmin.showNotification(
                                `${response.data.new_count} nueva(s) garantía(s) recibida(s)`, 
                                'info'
                            );
                        }
                    }
                }
            });
        },
        
        /**
         * Actualizaciones de estado en tiempo real
         */
        initStatusUpdates: function() {
            // Confirmar acciones críticas
            $(document).on('click', '.action-approve, .action-reject', function(e) {
                const accion = $(this).hasClass('action-approve') ? 'aprobar' : 'rechazar';
                const codigo = $(this).closest('tr').find('.codigo-garantia').text().trim();
                
                if (!confirm(`¿Confirmas ${accion} la garantía ${codigo}?`)) {
                    e.preventDefault();
                    return false;
                }
            });
            
            // Validar motivo de rechazo
            $(document).on('submit', '.form-rechazo', function(e) {
                const motivo = $(this).find('textarea[name="motivo_rechazo"]').val().trim();
                
                if (!motivo) {
                    e.preventDefault();
                    garantiasAdmin.showNotification('Debes especificar un motivo de rechazo', 'error');
                    return false;
                }
            });
        },
        
        /**
         * Funciones de UI
         */
        showRowLoading: function($row) {
            $row.css('position', 'relative').append(
                '<div class="row-loading" style="position:absolute;top:0;left:0;right:0;bottom:0;background:rgba(255,255,255,0.8);display:flex;align-items:center;justify-content:center;z-index:10;"><div class="spinner-border" style="border:2px solid #f3f3f3;border-top:2px solid #007cba;border-radius:50%;width:20px;height:20px;animation:spin 1s linear infinite;"></div></div>'
            );
        },
        
        hideRowLoading: function($row) {
            $row.find('.row-loading').remove();
        },
        
        showNotification: function(message, type = 'info') {
            // Crear container si no existe
            if ($('#admin-notifications').length === 0) {
                $('body').append('<div id="admin-notifications" style="position:fixed;top:32px;right:20px;z-index:999999;"></div>');
            }
            
            const colors = {
                success: '#28a745',
                error: '#dc3545',
                warning: '#ffc107',
                info: '#17a2b8'
            };
            
            const notification = $(`
                <div class="admin-notification" style="
                    background: ${colors[type] || colors.info};
                    color: white;
                    padding: 12px 20px;
                    margin-bottom: 10px;
                    border-radius: 4px;
                    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
                    max-width: 300px;
                    opacity: 0;
                    transform: translateX(100%);
                    transition: all 0.3s ease;
                ">
                    ${message}
                    <button type="button" class="close-notification" style="
                        background: none;
                        border: none;
                        color: white;
                        float: right;
                        font-size: 16px;
                        font-weight: bold;
                        margin-left: 10px;
                        cursor: pointer;
                    ">&times;</button>
                </div>
            `);
            
            $('#admin-notifications').append(notification);
            
            // Animación de entrada
            setTimeout(() => {
                notification.css({opacity: 1, transform: 'translateX(0)'});
            }, 100);
            
            // Auto-remove
            setTimeout(() => {
                notification.css({opacity: 0, transform: 'translateX(100%)'});
                setTimeout(() => notification.remove(), 300);
            }, 5000);
            
            // Close button
            notification.find('.close-notification').on('click', function() {
                notification.css({opacity: 0, transform: 'translateX(100%)'});
                setTimeout(() => notification.remove(), 300);
            });
        }
    };
    
    // Estilos CSS
    const adminStyles = `
        <style>
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .row-updated {
            background-color: #d4edda !important;
            transition: background-color 0.3s ease;
        }
        
        .comentario-editable {
            cursor: pointer;
            border: 1px dashed transparent;
            padding: 5px;
            min-height: 20px;
        }
        
        .comentario-editable:hover {
            border-color: #ccc;
            background-color: #f9f9f9;
        }
        
        .comentario-editable.editing {
            border-color: #007cba;
            background-color: #fff;
        }
        
        .garantia-checkbox {
            transform: scale(1.2);
        }
        
        .quick-status-change {
            border: none;
            background: #f1f1f1;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
        }
        
        .quick-status-change:focus {
            outline: 2px solid #007cba;
        }
        </style>
    `;
    
    $('head').append(adminStyles);
    
    // Inicializar
    garantiasAdmin.init();
});