<?php
/**
 * Dashboard de Tutor (landing con 3 opciones)
 */

if (!defined('ABSPATH')) exit;

add_shortcode('trd_tutor_dashboard', function($atts){
    $a = shortcode_atts([
        'kits_url'     => 'https://robodacta-steam.mx/tutor-seleccion-de-kit/',          // URL de Selección de Kit
        'students_url' => 'https://robodacta-steam.mx/tutor-informe-de-grupos/',          // URL de Tabla de alumnos
        'unlock_url'   => 'https://robodacta-steam.mx/tutor-gestion-de-contenido/',          // URL de Panel de bloqueo
        'title'        => 'Bienvenido profesor',
        'subtitle'     => 'Gestiona a tus alumnos y su aprendizaje desde aquí.',
    ], $atts, 'trd_tutor_dashboard');

    if (!is_user_logged_in() || !current_user_can('groups_manage_groups')) {
        return '<div style="padding:14px;border:1px solid #f8caca;background:#fff4f4;border-radius:10px;">No tienes permiso para ver este panel.</div>';
    }

    // Métricas rápidas
    $user_id     = get_current_user_id();
    $display     = wp_get_current_user()->display_name ?: 'Profesor(a)';
    $group_count = 0; $student_count = 0; $course_count = 0;

    if (function_exists('learndash_get_administrators_group_ids')) {
        $group_ids   = (array) learndash_get_administrators_group_ids($user_id);
        $group_count = count($group_ids);

        if (!empty($group_ids) && function_exists('learndash_get_groups_user_ids') && function_exists('learndash_get_groups_courses_ids')) {
            // Alumnos únicos en todos tus grupos
            $students_all = [];
            $courses_all  = [];
            foreach ($group_ids as $gid) {
                foreach ((array) learndash_get_groups_user_ids($gid) as $sid) {
                    $students_all[$sid] = true;
                }
                foreach ((array) learndash_get_groups_courses_ids($user_id, [$gid]) as $cid) {
                    $courses_all[$cid] = true;
                }
            }
            $student_count = count($students_all);
            $course_count  = count($courses_all);
        }
    }

    // URLs (las pondrás tú en el shortcode)
    $u_kits     = esc_url($a['kits_url']);
    $u_students = esc_url($a['students_url']);
    $u_unlock   = esc_url($a['unlock_url']);

    ob_start(); ?>
    <style>
      .trd-dash{--navy:#2e467b;--orange:#f16d10;--ink:#0f172a;--muted:#64748b;--card:#ffffff;--bg:#f6f8fc}
      .trd-dash{max-width:1100px;margin:0 auto;padding:16px}
      .trd-hero{background:linear-gradient(135deg,var(--navy),#3a5a9b);border-radius:18px;color:#fff;padding:28px 24px;box-shadow:0 8px 22px #102a571f}
      .trd-hero h2{margin:.1em 0 .2em;font-size:1.8rem;letter-spacing:.3px}
      .trd-hero p{margin:0;opacity:.95}
      .trd-metrics{display:grid;grid-template-columns:repeat(3, minmax(0,1fr));gap:12px;margin-top:18px}
      .trd-metric{background:#ffffff12;border:1px solid #ffffff24;border-radius:14px;padding:14px}
      .trd-metric b{display:block;font-size:1.6rem;line-height:1.1}
      .trd-cards{display:grid;grid-template-columns:repeat(3, minmax(0,1fr));gap:18px;margin:22px 0}
      .trd-card{background:var(--card);border:1px solid #e9eef5;border-radius:16px;overflow:hidden;box-shadow:0 6px 20px #0b1a3a12;display:flex;flex-direction:column}
      .trd-card .hd{display:flex;align-items:center;gap:10px;background:var(--bg);padding:16px;border-bottom:1px solid #e9eef5}
      .trd-card .hd .ico{width:36px;height:36px;border-radius:9px;background:#fff;display:grid;place-items:center;border:1px solid #e9eef5}
      .trd-card .tx{padding:16px;color:var(--ink);flex:1}
      .trd-card .tx p{color:var(--muted);margin:.3em 0 0}
      .trd-card .ft{padding:14px 16px;border-top:1px solid #eef2f7;display:flex;justify-content:flex-end}
      .trd-btn{appearance:none;border:none;background:#f16d10;color:orange;font-weight:700;padding:10px 16px;border-radius:10px;cursor:pointer;text-decoration:none;display:inline-block}
      .trd-btn:hover{background:#2e467b;color:#fff;}
      .trd-note{background:#fff;border:1px dashed #d7deea;border-radius:14px;padding:14px 16px;color:var(--muted)}
      @media (max-width:960px){.trd-cards{grid-template-columns:1fr 1fr}}
      @media (max-width:640px){.trd-metrics{grid-template-columns:1fr 1fr}.trd-cards{grid-template-columns:1fr}}
      .trd-ico{display:block;width:22px;height:22px}
    </style>

    <div class="trd-dash">
      <div class="trd-hero">
        <h2><?php echo esc_html($a['title']); ?>, <?php echo esc_html($display); ?> 👋</h2>
        <p><?php echo esc_html($a['subtitle']); ?></p>

        <div class="trd-metrics" role="list">
          <div class="trd-metric" role="listitem" aria-label="Grupos">
            <small>Grupos</small>
            <b><?php echo (int) $group_count; ?></b>
          </div>
          <div class="trd-metric" role="listitem" aria-label="Alumnos">
            <small>Alumnos</small>
            <b><?php echo (int) $student_count; ?></b>
          </div>
          <div class="trd-metric" role="listitem" aria-label="Cursos">
            <small>Cursos</small>
            <b><?php echo (int) $course_count; ?></b>
          </div>
        </div>
      </div>

      <div class="trd-cards">
        <!-- Selección de Kit -->
        <div class="trd-card">
          <div class="hd">
            <span class="ico" aria-hidden="true">
              <!-- toolbox -->
              <svg class="trd-ico" viewBox="0 0 24 24" fill="none"><path d="M3 10h18v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-9Z" stroke="#2e467b" stroke-width="1.8"/><path d="M8 10V7a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v3" stroke="#2e467b" stroke-width="1.8"/></svg>
            </span>
            <div><strong>Selección de kit</strong><br><small>Define el kit por grupo</small></div>
          </div>
          <div class="tx">
            <p>Elige el kit didactico para cada grupo. El alumno verá automáticamente el video correspondiente en su lección.</p>
          </div>
          <div class="ft">
            <a class="trd-btn" href="<?php echo $u_kits; ?>">Abrir</a>
          </div>
        </div>

        <!-- Tabla de Alumnos -->
        <div class="trd-card">
          <div class="hd">
            <span class="ico" aria-hidden="true">
              <!-- users -->
              <svg class="trd-ico" viewBox="0 0 24 24" fill="none"><path d="M7 20a5 5 0 0 1 10 0" stroke="#2e467b" stroke-width="1.8"/><circle cx="12" cy="8" r="3.5" stroke="#2e467b" stroke-width="1.8"/></svg>
            </span>
            <div><strong>Informe de grupos</strong><br><small>Informe de cada alumno</small></div>
          </div>
          <div class="tx">
            <p>Consulta alumnos por grupo, abre su perfil y accede al informe detallado de progreso con un clic.</p>
          </div>
            <div class="ft">
              <a class="trd-btn" href="<?php echo $u_students; ?>">Abrir</a>
            </div>
        </div>

        <!-- Bloqueo/Desbloqueo -->
        <div class="trd-card">
          <div class="hd">
            <span class="ico" aria-hidden="true">
              <!-- lock -->
              <svg class="trd-ico" viewBox="0 0 24 24" fill="none"><rect x="3.5" y="10" width="17" height="10" rx="2" stroke="#2e467b" stroke-width="1.8"/><path d="M8 10V7a4 4 0 1 1 8 0v3" stroke="#2e467b" stroke-width="1.8"/></svg>
            </span>
            <div><strong>Gestion de contenido</strong><br><small>Control de acceso por grupo</small></div>
          </div>
          <div class="tx">
            <p>Bloquea/desbloquea cursos completos o lecciones/temas específicos para tu grupo, en segundos.</p>
          </div>
          <div class="ft">
            <a class="trd-btn" href="<?php echo $u_unlock; ?>">Abrir</a>
          </div>
        </div>
      </div>
    </div>
    <?php
    return ob_get_clean();
});
