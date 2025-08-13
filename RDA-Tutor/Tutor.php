<?php 
/**
 * Plugin Name: RDA-Tutor
 * Plugin URI: https://robodacta-steam.mx
 * Description: Funciones de Tutor para LearnDash, incluyendo progresos e informes
 * Version: 1.3.0
 * Author: Robodacta
 * Author URI: https://robodacta-steam.mx
 * License: GPL2
 */
 
// =======================
// Dependencias propias
// =======================
if (!defined('TRD_DIR')) define('TRD_DIR', plugin_dir_path(__FILE__));
if (!defined('TRD_URL')) define('TRD_URL', plugin_dir_url(__FILE__));

$trd_includes = [   
    'tutor-dashboard.php'              => 'Dashboard Tutor',
    'seleccion-kit.php'                 => 'Selección-Kit',
    'consulta-juegos-cuestionarios.php' => 'Consulta Juegos-Cuestionarios',
];

foreach ($trd_includes as $file => $label) {
    $path = TRD_DIR . 'includes/' . $file;
    if (is_readable($path)) {
        require_once $path;
    } else {
        // Aviso en admin y log si el archivo falta (evita fatal)
        add_action('admin_notices', function() use ($path, $label, $file) {
            echo '<div class="notice notice-error"><p><strong>Tutor Robodacta:</strong> No se encontró <code>'
               . esc_html($file) . '</code> (' . esc_html($label) . ') en <code>'
               . esc_html($path) . '</code>.</p></div>';
        });
        error_log('[Tutor Robodacta] Falta include: ' . $label . ' => ' . $path);
    }
}

// =======================
// 1. Permisos para Group Leaders
// =======================
function robodacta_add_group_leader_caps() {
    $role = get_role('group_leader');
    if (!$role) return;

    $role->add_cap('groups_assign_groups');
    $role->add_cap('groups_manage_groups');
    $role->add_cap('read');
    $role->add_cap('edit_posts');
}
add_action('init', 'robodacta_add_group_leader_caps');


// ==========================
// Tabla de Alumnos Inscritos
// ==========================
add_shortcode('tutor_group_students_table', function() {
    if (!is_user_logged_in() || !current_user_can('groups_manage_groups')) {
        return '<p>No tienes permiso para ver este panel.</p>';
    }
    if (!function_exists('learndash_get_administrators_group_ids') || !function_exists('learndash_get_groups_user_ids')) {
        return '<p>LearnDash no está activo.</p>';
    }

    $user_id = get_current_user_id();
    $group_ids = learndash_get_administrators_group_ids($user_id);
    if (empty($group_ids)) {
        return '<p>No lideras ningún grupo.</p>';
    }
    $group_names = [];
    foreach ($group_ids as $gid) {
        $group_names[$gid] = get_the_title($gid);
    }
    asort($group_names, SORT_NATURAL | SORT_FLAG_CASE);
    $group_ids = array_keys($group_names);

    // Cambia aquí la URL a la de tu página de informes (debe tener el shortcode de abajo)
    $informe_url = '/informe-de-alumno/';

    ob_start();
    ?>
    <style>
    .rd-student-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin-bottom: 2em;
        background: #fff;
        box-shadow: 0 2px 8px #2e467b33;
        border-radius: 12px;
        overflow: hidden;
    }
    .rd-student-table th, .rd-student-table td {
        padding: 12px 10px;
        border-bottom: 1px solid #f16d1055;
    }
    .rd-student-table th {
        background: #2e467b;
        color: #fff;
        font-weight: 600;
        font-size: 1.06em;
        border: none;
        letter-spacing: .04em;
    }
    .rd-student-table tr:last-child td { border-bottom: none; }
    .rd-student-table-group {
        background: #f16d10;
        color: #fff;
        font-weight: 600;
        font-size: 1.08em;
        border-bottom: 2px solid #2e467b;
    }
    .rd-student-avatar {
        border-radius: 50%;
        width: 36px;
        height: 36px;
        object-fit: cover;
        border: 2px solid #2e467b22;
        background: #f1b788;
        margin-right: 9px;
        vertical-align: middle;
    }
    .rd-informe-btn {
        background: #2e467b;
        color: #fff;
        border: none;
        border-radius: 5px;
        padding: 4px 10px;
        cursor: pointer;
        text-decoration: none;
        font-size: 1em;
        display: inline-block;
        transition: background .18s;
    }
    .rd-informe-btn:hover {
        background: #f16d10;
        color: #fff;
    }
    </style>
    <div class="rd-student-list-wrap">
    <?php foreach($group_ids as $group_id):
        $group_title = esc_html(get_the_title($group_id));
        $student_ids = learndash_get_groups_user_ids($group_id);
        if (empty($student_ids)) continue;
        // --- Ordena alumnos por nombre ---
        $students = [];
        foreach ($student_ids as $sid) {
            $u = get_userdata($sid);
            if ($u) $students[$sid] = $u->display_name;
        }
        asort($students, SORT_NATURAL | SORT_FLAG_CASE);

        ?>
        <table class="rd-student-table">
            <thead>
                <tr>
                    <th colspan="5" class="rd-student-table-group"><?php echo $group_title; ?></th>
                </tr>
                <tr>
                    <th style="width:48px;">Avatar</th>
                    <th>Nombre</th>
                    <th>Usuario</th>
                    <th>Email</th>
                    <th style="width:90px;">Informe</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $sid=>$sname):
                    $u = get_userdata($sid);
                    if (!$u) continue;
                    $avatar = get_avatar_url($u->ID, ['size'=>64]);
                    // Genera la URL del informe
                    $link = esc_url( add_query_arg('uid', $u->ID, $informe_url ) );
                    ?>
                    <tr>
                        <td><img src="<?php echo esc_url($avatar); ?>" class="rd-student-avatar" alt="Avatar"></td>
                        <td><?php echo esc_html($u->display_name); ?></td>
                        <td><?php echo esc_html($u->user_login); ?></td>
                        <td><?php echo esc_html($u->user_email); ?></td>
                        <td>
                            <a class="rd-informe-btn" href="<?php echo $link; ?>" target="_blank" title="Ver informe de progreso">
                                📊 Informe
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
});

// ==========================
// Pagina Informe de Alumno
// ==========================
add_shortcode('informe_alumno_robodacta', function(){
    $user_id = isset($_GET['uid']) ? intval($_GET['uid']) : 0;
    if(!$user_id) return '<p>No se indicó alumno.</p>';
    return do_shortcode('[ld_profile user_id="'.$user_id.'"]');
});



// ==========================
// Panel: Gestion de Acceso para Alumnos
// ==========================


add_action('init', function(){
    add_shortcode('tutor_group_unlock_panel','robodacta_group_unlock_panel');
});

function robodacta_group_unlock_panel(){
    if(!is_user_logged_in()||!current_user_can('groups_manage_groups')){
        return '<p>No tienes permiso para ver este panel.</p>';
    }
    if(
        !function_exists('learndash_get_administrators_group_ids')||
        !function_exists('learndash_get_groups_courses_ids')||
        !function_exists('learndash_get_course_lessons_list')||
        !function_exists('learndash_get_topic_list')
    ){
        return '<p>LearnDash no estÃ¡ activo o no es compatible.</p>';
    }

    $user_id   = get_current_user_id();
    $group_ids = learndash_get_administrators_group_ids($user_id);
    
    if(empty($group_ids)){
        return '<p>No lideras ningÃºn grupo.</p>';
    }
    //Acomodo de grupos alfabetico
    $group_names = [];
    foreach ($group_ids as $gid) {
        $group_names[$gid] = get_the_title($gid);
    }
    asort($group_names, SORT_NATURAL | SORT_FLAG_CASE);
    $group_ids = array_keys($group_names);

    $locks  = get_option('robodacta_group_locks',[]);
    $course_locks = get_option('robodacta_course_locks',[]);
    ob_start();
    ?>
    <style>
    .rd-group-title {
        font-size: 1.5em;
        color: #2e467b;
        margin: 36px 0 16px 0;
        font-weight: bold;
    }
    .rd-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        background: #fff;
        margin-bottom: 1.5em;
        box-shadow: 0 2px 8px #2e467b33;
        border-radius: 12px;
        overflow: hidden;
    }
    .rd-table th, .rd-table td {
        padding: 12px 10px;
        border-bottom: 1px solid #f16d1055;
    }
    .rd-table th {
        background: #2e467b;
        color: #fff;
        font-weight: 600;
        letter-spacing: .04em;
        font-size: 1.06em;
        border: none;
    }
    .rd-table tr:last-child td { border-bottom: none; }
    .rd-course-head {
        background: #f16d10;
        color: #fff;
        font-weight: 600;
        font-size: 1.08em;
        border-bottom: 2px solid #2e467b;
        position: relative;
    }
    .rd-course-head .arrow {
        transition: transform .2s;
        margin-right: 9px;
        color: #fff;
        font-size: 1.2em;
        cursor: pointer;
    }
    .rd-course-head .rd-course-title-label { cursor: pointer; }
    .rd-course-head.collapsed .arrow { transform: rotate(-90deg); }
    .rd-course-content { background: #fff6ef; }
    .rd-table tr.rd-hide { display: none; }
    .rd-slider-curso {
        position: absolute;
        right: 16px;
        top: 50%;
        transform: translateY(-50%);
        z-index: 2;
    }
    .rd-toggle {
        position: relative;
        display: inline-block;
        width: 44px;
        height: 22px;
    }
    .rd-toggle input { opacity: 0; width: 0; height: 0; }
    .rd-slider {
        position: absolute;
        cursor: pointer;
        top: 0; left: 0; right: 0; bottom: 0;
        background-color: #f1b788;
        transition: .4s;
        border-radius: 22px;
        box-shadow: 0 1px 5px #2e467b11;
    }
    .rd-slider:before {
        position: absolute;
        content: "";
        height: 18px; width: 18px; left: 2px; bottom: 2px;
        background-color: #fff;
        transition: .4s;
        border-radius: 50%;
        box-shadow: 0 2px 4px #2e467b33;
    }
    input:checked + .rd-slider { background-color: #2e467b; }
    input:checked + .rd-slider:before { transform: translateX(22px); }
    .rd-save-btn {
        padding: 12px 28px;
        background-color: #f16d10;
        color: #fff;
        border: none;
        cursor: pointer;
        border-radius: 6px;
        font-size: 1.11em;
        font-weight: 600;
        margin-top: 12px;
        letter-spacing: .02em;
        box-shadow: 0 2px 4px #2e467b14;
        transition: background 0.25s;
    }
    .rd-save-btn:disabled {
        background: #e0e0e0;
        color: #bbb;
        cursor: default;
    }
    .rd-loader {
        display: none;
        margin-left: 1em;
        vertical-align: middle;
        width: 22px;
        height: 22px;
        border: 3px solid #f3f3f3;
        border-top: 3px solid #2e467b;
        border-radius: 50%;
        animation: rd-spin 1s linear infinite;
    }
    @keyframes rd-spin { 100% { transform: rotate(360deg);} }
    .rd-toast {
        visibility: hidden;
        min-width: 240px;
        background: #2e467b;
        color: #fff;
        text-align: center;
        border-radius: 8px;
        padding: 16px;
        position: fixed;
        z-index: 9999;
        right: 32px;
        bottom: 32px;
        font-size: 16px;
        opacity: 0;
        transition: opacity 0.4s, visibility 0.4s;
        font-weight: 600;
        letter-spacing: .02em;
        box-shadow: 0 3px 12px #f16d1030;
    }
    .rd-toast.rd-show { visibility: visible; opacity: 1;}
    .rd-toast.rd-error { background: #f16d10; color: #fff;}
    .rd-toast.rd-success { background: #2e467b; color: #fff;}
    .rd-course-head .arrow svg { transition: transform 0.25s;}
    .rd-course-head .arrow svg {
        transition: transform 0.25s;
        transform: translateY(-50%) rotate(0deg);  /* centrado vertical + sin rotar */
    }
    .rd-course-head.collapsed .arrow svg {
        transform: translateY(-50%) rotate(-90deg); /* centrado vertical + rotado */
    }

    .rd-slider-curso {
        position: absolute;
        right: 16px;
        top: 50%;
        transform: translateY(-50%);
        z-index: 2;
    }

    </style>

    <form id="rd-unlock-form">
    <?php foreach($group_ids as $group_id): ?>
        <?php
            $group_title = esc_html(get_the_title($group_id));
            $course_ids = learndash_get_groups_courses_ids($user_id,[$group_id]);
            // Orden personalizado robusto
            $desired = [
                'IntroducciÃ³n a la RobÃ³tica',
                'MecÃ¡nica clÃ¡sica',
                'Mecanismos',
                'EnergÃ­a',
                'Circuitos ElÃ©ctricos',
                'ElectrÃ³nica BÃ¡sica',
                'ProgramaciÃ³n',
            ];
            $map = [];
            foreach ($course_ids as $cid) {
                $title = get_the_title($cid);
                $norm  = str_replace(['â€“', 'â€”', 'â€‘'], '-', $title);
                $map[$cid] = $norm;
            }
            $ordered = [];
            foreach ($desired as $want_base) {
                foreach ($map as $cid => $norm) {
                    if (stripos($norm, $want_base) !== false) {
                        $ordered[] = $cid;
                        break;
                    }
                }
            }
            foreach ($course_ids as $cid) {
                if (!in_array($cid, $ordered, true)) {
                    $ordered[] = $cid;
                }
            }
            $course_ids = $ordered;
        ?>
        <div class="rd-group-title"><?php echo $group_title; ?></div>
        <table class="rd-table" data-group="<?php echo $group_id; ?>">
        <thead>
            <tr>
                <th style="width: 32%;">Curso</th>
                <th style="width: 13%;">Tipo</th>
                <th>Nombre</th>
                <th style="width: 10%;">Bloqueado</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($course_ids as $course_index => $course_id):
            $curso_nombre = esc_html(get_the_title($course_id));
            $lessons = learndash_get_course_lessons_list($course_id);
            $has_content = !empty($lessons);
            $course_rowid = "rdc-{$group_id}-{$course_id}";
            $curso_bloqueado = !empty($course_locks[$group_id][$course_id]) ? 'checked' : '';
            $slider_id = "slider-{$group_id}-{$course_id}";
            ?>
            <tr class="rd-course-head" data-toggle="<?php echo $course_rowid; ?>">
                <td colspan="3" style="position:relative; padding-left: 36px;">
                    <span class="arrow" aria-label="Desplegar" style="position:absolute; left:10px; top:50%; font-size:1.3em; transition:transform .2s;">
                        <!-- SVG, mÃ¡s accesible y responsivo -->
                        <svg width="20" height="20" viewBox="0 0 24 24" style="display:block;">
                            <polyline points="8 10 12 14 16 10" fill="none" stroke="#fff" stroke-width="2"/>
                        </svg>
                    </span>
                    <span class="rd-course-title-label" style="cursor:pointer;"><?php echo $curso_nombre; ?></span>
                </td>
                <td style="min-width:60px;text-align:center;">
                    <label class="rd-toggle rd-slider-curso" title="Bloquear acceso completo a este curso" style="float:right;cursor:pointer;" for="<?php echo $slider_id; ?>">
                        <input
                            type="checkbox"
                            class="rd-curso-slider"
                            id="<?php echo $slider_id; ?>"
                            data-group="<?php echo $group_id; ?>"
                            data-course="<?php echo $course_id; ?>"
                            <?php echo $curso_bloqueado; ?>
                        >
                        <span class="rd-slider"></span>
                    </label>
                </td>
            </tr>
            <?php if($has_content): foreach($lessons as $lesson):
                $lid      = $lesson['post']->ID;
                $locked   = !empty($locks[$group_id][$lid]) ? 'checked' : '';
            ?>
            <tr class="rd-course-content <?php echo $course_rowid; ?> rd-hide" data-group="<?php echo $group_id; ?>" data-content="<?php echo $lid; ?>" data-type="lesson">
                <td></td>
                <td>Lección</td>
                <td><?php echo esc_html($lesson['post']->post_title); ?></td>
                <td><label class="rd-toggle"><input type="checkbox" <?php echo $locked; ?>><span class="rd-slider"></span></label></td>
            </tr>
                <?php
                $topics = learndash_get_topic_list($lid);
                foreach($topics as $topic):
                    $tid    = $topic->ID;
                    $t_locked = !empty($locks[$group_id][$tid]) ? 'checked' : '';
                ?>
                <tr class="rd-course-content <?php echo $course_rowid; ?> rd-hide" data-group="<?php echo $group_id; ?>" data-content="<?php echo $tid; ?>" data-type="topic">
                    <td></td>
                    <td>Tema</td>
                    <td><?php echo esc_html($topic->post_title); ?></td>
                    <td><label class="rd-toggle"><input type="checkbox" <?php echo $t_locked; ?>><span class="rd-slider"></span></label></td>
                </tr>
                <?php endforeach; endforeach; endif; ?>
        <?php endforeach; ?>
        </tbody>
        </table>
    <?php endforeach; ?>
    <button type="submit" class="rd-save-btn" disabled>Guardar cambios</button>
    <span class="rd-loader" id="rd-loader"></span>
    </form>
    <div aria-live="polite" aria-atomic="true" class="rd-toast" id="rd-toast"></div>
    <script>
    var ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";
    (function($){
      function showToast(msg, type){
        var toast = $('#rd-toast');
        toast.removeClass('rd-success rd-error').addClass(type?'rd-'+type:'');
        toast.text(msg).addClass('rd-show');
        setTimeout(function(){ toast.removeClass('rd-show'); }, 3400);
      }
      // AcordeÃ³n: sÃ³lo clic en nombre o flecha (no en slider)
      $('.rd-course-title-label, .arrow').on('click', function(e){
        var $row = $(this).closest('.rd-course-head');
        var id = $row.data('toggle');
        var isOpen = !$row.hasClass('collapsed');
        $('.'+id).toggleClass('rd-hide');
        $row.toggleClass('collapsed', isOpen);
        $row.find('.arrow').css('transform', isOpen?'rotate(-90deg)':'');
      });

      // SLIDER DE CURSO: activa/desactiva TODO el curso (lecciones/temas)
      $('.rd-curso-slider').on('change', function(e){
        var group = $(this).data('group'),
            course = $(this).data('course'),
            checked = $(this).prop('checked');
        var rows = $('.rd-table[data-group="'+group+'"] .rd-course-content.rdc-'+group+'-'+course);
        rows.find('input[type=checkbox]').each(function(){
          this.checked = checked;
        });
        $('.rd-save-btn').prop('disabled', false);
        e.stopPropagation();
      });

      // Cambios en cualquier slider activan guardar
      $('.rd-table input[type=checkbox]').on('change', function(){
        $('.rd-save-btn').prop('disabled', false);
      });

      $('#rd-unlock-form').on('submit', function(e){
        e.preventDefault();
        var btn = $('.rd-save-btn'),
            loader = $('#rd-loader');
        btn.prop('disabled', true);
        loader.show();

        var allLocks = {};
        var courseLocks = {};
        $('.rd-table tbody tr[data-content]').each(function(){
            var tr  = $(this),
                gid = tr.data('group'),
                cid = tr.data('content'),
                checked = tr.find('input[type=checkbox]').prop('checked') ? 1 : 0;
            if(!allLocks[gid]) allLocks[gid]={};
            allLocks[gid][cid]=checked;
        });
        $('.rd-curso-slider').each(function(){
            var gid = $(this).data('group'),
                cid = $(this).data('course'),
                checked = $(this).prop('checked') ? 1 : 0;
            if(!courseLocks[gid]) courseLocks[gid] = {};
            courseLocks[gid][cid] = checked;
        });

        $.post(ajaxurl, {
          action: 'robodacta_save_locks',
          security: '<?php echo wp_create_nonce("robodacta-locks"); ?>',
          locks: allLocks,
          courseLocks: courseLocks
        })
        .done(function(resp){
          loader.hide();
          if(resp.success){
            btn.text('Guardar cambios').prop('disabled', true);
            showToast('Cambios guardados correctamente', 'success');
          } else {
            btn.text('Guardar cambios').prop('disabled', false);
            showToast('Â¡Error! '+(resp.data||''), 'error');
          }
        })
        .fail(function(){
          loader.hide();
          btn.text('Guardar cambios').prop('disabled', false);
          showToast('Error de conexiÃ³n. Intenta de nuevo.', 'error');
        });
      });
    })(jQuery);
    </script>
    <?php
    return ob_get_clean();
}

// ------ CALLBACK AJAX ------
add_action('wp_ajax_robodacta_save_locks','robodacta_save_locks_callback');
function robodacta_save_locks_callback(){
    if(! current_user_can('groups_manage_groups') ||
       ! check_ajax_referer('robodacta-locks','security',false)
    ){
        wp_send_json_error('Permisos o nonce invÃ¡lido');
    }

    $locks = [];
    if(!empty($_POST['locks']) && is_array($_POST['locks'])){
        foreach($_POST['locks'] as $gid => $items){
            foreach($items as $cid => $val){
                if($val) $locks[(int)$gid][(int)$cid]=true;
            }
        }
    }
    update_option('robodacta_group_locks', $locks);

    // Guardar sliders de curso completo
    $course_locks = [];
    if(!empty($_POST['courseLocks']) && is_array($_POST['courseLocks'])){
        foreach($_POST['courseLocks'] as $gid => $items){
            foreach($items as $cid => $val){
                if($val) $course_locks[(int)$gid][(int)$cid]=true;
            }
        }
    }
    update_option('robodacta_course_locks', $course_locks);

    wp_send_json_success();
}

// ------ BLOQUEO ------
add_action('template_redirect', function(){
    if(!is_user_logged_in()) return;
    $user = wp_get_current_user();
    if(!in_array('miembro_colegio',$user->roles,true)) return;
    if(is_singular(['sfwd-lessons','sfwd-topic','sfwd-courses'])){
        $uid    = $user->ID;
        $groups = learndash_get_users_group_ids($uid);
        $cid    = get_the_ID();
        $locks  = get_option('robodacta_group_locks',[]);
        $course_locks = get_option('robodacta_course_locks',[]);
        foreach($groups as $gid){
            // Si el curso estÃ¡ bloqueado, bloquea todo el contenido del curso
            if(is_singular('sfwd-courses') && !empty($course_locks[$gid][$cid])){
                wp_die('<h2>Este curso ha sido bloqueado por tu tutor.</h2><p>Por favor, espera a que lo habiliten.</p>','Curso bloqueado',['response'=>403]);
            }
            // Si es una lección o tema y el curso estÃ¡ bloqueado
            if(!empty($course_locks[$gid])){
                $course_id = false;
                if(is_singular('sfwd-lessons')) {
                    $course_id = get_post_meta($cid, 'course_id', true);
                } elseif(is_singular('sfwd-topic')) {
                    $parent_lesson = get_post_meta($cid, 'lesson_id', true);
                    if($parent_lesson) {
                        $course_id = get_post_meta($parent_lesson, 'course_id', true);
                    }
                }
                if($course_id && !empty($course_locks[$gid][$course_id])){
                    wp_die('<h2>ðŸ”’ Este curso ha sido bloqueado por tu tutor.</h2><p>Por favor, espera a que lo habiliten.</p>','Curso bloqueado',['response'=>403]);
                }
            }
            if(!empty($locks[$gid][$cid])){
                wp_die('<h2>ðŸ”’ Este contenido ha sido bloqueado por tu tutor.</h2><p>Por favor, espera a que lo habiliten.</p>','Contenido bloqueado',['response'=>403]);
            }
        }
    }
});
