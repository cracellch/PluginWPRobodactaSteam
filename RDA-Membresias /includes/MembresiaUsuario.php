<?php
// Shortcode: [rd_validar_licencia]
add_shortcode('rd_validar_licencia', function(){
    $licencia = '';
    $licencia_mask = '';
    $user_id = get_current_user_id();

    if ($user_id) {
        $licencia = get_user_meta($user_id, 'rd_codigo_membresia', true);
        if ($licencia && function_exists('rd_enmascarar_licencia')) {
            $licencia_mask = strtoupper(rd_formatear_cod_con_guiones(rd_enmascarar_licencia($licencia)));
        }
    }

    ob_start(); ?>
    <div id="licencia-ui-container" style="max-width:400px;margin:2em auto;padding:28px 24px;border-radius:18px;background:#fafcff;box-shadow:0 1px 6px #2e467b22;text-align:center;">
        <h3 style="color:#2e467b;">Vincular Licencia</h3>
        <?php if ($licencia_mask): ?>
            <div style="background:#e9fff5; color:#228b22; border-radius:8px; padding:14px; margin-bottom:20px;">
                <b>Tu licencia activa:</b><br>
                <span style="font-size:1.2em;letter-spacing:2px;"><?php echo esc_html($licencia_mask); ?></span>
            </div>
        <?php endif; ?>
        <input type="text" id="licencia_input" placeholder="Introduce tu licencia" style="padding:8px 12px;width:75%;border-radius:8px;border:1px solid #ccc;font-size:1.1em;">
        <button id="btn_validar_licencia" style="margin-left:8px;padding:8px 20px;border-radius:8px;border:none;background:#2e467b;color:#fff;font-weight:bold;cursor:pointer;">
            Validar
        </button>
    </div>
    <div id="modal-validacion" style="display:none; position:fixed; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.3); z-index:9999; justify-content:center; align-items:center;">
      <div id="modal-validacion-msg" style="background:#fff; padding:32px 40px; border-radius:18px; box-shadow:0 0 20px #0002; font-size:1.3em; color:#2e467b; text-align:center;"></div>
    </div>
    <script>
    function mostrarModalValidacion(mensaje, esExito=false) {
        var modal = document.getElementById('modal-validacion');
        var msg = document.getElementById('modal-validacion-msg');
        msg.innerHTML = mensaje;
        msg.style.color = esExito ? "#228b22" : "#a50c0c";
        modal.style.display = "flex";
        setTimeout(function(){
            modal.style.display = "none";
        }, 3200);
    }
    document.getElementById('btn_validar_licencia')?.addEventListener('click', function(){
        let licencia = document.getElementById('licencia_input').value.trim();
        if(!licencia){
            mostrarModalValidacion('Por favor, ingresa tu licencia.');
            return;
        }
        this.disabled = true;
        this.textContent = "Validando...";
        fetch(ajaxurl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=rd_validar_y_asignar_licencia&licencia=' + encodeURIComponent(licencia)
        }).then(res=>res.json()).then(res=>{
            this.disabled = false;
            this.textContent = "Validar";
            if(res.success){
                mostrarModalValidacion('¡Licencia vinculada exitosamente!<br>Ahora tienes acceso a la plataforma.', true);
                setTimeout(()=>window.location.reload(), 3000); // Recarga para mostrar la licencia activa
            }else{
                mostrarModalValidacion(res.data || 'Licencia no válida o ya utilizada.');
            }
        }).catch(()=>{
            this.disabled = false;
            this.textContent = "Validar";
            mostrarModalValidacion('Hubo un error al validar la licencia.');
        });
    });
    </script>
    <?php
    return ob_get_clean();
});
