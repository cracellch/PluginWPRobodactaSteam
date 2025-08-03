<?php
/**
 * Plugin Name: RDASTEAM Gestion Membresias
 * Plugin URI: https://robodacta-steam.mx
 * Description: Creacion de membresias y registro de escuelas. 
 * Version: 1.0.0
 * Author: Robodacta Miguel Alfonso
 * Author URI: https://robodacta-steam.mx
 * License: GPL2
 */
 add_action('all', function($hook_name = null) {
    if (!is_admin()) return; // Opcional: solo en admin
    if (strpos($_SERVER['REQUEST_URI'], 'memberdash') !== false) { // Solo cuando estés en páginas de memberdash
        error_log('[HOOK] Acción disparada: ' . current_filter());
    }
});

//////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////Hook codigo para miembro estudiante
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

////////////////////////////////////////////Desactivacion/Eliminacion

add_action('ms_model_member_cancel_membership', function($membership_id, $member) {
    error_log("[MemberDash] El usuario {$member->id} canceló la membresía $membership_id.");
    // Aquí tu código: notificación, actualizar CRM, etc.
}, 10, 2);


add_action('ms_model_membership_drop_membership', function($subscription, $member) {
    // NOTA: A veces el primer argumento puede ser $subscription (relación), otras veces es solo el membership_id
    if (is_object($subscription) && property_exists($subscription, 'membership_id')) {
        $membership_id = $subscription->membership_id;
    } else {
        $membership_id = $subscription;
    }
    error_log("[MemberDash] El usuario {$member->id} DESACTIVÓ la membresía $membership_id.");
    // Aquí tu lógica: baja, email, update externo...
}, 10, 2);



/*
//detectar cambios memberdash drop/cancel
add_action('ms_model_membership_drop_membership', function($a, $b) {
    error_log('Drop Membership Hook --> '.print_r(func_get_args(),true));
}, 10, 2);

add_action('ms_model_membership_cancel_membership', function($membership_id, $member_obj) {
    error_log('Cancel Membership Hook: membership_id='.$membership_id.' user_id='.$member_obj->id);
}, 10, 2);


//Desactivar y cancelar membresias junto con licencia 
// Hook para quitar la licencia cuando se desactiva/cancela membresía
add_action('ms_model_membership_drop_membership', function($param1, $param2) {
    // Averigua cuál parámetro es el member_obj, dependiendo cómo llega en tu caso real
    $user_id = null;
    if (is_object($param1) && property_exists($param1, 'user_id')) {
        $user_id = $param1->user_id;
    } elseif (is_object($param2) && property_exists($param2, 'id')) {
        $user_id = $param2->id;
    }

    if (!$user_id) return;
    global $wpdb;
    // Busca el código asignado
    $codigo = $wpdb->get_var($wpdb->prepare(
        "SELECT codigo FROM wp89_rd_membresias WHERE user_id=%d AND status='asignado' LIMIT 1", $user_id
    ));
    if ($codigo) {
        $wpdb->update('wp89_rd_membresias', [
            'status' => 'libre',
            'user_id' => null,
            'fecha_asignacion' => null
        ], [
            'codigo' => $codigo
        ]);
        // Limpia el user_meta
        delete_user_meta($user_id, 'rd_codigo_membresia');
        error_log("✔️ Licencia $codigo desasignada de user_id=$user_id");
    }
}, 10, 2);

// Lo mismo puedes hacer con el hook cancel_membership si lo necesitas.


// Función para desvincular licencia con más logs
function rd_desvincula_membresia_codigo($user_id, $origen=''){
    global $wpdb;
    $table = $wpdb->prefix . 'rd_membresias';
    $codigo = $wpdb->get_var($wpdb->prepare(
        "SELECT codigo FROM $table WHERE user_id = %d AND status = 'asignado' LIMIT 1", $user_id
    ));
    error_log("[DEBUG] Desvinculando licencia. Origen: $origen | user_id: $user_id | codigo: $codigo");
    if ($codigo) {
        $wpdb->update($table, [
            'status' => 'libre',
            'user_id' => null,
            'fecha_asignacion' => null
        ], [
            'codigo' => $codigo
        ]);
        error_log("[DEBUG] Licencia $codigo liberada en la BD.");
    } else {
        error_log("[DEBUG] No se encontró licencia asignada para user_id=$user_id.");
    }
    // Borra el user_meta
    delete_user_meta($user_id, 'rd_codigo_membresia');
    error_log("[DEBUG] user_meta rd_codigo_membresia eliminado para user_id=$user_id.");
}*/







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
                <td style="padding:7px 0;"><?php echo esc_html($nombre); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php
    return ob_get_clean();
});
//////////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////Generacion de codigos para membresia.
//////////////////////////////////////////////////////////////////////////////////////////////////////
add_shortcode('rd_generar_codigos_membresia', function(){
    $escuelas = get_option('rd_escuelas', []);
    ob_start(); ?>
    <form id="rd-gen-codigos-form" style="max-width:500px;margin:2em auto;background:#f8f8ff;padding:30px 20px;border-radius:16px;box-shadow:0 2px 8px #2e467b22;">
        <h3 style="color:#2e467b;text-align:center;margin-bottom:18px;">Generar CÃ³digos de MembresÃ­a</h3>
        <label>Tipo de usuario:
            <select id="tipo_usuario" required onchange="mostrarColegioCampos(this.value)">
                <option value="CG">Colegio</option>
                <option value="ES">Estudiante</option>
            </select>
        </label><br>
        <div id="campos_colegio">
            <label>AÃ±o:
                <input type="number" id="anio" value="<?php echo date('Y'); ?>" required min="2023" max="2099">
            </label><br>
            <label>Escuela:
                <select id="escuela" required>
                    <?php foreach($escuelas as $id=>$nombre){
                        echo "<option value='$id'>($id) ".esc_html($nombre)."</option>";
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
                        echo "<option value='$v'>{$i}Â°</option>";
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
            <label>AÃ±o:
                <input type="number" id="anio_es" value="<?php echo date('Y'); ?>" required min="2023" max="2099">
            </label><br>
        </div>
        <button type="button" id="generar_codigos" style="margin-top:14px;background:#2e467b;color:#fff;border:none;padding:10px 24px;border-radius:7px;font-weight:bold;cursor:pointer;">Generar</button>
    </form>
    <div id="codigos_resultado" style="max-width:700px;margin:1.5em auto 3em auto;padding:10px;"></div>
    <script>
    function mostrarColegioCampos(val){
        if(val=="CG"){
            document.getElementById('campos_colegio').style.display = "";
            document.getElementById('campos_estudiante').style.display = "none";
        }else{
            document.getElementById('campos_colegio').style.display = "none";
            document.getElementById('campos_estudiante').style.display = "";
        }
    }
    document.getElementById('generar_codigos').onclick = function(){
        let tipo = document.getElementById('tipo_usuario').value;
        let codigos = [];
        if(tipo=="CG"){
            let anio = document.getElementById('anio').value;
            let escuela = String(document.getElementById('escuela').value).padStart(4,'0');
            let escolaridad = document.getElementById('escolaridad').value;
            let grado = document.getElementById('grado').value;
            let grupo = document.getElementById('grupo').value;
            let cantidad = parseInt(document.getElementById('cantidad').value);
            for(let i=1;i<=cantidad;i++) {
                let id = String(i).padStart(4,'0');
                codigos.push(`${tipo}${anio}${escuela}${escolaridad}${grado}${grupo}${id}`);
            }
        }else{
            let anio_es = document.getElementById('anio_es').value;
            // AquÃ­ podrÃ­as agregar un ID largo correlativo si lo necesitas.
            codigos.push(`ES${anio_es}`);
        }
        let html = '<h4>CÃ³digos generados:</h4><textarea style="width:98%;height:180px;">' + codigos.join('\n') + '</textarea>';
        html += `<div style="margin-top:12px;font-size:14px;"><b>Total:</b> ${codigos.length}</div>`;
        document.getElementById('codigos_resultado').innerHTML = html;
    };
    </script>
    <?php
    return ob_get_clean();
});

?>
