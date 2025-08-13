<?php
/**
 * Selección de Kit (Tutor y Alumno) — por Grupo y por Tema (post_id)
 * Requiere LearnDash (grupos)
 */
if (!defined('ABSPATH')) exit;

/* =========================================================
 *  CATÁLOGO por post_id (temas) -> kits disponibles
 * ========================================================= */
function trd_kit_choices_for_post( $post_id ) {
    $CAT = [

        // Nivel 1 — Tema: 12039 (3 opciones)
        12039 => [
            'motobot' => [
                'label' => 'KIT MotoBot',
                'video' => 'https://player.vimeo.com/video/1094239377',
                'img'   => 'https://robodacta-steam.mx/wp-content/uploads/2025/07/MotoP.png',
            ],
            'ranabot' => [
                'label' => 'KIT RanaBot',
                'video' => 'https://player.vimeo.com/video/1099760261',
                'img'   => 'https://robodacta-steam.mx/wp-content/uploads/2025/07/RanaP.png',
            ],
            'rover' => [
                'label' => 'KIT Rover',
                'video' => 'https://player.vimeo.com/video/1006310035',
                'img'   => 'https://robodacta-steam.mx/wp-content/uploads/2025/07/RovP.png',
            ],
        ],

        // Nivel 2 — TODOS FIJOS
        17745 => [ // Armado de plano inclinado
            'plano_inclinado' => [ 'label' => 'Plano inclinado', 'video' => null, 'img' => 'https://robodacta-steam.mx/wp-content/uploads/2025/08/PlanoInclinado.png' ],
        ],
        17750 => [ // Armado de palanca
            'palanca' => [ 'label' => 'Palanca', 'video' => null, 'img' => 'https://robodacta-steam.mx/wp-content/uploads/2025/08/Palanca.png' ],
        ],
        15089 => [ // Armado de polea
            'polea' => [ 'label' => 'Polea', 'video' => null, 'img' => 'https://robodacta-steam.mx/wp-content/uploads/2025/08/Polea.png' ],
        ],
        17757 => [ // Armado de cuña
            'cuna' => [ 'label' => 'Cuña', 'video' => null, 'img' => 'https://robodacta-steam.mx/wp-content/uploads/2025/08/Cuna.png' ],
        ],

        // Nivel 3 — Tema: 21940 (5 opciones)
        21940 => [
            'triceratops'       => [ 'label' => 'Triceratops',                   'video' => 'https://player.vimeo.com/video/1107162050', 'img' => 'https://robodacta-steam.mx/wp-content/uploads/2025/08/Triceratops.png' ],
            'rinobot'           => [ 'label' => 'RinoBot',                       'video' => 'https://player.vimeo.com/video/1099029415', 'img' => 'https://robodacta-steam.mx/wp-content/uploads/2025/08/Rinobot-1.png' ],
            'caminante'         => [ 'label' => 'Caminante',                     'video' => 'https://player.vimeo.com/video/1101323011', 'img' => 'https://robodacta-steam.mx/wp-content/uploads/2025/08/Caminante.png' ],
            'elevador_mecanico' => [ 'label' => 'Elevador Mecánico',            'video' => 'https://player.vimeo.com/video/1074818826', 'img' => 'https://robodacta-steam.mx/wp-content/uploads/2025/08/ElevadorMecanico.png' ],
            'molino_viento'     => [ 'label' => 'Molino de viento mecánico',    'video' => 'https://player.vimeo.com/video/1107137891', 'img' => 'https://robodacta-steam.mx/wp-content/uploads/2025/08/MolinoDeViento.png' ],
        ],

        // Nivel 3 — Tema: 22618 (2 opciones)
        22618 => [
            'hormiga' => [ 'label' => 'Hormiga', 'video' => 'https://player.vimeo.com/video/1109540196', 'img' => 'https://robodacta-steam.mx/wp-content/uploads/2025/08/Hormiga.png' ],
            'rana'    => [ 'label' => 'Rana',    'video' => 'https://player.vimeo.com/video/1099760261', 'img' => 'https://robodacta-steam.mx/wp-content/uploads/2025/07/RanaP.png' ],
        ],

        // Nivel 4 — Tema: 32837 (varios)
        32837 => [
            'cabana_ecologica'    => [
                'label' => 'Cabaña ecológica',
                'video' => 'https://player.vimeo.com/video/1109543906',
                'img'   => 'https://robodacta-steam.mx/wp-content/uploads/2025/08/Cabana-1.png'
            ],
            'generador_electrico' => [
                'label' => 'Armado de generador de energía eléctrica',
                'video' => null,
                'img'   => null
            ],
            'vw_carrito_solar'    => [
                'label' => 'VW Carrito solar',
                'video' => 'https://player.vimeo.com/video/1109545149',
                'img'   => 'https://robodacta-steam.mx/wp-content/uploads/2025/08/VWCarroSolar.png'
            ],
            'brazo_hidraulico'    => [ 'label' => 'Brazo hidráulico',    'video' => null, 'img' => null ],
            'elevador_hidraulico' => [ 'label' => 'Elevador hidráulico', 'video' => null, 'img' => null ],
            'helicoptero'         => [ 'label' => 'Helicóptero',          'video' => null, 'img' => null ],
        ],

        // Nivel 5 — Tema: 32856 (2 kits)
        32856 => [
            'cinta_conductora' => [ 'label' => 'Proyecto con cinta conductora', 'video' => null, 'img' => null ],
            'arbolito_navidad' => [ 'label' => 'Arbolito de navidad',           'video' => null, 'img' => null ],
        ],

        // Nivel 6 — Tema: 32868 (kit fijo NUEVO nombre)
        32868 => [
            'seguidor_luz' => [ 'label' => 'Seguidor de Luz', 'video' => null, 'img' => null ],
        ],

        // Nivel 7 — Tema: 32870 (kit fijo NUEVO nombre)
        32870 => [
            'kit_robodacta_steam' => [ 'label' => 'Kit Robodacta STEAM', 'video' => null, 'img' => null ],
        ],
    ];

    $CAT = apply_filters('trd_kit_video_catalog', $CAT);
    return isset($CAT[(int)$post_id]) ? $CAT[(int)$post_id] : [];
}

/* =========================================================
 *  AGRUPACIÓN por nivel (para UI del tutor)
 * ========================================================= */
function trd_post_level_map() {
    return [
        12039 => 1,
        17745 => 2, 17750 => 2, 15089 => 2, 17757 => 2,
        21940 => 3, 22618 => 3,
        32837 => 4,
        32856 => 5,
        32868 => 6,
        32870 => 7,
    ];
}
function trd_post_level_label($level){ return $level ? ('Nivel '.$level) : 'Otros'; }
function trd_post_level($pid){ $m = trd_post_level_map(); return isset($m[(int)$pid]) ? (int)$m[(int)$pid] : 0; }
function trd_group_posts_by_level(array $post_ids){
    $out = [];
    foreach ($post_ids as $pid){ $lvl = trd_post_level($pid); $out[$lvl][] = (int)$pid; }
    ksort($out);
    return $out;
}

/* =========================================================
 *  LISTA de post_ids que usan kits
 * ========================================================= */
function trd_kit_catalog_post_ids() {
    static $cache = null;
    if ($cache !== null) return $cache;
    $ids = [12039,17745,17750,15089,17757,21940,22618,32837,32856,32868,32870];
    sort($ids);
    return $cache = $ids;
}

/* =========================================================
 *  OPTION: matriz de selecciones (grupo -> post -> kit)
 * ========================================================= */
function trd_kit_matrix_option_key() { return 'trd_group_post_kit_matrix'; }

/* =========================================================
 *  HELPERS para usuarios demo
 * ========================================================= */
// Puede ver todos los kits sin ser admin
function trd_is_preview_all_kits_user($user = null){
    if (!$user) $user = wp_get_current_user();
    if (!$user || !is_user_logged_in()) return false;

    // Admins ya pasan
    if (user_can($user, 'manage_options')) return true;
    // admins like
    // IDs que quieres que tengan acceso como admin
    $special_ids = [92, 50]; // aquí pones el/los ID(s) de usuario
    if ( in_array($user->ID, $special_ids, true) ) {
        return true;
    }
    return current_user_can('manage_options') || in_array('administrator', (array)$user->roles, true);
    // Flag por usuario (user meta) o cap específica (por si algún día la usas)
    $flag = get_user_meta($user->ID, 'trd_preview_all_kits', true);
    if ($flag === '1' || $flag === 1 || $flag === 'yes') return true;

    if (user_can($user, 'trd_preview_all_kits')) return true;

    return false;
}
/* =========================================================
 *  HELPERS de grupos
 * ========================================================= */
function trd_get_leader_groups_sorted($leader_id){
    if (!function_exists('learndash_get_administrators_group_ids')) return [];
    $gids = (array) learndash_get_administrators_group_ids((int)$leader_id);
    if (empty($gids)) return [];
    $names = [];
    foreach ($gids as $gid) $names[$gid] = get_the_title($gid);
    natcasesort($names);
    return array_keys($names);
}
function trd_get_selected_kit_for_group_post($group_id, $post_id){
    $mx = get_option(trd_kit_matrix_option_key(), []);
    return isset($mx[(int)$group_id][(int)$post_id]) ? $mx[(int)$group_id][(int)$post_id] : null;
}

/* =========================================================
 *  TRAYECTORIA FIJA ES (por post_id)
 * ========================================================= */
function trd_es_fixed_kit_for_post($post_id){
    $map = [
        // N1
        12039 => 'rover',
        // N2
        15089 => 'polea',
        17745 => 'plano_inclinado',
        17750 => 'palanca',
        17757 => 'cuna',
        // N3
        21940 => 'rinobot',
        22618 => 'rana',
        // N4
        32837 => 'vw_carrito_solar',
        // N5
        32856 => 'arbolito_navidad',
        // N6
        32868 => 'seguidor_luz',
        // N7
        32870 => 'kit_robodacta_steam',
    ];
    return isset($map[(int)$post_id]) ? $map[(int)$post_id] : null;
}

/* =========================================================
 *  SHORTCODE: Panel del Tutor  [trd_kit_selector_panel]
 * ========================================================= */
add_shortcode('trd_kit_selector_panel', function () {
    if (!is_user_logged_in() || !current_user_can('groups_manage_groups')) {
        return '<div style="padding:12px;background:#fff4f4;border:1px solid #f8caca;border-radius:8px;">No tienes permiso para ver este panel.</div>';
    }
    if (!function_exists('learndash_get_administrators_group_ids')) {
        return '<div style="padding:12px;background:#fff4f4;border:1px solid #f8caca;border-radius:8px;">LearnDash no está activo.</div>';
    }

    $leader_id      = get_current_user_id();
    $group_ids      = trd_get_leader_groups_sorted($leader_id);
    if (empty($group_ids)) {
        return '<div style="padding:12px;background:#fff;border:1px solid #eee;border-radius:8px;">No lideras ningún grupo.</div>';
    }

    $post_ids       = trd_kit_catalog_post_ids();
    $posts_by_level = trd_group_posts_by_level($post_ids);
    $nonce          = wp_create_nonce('trd-kit-matrix');

    ob_start(); ?>
    <style>
      .trd-kit-wrap{max-width:1060px;margin:1.25em auto;font:14px/1.5 system-ui}
      /* Group card */
      .trd-group{background:#fff;border:1px solid #e9eef5;border-radius:12px;margin:18px 0;box-shadow:0 2px 8px #2e467b1a;overflow:hidden}
      .trd-accordion{display:flex;align-items:center;gap:10px;width:100%;text-align:left;border:0;cursor:pointer}
      .trd-group-h.trd-accordion{background:#2e467b;color:#fff;padding:12px 14px;font-weight:700}
      .trd-chevron{display:inline-flex;align-items:center;justify-content:center;transition:transform .22s}
      .trd-chevron svg{display:block}
      .trd-accordion[aria-expanded="false"] .trd-chevron{transform:rotate(-90deg)}
      .trd-group-body{padding:0}
      /* Level section */
      .trd-level{border-top:1px solid #e2e8f0}
      .trd-level-h.trd-accordion{background:#f0f4fb;color:#2e467b;padding:10px 14px;font-weight:700}
      .trd-level-body{padding:0}
      /* Post & kit cards */
      .trd-post{padding:12px 14px;border-top:1px solid #eef2f7;background:#fff}
      .trd-post:nth-child(odd){background:#fffaf7}
      .trd-post-h{display:flex;align-items:center;gap:10px;margin-bottom:10px}
      .trd-post-title{font-weight:700;color:#2e467b}
      .trd-kits{display:flex;gap:14px;flex-wrap:wrap}
      .trd-kit-card{border:2px solid #cfd6e4;border-radius:10px;overflow:hidden;width:220px;background:#fff;cursor:pointer;position:relative}
      .trd-kit-card input{display:none}
      .trd-kit-thumb{aspect-ratio:16/10;background:#f7fafc;display:block}
      .trd-kit-thumb img{width:100%;height:100%;object-fit:cover;display:block}
      .trd-kit-title{padding:10px 12px;font-weight:600;color:#2e467b}
      .trd-kit-card input:checked + .trd-kit-thumb{outline:4px solid #f16d10}
      .trd-chip-fixed{display:inline-block;background:#e9eef5;color:#2e467b;padding:4px 8px;border-radius:999px;font-size:12px}
      /* Footer */
      .trd-save{margin:14px 0;display:flex;gap:10px;align-items:center}
      .trd-btn{appearance:none;border:none;background:#f16d10;color:#fff;font-weight:700;padding:10px 16px;border-radius:10px;cursor:pointer;text-decoration:none;display:inline-block}
      .trd-btn:hover{background:#2e467b;color:#fff}
      .trd-btn[disabled]{opacity:.55;cursor:default}
      .trd-tools{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px}
      .trd-mini{font-size:12px;padding:6px 10px;border-radius:8px}
      /* Toast */
      .trd-toast{position:fixed;right:24px;bottom:24px;background:#2e467b;color:#fff;padding:12px 16px;border-radius:8px;box-shadow:0 6px 20px #0002;opacity:0;transition:opacity .25s;z-index:9999}
      .trd-toast.show{opacity:1}
    </style>
    <div class="trd-kit-wrap">
      <h3 style="margin:.2em 0 10px;color:#2e467b">Selección de Kit por Grupo y por Tema</h3>
      <p>Los temas se agrupan por <strong>nivel</strong>. Si un tema es <em>fijo</em>, no requiere selección.</p>

      <!-- Herramientas rápidas -->
      <div class="trd-tools">
        <button type="button" class="trd-btn trd-mini" id="trd-expand-all">Expandir todo</button>
        <button type="button" class="trd-btn trd-mini" id="trd-collapse-all">Contraer todo</button>
      </div>

      <form id="trd-kit-matrix-form">
        <?php foreach ($group_ids as $gid): ?>
          <?php
            $group_title = esc_html(get_the_title($gid));
            $group_body_id = 'trd-group-body-' . (int)$gid;
          ?>
          <div class="trd-group" data-group="<?php echo (int)$gid; ?>">
            <button type="button"
                    class="trd-accordion trd-group-h"
                    aria-expanded="false"
                    aria-controls="<?php echo esc_attr($group_body_id); ?>"
                    id="trd-group-btn-<?php echo (int)$gid; ?>">
              <span class="trd-chevron" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24">
                  <polyline points="8 10 12 14 16 10" fill="none" stroke="#fff" stroke-width="2"/>
                </svg>
              </span>
              <span><?php echo $group_title; ?> <small style="opacity:.75">(#<?php echo (int)$gid; ?>)</small></span>
            </button>

            <div class="trd-group-body" id="<?php echo esc_attr($group_body_id); ?>" hidden>
              <?php foreach ($posts_by_level as $lvl => $pids_lvl): ?>
                <?php
                  $level_body_id = 'trd-level-body-' . (int)$gid . '-' . (int)$lvl;
                  $level_label   = trd_post_level_label($lvl);
                ?>
                <div class="trd-level">
                  <button type="button"
                          class="trd-accordion trd-level-h"
                          aria-expanded="false"
                          aria-controls="<?php echo esc_attr($level_body_id); ?>"
                          id="trd-level-btn-<?php echo (int)$gid . '-' . (int)$lvl; ?>">
                    <span class="trd-chevron" aria-hidden="true">
                      <svg width="18" height="18" viewBox="0 0 24 24">
                        <polyline points="8 10 12 14 16 10" fill="none" stroke="#2e467b" stroke-width="2"/>
                      </svg>
                    </span>
                    <span><?php echo esc_html($level_label); ?></span>
                  </button>

                  <div class="trd-level-body" id="<?php echo esc_attr($level_body_id); ?>" hidden>
                    <?php foreach ($pids_lvl as $pid):
                        $choices    = trd_kit_choices_for_post($pid);
                        if (empty($choices)) continue;
                        $is_fixed   = (count($choices) === 1);
                        $sel        = trd_get_selected_kit_for_group_post($gid, $pid);
                        $post_title = get_the_title($pid); if (!$post_title) $post_title = 'Tema #'.$pid;
                    ?>
                      <div class="trd-post" data-post="<?php echo (int)$pid; ?>">
                        <div class="trd-post-h">
                          <div class="trd-post-title"><?php echo esc_html($post_title); ?> <small style="opacity:.7">(#<?php echo (int)$pid; ?>)</small></div>
                          <?php if ($is_fixed): ?><span class="trd-chip-fixed" title="Este tema tiene un único kit">Fijo</span><?php endif; ?>
                        </div>

                        <div class="trd-kits">
                          <?php if ($is_fixed):
                                  $k    = array_key_first($choices);
                                  $data = $choices[$k]; ?>
                                  <label class="trd-kit-card" title="Kit fijo">
                                    <input type="radio" checked disabled>
                                    <span class="trd-kit-thumb"><?php if(!empty($data['img'])): ?><img src="<?php echo esc_url($data['img']); ?>" alt="<?php echo esc_attr($data['label']); ?>"><?php endif; ?></span>
                                    <div class="trd-kit-title"><?php echo esc_html($data['label']); ?></div>
                                  </label>
                          <?php else:
                                  foreach ($choices as $key=>$data): ?>
                                    <label class="trd-kit-card">
                                      <input type="radio"
                                             name="sel[<?php echo (int)$gid; ?>][<?php echo (int)$pid; ?>]"
                                             value="<?php echo esc_attr($key); ?>"
                                             <?php checked($sel, $key); ?>>
                                      <span class="trd-kit-thumb"><?php if(!empty($data['img'])): ?><img src="<?php echo esc_url($data['img']); ?>" alt="<?php echo esc_attr($data['label']); ?>"><?php endif; ?></span>
                                      <div class="trd-kit-title"><?php echo esc_html($data['label']); ?></div>
                                    </label>
                                  <?php endforeach;
                                endif; ?>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>

        <div class="trd-save">
          <button type="submit" class="trd-btn" id="trd-save-btn">Guardar selección</button>
          <span id="trd-save-spin" style="display:none">Guardando…</span>
        </div>
        <input type="hidden" name="action" value="trd_save_group_post_kits">
        <input type="hidden" name="security" value="<?php echo esc_attr($nonce); ?>">
      </form>
    </div>
    <div id="trd-toast" class="trd-toast" role="status" aria-live="polite"></div>
    <script>
    (function(){
      // --- acordeón genérico (grupos y niveles)
      document.querySelectorAll('.trd-accordion').forEach(function(btn){
        btn.addEventListener('click', function(){
          var expanded = btn.getAttribute('aria-expanded') === 'true';
          btn.setAttribute('aria-expanded', String(!expanded));
          var panelId = btn.getAttribute('aria-controls');
          var panel = panelId ? document.getElementById(panelId) : null;
          if (panel) panel.hidden = expanded;
        });
      });

      // Expandir/Contraer todo
      var expandAll = document.getElementById('trd-expand-all');
      var collapseAll = document.getElementById('trd-collapse-all');
      function setAll(state){
        document.querySelectorAll('.trd-accordion').forEach(function(btn){
          btn.setAttribute('aria-expanded', state ? 'true' : 'false');
          var panel = document.getElementById(btn.getAttribute('aria-controls'));
          if (panel) panel.hidden = !state;
        });
      }
      if (expandAll) expandAll.addEventListener('click', function(){ setAll(true); });
      if (collapseAll) collapseAll.addEventListener('click', function(){ setAll(false); });

      // --- guardado AJAX (igual que antes)
      var f = document.getElementById('trd-kit-matrix-form');
      var btn = document.getElementById('trd-save-btn');
      var spin = document.getElementById('trd-save-spin');
      var toast = document.getElementById('trd-toast');
      function showToast(msg, ok){
        toast.textContent = msg;
        toast.style.background = ok ? '#2e467b' : '#f16d10';
        toast.classList.add('show');
        setTimeout(function(){ toast.classList.remove('show'); }, 2600);
      }
      f.addEventListener('submit', function(e){
        e.preventDefault();
        btn.disabled = true; spin.style.display='inline';
        var fd = new FormData(f);
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {method:'POST', body:fd})
        .then(function(r){ return r.json(); })
        .then(function(r){
          btn.disabled = false; spin.style.display='none';
          if (r && r.success) { showToast('Selección guardada.', true); }
          else { showToast('Error: ' + (r && r.data ? r.data : 'intenta de nuevo'), false); }
        })
        .catch(function(){
          btn.disabled=false; spin.style.display='none';
          showToast('Error de red.', false);
        });
      });
    })();
    </script>
    <?php
    if (function_exists('trd_debug_point')) trd_debug_point('Panel de selección de kit (grupo/tema) renderizado');
    return ob_get_clean();
});

/* =========================================================
 *  AJAX: Guardar selección (grupo/post)
 * ========================================================= */
add_action('wp_ajax_trd_save_group_post_kits', function(){
    if (!current_user_can('groups_manage_groups') || !check_ajax_referer('trd-kit-matrix','security',false)) {
        wp_send_json_error('Permisos o nonce inválido');
    }
    if (!function_exists('learndash_get_administrators_group_ids')) {
        wp_send_json_error('LearnDash no disponible');
    }
    $leader  = get_current_user_id();
    $allowed = trd_get_leader_groups_sorted($leader);
    $allowed_map = array_fill_keys($allowed, true);

    $posted = isset($_POST['sel']) && is_array($_POST['sel']) ? $_POST['sel'] : [];
    $matrix = get_option(trd_kit_matrix_option_key(), []);

    foreach ($posted as $gid => $per_post) {
        $gid = (int)$gid;
        if (!isset($allowed_map[$gid])) continue;
        foreach ((array)$per_post as $pid => $key) {
            $pid = (int)$pid;
            $key = sanitize_key($key);
            $choices = trd_kit_choices_for_post($pid);
            if (empty($choices) || !isset($choices[$key])) continue;
            if (count($choices) === 1) continue; // fijo: no editable
            $matrix[$gid][$pid] = $key;
        }
    }
    update_option(trd_kit_matrix_option_key(), $matrix);
    wp_send_json_success(true);
});
/* =========================================================
 *  SHORTCODE Alumno/Admin: muestra video del tema actual
 *  [trd_kit_video post_id="12039" fallback="message|hidden"]
 * ========================================================= */
add_shortcode('trd_kit_video', function($atts){
    $a = shortcode_atts([
        'post_id'  => '',
        'fallback' => 'message',
    ], $atts, 'trd_kit_video');

    if (!is_user_logged_in()) return '';
    $current_post = ($a['post_id'] !== '') ? (int)$a['post_id'] : (int)get_the_ID();
    if ($current_post <= 0) return '';

    $choices = trd_kit_choices_for_post($current_post);
    if (empty($choices)) return ''; // tema sin kits

    $user  = wp_get_current_user();
    $roles = (array)$user->roles;

    // ✅ Admin real o usuario con pase de “preview”
    $is_admin_like = trd_is_preview_all_kits_user($user);

    $is_es = in_array('miembro_estudiante', $roles, true);

    /* === Caso ADMIN-LIKE: selector libre para visualizar cualquier kit del tema === */
    if ($is_admin_like) {
        $pre = isset($_GET['kit']) ? sanitize_key($_GET['kit']) : '';
        if (!$pre || !isset($choices[$pre])) $pre = array_key_first($choices);

        $sel_video = !empty($choices[$pre]['video']) ? esc_url($choices[$pre]['video']) : '';
        $sel_label = esc_html($choices[$pre]['label']);

        ob_start(); ?>
        <style>
          .trd-video-wrap{background:#fff;border:1px solid #e9eef5;border-radius:12px;box-shadow:0 2px 8px #2e467b1a;margin:10px 0 18px}
          .trd-video-h{background:#2e467b;color:#fff;padding:10px 14px;font-weight:700;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
          .trd-kits-admin{display:flex;gap:10px;flex-wrap:wrap}
          .trd-chip{border:2px solid #cfd6e4;border-radius:999px;padding:6px 10px;cursor:pointer;background:#fff}
          .trd-chip.active{border-color:#f16d10}
          .trd-frame{position:relative;padding-top:56.25%}
          .trd-frame iframe{position:absolute;inset:0;width:100%;height:100%;border:0}
          .trd-info{padding:10px 14px}
        </style>
        <div class="trd-video-wrap" id="trd-admin-view">
          <div class="trd-video-h">
            <span>Vista Admin · <strong id="trd-admin-title"><?php echo $sel_label; ?></strong></span>
            <div class="trd-kits-admin" id="trd-admin-chips">
              <?php foreach ($choices as $key=>$data): ?>
                <button type="button" class="trd-chip<?php echo $key===$pre?' active':''; ?>" data-kit="<?php echo esc_attr($key); ?>" data-video="<?php echo esc_attr($data['video'] ?? ''); ?>">
                  <?php echo esc_html($data['label']); ?>
                </button>
              <?php endforeach; ?>
            </div>
          </div>

          <?php if ($sel_video): ?>
            <div class="trd-frame"><iframe id="trd-admin-iframe" src="<?php echo $sel_video; ?>?autoplay=0" allow="autoplay; fullscreen; picture-in-picture"></iframe></div>
          <?php else: ?>
            <div class="trd-info" id="trd-admin-info">Este kit no tiene video configurado.</div>
          <?php endif; ?>
        </div>
        <script>
        (function(){
          var chips = document.querySelectorAll('#trd-admin-chips .trd-chip');
          var title = document.getElementById('trd-admin-title');
          var ifr   = document.getElementById('trd-admin-iframe');
          var info  = document.getElementById('trd-admin-info');
          chips.forEach(function(b){
            b.addEventListener('click', function(){
              chips.forEach(c=>c.classList.remove('active'));
              b.classList.add('active');
              title.textContent = b.textContent.trim();
              var v = b.getAttribute('data-video') || '';
              if (v) {
                if (info) info.remove();
                if (!ifr) {
                  var wrap = document.getElementById('trd-admin-view');
                  var div = document.createElement('div'); div.className='trd-frame';
                  var f = document.createElement('iframe'); f.id='trd-admin-iframe'; f.src=v+'?autoplay=0'; f.allow='autoplay; fullscreen; picture-in-picture';
                  div.appendChild(f); wrap.appendChild(div);
                  ifr = f;
                } else {
                  ifr.src = v + '?autoplay=0';
                }
              } else {
                if (ifr) { ifr.parentNode.remove(); ifr=null; }
                if (!info) {
                  var d=document.createElement('div'); d.id='trd-admin-info'; d.className='trd-info'; d.textContent='Este kit no tiene video configurado.';
                  document.getElementById('trd-admin-view').appendChild(d);
                  info = d;
                }
              }
            });
          });
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    /* === Caso ES: trayectoria fija por post_id === */
    if ($is_es) {
        $forced = trd_es_fixed_kit_for_post($current_post);
        if ($forced && isset($choices[$forced])) {
            $k     = $choices[$forced];
            $title = esc_html($k['label']);
            $video = !empty($k['video']) ? esc_url($k['video']) : '';
            ob_start(); ?>
            <style>
              .trd-video-wrap{background:#fff;border:1px solid #e9eef5;border-radius:12px;box-shadow:0 2px 8px #2e467b1a;margin:10px 0 18px}
              .trd-video-h{background:#2e467b;color:#fff;padding:10px 14px;font-weight:700}
              .trd-frame{position:relative;padding-top:56.25%}
              .trd-frame iframe{position:absolute;inset:0;width:100%;height:100%;border:0}
              .trd-info{padding:10px 14px}
            </style>
            <div class="trd-video-wrap">
              <div class="trd-video-h"><span><?php echo $title; ?></span></div>
              <?php if ($video): ?>
                <div class="trd-frame"><iframe src="<?php echo $video; ?>?autoplay=0" allow="autoplay; fullscreen; picture-in-picture" title="<?php echo $title; ?>"></iframe></div>
              <?php else: ?>
                <div class="trd-info">El video para <strong><?php echo $title; ?></strong> aún no está configurado.</div>
              <?php endif; ?>
            </div>
            <?php
            return ob_get_clean();
        }
        // Si no hay mapeo, sigue flujo CG.
    }

    /* === Caso CG (u otros): selección por grupo+post o fijo === */
    if (!function_exists('learndash_get_users_group_ids')) return '';
    $groups = (array) learndash_get_users_group_ids(get_current_user_id(), true);

    // Tema fijo (una opción)
    if (count($choices) === 1) {
        $key   = array_key_first($choices);
        $k     = $choices[$key];
        $title = esc_html($k['label']);
        $video = !empty($k['video']) ? esc_url($k['video']) : '';
        ob_start(); ?>
        <style>
          .trd-video-wrap{background:#fff;border:1px solid #e9eef5;border-radius:12px;box-shadow:0 2px 8px #2e467b1a;margin:10px 0 18px}
          .trd-video-h{background:#2e467b;color:#fff;padding:10px 14px;font-weight:700}
          .trd-frame{position:relative;padding-top:56.25%}
          .trd-frame iframe{position:absolute;inset:0;width:100%;height:100%;border:0}
          .trd-info{padding:10px 14px}
        </style>
        <div class="trd-video-wrap">
          <div class="trd-video-h"><span><?php echo $title; ?></span></div>
          <?php if ($video): ?>
            <div class="trd-frame"><iframe src="<?php echo $video; ?>?autoplay=0" allow="autoplay; fullscreen; picture-in-picture" title="<?php echo $title; ?>"></iframe></div>
          <?php else: ?>
            <div class="trd-info">El video para <strong><?php echo $title; ?></strong> aún no está configurado.</div>
          <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    // Varias opciones: buscamos selección guardada en alguno de sus grupos
    if (!empty($groups)) {
        $mx = get_option(trd_kit_matrix_option_key(), []);
        foreach ($groups as $gid) {
            if (!empty($mx[$gid][$current_post]) && isset($choices[$mx[$gid][$current_post]])) {
                $key   = $mx[$gid][$current_post];
                $k     = $choices[$key];
                $title = esc_html($k['label']);
                $video = !empty($k['video']) ? esc_url($k['video']) : '';
                ob_start(); ?>
                <style>
                  .trd-video-wrap{background:#fff;border:1px solid #e9eef5;border-radius:12px;box-shadow:0 2px 8px #2e467b1a;margin:10px 0 18px}
                  .trd-video-h{background:#2e467b;color:#fff;padding:10px 14px;font-weight:700}
                  .trd-frame{position:relative;padding-top:56.25%}
                  .trd-frame iframe{position:absolute;inset:0;width:100%;height:100%;border:0}
                  .trd-info{padding:10px 14px}
                </style>
                <div class="trd-video-wrap">
                  <div class="trd-video-h"><span><?php echo $title; ?></span></div>
                  <?php if ($video): ?>
                    <div class="trd-frame"><iframe src="<?php echo $video; ?>?autoplay=0" allow="autoplay; fullscreen; picture-in-picture" title="<?php echo $title; ?>"></iframe></div>
                  <?php else: ?>
                    <div class="trd-info">El video para <strong><?php echo $title; ?></strong> aún no está configurado.</div>
                  <?php endif; ?>
                </div>
                <?php
                return ob_get_clean();
            }
        }
    }

    // Sin selección aún
    if ($a['fallback'] === 'hidden') return '';
    return '<div style="padding:14px;background:#fffbea;border:1px solid #ffe58f;border-radius:8px;">Tu tutor aún no ha seleccionado un kit para este tema.</div>';
});


/* =========================================================
 *  (Opcional) Shortcode de DEBUG: [trd_kit_debug]
 * ========================================================= */
add_shortcode('trd_kit_debug', function(){
    if (!current_user_can('manage_options')) return '';
    $mx = get_option(trd_kit_matrix_option_key(), []);
    ob_start();
    echo '<pre style="white-space:pre-wrap;background:#fff;border:1px solid #ddd;border-radius:8px;padding:10px">';
    echo esc_html(print_r($mx, true));
    echo '</pre>';
    return ob_get_clean();
});
