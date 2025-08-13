<?php
/**
 * Plugin Name: Custom HTML Quiz Attempts
 * Description: Guarda y muestra intentos y calificaciones de cuestionarios hechos en HTML (fuera de LearnDash). Incluye informe por alumno y exportación CSV.
 * Version: 1.1.0
 * Author: Robodacta
 */

if ( ! defined('ABSPATH') ) exit;

class CHQA_Plugin {
    const TABLE = 'custom_quiz_attempts';
    const NONCE_ACTION = 'chqa_submit_attempt';
    const CSV_NONCE_ACTION = 'chqa_export_csv';

    public function __construct() {
        register_activation_hook(__FILE__, [$this, 'activate']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);

        // Guardado de intentos
        add_action('wp_ajax_chqa_submit_attempt',        [$this, 'ajax_submit_attempt']);
        add_action('wp_ajax_nopriv_chqa_submit_attempt', [$this, 'ajax_submit_attempt']);

        // Export CSV (solo logged-in)
        add_action('wp_ajax_chqa_export_csv',            [$this, 'ajax_export_csv']);

        // Shortcode
        add_shortcode('quiz_attempts', [$this, 'shortcode_attempts']);
    }

    public function activate() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $charset_collate = $wpdb->get_charset_collate();

        // Tabla sin columnas GENERATED para máxima compatibilidad
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            quiz_id VARCHAR(190) NOT NULL,          -- identificador del cuestionario
            user_id BIGINT UNSIGNED NULL,           -- id WP si está logueado
            user_name VARCHAR(190) NULL,            -- opcional si anónimo
            user_email VARCHAR(190) NULL,           -- opcional si anónimo
            score DECIMAL(10,2) NOT NULL DEFAULT 0, -- puntos obtenidos
            total INT NOT NULL DEFAULT 0,           -- puntos totales
            time_spent INT NOT NULL DEFAULT 0,      -- segundos
            started_at DATETIME NULL,
            completed_at DATETIME NOT NULL,
            details LONGTEXT NULL,                  -- JSON con respuestas u otros metadatos
            ip VARCHAR(45) NULL,
            ua TEXT NULL,
            PRIMARY KEY (id),
            KEY quiz_user_idx (quiz_id, user_id),
            KEY completed_at_idx (completed_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

public function enqueue() {
    // 1) Encola un JS real (evita que optimizadores descarten el handle)
    wp_register_script(
        'chqa-front',
        includes_url('js/wp-emoji-release.min.js'), // cualquier JS existente y público
        [], false, true
    );
    wp_enqueue_script('chqa-front');

    // 2) Inyecta CHQA justo DESPUÉS del script anterior
    $boot = sprintf(
        'window.CHQA = window.CHQA || { ajaxUrl: %s, nonce: %s };',
        json_encode(admin_url('admin-ajax.php')),
        json_encode(wp_create_nonce(self::NONCE_ACTION))
    );
    wp_add_inline_script('chqa-front', $boot);

    // 3) Redundancia: inyección directa en el footer (por si algo elimina el handle)
    add_action('wp_footer', function() use ($boot){
        echo '<script>'.$boot.'</script>';
    }, 1);
}

    /**
     * AJAX: registrar intento
     * Espera POST:
     * - quiz_id (string, requerido)
     * - score (float), total (int), time_spent (int), started_at (ISO opcional)
     * - user_name, user_email (opcionales si anónimo)
     * - details (JSON string opcional)
     */
    public function ajax_submit_attempt() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $quiz_id    = sanitize_text_field($_POST['quiz_id'] ?? '');
        if (!$quiz_id) wp_send_json_error(['message' => 'quiz_id requerido'], 400);

        $score      = is_numeric($_POST['score'] ?? null) ? (float) $_POST['score'] : 0;
        $total      = is_numeric($_POST['total'] ?? null) ? (int) $_POST['total'] : 0;
        $time_spent = is_numeric($_POST['time_spent'] ?? null) ? (int) $_POST['time_spent'] : 0;

        $started_at = sanitize_text_field($_POST['started_at'] ?? '');
        $started_at = $started_at ? gmdate('Y-m-d H:i:s', strtotime($started_at)) : null;

        $user_name  = sanitize_text_field($_POST['user_name'] ?? '');
        $user_email = sanitize_email($_POST['user_email'] ?? '');

        $details_raw = $_POST['details'] ?? '';
        $details = '';
        if ($details_raw) {
            $decoded = json_decode($details_raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $details = wp_json_encode($decoded);
            } else {
                wp_send_json_error(['message' => 'details no es JSON válido'], 400);
            }
        }

        $user_id = get_current_user_id() ?: null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $ok = $wpdb->insert($table, [
            'quiz_id'      => $quiz_id,
            'user_id'      => $user_id ?: null,
            'user_name'    => $user_name ?: null,
            'user_email'   => $user_email ?: null,
            'score'        => $score,
            'total'        => $total,
            'time_spent'   => $time_spent,
            'started_at'   => $started_at,
            'completed_at' => current_time('mysql', true), // UTC
            'details'      => $details ?: null,
            'ip'           => $ip,
            'ua'           => $ua,
        ], [
            '%s','%d','%s','%s','%f','%d','%d','%s','%s','%s','%s','%s'
        ]);

        if (!$ok) {
            wp_send_json_error(['message' => 'Error al guardar intento'], 500);
        }

        wp_send_json_success([
            'id' => (int) $wpdb->insert_id,
            'message' => 'Intento guardado'
        ]);
    }

    /**
     * Shortcode: [quiz_attempts quiz_id="mi-cuestionario" user_id="current|all|123|query:student_id" per_page="10" show_export="yes|no"]
     */
    public function shortcode_attempts($atts) {
        $atts = shortcode_atts([
            'quiz_id'    => '',
            'user_id'    => 'current',   // current | all | número | query:<param>
            'per_page'   => 10,
            'show_export'=> 'no',        // "yes" para mostrar botón CSV (solo admins/editores)
        ], $atts, 'quiz_attempts');

        $allowed_all = current_user_can('edit_others_posts') || current_user_can('list_users');

        // ============================
        // 1) Resolver user_id dinámico
        // ============================
        $user_id_attr = trim($atts['user_id']);
        $user_id = null;

        if (stripos($user_id_attr, 'query:') === 0) {
            $param = trim(substr($user_id_attr, strlen('query:')));

            // 1) Param exacto
            $user_id = isset($_GET[$param]) ? intval($_GET[$param]) : 0;

            // 2) Alternativas comunes
            if (!$user_id) {
                foreach (['student_id','user_id','uid','sid','tutor_student_id'] as $alt) {
                    if (isset($_GET[$alt]) && intval($_GET[$alt]) > 0) {
                        $user_id = intval($_GET[$alt]);
                        break;
                    }
                }
            }

            // 3) Intentar /informe/123/
            if (!$user_id && !empty($_SERVER['REQUEST_URI'])) {
                if (preg_match('~/(\d+)/?$~', $_SERVER['REQUEST_URI'], $m)) {
                    $user_id = intval($m[1]);
                }
            }

            if (!$user_id) {
                return '<em>Falta el parámetro <code>'.esc_html($param).'</code> en la URL para identificar al alumno.</em>';
            }

        } elseif ($user_id_attr === 'current') {
            $user_id = get_current_user_id();
            if (!$user_id) {
                return '<em>Debes iniciar sesión para ver tus intentos.</em>';
            }

        } elseif ($user_id_attr === 'all') {
            if (!$allowed_all) return '<em>No tienes permisos para ver todos los usuarios.</em>';
            $user_id = null; // sin filtro por usuario

        } else {
            // numérico explícito o "0" (anónimos)
            $user_id = absint($user_id_attr);
            if (!$user_id && $user_id_attr !== '0') {
                return '<em>Parámetro user_id inválido.</em>';
            }
        }

        // ============================
        // 2) Resolver quiz_id(s)
        // ============================
        $quiz_ids = array_filter(array_map('trim', explode(',', $atts['quiz_id'])));
        if (empty($quiz_ids)) {
            if (!$allowed_all) {
                return '<em>Debes especificar <code>quiz_id</code> o tener permisos de administrador.</em>';
            }
        }

        $per_page = max(1, (int) $atts['per_page']);
        $paged = max(1, (int) ($_GET['chqa_paged'] ?? 1));
        $offset = ($paged - 1) * $per_page;

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $where = 'WHERE 1=1';
        $params = [];

        if (!empty($quiz_ids)) {
            $placeholders = implode(',', array_fill(0, count($quiz_ids), '%s'));
            $where .= " AND quiz_id IN ($placeholders)";
            $params = array_merge($params, $quiz_ids);
        }

        // Filtrado por usuario:
        // - user_id === null  => "all" (sin filtro)
        // - user_id === 0     => anónimos (user_id IS NULL)
        // - user_id > 0       => ese usuario
        if ($user_id_attr !== 'all') {
            if ($user_id === 0) {
                $where .= " AND user_id IS NULL";
            } elseif ($user_id > 0) {
                $where .= " AND user_id = %d";
                $params[] = $user_id;
            }
        }

        // total
        $sql_count = "SELECT COUNT(*) FROM $table $where";
        $total = (int) $wpdb->get_var($wpdb->prepare($sql_count, $params));

        // ============================
        // 3) Data (porcentaje calculado)
        // ============================
        $sql = "SELECT id, quiz_id, user_id, user_name, user_email, score, total,
                       (CASE WHEN total IS NULL OR total = 0 THEN 0 ELSE (score/total)*100 END) AS percentage,
                       time_spent, started_at, completed_at, details
                FROM $table
                $where
                ORDER BY completed_at DESC
                LIMIT %d OFFSET %d";
        $params_data = array_merge($params, [$per_page, $offset]);
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params_data), ARRAY_A);

        // ============================
        // 4) Render
        // ============================
        ob_start();
        ?>
        <div class="chqa-attempts">
            <?php
            // Botón Exportar CSV (solo admins/editores y si show_export="yes")
            $show_export = strtolower(trim($atts['show_export'])) === 'yes';
            if ($show_export && $allowed_all) {
                $csv_args = [
                    'action'   => 'chqa_export_csv',
                    '_wpnonce' => wp_create_nonce(self::CSV_NONCE_ACTION),
                    'quiz_id'  => implode(',', $quiz_ids),
                    'user_id'  => ($user_id_attr === 'all') ? 'all' : (string) ( $user_id ?? '' ), // '' = sin filtro (no debería ocurrir aquí)
                    'paged'    => $paged, // sin uso en export, pero por si se quisiera
                ];
                $csv_url = add_query_arg($csv_args, admin_url('admin-ajax.php'));
                echo '<p><a class="button button-primary" href="'.esc_url($csv_url).'">Exportar CSV</a></p>';
            }
            ?>
            <table class="chqa-table" style="width:100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="text-align:left; padding:6px; border-bottom:1px solid #ddd;">Intento</th>
                        <th style="text-align:left; padding:6px; border-bottom:1px solid #ddd;">Cuestionario</th>
                        <th style="text-align:right; padding:6px; border-bottom:1px solid #ddd;">Puntuación</th>
                        <th style="text-align:right; padding:6px; border-bottom:1px solid #ddd;">Porcentaje</th>
                        <th style="text-align:right; padding:6px; border-bottom:1px solid #ddd;">Tiempo (s)</th>
                        <th style="text-align:left; padding:6px; border-bottom:1px solid #ddd;">Completado</th>
                        <th style="text-align:left; padding:6px; border-bottom:1px solid #ddd;">Detalle</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="7" style="padding:8px;">Sin registros.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td style="padding:6px;"><?php echo (int)$r['id']; ?></td>
                            <td style="padding:6px;"><?php echo esc_html($r['quiz_id']); ?></td>
                            <td style="padding:6px; text-align:right;"><?php echo esc_html($r['score']) . ' / ' . esc_html($r['total']); ?></td>
                            <td style="padding:6px; text-align:right;"><?php echo number_format((float)$r['percentage'], 2) . '%'; ?></td>
                            <td style="padding:6px; text-align:right;"><?php echo (int)$r['time_spent']; ?></td>
                            <td style="padding:6px;"><?php echo esc_html( get_date_from_gmt($r['completed_at'], 'Y-m-d H:i:s') ); ?></td>
                            <td style="padding:6px;">
                                <?php if (!empty($r['details'])): ?>
                                    <button class="chqa-toggle" data-id="<?php echo (int)$r['id']; ?>">Ver</button>
                                    <pre id="chqa-details-<?php echo (int)$r['id']; ?>" style="display:none; max-width:600px; white-space:pre-wrap; background:#f7f7f7; padding:8px; border:1px solid #ddd; border-radius:6px;"><?php echo esc_html($r['details']); ?></pre>
                                <?php else: ?>
                                    <em>—</em>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
            <?php
            // Paginación
            $pages = (int) ceil($total / $per_page);
            if ($pages > 1):
                echo '<div class="chqa-pagination" style="margin-top:8px;">';
                for ($i=1; $i<=$pages; $i++) {
                    $url = add_query_arg('chqa_paged', $i);
                    $style = $i == $paged ? 'font-weight:bold;' : '';
                    echo '<a href="'.esc_url($url).'" style="margin-right:6px; '.$style.'">'.$i.'</a>';
                }
                echo '</div>';
            endif;
            ?>
        </div>
        <script>
        document.addEventListener('click', function(e){
            if(e.target && e.target.classList.contains('chqa-toggle')){
                var id = e.target.getAttribute('data-id');
                var pre = document.getElementById('chqa-details-' + id);
                if(pre) pre.style.display = (pre.style.display === 'none' || pre.style.display === '') ? 'block' : 'none';
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX: Export CSV
     * Requiere permisos de admin/editor (cap: edit_others_posts o list_users)
     * Query params: _wpnonce, quiz_id (coma-separado), user_id ("all" | 0 | n>0)
     */
    public function ajax_export_csv() {
        if ( ! is_user_logged_in() ) {
            wp_die('No autorizado', '', 403);
        }
        if ( ! wp_verify_nonce($_GET['_wpnonce'] ?? '', self::CSV_NONCE_ACTION) ) {
            wp_die('Nonce inválido', '', 403);
        }
        $allowed_all = current_user_can('edit_others_posts') || current_user_can('list_users');
        if ( ! $allowed_all ) {
            wp_die('Permisos insuficientes', '', 403);
        }

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        // Filtros
        $quiz_ids_raw = sanitize_text_field($_GET['quiz_id'] ?? '');
        $quiz_ids = array_filter(array_map('trim', explode(',', $quiz_ids_raw)));

        $user_id_raw = sanitize_text_field($_GET['user_id'] ?? '');
        // user_id puede ser: "all" | "" | "0" | "123"
        $user_id = null;
        $filter_user = false;
        $filter_user_is_null = false;
        if ($user_id_raw === 'all') {
            // sin filtro
        } elseif ($user_id_raw === '0') {
            $filter_user = true;
            $filter_user_is_null = true;
        } elseif (is_numeric($user_id_raw) && intval($user_id_raw) > 0) {
            $filter_user = true;
            $user_id = intval($user_id_raw);
        }

        $where = 'WHERE 1=1';
        $params = [];
        if (!empty($quiz_ids)) {
            $placeholders = implode(',', array_fill(0, count($quiz_ids), '%s'));
            $where .= " AND quiz_id IN ($placeholders)";
            $params = array_merge($params, $quiz_ids);
        }
        if ($filter_user) {
            if ($filter_user_is_null) {
                $where .= " AND user_id IS NULL";
            } else {
                $where .= " AND user_id = %d";
                $params[] = $user_id;
            }
        }

        $sql = "SELECT id, quiz_id, user_id, user_name, user_email, score, total,
                       (CASE WHEN total IS NULL OR total = 0 THEN 0 ELSE (score/total)*100 END) AS percentage,
                       time_spent, started_at, completed_at, details, ip, ua
                FROM $table
                $where
                ORDER BY completed_at DESC";

        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

        // Headers
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=quiz_attempts_'.date('Ymd_His').'.csv');

        $out = fopen('php://output', 'w');

        // Encabezados CSV
        fputcsv($out, [
            'id','quiz_id','user_id','user_name','user_email',
            'score','total','percentage','time_spent','started_at','completed_at','details','ip','ua'
        ]);

        foreach ($rows as $r) {
            fputcsv($out, [
                $r['id'],
                $r['quiz_id'],
                $r['user_id'],
                $r['user_name'],
                $r['user_email'],
                $r['score'],
                $r['total'],
                number_format((float)$r['percentage'], 2, '.', ''),
                $r['time_spent'],
                $r['started_at'],
                $r['completed_at'],
                $r['details'],
                $r['ip'],
                $r['ua'],
            ]);
        }
        fclose($out);
        exit;
    }
}
// === DEBUG CHQA: [chqa_diag] (solo admins/editores) ===
add_shortcode('chqa_diag', function(){
    if ( !current_user_can('edit_others_posts') && !current_user_can('list_users') ) {
        return '<em>No autorizado.</em>';
    }
    global $wpdb;
    $table = $wpdb->prefix . CHQA_Plugin::TABLE;
    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
    ob_start();
    echo '<div style="background:#eef7ff;padding:10px;border:1px solid #bcd;">';
    echo '<strong>CHQA Diag</strong><br>';
    echo 'Tabla: <code>'.esc_html($table).'</code><br>';
    echo 'Existe: <code>'.($exists ? 'sí' : 'no').'</code><br>';
    if ($exists) {
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        echo 'Total filas: <code>'.$count.'</code><br>';
        $rows = $wpdb->get_results("SELECT id,quiz_id,user_id,score,total,time_spent,completed_at FROM {$table} ORDER BY id DESC LIMIT 5", ARRAY_A);
        if ($rows) {
            echo '<pre>'.esc_html(print_r($rows, true)).'</pre>';
        }
    }
    echo '</div>';
    return ob_get_clean();
});
new CHQA_Plugin();
