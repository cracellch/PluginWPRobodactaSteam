<?php
// Shortcode: [rd_validar_licencia]
add_shortcode('rd_validar_licencia', function(){
    if (!is_user_logged_in()) {
        return '<div style="max-width:480px;margin:2em auto;padding:20px;border-radius:12px;background:#fff4f4;color:#7a1010;">Debes iniciar sesión para canjear una licencia.</div>';
    }

    $tiene_licencia = false;
    $licencia_mask  = '';
    $user_id        = get_current_user_id();

    if ($user_id) {
        global $wpdb;
        $table    = $wpdb->prefix . 'rd_membresias';
        $licencia = $wpdb->get_var($wpdb->prepare(
            "SELECT codigo FROM $table WHERE user_id = %d AND status = 'asignado' LIMIT 1", $user_id
        ));
        if ($licencia && function_exists('rd_enmascarar_licencia')) {
            $licencia_mask  = strtoupper(rd_formatear_cod_con_guiones(rd_enmascarar_licencia($licencia)));
            $tiene_licencia = true;
        }
    }

    $nonce = wp_create_nonce('rd-lic');

    ob_start(); ?>
    <div id="licencia-ui-container" style="max-width:420px;margin:2em auto;padding:24px;border-radius:16px;background:#fafcff;box-shadow:0 1px 6px #2e467b22;text-align:center;">
        <h3 style="color:#2e467b;margin-top:0;">Canjear c&oacute;digo</h3>

        <?php if ($tiene_licencia): ?>
        <div style="background:#e9fff5;color:#228b22;border-radius:8px;padding:12px;margin-bottom:16px;">
            <b>Tu licencia activa:</b><br>
            <span style="font-size:1.1em;letter-spacing:2px;"><?php echo esc_html($licencia_mask); ?></span>
        </div>
        <?php endif; ?>

        <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
            <input
                type="text"
                id="licencia_input"
                placeholder="CG/ES + 16 dígitos o código enmascarado"
                autocomplete="off"
                inputmode="latin"
                style="padding:10px 12px;width:260px;border-radius:10px;border:1px solid #cfd6e4;font-size:1.05em;text-transform:uppercase">
            <button id="btn_validar_licencia"
                style="padding:10px 16px;border-radius:10px;border:none;background:#2e467b;color:#fff;font-weight:700;cursor:pointer;">
                Validar
            </button>
        </div>
        <div id="licencia_msg" style="margin-top:12px;font-size:.95em;color:#6b7280;"></div>
    </div>

    <div id="modal-validacion" style="display:none;position:fixed;left:0;top:0;width:100vw;height:100vh;background:rgba(0,0,0,.3);z-index:9999;justify-content:center;align-items:center;">
      <div id="modal-validacion-msg" style="background:#fff;padding:28px 36px;border-radius:16px;box-shadow:0 0 20px #0002;font-size:1.1em;color:#2e467b;text-align:center;"></div>
    </div>

    <script>
    (function(){
      const ajaxurl = (typeof window.ajaxurl!=='undefined') ? window.ajaxurl : '<?php echo admin_url('admin-ajax.php'); ?>';
      const nonce   = '<?php echo esc_js($nonce); ?>';
      const $in     = document.getElementById('licencia_input');
      const $btn    = document.getElementById('btn_validar_licencia');
      const $msg    = document.getElementById('licencia_msg');

      function mostrarModal(texto, ok=false){
        const modal = document.getElementById('modal-validacion');
        const box   = document.getElementById('modal-validacion-msg');
        box.innerHTML = texto;
        box.style.color = ok ? '#228b22' : '#a50c0c';
        modal.style.display = 'flex';
        setTimeout(()=> modal.style.display='none', 3200);
      }

      function normalize(raw){
        return (raw||'').toUpperCase().replace(/[\s-]+/g,'').trim();
      }

      // UX: convertir a mayúsculas en vivo
      $in.addEventListener('input', ()=>{ $in.value = $in.value.toUpperCase(); });

      // Enviar con Enter
      $in.addEventListener('keydown', (e)=>{ if(e.key==='Enter'){ e.preventDefault(); $btn.click(); }});

      $btn.addEventListener('click', function(){
        const compact = normalize($in.value);
        if(!compact){
          mostrarModal('Por favor, ingresa tu licencia.');
          return;
        }

        // Aceptar real (ES/CG + 16 dígitos) o enmascarado (hashids, 16+)
        const looksReal   = /^(ES|CG)\d{16}$/.test(compact);
        const looksMasked = /^[A-Z0-9]{16,}$/.test(compact);
        if(!looksReal && !looksMasked){
          mostrarModal('Formato no reconocido. Verifica tu c&oacute;digo.');
          return;
        }

        if (this.dataset.loading === '1') return; // evita doble click
        this.dataset.loading = '1';
        const prev = this.textContent;
        this.disabled = true;
        this.textContent = 'Validando...';
        $msg.textContent = '';

        const body = new URLSearchParams();
        body.set('action','rd_validar_y_asignar_licencia');
        body.set('licencia', compact);
        body.set('_ajax_nonce', nonce);

        fetch(ajaxurl, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString() })
          .then(r=>r.json())
          .then(r=>{
            this.dataset.loading = '0';
            this.disabled = false;
            this.textContent = prev;

            if(r && r.success){
              mostrarModal('¡Licencia vinculada exitosamente!<br>Ya tienes acceso.', true);
              setTimeout(()=>window.location.reload(), 1200);
            } else {
              const msg = (r && r.data) ? r.data : 'Licencia no válida o ya utilizada.';
              mostrarModal(msg);
            }
          })
          .catch(()=>{
            this.dataset.loading = '0';
            this.disabled = false;
            this.textContent = prev;
            mostrarModal('Hubo un error al validar la licencia.');
          });
      });
    })();
    </script>
    <?php return ob_get_clean();
});
