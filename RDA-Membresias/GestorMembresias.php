<?php
/**
 * Plugin Name: RDASTEAM Gestion Membresias
 * Plugin URI: https://robodacta-steam.mx
 * Description: Creación/Cancelación de membresías, registro de escuelas y canje de licencias (MemberDash + LearnDash).
 * Version: 1.2.0
 * Author: Robodacta Miguel Alfonso
 * Author URI: https://robodacta-steam.mx
 * License: GPL2
 */

/* =========================================================
 *  DEPENDENCIAS
 * ========================================================= */
require_once plugin_dir_path(__FILE__) . 'includes/MembresiaUsuario.php';
require_once __DIR__ . '/Hashids/Hashids.php';

/* =========================================================
 *  DEBUG: ver meta de una ms_relationship  [rda_debug_sub_meta id="123"]
 * ========================================================= */
add_shortcode('rda_debug_sub_meta', function($atts){
    if (!current_user_can('manage_options')) return 'No autorizado.';
    $id = isset($atts['id']) ? intval($atts['id']) : 0;
    if (!$id) return 'Falta id.';
    $p    = get_post($id);
    $meta = get_post_meta($id);
    ob_start();
    echo '<div style="font:14px/1.4 system-ui;max-width:1000px">';
    echo '<h3>Post #'.$id.' (type='.$p->post_type.', status='.$p->post_status.')</h3>';
    echo '<table border="1" cellpadding="6" cellspacing="0"><tr><th>Meta key</th><th>Valor</th></tr>';
    foreach ($meta as $k=>$v){
        $val = maybe_unserialize($v[0]);
        echo '<tr><td><code>'.esc_html($k).'</code></td><td><pre style="margin:0">'.esc_html(print_r($val, true)).'</pre></td></tr>';
    }
    echo '</table></div>';
    return ob_get_clean();
});

/* =========================================================
 *  HASHIDS (enmascarado de licencias)
 * ========================================================= */
function rd_get_hashids(){
    static $hash = null;
    if ($hash === null) {
        $hash = new Hashids('TuSuperSecretoUnico!', 18, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890');
    }
    return $hash;
}
function licencia_a_numeros($lic){ return (int) substr($lic, 2); }
function numeros_a_licencia($num, $tipo = 'CG'){
    $tipo = strtoupper($tipo) === 'ES' ? 'ES' : 'CG';
    return $tipo . str_pad((string)$num, 16, '0', STR_PAD_LEFT);
}
function rd_enmascarar_licencia($lic){
    $hashids = rd_get_hashids();
    $lic  = strtoupper(trim($lic));
    $tipo = substr($lic, 0, 2);
    $num  = licencia_a_numeros($lic);
    $tIdx = ($tipo === 'ES') ? 1 : 2;
    return $hashids->encode([$tIdx, $num]);
}
function rd_desenmascarar_licencia($hash){
    $arr = rd_get_hashids()->decode(trim($hash));
    if (!is_array($arr) || !count($arr)) return false;
    if (count($arr) === 2) { $tipo = ((int)$arr[0] === 1) ? 'ES' : 'CG'; return numeros_a_licencia((int)$arr[1], $tipo); }
    return numeros_a_licencia((int)$arr[0], 'CG');
}
function rd_formatear_cod_con_guiones($code) {
    return strtoupper(implode('-', str_split($code, 6)));
}

/* =========================================================
 *  CONFIGURACIÓN (AJUSTA AQUÍ)
 * ========================================================= */
function rda_get_membership_ids(){
    return [
        'ES' => 24218, // Estudiante  (AJUSTA)
        'CG' => 7694,  // Colegio     (AJUSTA)
    ];
}
function rda_get_courses_by_tipo($tipo){
    if ($tipo === 'CG' || $tipo === 'ES') {
        // AJUSTA los IDs de curso
        return array(25306, 19476, 7363, 5957, 5624, 5619, 5613);
    }
    return array();
}
function rda_get_groups_by_tipo($tipo){
    // Si tienes grupos “preset” por tipo, colócalos aquí; si no, deja vacío.
    return array();
}

/* =========================================================
 *  ROLES + LEARNDASH HELPERS
 * ========================================================= */
function rd_set_role_por_tipo($user_id, $tipo_lic) {
    $user = get_userdata($user_id);
    if (!$user) return;

    if (!get_role('miembro_colegio'))    add_role('miembro_colegio', 'Miembro Colegio', ['read' => true]);
    if (!get_role('miembro_estudiante')) add_role('miembro_estudiante', 'Miembro Estudiante', ['read' => true]);

    $target = ($tipo_lic === 'CG') ? 'miembro_colegio' : 'miembro_estudiante';
    $other  = ($tipo_lic === 'CG') ? 'miembro_estudiante' : 'miembro_colegio';
    $generic = ['logueado', 'subscriber'];

    $is_admin_like = array_intersect($user->roles, ['administrator','editor','author','contributor']);
    if (!empty($is_admin_like)) {
        foreach ($generic as $gr) if (in_array($gr, (array)$user->roles, true)) $user->remove_role($gr);
        if (in_array($other, (array)$user->roles, true)) $user->remove_role($other);
        if (!in_array($target, (array)$user->roles, true)) $user->add_role($target);
        error_log("[RDA roles] admin-like -> +$target, -$other, limpia genéricos uid=$user_id");
        return;
    }
    $user->set_role($target);
    error_log("[RDA roles] normal -> set '$target' uid=$user_id");
}

if (!defined('RDA_ROLE_KEY')) define('RDA_ROLE_KEY', 'um_logueado');
function rda_set_only_logueado($user_id){
    $u = get_userdata($user_id);
    if (!$u) return;
    $admin_like = array_intersect((array)$u->roles, ['administrator','editor','author','contributor']);
    if (!empty($admin_like)) return;
    if (!get_role(RDA_ROLE_KEY)) return;
    foreach ((array)$u->roles as $r) if ($r !== RDA_ROLE_KEY) $u->remove_role($r);
    if (!in_array(RDA_ROLE_KEY, (array)$u->roles, true)) $u->add_role(RDA_ROLE_KEY);
}

function rd_asignar_cursos_learndash($user_id, array $course_ids) {
    if (!function_exists('ld_update_course_access')) return;
    foreach ($course_ids as $cid) {
        ld_update_course_access($user_id, (int)$cid);
        update_user_meta($user_id, 'learndash_course_' . (int)$cid . '_enrolled_at', time());
    }
}

function rda_attach_courses_to_group($group_id, $tipo){
    $group_id = (int)$group_id; if ($group_id <= 0) return false;
    $courses = array_map('intval', rda_get_courses_by_tipo(strtoupper($tipo)));
    if (empty($courses)) { error_log('[RDA grupos] No hay cursos para tipo='.$tipo); return false; }

    if (function_exists('learndash_set_group_enrolled_courses')) {
        $existing = function_exists('learndash_group_enrolled_courses') ? array_map('intval', (array) learndash_group_enrolled_courses($group_id)) : [];
        $final = array_values(array_unique(array_merge($existing, $courses)));
        learndash_set_group_enrolled_courses($group_id, $final);
        error_log('[RDA grupos] set_group_enrolled_courses #'.$group_id.' => '.implode(',', $final));
        return true;
    }
    if (function_exists('learndash_set_groups_courses')) {
        $existing = function_exists('learndash_group_enrolled_courses') ? array_map('intval', (array) learndash_group_enrolled_courses($group_id)) : [];
        $final = array_values(array_unique(array_merge($existing, $courses)));
        learndash_set_groups_courses($group_id, $final);
        error_log('[RDA grupos] set_groups_courses #'.$group_id.' => '.implode(',', $final));
        return true;
    }
    if (function_exists('ld_update_course_group_access')) {
        foreach ($courses as $cid) ld_update_course_group_access((int)$cid, $group_id, false);
        error_log('[RDA grupos] ld_update_course_group_access #'.$group_id.' => '.implode(',', $courses));
        return true;
    }
    error_log('[RDA grupos][ERROR] No hay API LD para asignar cursos al grupo.');
    return false;
}

function rda_remove_learndash_access($user_id, $tipo){
    $user_id = (int)$user_id;
    $course_ids = array_map('intval', rda_get_courses_by_tipo($tipo));
    if (empty($course_ids)) { error_log("[RDA baja] Sin courses para tipo={$tipo}"); return; }

    if (function_exists('ld_update_course_access')) {
        foreach ($course_ids as $cid){
            ld_update_course_access($user_id, $cid, true);
            if (function_exists('learndash_user_clear_course_transients')) learndash_user_clear_course_transients($user_id, $cid);
            delete_user_meta($user_id, "course_{$cid}_access_from");
            delete_user_meta($user_id, "ld_course_{$cid}_access_from");
        }
    }

    if (function_exists('learndash_get_users_group_ids') && function_exists('ld_update_group_access') && function_exists('learndash_group_enrolled_courses')) {
        $user_groups = learndash_get_users_group_ids($user_id, true);
        foreach ((array)$user_groups as $gid){
            $group_courses = (array) learndash_group_enrolled_courses((int)$gid);
            if (!empty(array_intersect($course_ids, array_map('intval',$group_courses)))) {
                ld_update_group_access($user_id, (int)$gid, true);
                error_log("[RDA baja] Quitado del grupo {$gid}");
            }
        }
    }

    if (function_exists('ld_update_group_access')) {
        foreach ((array)rda_get_groups_by_tipo($tipo) as $gid) ld_update_group_access($user_id, (int)$gid, true);
    }

    if (function_exists('sfwd_lms_has_access')) {
        foreach ($course_ids as $cid) if (sfwd_lms_has_access($cid, $user_id)) error_log("[RDA baja][ALERTA] Aún tiene acceso {$cid}");
    }
    clean_user_cache($user_id);
}

/* =========================================================
 *  GROUP BUILDERS
 * ========================================================= */
function rda_build_group_key($tipo, $anio, $id_escuela, $escolaridad, $grado, $grupo){
    $tipo        = strtoupper($tipo);
    $anio        = str_pad((string)$anio,        2, '0', STR_PAD_LEFT);
    $id_escuela  = str_pad((string)$id_escuela,  4, '0', STR_PAD_LEFT);
    $escolaridad = str_pad((string)$escolaridad, 2, '0', STR_PAD_LEFT);
    $grado       = str_pad((string)$grado,       2, '0', STR_PAD_LEFT);
    $grupo       = str_pad((string)$grupo,       2, '0', STR_PAD_LEFT);
    return sprintf('%s-%s-%s-%s-%s-%s', $tipo, $anio, $id_escuela, $escolaridad, $grado, $grupo);
}
/** ===============================
 *  Helpers de formateo de títulos
 * =============================== */
function rda_escolaridad_label($code){
    $map = [
        '01' => 'Primaria',
        '02' => 'Secundaria',
        '03' => 'Preparatoria',
        '04' => 'Universidad',
    ];
    $code = str_pad((string)$code, 2, '0', STR_PAD_LEFT);
    return $map[$code] ?? ('Nivel '.$code);
}
function rda_grupo_letra($code){
    // 01->A, 02->B, ..., 26->Z. Si se sale de rango, devuelve el código tal cual.
    $n = (int)$code;
    if ($n >= 1 && $n <= 26) return chr(64 + $n);
    return (string)$code;
}
function rda_cycle_label($anio2){
    // "25" -> "25-26"
    $a = (int)$anio2;
    $b = ($a + 1) % 100;
    $a = str_pad((string)$a, 2, '0', STR_PAD_LEFT);
    $b = str_pad((string)$b, 2, '0', STR_PAD_LEFT);
    return $a . '-' . $b;
}
function rda_school_name_from_option($id_escuela){
    // Busca el nombre en la opción rd_escuelas (ID => nombre). Fallback a "Escuela XXXX".
    $escuelas = get_option('rd_escuelas', []);
    $id_escuela = str_pad((string)$id_escuela, 4, '0', STR_PAD_LEFT);
    if (!empty($escuelas[$id_escuela])) {
        // Permitimos que el nombre guardado ya incluya prefijos como CEN, etc.
        return stripslashes($escuelas[$id_escuela]);
    }
    return 'Escuela ' . $id_escuela;
}

/** ===========================================
 *  NUEVO formateo de títulos de grupos (bonito)
 *  Reemplaza la versión anterior de esta función
 * =========================================== */
function rda_build_group_title($tipo, $anio, $id_escuela, $escolaridad, $grado, $grupo){
    $tipo  = strtoupper($tipo);
    $anio  = str_pad((string)$anio, 2, '0', STR_PAD_LEFT);
    $nivel = rda_escolaridad_label($escolaridad);
    $school= rda_school_name_from_option($id_escuela);
    $grado_n = (int)$grado;
    $letra   = rda_grupo_letra($grupo);
    $ciclo   = rda_cycle_label($anio);

    // Ejemplo: Ciclo 25-26 CEN "Las Américas" Primaria 1 - A
    // (Si guardas el nombre como: CEN "Las Américas", saldrá tal cual)
    return sprintf('Ciclo %s %s %s %d - %s', $ciclo, $school, $nivel, $grado_n, $letra);
}

function rda_ensure_ld_group($tipo, $anio, $id_escuela, $escolaridad, $grado, $grupo){
    $post_type = 'groups';
    if (!post_type_exists($post_type)) return 0;

    $key = rda_build_group_key($tipo, $anio, $id_escuela, $escolaridad, $grado, $grupo);
    $existing = get_posts([
        'post_type'      => $post_type,
        'posts_per_page' => 1,
        'post_status'    => 'any',
        'meta_key'       => 'rda_group_key',
        'meta_value'     => $key,
        'fields'         => 'ids',
        'no_found_rows'  => true
    ]);

    $new_title = rda_build_group_title($tipo, $anio, $id_escuela, $escolaridad, $grado, $grupo);

    if (!empty($existing)) {
        $gid = (int)$existing[0];
        // 🔄 Si el título cambió (p.ej. antes tenía el formato antiguo), lo actualizamos.
        $current_title = get_the_title($gid);
        if ($current_title !== $new_title) {
            wp_update_post(['ID' => $gid, 'post_title' => $new_title]);
        }
        rda_attach_courses_to_group($gid, $tipo);
        return $gid;
    }

    // Crear nuevo grupo con título bonito
    $gid = wp_insert_post([
        'post_type'   => $post_type,
        'post_status' => 'publish',
        'post_title'  => $new_title,
    ]);
    if (is_wp_error($gid) || !$gid) return 0;

    update_post_meta($gid, 'rda_group_key',   $key);
    update_post_meta($gid, 'rda_tipo',        strtoupper($tipo));
    update_post_meta($gid, 'rda_anio',        str_pad((string)$anio, 2, '0', STR_PAD_LEFT));
    update_post_meta($gid, 'rda_id_escuela',  str_pad((string)$id_escuela, 4, '0', STR_PAD_LEFT));
    update_post_meta($gid, 'rda_escolaridad', str_pad((string)$escolaridad, 2, '0', STR_PAD_LEFT));
    update_post_meta($gid, 'rda_grado',       str_pad((string)$grado, 2, '0', STR_PAD_LEFT));
    update_post_meta($gid, 'rda_grupo',       str_pad((string)$grupo, 2, '0', STR_PAD_LEFT));

    rda_attach_courses_to_group($gid, $tipo);
    return (int)$gid;
}

function rda_parse_license_parts($codigo){
    $codigo = strtoupper(trim($codigo));
    if (!preg_match('/^(ES|CG)\d{16}$/', $codigo)) return false;
    return [
        'tipo'        => substr($codigo, 0, 2),
        'anio'        => substr($codigo, 2, 2),
        'id_escuela'  => substr($codigo, 4, 4),
        'escolaridad' => substr($codigo, 8, 2),
        'grado'       => substr($codigo,10, 2),
        'grupo'       => substr($codigo,12, 2),
        'seq_id'      => substr($codigo,14, 4),
    ];
}

/* =========================================================
 *  MEMBERDASH: HELPERS ANTI-"WAITING"
 * ========================================================= */
function rda_md_rel_from_mixed($rel_mixed){
    if (is_object($rel_mixed)) return $rel_mixed;
    $rid = (is_numeric($rel_mixed) ? (int)$rel_mixed : 0);
    if ($rid <= 0) return null;
    if (class_exists('MS_Factory') && method_exists('MS_Factory','load')) {
        $obj = MS_Factory::load('MS_Model_Relationship', $rid);
        if (is_object($obj)) return $obj;
    }
    if (class_exists('MS_Model_Relationship') && method_exists('MS_Model_Relationship','load')) {
        $obj = MS_Model_Relationship::load($rid);
        if (is_object($obj)) return $obj;
    }
    error_log('[RDA][rel_from_mixed] No se pudo cargar ID='.$rid);
    return null;
}

/** Fuerza expire_date visibles en meta del CPT */
function rda_force_meta_expire($rel, $years = 1){
    if (!is_object($rel) || !property_exists($rel,'id') || !intval($rel->id)) return;
    $pid = (int)$rel->id;
    $start = get_post_meta($pid, 'start_date', true);
    if (empty($start)) {
        $start = date('Y-m-d', current_time('timestamp'));
        update_post_meta($pid, 'start_date', $start);
        update_post_meta($pid, 'start_date_gmt', substr(get_gmt_from_date($start.' 00:00:00'), 0, 10));
    }
    $expire     = date('Y-m-d', strtotime('+'.$years.' year', strtotime($start)));
    $expire_gmt = substr(get_gmt_from_date($expire.' 00:00:00'), 0, 10);

    update_post_meta($pid, 'expire_date',     $expire);
    update_post_meta($pid, 'expire_date_gmt', $expire_gmt);
    update_post_meta($pid, 'end_date',        $expire);
    update_post_meta($pid, '_end_date',       $expire);

    wp_cache_delete($pid, 'post_meta');
    update_meta_cache('post', array($pid));
    error_log("[RDA][force-meta-expire] pid=$pid start=$start expire=$expire");
}

/** Mantiene visibles metas clave para la UI si faltan */
function rda_sync_expire_metas_for_ui($post_id, $years = 1){
    $p = get_post($post_id);
    if (!$p || $p->post_type !== 'ms_relationship') return;
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;

    $exp_meta = get_post_meta($post_id, 'expire_date', true);
    if (!empty($exp_meta)) return;

    $start = get_post_meta($post_id, 'start_date', true);
    if (empty($start)) {
      $start = date('Y-m-d', current_time('timestamp'));
      update_post_meta($post_id, 'start_date', $start);
      update_post_meta($post_id, 'start_date_gmt', substr(get_gmt_from_date($start.' 00:00:00'),0,10));
    }
    $ts = strtotime($start.' 00:00:00');
    $expire  = date('Y-m-d', strtotime('+'.$years.' year', $ts));
    $expire_gmt = substr(get_gmt_from_date($expire.' 00:00:00'), 0, 10);

    update_post_meta($post_id, 'expire_date',           $expire);
    update_post_meta($post_id, 'expire_date_gmt',       $expire_gmt);
    update_post_meta($post_id, '_expire_date',          $expire);
    update_post_meta($post_id, '_expire_date_gmt',      $expire_gmt);
    update_post_meta($post_id, 'ms_expire_date',        $expire);
    update_post_meta($post_id, 'ms_expire_date_gmt',    $expire_gmt);
    update_post_meta($post_id, 'ms_relationship_expire_date',     $expire);
    update_post_meta($post_id, 'ms_relationship_expire_date_gmt', $expire_gmt);

    clean_post_cache($post_id);
    error_log("[RDA][sync-expire] post_id={$post_id} start={$start} expire={$expire}");
}
function rda_sync_expire_metas_for_ui_aggressive($post_id){
    $ptype = get_post_type($post_id);
    if (!$ptype) return;
    $md_types = array('ms_relationship','ms_subscription','memberdash_subscription');
    if (!in_array($ptype, $md_types, true)) return;

    $start  = get_post_meta($post_id, 'start_date', true);
    $expire = get_post_meta($post_id, 'expire_date', true);
    if (empty($expire)) $expire = get_post_meta($post_id, 'end_date', true);
    if (empty($expire) && $start) $expire = date('Y-m-d', strtotime('+1 year', strtotime($start)));
    if (!$expire) return;

    $expire_dt  = $expire . ' 00:00:00';
    $expire_gmt = function_exists('get_gmt_from_date') ? substr(get_gmt_from_date($expire_dt), 0, 10) : gmdate('Y-m-d', strtotime($expire_dt));

    update_post_meta($post_id, 'expire_date',        $expire);
    update_post_meta($post_id, 'expire_date_gmt',    $expire_gmt);
    update_post_meta($post_id, 'end_date',           $expire);
    update_post_meta($post_id, '_end_date',          $expire);
    update_post_meta($post_id, 'ms_expire_date',     $expire);
    update_post_meta($post_id, '_ms_expire_date',    $expire);
    update_post_meta($post_id, 'expire_date_dt',     $expire_dt);
    update_post_meta($post_id, 'expire_date_dt_gmt', function_exists('get_gmt_from_date') ? get_gmt_from_date($expire_dt) : gmdate('Y-m-d H:i:s', strtotime($expire_dt)));

    wp_cache_delete($post_id, 'post_meta');
    update_meta_cache('post', array($post_id));
    error_log('[RDA][sync-expire] pid='.$post_id.' keys='.implode(',', array_keys(get_post_meta($post_id))));
}
add_action('save_post_ms_relationship', function($post_id, $post, $update){
  rda_sync_expire_metas_for_ui_aggressive($post_id);
}, 999, 3);
add_action('save_post', function($post_id){
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    rda_sync_expire_metas_for_ui($post_id);
}, 20);
add_action('current_screen', function($screen){
    if (empty($screen) || $screen->base !== 'post') return;
    $post = get_post(); if (!$post) return;
    rda_sync_expire_metas_for_ui($post->ID);
});
/** === Núcleo anti-"waiting" (sin save()) === */
function rda_md_ensure_start_and_period($rel, $years = 1) {
    if (!is_object($rel)) return;
    $ts_local = current_time('timestamp') - 600; // 10 min atrás

    // SOLO fecha para metas; datetime para setters
    $start_d  = date('Y-m-d', $ts_local);
    $start_dt = $start_d . ' 00:00:00';
    $expire_d = date('Y-m-d', strtotime('+'.$years.' year', $ts_local));

    if (method_exists($rel,'set_start_date')) $rel->set_start_date($start_dt); else $rel->start_date = $start_dt;
    if (method_exists($rel,'set_expire_date')) $rel->set_expire_date($expire_d); else $rel->expire_date = $expire_d;
    foreach (['trial_period','trial_until','trial_ends','trial_end','trial'] as $f) if (property_exists($rel,$f)) $rel->$f = 0;
    if (method_exists($rel,'config_period')) $rel->config_period();
    if (method_exists($rel,'set_status')) $rel->set_status('active'); else $rel->status = 'active';

    if (property_exists($rel,'id') && (int)$rel->id) {
        $pid = (int)$rel->id;
        update_post_meta($pid, 'start_date',      $start_d);          // <- sin hora
        update_post_meta($pid, 'start_date_gmt',  gmdate('Y-m-d', $ts_local));
        update_post_meta($pid, 'expire_date',     $expire_d);
        update_post_meta($pid, 'expire_date_gmt', gmdate('Y-m-d', strtotime($expire_d.' 00:00:00')));
        update_post_meta($pid, 'status','active');
        update_post_meta($pid, '_status','active');
        wp_update_post(['ID'=>$pid,'post_status'=>'publish']);
        clean_post_cache($pid);
    }
}
function rda_md_force_activate($rel){
    if (!is_object($rel)) return;
    if (property_exists($rel,'gateway_id')) $rel->gateway_id = 'admin';
    if (method_exists($rel,'set_status')) $rel->set_status('active'); else $rel->status = 'active';
    if (property_exists($rel,'id') && (int)$rel->id){
        $rid = (int)$rel->id;
        update_post_meta($rid, 'gateway_id', 'admin');
        update_post_meta($rid, 'gateway',    'admin');
        update_post_meta($rid, 'status',     'active');
        update_post_meta($rid, '_status',    'active');
        update_post_meta($rid, 'ms_status',  'active');
        delete_post_meta($rid, 'trial_end');
        delete_post_meta($rid, 'trial_ends');
        delete_post_meta($rid, 'trial_until');
        wp_update_post(['ID'=>$rid,'post_status'=>'publish']);
    }
}
function rda_md_mark_paid_for_ui($rel){
    if (!is_object($rel) || !property_exists($rel,'id') || !intval($rel->id)) return;
    $rid = (int)$rel->id;

    // Flags de relación
    update_post_meta($rid, 'gateway_id','admin');
    update_post_meta($rid, 'gateway','admin');
    update_post_meta($rid, 'status','active');
    update_post_meta($rid, '_status','active');
    update_post_meta($rid, 'ms_status','active');
    update_post_meta($rid, 'is_paid',1);
    update_post_meta($rid, 'paid',1);
    update_post_meta($rid, 'payment_status','paid');
    update_post_meta($rid, 'ms_payment_status','paid');

    // 🔒 Fijar autor de la relación = user_id (algunas UIs lo usan)
    if (property_exists($rel, 'user_id') && (int)$rel->user_id) {
        wp_update_post(['ID'=>$rid,'post_author'=>(int)$rel->user_id]);
    }

    // ✅ Crear factura dummy y enlazar de forma completa
    if (post_type_exists('ms_invoice')) {
        $inv_id = wp_insert_post([
            'post_type'   => 'ms_invoice',
            'post_status' => 'publish',
            'post_title'  => 'Admin payment '.$rid,
            'post_parent' => $rid, // <- enlaza por parent
        ]);
        if (!is_wp_error($inv_id) && $inv_id) {
            // Metas típicas que la UI consulta
            update_post_meta($inv_id, 'relationship_id', $rid);
            update_post_meta($inv_id, 'ms_relationship_id', $rid);
            if (property_exists($rel, 'user_id')) update_post_meta($inv_id, 'member_id', (int)$rel->user_id);
            if (property_exists($rel, 'membership_id')) update_post_meta($inv_id, 'membership_id', (int)$rel->membership_id);

            update_post_meta($inv_id, 'status', 'paid');
            update_post_meta($inv_id, 'payment_status', 'paid');
            update_post_meta($inv_id, 'gateway_id', 'admin');
            update_post_meta($inv_id, 'total', 0);
            update_post_meta($inv_id, 'amount', 0);
            update_post_meta($inv_id, 'invoice_date', current_time('mysql'));

            // Enlaces inversos en la relación
            update_post_meta($rid, 'invoice_id',    $inv_id);
            update_post_meta($rid, 'ms_invoice_id', $inv_id);
        }
    }

    // Publicar y despejar cachés
    wp_update_post(['ID'=>$rid,'post_status'=>'publish']);
    wp_cache_delete($rid, 'post_meta');
    update_meta_cache('post', array($rid));
    clean_post_cache($rid);
}
function rda_md_normalize_and_publish($rel, $years = 1){
    if (!is_object($rel)) return false;

    rda_md_ensure_start_and_period($rel, $years);
    rda_md_force_activate($rel);

    if (property_exists($rel,'id') && (int)$rel->id) {
        $pid = (int)$rel->id;
        rda_md_mark_paid_for_ui($rel);
        rda_force_meta_expire($rel, $years);
        rda_sync_expire_metas_for_ui_aggressive($pid);
        wp_update_post(['ID'=>$pid,'post_status'=>'publish']);
        clean_post_cache($pid);

        // <- IMPORTANTE
        if (method_exists($rel,'save')) { try { $rel->save(); } catch(\Throwable $e){ error_log('[RDA normalize] save(): '.$e->getMessage()); } }
    }

    $st = method_exists($rel,'get_status') ? strtolower($rel->get_status())
         : (property_exists($rel,'status') ? strtolower($rel->status) : '');
    return in_array($st, ['active','trial'], true);
}
function rda_md_hard_publish_active($rel_or_id, $years = 1){
    $rel = is_object($rel_or_id) ? $rel_or_id : rda_md_rel_from_mixed($rel_or_id);
    $rid = is_object($rel) && !empty($rel->id) ? (int)$rel->id : (is_numeric($rel_or_id) ? (int)$rel_or_id : 0);
    if ($rid <= 0) return false;

    rda_force_meta_expire( is_object($rel) ? $rel : (object)['id'=>$rid], $years );
    if (is_object($rel)) { rda_md_force_activate($rel); rda_md_mark_paid_for_ui($rel); }
    else {
        update_post_meta($rid, 'status','active');
        update_post_meta($rid, '_status','active');
        update_post_meta($rid, 'ms_status','active');
        update_post_meta($rid, 'gateway_id','admin');
        update_post_meta($rid, 'payment_status','paid');
        update_post_meta($rid, 'ms_payment_status','paid');
        wp_update_post(['ID'=>$rid,'post_status'=>'publish']);
    }
    error_log('[RDA][hard-publish] Relación #'.$rid.' forzada ACTIVE/PUBLISH.');
    return true;
}

/** Filtros de “paid” (por si tu build los usa en la UI) */
add_filter('ms_model_relationship_admin_gateway_paid', function($paid, $subscription){
    if (is_object($subscription) && property_exists($subscription,'gateway_id') && $subscription->gateway_id === 'admin') return true;
    return $paid;
}, 99, 2);
add_filter('ms_model_relationship_is_paid', function($paid, $subscription = null){
    if (is_object($subscription) && property_exists($subscription,'gateway_id') && $subscription->gateway_id === 'admin') return true;
    return $paid;
}, 99, 2);
add_filter('ms_model_relationship_is_paid_admin_gateway', function($paid, $subscription = null){
    if (is_object($subscription) && property_exists($subscription,'gateway_id') && $subscription->gateway_id === 'admin') return true;
    return $paid;
}, 99, 2);

/* =========================================================
 *  LICENCIAS: BD helpers
 * ========================================================= */
function rda_get_lic_row_by_codigo($codigo){
    global $wpdb; $table = $wpdb->prefix.'rd_membresias';
    $codigo = strtoupper(trim($codigo));
    $lic_normalizada = str_replace('-', '', $codigo);

    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE codigo=%s LIMIT 1", $lic_normalizada));
    if ($row) return $row;

    $posible = rd_desenmascarar_licencia($lic_normalizada);
    if ($posible){
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE codigo=%s LIMIT 1", $posible));
        if ($row) return $row;
    }
    return null;
}

/* =========================================================
 *  HOOK: cuando agregan membresía ES (MemberDash) => role/cursos/código + normalización
 * ========================================================= */
add_filter('ms_model_member_add_membership', function($subscription, $membership_id, $gateway_id, $move_from_id, $member_obj) {
    static $running = false;
    if ($running) return $subscription;
    $running = true;

    if (!is_object($member_obj) || !property_exists($member_obj, 'id')) { $running = false; return $subscription; }
    $user_id = (int) $member_obj->id;
    $membresia_estudiante_id = (int) rda_get_membership_ids()['ES'];

    if ((int)$membership_id === $membresia_estudiante_id && $user_id > 0) {
        if (!did_action('rda_assigned_es_license_for_user_'.$user_id)) {
            error_log('[MemberDash] add_membership ES -> asignar/generar código ES para user_id=' . $user_id);
            assign_membresia_codigo_a_usuario($user_id);

            if (class_exists('MS_Model_Relationship') && method_exists('MS_Model_Relationship','get_subscription')) {
                $rel = MS_Model_Relationship::get_subscription($user_id, $membresia_estudiante_id);
                $rel = rda_md_rel_from_mixed($rel);
                if ($rel) {
                    rda_md_normalize_and_publish($rel, 1);
                }
            }
            rd_set_role_por_tipo($user_id, 'ES');
            $es_courses = rda_get_courses_by_tipo('ES');
            if (!empty($es_courses)) {
                rd_asignar_cursos_learndash($user_id, $es_courses);
                if (function_exists('learndash_user_clear_course_transients')) foreach ($es_courses as $cid) learndash_user_clear_course_transients($user_id, (int)$cid);
            }
            do_action('rda_assigned_es_license_for_user_'.$user_id);
        }
    }

    $running = false;
    return $subscription;
}, 10, 5);

/* =========================================================
 *  LICENCIAS: asignar/generar para ES
 * ========================================================= */
function assign_membresia_codigo_a_usuario($user_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'rd_membresias';
    $user_id = (int)$user_id;

    $ya = $wpdb->get_var($wpdb->prepare("SELECT codigo FROM $table WHERE user_id=%d AND status='asignado' LIMIT 1", $user_id));
    if ($ya) { update_user_meta($user_id, 'rd_codigo_membresia', $ya); error_log("[RDA ES hook] Ya tenía activa: $ya"); return; }

    $codigo = $wpdb->get_var("SELECT codigo FROM $table WHERE status='libre' AND tipo_usuario='ES' LIMIT 1");
    if ($codigo) {
        $wpdb->update($table, ['status'=>'asignado','user_id'=>$user_id,'fecha_asignacion'=>current_time('mysql')], ['codigo'=>$codigo]);
        update_user_meta($user_id, 'rd_codigo_membresia', $codigo);
        error_log("[RDA ES hook] Asignado libre existente: $codigo");
        return;
    }

    $anio2 = date('y'); $intentos = 0;
    do {
        $intentos++;
        $ultimo = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(CAST(RIGHT(codigo,14) AS UNSIGNED)) FROM $table WHERE tipo_usuario='ES' AND LEFT(RIGHT(codigo,16),2)=%s",
            $anio2
        ));
        $next   = str_pad((int)$ultimo + 1, 14, '0', STR_PAD_LEFT);
        $codigo = 'ES' . $anio2 . $next;
        $num4   = substr($next, -4);

        $ok = $wpdb->insert($table, [
            'codigo'=>$codigo,'tipo_usuario'=>'ES','anio'=>$anio2,'id_escuela'=>'0000','escolaridad'=>'00','grado'=>'00','grupo'=>'00',
            'num_alumno'=>$num4,'status'=>'asignado','user_id'=>$user_id,'fecha_asignacion'=>current_time('mysql'),'fecha_uso'=>null
        ], ['%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s']);
        if ($ok) { update_user_meta($user_id,'rd_codigo_membresia',$codigo); error_log("[RDA ES hook] Generado y asignado: $codigo (num4=$num4)"); return; }
    } while ($intentos < 3 && strpos((string)$wpdb->last_error, 'Duplicate') !== false);

    error_log('[RDA ES hook] ERROR al generar licencia ES: '.$wpdb->last_error);
}

/* =========================================================
 *  SHORTCODE: Registrar escuelas
 * ========================================================= */
add_shortcode('rd_registrar_escuela', function(){
    if(isset($_POST['rd_escuela_nombre']) && current_user_can('manage_options')){
        $escuelas = get_option('rd_escuelas', []);
        $nombre = trim(sanitize_text_field($_POST['rd_escuela_nombre']));
        if($nombre){ $nuevo_id = str_pad(count($escuelas)+1, 4, '0', STR_PAD_LEFT); $escuelas[$nuevo_id] = $nombre; update_option('rd_escuelas', $escuelas);
            echo "<div style='color:#2e467b;background:#d5f7ec;padding:10px 14px;border-radius:6px;margin-bottom:10px;'>Escuela <b>$nombre</b> registrada con ID <b>$nuevo_id</b>.</div>";
        }
    }
    $escuelas = get_option('rd_escuelas', []);
    ob_start(); ?>
    <form method="POST" style="max-width:420px;margin:1em auto 2em auto;padding:20px;background:#f8f8ff;border-radius:12px;box-shadow:0 1px 6px #2e467b22;">
        <h3 style="color:#2e467b;margin-bottom:12px;">Registrar nueva escuela</h3>
        <input name="rd_escuela_nombre" type="text" placeholder="Nombre de la escuela" required style="width:75%;padding:8px 10px;border-radius:6px;border:1px solid #ccc;">
        <button type="submit" style="background:#f16d10;color:#fff;border:none;padding:8px 18px;margin-left:6px;border-radius:6px;cursor:pointer;font-weight:600;">Registrar</button>
    </form>
    <div style="max-width:500px;margin:1em auto;">
        <h4 style="color:#2e467b;">Escuelas registradas</h4>
        <table style="width:100%;background:#fff;border-radius:8px;box-shadow:0 1px 6px #2e467b22;">
            <tr style="background:#2e467b;color:#fff;"><th style="padding:7px 0;">ID</th><th style="padding:7px 0;">Nombre</th></tr>
            <?php foreach($escuelas as $id=>$nombre): ?>
            <tr><td style="padding:7px 0;text-align:center;"><?php echo $id; ?></td><td style="padding:7px 0;"><?php echo esc_html(stripslashes($nombre)); ?></td></tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php return ob_get_clean();
});

/* =========================================================
 *  AJAX util: ajaxurl al front
 * ========================================================= */
add_action('wp_enqueue_scripts', function(){
    if (!is_admin()) echo "<script>var ajaxurl='".admin_url('admin-ajax.php')."';</script>";
});

/* =========================================================
 *  AJAX: enmascarar / guardar / máximos / asegurar grupo
 * ========================================================= */
add_action('wp_ajax_rd_enmascarar_codigos', function(){
    $codigos = isset($_POST['codigos']) ? json_decode(stripslashes($_POST['codigos']), true) : [];
    if (!$codigos || !is_array($codigos)) wp_send_json_error('No se recibieron códigos');
    $enmascarados = [];
    foreach ($codigos as $c) $enmascarados[] = rd_formatear_cod_con_guiones(rd_enmascarar_licencia($c));
    wp_send_json_success($enmascarados);
});
add_action('wp_ajax_rd_guardar_codigos_membresia', function(){
    if (!current_user_can('manage_options')) wp_send_json_error('No autorizado');
    global $wpdb; $table = $wpdb->prefix . 'rd_membresias';

    $data = isset($_POST['data']) ? json_decode(stripslashes($_POST['data']), true) : [];
    if (!$data || !is_array($data)) wp_send_json_error('Datos incorrectos');

    $guardados = [];
    foreach ($data as $row) {
        $codigo       = sanitize_text_field($row['codigo']);
        $tipo_usuario = sanitize_text_field($row['tipo_usuario']);
        $anio         = sanitize_text_field($row['anio']);
        $id_escuela   = sanitize_text_field($row['id_escuela']);
        $escolaridad  = sanitize_text_field($row['escolaridad']);
        $grado        = sanitize_text_field($row['grado']);
        $grupo        = sanitize_text_field($row['grupo']);
        $num_alumno   = sanitize_text_field($row['num_alumno']);

        $existe = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE codigo = %s", $codigo));
        if ($existe) continue;

        $ok = $wpdb->insert($table, [
            'codigo'=>$codigo,'tipo_usuario'=>$tipo_usuario,'anio'=>$anio,'id_escuela'=>$id_escuela,
            'escolaridad'=>$escolaridad,'grado'=>$grado,'grupo'=>$grupo,'num_alumno'=>$num_alumno,
            'status'=>'libre','fecha_asignacion'=>null,'user_id'=>null,'fecha_uso'=>null
        ]);
        if ($ok) $guardados[] = $codigo;
    }
    wp_send_json_success(['guardados'=>$guardados,'total'=>count($guardados)]);
});
add_action('wp_ajax_rd_max_alumno_grupo', function(){
    if (!current_user_can('manage_options')) wp_send_json_error('No autorizado');
    global $wpdb; $table = $wpdb->prefix . 'rd_membresias';
    $anio = sanitize_text_field($_POST['anio'] ?? '');
    $id_escuela = sanitize_text_field($_POST['id_escuela'] ?? '');
    $escolaridad = sanitize_text_field($_POST['escolaridad'] ?? '');
    $grado = sanitize_text_field($_POST['grado'] ?? '');
    $grupo = sanitize_text_field($_POST['grupo'] ?? '');
    $max = $wpdb->get_var($wpdb->prepare(
        "SELECT MAX(CAST(num_alumno AS UNSIGNED)) FROM $table WHERE anio=%s AND id_escuela=%s AND escolaridad=%s AND grado=%s AND grupo=%s",
        $anio, $id_escuela, $escolaridad, $grado, $grupo
    ));
    wp_send_json_success(['max'=>intval($max)]);
});
add_action('wp_ajax_rd_ensure_ld_group_for_batch', function(){
    if (!current_user_can('manage_options')) wp_send_json_error('No autorizado');
    $anio = sanitize_text_field($_POST['anio'] ?? '');
    $id_escuela = sanitize_text_field($_POST['id_escuela'] ?? '');
    $escolaridad = sanitize_text_field($_POST['escolaridad'] ?? '');
    $grado = sanitize_text_field($_POST['grado'] ?? '');
    $grupo = sanitize_text_field($_POST['grupo'] ?? '');
    if (!$anio || !$id_escuela || !$escolaridad || !$grado || !$grupo) wp_send_json_error('Parámetros incompletos');
    $gid = rda_ensure_ld_group('CG', $anio, $id_escuela, $escolaridad, $grado, $grupo);
    if ($gid) wp_send_json_success(['group_id'=>$gid, 'title'=>get_the_title($gid)]);
    wp_send_json_error('No se pudo crear/asegurar el grupo.');
});
add_action('wp_ajax_rd_max_es_correlativo', function(){
    if (!current_user_can('manage_options')) wp_send_json_error('No autorizado');
    global $wpdb; $table = $wpdb->prefix . 'rd_membresias';
    $anio2 = sanitize_text_field($_POST['anio2'] ?? '');
    if ($anio2 === '' || strlen($anio2) !== 2) wp_send_json_error('Año inválido');
    $max = $wpdb->get_var($wpdb->prepare(
        "SELECT MAX(CAST(RIGHT(codigo, 14) AS UNSIGNED)) FROM $table WHERE tipo_usuario='ES' AND LEFT(RIGHT(codigo,16),2) = %s",
        $anio2
    ));
    wp_send_json_success(['max'=>intval($max)]);
});

/* =========================================================
 *  SHORTCODE: Generar códigos CG/ES (UI)
 * ========================================================= */
add_shortcode('rd_generar_codigos_membresia', function(){
    $escuelas = get_option('rd_escuelas', []);
    ob_start(); ?>
    <form id="rd-gen-codigos-form" style="max-width:520px;margin:2em auto;background:#f8f8ff;padding:30px 20px;border-radius:16px;box-shadow:0 2px 8px #2e467b22;">
        <h3 style="color:#2e467b;text-align:center;margin-bottom:18px;">Generar Códigos de Membresía</h3>
        <label>Tipo de usuario:
            <select id="tipo_usuario" required onchange="mostrarColegioCampos(this.value)">
                <option value="CG">Colegio</option>
                <option value="ES">Estudiante</option>
            </select>
        </label><br>

        <div id="campos_colegio">
            <label>A&ntilde;o:
                <select id="anio" required>
                <?php for($an=25;$an<=50;$an++){ $n=str_pad($an,2,'0',STR_PAD_LEFT); echo "<option value='$n'>20{$an}</option>"; } ?>
                </select>
            </label><br>
            <label>Escuela:
                <select id="escuela" required>
                    <?php foreach($escuelas as $id=>$nombre){ echo "<option value='$id'>($id) ".esc_html(stripslashes($nombre))."</option>"; } ?>
                </select>
            </label><br>
            <label>Escolaridad:
                <select id="escolaridad" required>
                    <option value="01">Primaria</option>
                    <option value="02">Secundaria</option>
                    <option value="03">Preparatoria</option>
                    <option value="04">Universidad</option>
                </select>
            </label><br>
            <label>Grado:
                <select id="grado" required>
                    <?php for($i=1;$i<=12;$i++){ $v=str_pad($i,2,'0',STR_PAD_LEFT); echo "<option value='$v'>{$i}°</option>"; } ?>
                </select>
            </label><br>
            <label>Grupo:
                <select id="grupo" required>
                    <?php foreach(range('A','Z') as $i=>$g){ $val=str_pad($i+1,2,'0',STR_PAD_LEFT); echo "<option value='$val'>{$g}</option>"; } ?>
                </select>
            </label><br>
            <label>Cantidad de alumnos:
                <input type="number" id="cantidad" min="1" max="300" required value="1">
            </label><br>
        </div>

        <div id="campos_estudiante" style="display:none;">
            <label>A&ntilde;o (YYYY):
                <input type="number" id="anio_es" value="<?php echo date('Y'); ?>" required min="2023" max="2099">
            </label><br>
            <label>Cantidad de licencias:
                <input type="number" id="cantidad_es" min="1" max="1000" required value="30">
            </label><br>
        </div>

        <button type="button" id="generar_codigos" style="margin-top:14px;background:#2e467b;color:#fff;border:none;padding:10px 24px;border-radius:7px;font-weight:bold;cursor:pointer;">Generar</button>
    </form>

    <div id="codigos_resultado" style="max-width:700px;margin:1.5em auto 3em auto;padding:10px;"></div>

    <div id="modal-licencias" style="display:none; position:fixed; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.3); z-index:9999; justify-content:center; align-items:center;">
      <div id="modal-licencias-msg" style="background:#fff; padding:30px 50px; border-radius:18px; box-shadow:0 0 20px #0002; font-size:1.1em; color:#2e467b; text-align:center;"></div>
    </div>

    <script>
    function mostrarModalLicencias(mensaje) {
        var modal = document.getElementById('modal-licencias');
        var msg = document.getElementById('modal-licencias-msg');
        msg.innerHTML = mensaje; modal.style.display = "flex";
        setTimeout(function(){ modal.style.display = "none"; }, 3000);
    }
    function mostrarColegioCampos(val){
        document.getElementById('campos_colegio').style.display    = (val==="CG") ? "" : "none";
        document.getElementById('campos_estudiante').style.display = (val==="ES") ? "" : "none";
    }
    if (typeof ajaxurl === "undefined") { var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>'; }

    document.getElementById('generar_codigos').onclick = function(){
        let tipo = document.getElementById('tipo_usuario').value;
        if (typeof ajaxurl === "undefined") { alert('ajaxurl no definido'); return; }

        if (tipo === "ES") {
            const anio_es_full = document.getElementById('anio_es').value;
            const cantidad_es  = parseInt(document.getElementById('cantidad_es').value, 10);
            if (!anio_es_full || !cantidad_es || cantidad_es < 1) { alert('Completa año y cantidad válidos'); return; }

            const anio2 = String(anio_es_full).slice(2);
            const boton = this; boton.disabled = true; boton.textContent = 'Procesando...';

            fetch(ajaxurl, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:'action=rd_max_es_correlativo&anio2=' + encodeURIComponent(anio2)
            })
            .then(r => r.json())
            .then(r => {
                let start = 1;
                if (r.success && r.data && typeof r.data.max === 'number') start = r.data.max + 1;

                const codigos = [], dataToSend = [];
                for (let i=0;i<cantidad_es;i++){
                    const corr14 = String(start+i).padStart(14,'0');
                    const codigo = `ES${anio2}${corr14}`;
                    const num4 = corr14.slice(-4);
                    codigos.push(codigo);
                    dataToSend.push({codigo:codigo,tipo_usuario:'ES',anio:anio2,id_escuela:'0000',escolaridad:'00',grado:'00',grupo:'00',num_alumno:num4});
                }

                return fetch(ajaxurl,{ method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
                    body:'action=rd_enmascarar_codigos&codigos=' + encodeURIComponent(JSON.stringify(codigos))
                })
                .then(res=>res.json())
                .then(maskRes=>{
                    let html = '<h4>Códigos reales (ES):</h4><textarea style="width:98%;height:120px;">' + codigos.join('\n') + '</textarea>';
                    html += '<h4 style="margin-top:16px;">Códigos enmascarados para entregar:</h4><textarea style="width:98%;height:120px;background:#e9fff5;">' + (maskRes.success && maskRes.data ? maskRes.data.join('\n') : 'Error al enmascarar') + '</textarea>';
                    document.getElementById('codigos_resultado').innerHTML = html;

                    return fetch(ajaxurl,{ method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
                        body:'action=rd_guardar_codigos_membresia&data=' + encodeURIComponent(JSON.stringify(dataToSend))
                    });
                })
                .then(res=>res.json())
                .then(saveRes=>{
                    boton.disabled=false; boton.textContent='Generar';
                    if (saveRes && saveRes.success) { mostrarModalLicencias(`✅ ${saveRes.data.total} códigos ES guardados`); }
                    else { mostrarModalLicencias(`❌ Error al guardar en la base de datos`); }
                })
                .catch(_=>{ boton.disabled=false; boton.textContent='Generar'; alert('Error procesando códigos ES'); });
            })
            .catch(_=>{ const boton=document.getElementById('generar_codigos'); boton.disabled=false; boton.textContent='Generar'; alert('Error al consultar el correlativo ES'); });

            return;
        }

        let anio        = document.getElementById('anio').value;
        let id_escuela  = String(document.getElementById('escuela').value).padStart(4,'0');
        let escolaridad = document.getElementById('escolaridad').value;
        let grado       = document.getElementById('grado').value;
        let grupo       = document.getElementById('grupo').value;
        let cantidad    = parseInt(document.getElementById('cantidad').value, 10);

        const boton = this; boton.disabled=true; boton.textContent = 'Enmascarando...';

        fetch(ajaxurl, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:`action=rd_max_alumno_grupo&anio=${anio}&id_escuela=${id_escuela}&escolaridad=${escolaridad}&grado=${grado}&grupo=${grupo}`
        })
        .then(res=>res.json())
        .then(res=>{
            let start = 1;
            if (res.success && res.data && typeof res.data.max === "number") start = res.data.max + 1;
            const codigos = [], dataToSend = [];
            for (let i=0;i<cantidad;i++){
                const num_alumno = String(start+i).padStart(4,'0');
                const codigo = `CG${anio}${id_escuela}${escolaridad}${grado}${grupo}${num_alumno}`;
                codigos.push(codigo);
                dataToSend.push({codigo,tipo_usuario:'CG',anio,id_escuela,escolaridad,grado,grupo,num_alumno});
            }

            return fetch(ajaxurl,{ method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:'action=rd_enmascarar_codigos&codigos=' + encodeURIComponent(JSON.stringify(codigos))
            })
            .then(res=>res.json())
            .then(maskRes=>{
                let html = '<h4>Códigos reales (CG):</h4><textarea style="width:98%;height:120px;">' + codigos.join('\n') + '</textarea>';
                html += '<h4 style="margin-top:16px;">Códigos enmascarados para entregar:</h4><textarea style="width:98%;height:120px;background:#e9fff5;">' + (maskRes.success && maskRes.data ? maskRes.data.join('\n') : 'Error al enmascarar') + '</textarea>';
                document.getElementById('codigos_resultado').innerHTML = html;

                return fetch(ajaxurl,{ method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
                    body:'action=rd_guardar_codigos_membresia&data=' + encodeURIComponent(JSON.stringify(dataToSend))
                });
            })
            .then(res=>res.json())
            .then(saveRes=>{
                boton.disabled=false; boton.textContent='Generar';
                if (saveRes && saveRes.success) {
                    mostrarModalLicencias(`✅ ${saveRes.data.total} códigos guardados exitosamente`);
                    fetch(ajaxurl,{ method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
                        body:'action=rd_ensure_ld_group_for_batch'
                            + '&anio=' + encodeURIComponent(anio)
                            + '&id_escuela=' + encodeURIComponent(id_escuela)
                            + '&escolaridad=' + encodeURIComponent(escolaridad)
                            + '&grado=' + encodeURIComponent(grado)
                            + '&grupo=' + encodeURIComponent(grupo)
                    })
                    .then(r=>r.json())
                    .then(gr=>{ if (gr && gr.success) mostrarModalLicencias(`✅ ${saveRes.data.total} códigos guardados. Grupo LD: ${gr.data.title} (#${gr.data.group_id})`);
                                else mostrarModalLicencias(`✅ ${saveRes.data.total} códigos guardados. ⚠️ Grupo LD no se aseguró.`); })
                    .catch(()=> mostrarModalLicencias(`✅ ${saveRes.data.total} códigos guardados. ⚠️ Error al asegurar el grupo.`));
                } else {
                    mostrarModalLicencias(`❌ Error al guardar en la base de datos`);
                }
            })
            .catch(_=>{ boton.disabled=false; boton.textContent='Generar'; alert('Error al enmascarar/guardar códigos CG'); });
        })
        .catch(_=>{ boton.disabled=false; boton.textContent='Generar'; alert('Error al consultar el máximo del grupo'); });
    };
    </script>
    <?php return ob_get_clean();
});

/* =========================================================
 *  AJAX: Canje/validación de licencias (CG/ES)
 * ========================================================= */
add_action('wp_ajax_rd_validar_y_asignar_licencia', function(){
    check_ajax_referer('rd-lic');
    if (!is_user_logged_in()) wp_send_json_error('Debes iniciar sesión.');

    global $wpdb; $table = $wpdb->prefix . 'rd_membresias';
    $user_id = get_current_user_id();

    $lic_user = isset($_POST['licencia']) ? trim(sanitize_text_field($_POST['licencia'])) : '';
    if ($lic_user === '') wp_send_json_error('Ingresa tu licencia.');
    $lic_normalizada = str_replace('-', '', $lic_user);

    $row_actual = $wpdb->get_row($wpdb->prepare("SELECT codigo FROM $table WHERE user_id=%d AND status='asignado' LIMIT 1", $user_id));
    if ($row_actual) {
        $mask = strtoupper(rd_formatear_cod_con_guiones(rd_enmascarar_licencia($row_actual->codigo)));
        wp_send_json_error('Tu cuenta ya tiene una licencia activa:<br><span style="font-size:1.1em;color:#004;">'.$mask.'</span>');
    }

    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE codigo=%s LIMIT 1", $lic_normalizada));
    if (!$row) {
        $posible_real = rd_desenmascarar_licencia($lic_normalizada);
        if ($posible_real) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE codigo=%s LIMIT 1", $posible_real));
            if ($row) $lic_normalizada = $posible_real;
        }
    }
    if (!$row) wp_send_json_error('La licencia no existe.');
    if ($row->status !== 'libre' || !empty($row->user_id)) wp_send_json_error('Esta licencia ya ha sido usada o está inactiva.');

    $ok = $wpdb->update($table, ['status'=>'asignado','user_id'=>$user_id,'fecha_asignacion'=>current_time('mysql')], ['id'=>(int)$row->id]);
    if ($ok === false || $ok === 0) wp_send_json_error('No se pudo asignar la licencia (BD).');
    update_user_meta($user_id, 'rd_codigo_membresia', $lic_normalizada);

    $mids = rda_get_membership_ids();
    $tipo_lic = strtoupper(substr($lic_normalizada, 0, 2));
    $mem_to_assign = ($tipo_lic === 'ES') ? (int)$mids['ES'] : (($tipo_lic === 'CG') ? (int)$mids['CG'] : 0);

    $ok_membership = false; $rel_created = null;

    $force_paid_cb = function($paid){ return true; };
    add_filter('ms_model_relationship_admin_gateway_paid', $force_paid_cb, 99);
    add_filter('ms_model_relationship_is_paid', $force_paid_cb, 99);
    add_filter('ms_model_relationship_is_paid_admin_gateway',$force_paid_cb, 99);

    if (class_exists('MS_Model_Relationship') && method_exists('MS_Model_Relationship','get_subscription')){
        $existing = MS_Model_Relationship::get_subscription($user_id, $mem_to_assign);
        $existing = rda_md_rel_from_mixed($existing);
        if ($existing){ $ok_membership = rda_md_normalize_and_publish($existing, 1); $rel_created = $existing; }
    }
    if (!$ok_membership && class_exists('MS_Model_Relationship')) {
        $rel = MS_Model_Relationship::create_ms_relationship($mem_to_assign, $user_id, 'admin', '', true);
        $rel_created = $rel;
        $rel = rda_md_rel_from_mixed($rel);
        if ($rel){ $ok_membership = rda_md_normalize_and_publish($rel, 1); }
    }
    if (!$ok_membership && class_exists('MS_Factory')){
        $member = MS_Factory::create('MS_Model_Member', $user_id);
        if ($member && method_exists($member,'add_membership')){
            $sub = $member->add_membership($mem_to_assign, 'admin');
            $rel_created = $rel_created ?: $sub;
            $sub = rda_md_rel_from_mixed($sub);
            if ($sub){ $ok_membership = rda_md_normalize_and_publish($sub, 1); }
        }
    }
    if (!$ok_membership && $rel_created){ $ok_membership = rda_md_hard_publish_active($rel_created, 1); }

    remove_filter('ms_model_relationship_admin_gateway_paid', $force_paid_cb, 99);
    remove_filter('ms_model_relationship_is_paid', $force_paid_cb, 99);
    remove_filter('ms_model_relationship_is_paid_admin_gateway',$force_paid_cb, 99);

    if ($ok_membership) rd_set_role_por_tipo($user_id, $tipo_lic); else if ($rel_created) rd_set_role_por_tipo($user_id, $tipo_lic);

    if (($ok_membership || $rel_created) && $tipo_lic === 'CG' && function_exists('ld_update_group_access')) {
        if (preg_match('/^(ES|CG)\d{16}$/', $lic_normalizada)) {
            $anio = substr($lic_normalizada, 2, 2);
            $id_escuela = substr($lic_normalizada, 4, 4);
            $escolaridad = substr($lic_normalizada, 8, 2);
            $grado = substr($lic_normalizada,10, 2);
            $grupo = substr($lic_normalizada,12, 2);
            $group_key = sprintf('CG-%s-%s-%s-%s-%s', $anio, $id_escuela, $escolaridad, $grado, $grupo);

            $gids = get_posts(['post_type'=>'groups','posts_per_page'=>1,'post_status'=>'any','meta_key'=>'rda_group_key','meta_value'=>$group_key,'fields'=>'ids','no_found_rows'=>true]);
            if (!empty($gids)) {
                $gid = (int)$gids[0];
                rda_attach_courses_to_group($gid, 'CG');
                ld_update_group_access($user_id, $gid, false);
                if (function_exists('learndash_user_clear_course_transients')) learndash_user_clear_course_transients($user_id);
                error_log("[RDA canje] User {$user_id} ligado a grupo LD #{$gid} por clave {$group_key}");
            } else {
                error_log("[RDA canje][WARN] No se encontró grupo LD para clave {$group_key}");
            }
        }
    }

    $mask_ok = strtoupper(rd_formatear_cod_con_guiones(rd_enmascarar_licencia($lic_normalizada)));
    wp_send_json_success([
        'mask'    => $mask_ok,
        'tipo'    => $tipo_lic,
        'user_id' => $user_id,
        'msg'     => $ok_membership ? '¡Licencia y membresía asignadas!' : 'Licencia asignada. Verifica tu membresía en unos segundos.',
    ]);
});

/* =========================================================
 *  REVOCACIÓN (AJAX + shortcode)
 * ========================================================= */
function rda_revocar_por_codigo($codigo){
    global $wpdb; $table = $wpdb->prefix.'rd_membresias';
    $row = rda_get_lic_row_by_codigo($codigo);
    if (!$row) return ['success'=>false, 'message'=>'La licencia no existe'];

    // Si ya no estaba asignada, márcala como removida y listo
    if ($row->status !== 'asignado' || empty($row->user_id)) {
        $ok = $wpdb->update($table, ['status'=>'removido'], ['id'=>(int)$row->id], ['%s'], ['%d']);
        if ($ok === false) error_log('[RDA revocar] DB error (no asignada): '.$wpdb->last_error);
        return ['success'=>true, 'message'=>'La licencia no estaba asignada; se marcó como removida.'];
    }

    $user_id = (int)$row->user_id;
    $tipo    = strtoupper(substr($row->codigo, 0, 2));
    $mids    = rda_get_membership_ids();
    $mem_id  = isset($mids[$tipo]) ? (int)$mids[$tipo] : 0;

    // Desactivar relación de MemberDash (objeto garantizado)
    if ($mem_id && class_exists('MS_Model_Relationship') && method_exists('MS_Model_Relationship','get_subscription')){
        $sub = MS_Model_Relationship::get_subscription($user_id, $mem_id);
        rda_md_deactivate_relationship($sub);
    }

    // Quitar LearnDash + rol
    rda_remove_learndash_access($user_id, $tipo);
    rda_set_only_logueado($user_id);

    // Marcar licencia como removida
    $ok = $wpdb->update($table, ['status'=>'removido'], ['id'=>(int)$row->id], ['%s'], ['%d']);
    if ($ok === false) error_log('[RDA revocar] DB error (asignada): '.$wpdb->last_error.' id='.$row->id);

    // Limpia meta de usuario
    delete_user_meta($user_id, 'rd_codigo_membresia');

    return ['success'=>true, 'message'=>'Licencia revocada: membresía desactivada, acceso LD removido y rol ajustado.'];
}
add_action('wp_ajax_rd_revocar_licencia', function(){
    check_ajax_referer('rd-lic');
    if (!current_user_can('manage_options')) wp_send_json_error('No autorizado');
    $codigo = isset($_POST['codigo']) ? sanitize_text_field($_POST['codigo']) : '';
    if (!$codigo) wp_send_json_error('Código requerido');
    $res = rda_revocar_por_codigo($codigo);
    if ($res['success']) wp_send_json_success($res['message']); wp_send_json_error($res['message']);
});
add_action('wp_ajax_rd_liberar_licencia', function(){
    check_ajax_referer('rd-lic');
    if (!current_user_can('manage_options')) wp_send_json_error('No autorizado');
    global $wpdb; $table = $wpdb->prefix.'rd_membresias';
    $codigo = isset($_POST['codigo']) ? sanitize_text_field($_POST['codigo']) : '';
    if (!$codigo) wp_send_json_error('Código requerido');
    $row = rda_get_lic_row_by_codigo($codigo);
    if (!$row) wp_send_json_error('No existe la licencia');
    if ($row->status !== 'removido') wp_send_json_error('La licencia no está en estado removido');
    $wpdb->update($table, ['status'=>'libre','user_id'=>null,'fecha_asignacion'=>null], ['id'=>(int)$row->id]);
    wp_send_json_success('Licencia liberada (disponible para reasignar).');
});
add_shortcode('rd_revocar_licencia_admin', function(){
    if (!current_user_can('manage_options')) return '<div style="background:#fff4f4;color:#7a1010;padding:10px;border-radius:8px;">No autorizado.</div>';
    $nonce = wp_create_nonce('rd-lic');
    ob_start(); ?>
    <div style="max-width:520px;margin:1.5em auto;padding:18px;border-radius:12px;background:#f8f8ff;box-shadow:0 1px 6px #2e467b22;">
        <h3 style="margin:0 0 10px;color:#2e467b;">Revocar licencia</h3>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <input id="rda-revocar-codigo" type="text" placeholder="Código real o enmascarado"
                   style="flex:1;min-width:260px;padding:10px;border:1px solid #cfd6e4;border-radius:10px;">
            <button id="rda-revocar-btn" style="padding:10px 16px;border:none;border-radius:10px;background:#a50c0c;color:#fff;font-weight:700;cursor:pointer;">
                Revocar
            </button>
            <button id="rda-liberar-btn" style="padding:10px 16px;border:none;border-radius:10px;background:#6b7280;color:#fff;cursor:pointer;">
                Liberar
            </button>
        </div>
        <div id="rda-revocar-msg" style="margin-top:12px;color:#444;"></div>
    </div>
    <script>
    (function(){
      const ajaxurl = (typeof window.ajaxurl!=='undefined') ? window.ajaxurl : '<?php echo admin_url('admin-ajax.php'); ?>';
      const nonce   = '<?php echo esc_js($nonce); ?>';
      const input   = document.getElementById('rda-revocar-codigo');
      const out     = document.getElementById('rda-revocar-msg');

      async function call(action){
        const code = (input.value||'').trim();
        if(!code){ out.textContent='Ingresa un código.'; return; }
        const body = new URLSearchParams();
        body.set('action', action); body.set('_ajax_nonce', nonce); body.set('codigo', code);
        out.textContent = 'Procesando...';
        try{
          const r = await fetch(ajaxurl, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString() }).then(x=>x.json());
          out.style.color = r.success ? '#228b22' : '#a50c0c';
          out.textContent = (r.data || (r.success ? 'Listo.' : 'Error'));
          if(r.success) setTimeout(()=>location.reload(), 1000);
        }catch(_){ out.style.color = '#a50c0c'; out.textContent = 'Error de red.'; }
      }
      document.getElementById('rda-revocar-btn').addEventListener('click', ()=>call('rd_revocar_licencia'));
      document.getElementById('rda-liberar-btn').addEventListener('click', ()=>call('rd_liberar_licencia'));
    })();
    </script>
    <?php return ob_get_clean();
});
//Barrido 
// Ejecutar SOLO manualmente con ?rda_fix_waiting=1 y SOLO sobre waiting
add_action('admin_init', function(){
    if (!current_user_can('manage_options')) return;
    if (!isset($_GET['rda_fix_waiting'])) return; // <-- correlo a demanda

    $q = new WP_Query([
        'post_type'      => 'ms_relationship',
        'post_status'    => 'any',
        'posts_per_page' => 200,
        'meta_query'     => [
            ['key'=>'gateway_id','value'=>'admin','compare'=>'='],
            ['key'=>'status',    'value'=>'waiting','compare'=>'='], // <-- solo waiting
        ],
        'no_found_rows'  => true,
        'fields'         => 'ids',
    ]);

    foreach ($q->posts as $rid){
        update_post_meta($rid, 'status','active');
        update_post_meta($rid, '_status','active');
        update_post_meta($rid, 'ms_status','active');
        wp_update_post(['ID'=>$rid,'post_status'=>'publish']);
        clean_post_cache($rid);
    }
});
//helpers
function rda_md_deactivate_relationship($rel_or_id){
    $rel = rda_md_rel_from_mixed($rel_or_id);
    if (!$rel) return false;

    // Estado del modelo
    if (method_exists($rel,'set_status')) $rel->set_status('deactivated');
    elseif (property_exists($rel,'status')) $rel->status = 'deactivated';

    // Espejar en el CPT + metas que la UI lee
    if (property_exists($rel,'id') && (int)$rel->id){
        $rid = (int)$rel->id;
        $today = date('Y-m-d', current_time('timestamp'));
        $today_gmt = substr(get_gmt_from_date($today.' 00:00:00'),0,10);

        update_post_meta($rid, 'status','deactivated');
        update_post_meta($rid, '_status','deactivated');
        update_post_meta($rid, 'ms_status','deactivated');

        update_post_meta($rid, 'expire_date',     $today);
        update_post_meta($rid, 'expire_date_gmt', $today_gmt);
        update_post_meta($rid, 'end_date',        $today);
        update_post_meta($rid, '_end_date',       $today);

        if (method_exists($rel,'save')) $rel->save();
        wp_update_post(['ID'=>$rid,'post_status'=>'publish']);
        clean_post_cache($rid);
    }
    return true;
}


