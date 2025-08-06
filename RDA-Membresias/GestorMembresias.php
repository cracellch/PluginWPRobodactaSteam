<?php
/**
 * Plugin Name: RDASTEAM Gestion Membresias
 * Plugin URI: https://robodacta-steam.mx
 * Description: Creación/Cancelación de membresias y registro de escuelas. 
 * Version: 1.1.0
 * Author: Robodacta Miguel Alfonso
 * Author URI: https://robodacta-steam.mx
 * License: GPL2
 */
 
 require_once __DIR__ . '/includes/MembresiaUsuario.php';
 
//////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////Creacion/asignacion licencia para miembro estudiante
//////////////////////////////////////////////////////////////////////////////////////////////////////

//Hook para detectar membresía asignada
add_filter('ms_model_member_add_membership', function($subscription, $membership_id, $gateway_id, $move_from_id, $member_obj) {
    $user_id = $member_obj->id;

    // Aquí puedes filtrar si solo quieres para cierto membership_id:
    // (Por ejemplo, tu membresía de estudiante tiene ID=3)
    $membresia_estudiante_id = 24218; // Cambia por el ID real de tu membresía "Estudiante"
    if ($membership_id != $membresia_estudiante_id) {
        return $subscription;
    }

    error_log('[MemberDash] Asignando código por ms_model_member_add_membership para user_id=' . $user_id);
    assign_membresia_codigo_a_usuario($user_id);

    return $subscription;
}, 10, 5);
// Función para asignar o generar código de membresía
function assign_membresia_codigo_a_usuario($user_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'rd_membresias'; // Usar el prefijo correcto
    $year = date('Y');

    // 1. Busca un código libre
    $codigo = $wpdb->get_var("SELECT codigo FROM $table WHERE status='libre' AND tipo_usuario='ES' LIMIT 1");
    if(!$codigo) {
        // Si no hay códigos libres, genera uno nuevo
        $codigo = rd_generar_nuevo_codigo_estudiante();
        if (!$codigo) return; // No se pudo generar
        // Inserta en la tabla
        $wpdb->insert($table, [
            'codigo' => $codigo,
            'tipo_usuario' => 'ES',
            'anio' => $year,
            'status' => 'asignado',
            'user_id' => $user_id,
            'fecha_asignacion' => current_time('mysql')
        ]);
    } else {
        // 2. Asigna el código al usuario
        $wpdb->update($table, [
            'status' => 'asignado',
            'user_id' => $user_id,
            'fecha_asignacion' => current_time('mysql')
        ], [
            'codigo' => $codigo
        ]);
    }
    // 3. Guarda en el user_meta para acceso rápido
    update_user_meta($user_id, 'rd_codigo_membresia', $codigo);
}

// Generar nuevo código correlativo de estudiante
function rd_generar_nuevo_codigo_estudiante() {
    global $wpdb;
    $table = $wpdb->prefix . 'rd_membresias'; // Usar el prefijo correcto
    $anio = date('y'); // SOLO dos dígitos del año (ej. '25')
    // Busca el último número correlativo de alumno para este año
    $ultimo = $wpdb->get_var(
        "SELECT MAX(CAST(RIGHT(codigo, 14) AS UNSIGNED)) FROM $table WHERE tipo_usuario='ES' AND LEFT(RIGHT(codigo,16),2) = '{$anio}'"
    );
    $siguiente = str_pad(intval($ultimo) + 1, 14, '0', STR_PAD_LEFT);
    // Formato: ES + aa + id (14 dígitos)
    $codigo = "ES" . $anio . $siguiente;
    return $codigo;
}

//////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////Limpieza y cancelacion de membresias Estudiante/Colegio.
//////////////////////////////////////////////////////////////////////////////////////////////////////
// Limpieza de membresía "Estudiante"
if (!wp_next_scheduled('robodacta_cron_cleanup_24218')) {
    wp_schedule_single_event(time() + 60, 'robodacta_cron_cleanup_24218');
}

add_action('robodacta_cron_cleanup_24218', 'robodacta_clean_membership_24218');

function robodacta_clean_membership_24218() {
    error_log("⏰ [CRON 24218] Iniciando limpieza de membresías desactivadas...");

    $membership_id = 24218;
    $course_ids = array(25306, 19476, 7363, 5957, 5624, 5619, 5613); // Los cursos que quieras

    $users = get_users([
        'meta_key' => 'ms_subscriptions',
        'meta_compare' => 'EXISTS'
    ]);

    foreach ($users as $user) {
        $user_id = $user->ID;
        $subscriptions = get_user_meta($user_id, 'ms_subscriptions', true);

        if (!is_array($subscriptions)) continue;

        foreach ($subscriptions as $sub) {
            if (is_object($sub) && isset($sub->membership_id, $sub->status) && $sub->membership_id == $membership_id && $sub->status === 'deactivated') {
                foreach ($course_ids as $course_id) {
                    ld_update_course_access($user_id, $course_id, true);
                    delete_user_meta($user_id, 'course_' . $course_id . '_access_from');
                    delete_user_meta($user_id, 'learndash_course_' . $course_id . '_enrolled_at');
                }
                global $wpdb;
                $table =  $wpdb->prefix . 'rd_membresias';//Prefijo
                $wpdb->update($table, ['status' => 'removido'], [
                    'user_id' => $user_id
                ]);

                error_log("🟢 [CRON $membership_id] Status actualizado a removido en rd_membresias para usuario $user_id");

                error_log("✅ [CRON 24218] Accesos removidos de membresía estudiante para usuario $user_id");
            }
        }
    }
    error_log("✅ [CRON 24218] Limpieza completada.");
}
// Limpieza de membresía "Colegio"
// Funcion limpieza de membresia Colegio
if (!wp_next_scheduled('robodacta_cron_cleanup_7694')) {
    wp_schedule_single_event(time() + 120, 'robodacta_cron_cleanup_7694');
}

add_action('robodacta_cron_cleanup_7694', 'robodacta_clean_membership_7694');

function robodacta_clean_membership_7694() {
    global $wpdb;
    $table = $wpdb->prefix . 'rd_membresias';

    error_log("⏰ [CRON 7694] Iniciando limpieza de membresías Colegio desactivadas...");

    $membership_id = 7694;
    $course_ids = array(25306, 19476, 7363, 5957, 5624, 5619, 5613);

    $users = get_users([
        'meta_key' => 'ms_subscriptions',
        'meta_compare' => 'EXISTS'
    ]);

    foreach ($users as $user) {
        $user_id = $user->ID;
        $subscriptions = get_user_meta($user_id, 'ms_subscriptions', true);

        if (!is_array($subscriptions)) continue;

        foreach ($subscriptions as $sub) {
            if (is_object($sub) && isset($sub->membership_id, $sub->status) && $sub->membership_id == $membership_id && $sub->status === 'deactivated') {
                // Remover acceso a los cursos
                foreach ($course_ids as $course_id) {
                    ld_update_course_access($user_id, $course_id, true);
                    delete_user_meta($user_id, 'course_' . $course_id . '_access_from');
                    delete_user_meta($user_id, 'learndash_course_' . $course_id . '_enrolled_at');
                }

                // Cambiar status de la membresía en la tabla personalizada
                $codigo = get_user_meta($user_id, 'rd_codigo_membresia', true);
                if ($codigo) {
                    $wpdb->update($table, ['status' => 'removido'], [
                        'user_id' => $user_id,
                        'codigo' => $codigo
                    ]);
                    error_log("✅ [CRON 7694] Status removido en tabla rd_membresias para usuario $user_id y código $codigo");
                }
                error_log("✅ [CRON 7694] Accesos removidos de membresía Colegio para usuario $user_id");
            }
        }
    }

    error_log("✅ [CRON 7694] Limpieza completada.");
}

//////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////Registrar Escuelas.
//////////////////////////////////////////////////////////////////////////////////////////////////////
add_shortcode('rd_registrar_escuela', function(){
    // Guardar escuela
    if(isset($_POST['rd_escuela_nombre']) && current_user_can('manage_options')){
        $escuelas = get_option('rd_escuelas', []);
        $nombre = trim(sanitize_text_field($_POST['rd_escuela_nombre']));
        if($nombre){
            $nuevo_id = str_pad(count($escuelas)+1, 4, '0', STR_PAD_LEFT);
            $escuelas[$nuevo_id] = $nombre;
            update_option('rd_escuelas', $escuelas);
            echo "<div style='color: #2e467b; background: #d5f7ec; padding: 10px 14px; border-radius: 6px; margin-bottom: 10px;'>Escuela <b>$nombre</b> registrada con ID <b>$nuevo_id</b>.</div>";
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
            <tr style="background:#2e467b;color:#fff;">
                <th style="padding:7px 0;">ID</th>
                <th style="padding:7px 0;">Nombre</th>
            </tr>
            <?php foreach($escuelas as $id=>$nombre): ?>
            <tr>
                <td style="padding:7px 0;text-align:center;"><?php echo $id; ?></td>
                <td style="padding:7px 0;"><?php echo esc_html(stripslashes($nombre)); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php
    return ob_get_clean();
});
//////////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////Generacion de codigos para membresia ES/CG.
//////////////////////////////////////////////////////////////////////////////////////////////////////
// Incluye Hashids (asegúrate de tener el archivo en la ruta correcta)
require_once __DIR__ . '/Hashids/Hashids.php';

// Configuración de Hashids
function rd_get_hashids(){
    return new Hashids('TuSuperSecretoUnico!', 18, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890');
}

// Convierte código real a número (elimina el prefijo CG o ES)
function licencia_a_numeros($lic){
    return intval(substr($lic, 2));
}
// Convierte el número de vuelta a licencia (agrega el prefijo CG y rellena a 16 dígitos)
function numeros_a_licencia($num){
    return 'CG' . str_pad($num, 16, '0', STR_PAD_LEFT);
}
function rd_enmascarar_licencia($lic){
    $hashids = rd_get_hashids();
    return $hashids->encode(licencia_a_numeros($lic));
}
function rd_desenmascarar_licencia($hash){
    $hashids = rd_get_hashids();
    $nums = $hashids->decode($hash);
    if (count($nums)) {
        return numeros_a_licencia($nums[0]);
    }
    return false;
}
// Formato XXXXXX-XXXXXX-XXXXXX
function rd_formatear_cod_con_guiones($code) {
    return strtoupper(implode('-', str_split($code, 6)));
}

// Handler AJAX para enmascarar códigos
add_action('wp_ajax_rd_enmascarar_codigos', 'rd_ajax_enmascarar_codigos');
add_action('wp_ajax_nopriv_rd_enmascarar_codigos', 'rd_ajax_enmascarar_codigos');
function rd_ajax_enmascarar_codigos() {
    $codigos = isset($_POST['codigos']) ? json_decode(stripslashes($_POST['codigos']), true) : [];
    if (!$codigos || !is_array($codigos)) wp_send_json_error('No se recibieron códigos');
    $enmascarados = [];
    foreach ($codigos as $c) {
        $mask = rd_enmascarar_licencia($c);
        $enmascarados[] = rd_formatear_cod_con_guiones($mask);
    }
    wp_send_json_success($enmascarados);
}

// Handler AJAX para guardar los códigos en la BD
add_action('wp_ajax_rd_guardar_codigos_membresia', 'rd_guardar_codigos_membresia');
function rd_guardar_codigos_membresia() {
    if (!current_user_can('manage_options')) {
        error_log('[RDA] No autorizado para guardar códigos.');
        wp_send_json_error('No autorizado');
    }
    global $wpdb;
    $table = $wpdb->prefix . 'rd_membresias';

    $data = isset($_POST['data']) ? json_decode(stripslashes($_POST['data']), true) : [];
    if (!$data || !is_array($data)) {
        error_log('[RDA] Datos incorrectos');
        wp_send_json_error('Datos incorrectos');
    }

    $guardados = [];
    foreach ($data as $row) {
        error_log('[RDA] Intentando guardar: ' . print_r($row, true));
        $codigo       = sanitize_text_field($row['codigo']);
        $tipo_usuario = sanitize_text_field($row['tipo_usuario']);
        $anio         = sanitize_text_field($row['anio']);
        $id_escuela   = sanitize_text_field($row['id_escuela']);
        $escolaridad  = sanitize_text_field($row['escolaridad']);
        $grado        = sanitize_text_field($row['grado']);
        $grupo        = sanitize_text_field($row['grupo']);
        $num_alumno   = sanitize_text_field($row['num_alumno']);

        $existe = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE codigo = %s", $codigo));
        error_log("[RDA] ¿Ya existe $codigo? " . ($existe ? 'SI' : 'NO'));
        if ($existe) continue;

        $insertado = $wpdb->insert($table, [
            'codigo'        => $codigo,
            'tipo_usuario'  => $tipo_usuario,
            'anio'          => $anio,
            'id_escuela'    => $id_escuela,
            'escolaridad'   => $escolaridad,
            'grado'         => $grado,
            'grupo'         => $grupo,
            'num_alumno'    => $num_alumno,
            'status'        => 'libre',
            'fecha_asignacion' => null,
            'user_id'       => null,
            'fecha_uso'     => null
        ]);
        error_log("[RDA] Insertado $codigo: " . ($insertado ? 'SI' : 'NO'));
        if ($insertado) $guardados[] = $codigo;
    }

    error_log('[RDA] Códigos guardados: ' . implode(',', $guardados));

    wp_send_json_success([
        'guardados' => $guardados,
        'total'     => count($guardados)
    ]);
}

// Consultar el máximo ID de grupo seleccionado (para evitar duplicados)
add_action('wp_ajax_rd_max_alumno_grupo', function(){
    if (!current_user_can('manage_options')) wp_send_json_error('No autorizado');
    global $wpdb;
    $table = $wpdb->prefix . 'rd_membresias';

    $anio = sanitize_text_field($_POST['anio'] ?? '');
    $id_escuela = sanitize_text_field($_POST['id_escuela'] ?? '');
    $escolaridad = sanitize_text_field($_POST['escolaridad'] ?? '');
    $grado = sanitize_text_field($_POST['grado'] ?? '');
    $grupo = sanitize_text_field($_POST['grupo'] ?? '');

    $max = $wpdb->get_var($wpdb->prepare(
        "SELECT MAX(CAST(num_alumno AS UNSIGNED)) FROM $table WHERE anio=%s AND id_escuela=%s AND escolaridad=%s AND grado=%s AND grupo=%s",
        $anio, $id_escuela, $escolaridad, $grado, $grupo
    ));
    wp_send_json_success(['max' => intval($max)]);
});

// Define ajaxurl si no está definido (para el frontend)
add_action('wp_enqueue_scripts', function(){
    if (!is_admin()) {
        echo "<script>var ajaxurl='".admin_url('admin-ajax.php')."';</script>";
    }
});

// SHORTCODE de generación de códigos
add_shortcode('rd_generar_codigos_membresia', function(){
    $escuelas = get_option('rd_escuelas', []);
    ob_start(); ?>
    <form id="rd-gen-codigos-form" style="max-width:500px;margin:2em auto;background:#f8f8ff;padding:30px 20px;border-radius:16px;box-shadow:0 2px 8px #2e467b22;">
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
                <?php for($an=25;$an<=50;$an++){
                        $n = str_pad($an,2,'0',STR_PAD_LEFT);
                        echo "<option value='$n'>20{$an}</option>";
                }?>
            </select>
            </label><br>
            <label>Escuela:
                <select id="escuela" required>
                    <?php foreach($escuelas as $id=>$nombre){
                        echo "<option value='$id'>($id) ".esc_html(stripslashes($nombre))."</option>";
                    } ?>
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
                    <?php for($i=1;$i<=12;$i++){
                        $v = str_pad($i,2,'0',STR_PAD_LEFT);
                        echo "<option value='$v'>{$i}°</option>";
                    } ?>
                </select>
            </label><br>
            <label>Grupo:
                <select id="grupo" required>
                    <?php foreach(range('A','Z') as $i=>$g){
                        $val = str_pad($i+1,2,'0',STR_PAD_LEFT);
                        echo "<option value='$val'>{$g}</option>";
                    } ?>
                </select>
            </label><br>
            <label>Cantidad de alumnos:
                <input type="number" id="cantidad" min="1" max="300" required value="30">
            </label><br>
        </div>
        <div id="campos_estudiante" style="display:none;">
            <label>A&ntilde;o:
                <input type="number" id="anio_es" value="<?php echo date('Y'); ?>" required min="2023" max="2099">
            </label><br>
        </div>
        <button type="button" id="generar_codigos" style="margin-top:14px;background:#2e467b;color:#fff;border:none;padding:10px 24px;border-radius:7px;font-weight:bold;cursor:pointer;">Generar</button>
    </form>
    <div id="codigos_resultado" style="max-width:700px;margin:1.5em auto 3em auto;padding:10px;"></div>
    <div id="modal-licencias" style="display:none; position:fixed; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.3); z-index:9999; justify-content:center; align-items:center;">
      <div id="modal-licencias-msg" style="background:#fff; padding:30px 50px; border-radius:18px; box-shadow:0 0 20px #0002; font-size:1.3em; color:#2e467b; text-align:center;"></div>
    </div>

    <script>
    function mostrarModalLicencias(mensaje) {
        var modal = document.getElementById('modal-licencias');
        var msg = document.getElementById('modal-licencias-msg');
        msg.innerHTML = mensaje;
        modal.style.display = "flex";
        setTimeout(function(){
            modal.style.display = "none";
        }, 3000);
    }

    function mostrarColegioCampos(val){
        if(val=="CG"){
            document.getElementById('campos_colegio').style.display = "";
            document.getElementById('campos_estudiante').style.display = "none";
        }else{
            document.getElementById('campos_colegio').style.display = "none";
            document.getElementById('campos_estudiante').style.display = "";
        }
    }

    if (typeof ajaxurl === "undefined") {
        var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    }

    document.getElementById('generar_codigos').onclick = function(){
        let tipo = document.getElementById('tipo_usuario').value;
        if(tipo == "ES") {
            let anio_es = document.getElementById('anio_es').value;
            let codigos = [`ES${anio_es}`];
            let html = '<h4>Códigos generados:</h4><textarea style="width:98%;height:120px;">' + codigos.join('\n') + '</textarea>';
            document.getElementById('codigos_resultado').innerHTML = html;
            return;
        }
        // CG: Colegio
        let codigos = [];
        let dataToSend = [];
        let anio = document.getElementById('anio').value;
        let id_escuela = String(document.getElementById('escuela').value).padStart(4,'0');
        let escolaridad = document.getElementById('escolaridad').value;
        let grado = document.getElementById('grado').value;
        let grupo = document.getElementById('grupo').value;
        let cantidad = parseInt(document.getElementById('cantidad').value);

        let boton = this;
        boton.disabled = true;
        boton.textContent = 'Enmascarando...';

        // Consulta el máximo actual
        fetch(ajaxurl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=rd_max_alumno_grupo&anio=${anio}&id_escuela=${id_escuela}&escolaridad=${escolaridad}&grado=${grado}&grupo=${grupo}`
        })
        .then(res => res.json())
        .then(res => {
            let start = 1;
            if (res.success && res.data && typeof res.data.max === "number") {
                start = res.data.max + 1;
            }
            codigos = [];
            dataToSend = [];
            for(let i=0;i<cantidad;i++) {
                let num_alumno = String(start+i).padStart(4,'0');
                let codigo = `${tipo}${anio}${id_escuela}${escolaridad}${grado}${grupo}${num_alumno}`;
                codigos.push(codigo);
                dataToSend.push({
                    codigo: codigo,
                    tipo_usuario: tipo,
                    anio: anio,
                    id_escuela: id_escuela,
                    escolaridad: escolaridad,
                    grado: grado,
                    grupo: grupo,
                    num_alumno: num_alumno
                });
            }
            // 1. Enmascarar los códigos en el backend
            fetch(ajaxurl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=rd_enmascarar_codigos&codigos=' + encodeURIComponent(JSON.stringify(codigos))
            }).then(res => res.json())
            .then(res => {
                // 2. Mostrar los códigos reales y enmascarados
                let html = '<h4>Códigos reales:</h4><textarea style="width:98%;height:120px;">' + codigos.join('\n') + '</textarea>';
                html += '<h4 style="margin-top:16px;">Códigos enmascarados para entregar:</h4><textarea style="width:98%;height:120px;background:#e9fff5;">' + (res.success && res.data ? res.data.join('\n') : 'Error al enmascarar') + '</textarea>';
                document.getElementById('codigos_resultado').innerHTML = html;

                // 3. Guardar los códigos reales en la BD
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=rd_guardar_codigos_membresia&data=' + encodeURIComponent(JSON.stringify(dataToSend))
                }).then(res => res.json())
                .then(res => {
                    boton.disabled = false;
                    boton.textContent = 'Generar';
                    if(res.success) {
                        mostrarModalLicencias(`✅ ${res.data.total} códigos guardados exitosamente`);
                    } else {
                        mostrarModalLicencias(`❌ Error al guardar en la base de datos`);
                    }
                });
            }).catch(err => {
                boton.disabled = false;
                boton.textContent = 'Generar';
                alert('Error al enmascarar códigos');
            });
        });
    };
    </script>
    <?php
    return ob_get_clean();
});


//////////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////Validacion y asignacion de licencias.
//////////////////////////////////////////////////////////////////////////////////////////////////////
add_action('wp_ajax_rd_validar_y_asignar_licencia', 'rd_validar_y_asignar_licencia');
function rd_validar_y_asignar_licencia() {
    if (!is_user_logged_in()) wp_send_json_error('Debes iniciar sesión.');

    global $wpdb;
    $user_id = get_current_user_id();
    $table = $wpdb->prefix . 'rd_membresias';
    $licencia = isset($_POST['licencia']) ? trim(sanitize_text_field($_POST['licencia'])) : '';

    // Quitar guiones si la ponen con formato
    $licencia = str_replace('-', '', $licencia);

    // 1. Verificar si el usuario YA tiene una licencia activa
    $row_actual = $wpdb->get_row($wpdb->prepare(
        "SELECT codigo FROM $table WHERE user_id = %d AND status = 'asignado'", $user_id
    ));

    if ($row_actual) {
        // Enmascara para mostrarlo en el modal
        $licencia_mask = function_exists('rd_enmascarar_licencia') ? strtoupper(rd_formatear_cod_con_guiones(rd_enmascarar_licencia($row_actual->codigo))) : $row_actual->codigo;
        wp_send_json_error('Tu cuenta ya tiene una licencia activa:<br><span style="font-size:1.2em;color:#005;">'.$licencia_mask.'</span>');
    }

    // 2. Buscar la licencia ingresada
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE codigo = %s", $licencia));
    if (!$row && strlen($licencia) > 12 && function_exists('rd_desenmascarar_licencia')) {
        $des = rd_desenmascarar_licencia($licencia);
        if ($des) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE codigo = %s", $des));
        }
    }
    if (!$row) wp_send_json_error('La licencia no existe.');

    // 3. Verificar que esté libre
    if ($row->status !== 'libre' || !empty($row->user_id)) {
        wp_send_json_error('Esta licencia ya ha sido usada o está inactiva.');
    }

    // 4. Asigna al usuario
    $wpdb->update($table, [
        'status' => 'asignado',
        'user_id' => $user_id,
        'fecha_asignacion' => current_time('mysql')
    ], [
        'id' => $row->id
    ]);

    update_user_meta($user_id, 'rd_codigo_membresia', $licencia);

    wp_send_json_success(true);
}
