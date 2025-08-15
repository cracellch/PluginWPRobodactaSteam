<?php 
/**
 * Plugin Name: RDASTEAM 
 * Plugin URI: https://robodacta-steam.mx
 * Description: Funciones basicas RDASTEAM Mostrar Rol, Restringir wp-admin y mostrar nombre en header. 
 * Version: 1.1.2
 * Author: Robodacta Miguel Alfonso
 * Author URI: https://robodacta-steam.mx
 * License: GPL2
 */

function mostrar_rol_usuario() {
    $user = wp_get_current_user();
    return $user->roles[0] ?? 'none';
}
add_shortcode('rol_usuario', 'mostrar_rol_usuario');

function restringir_admin(){
    if(!current_user_can('manage_options') && (!defined('DOING_AJAX') || ! DOING_AJAX)){
        wp_redirect(size_url());
        exit;
    }
}

add_action('admin_init', 'restringir_admin');

add_action('shutdown', function () {
    $log_path = WP_CONTENT_DIR . '/debug.log';

    if (!file_exists($log_path)) return;

    $lines = file($log_path);
    $filtered = array_filter($lines, function($line) {
        return !str_contains($line, 'Translation loading for the');
    });

    file_put_contents($log_path, implode('', $filtered));
});
//Funcion Mostrar Nombre Usuario en Header
function mostrar_nombre_usuario_actual() {
    if ( is_user_logged_in() ) {
        $current_user = wp_get_current_user();
        return '<span class="nombre-usuario-destacado">Hola, ' . esc_html( $current_user->display_name ) . '!</span>';
    } else {
        return '<span class="nombre-usuario-destacado">Hola, visitante.</span>';
    }
}
add_shortcode('mostrar_nombre_usuario', 'mostrar_nombre_usuario_actual');

//FIltrador debug
if (!function_exists('rd_custom_error_filter')) {
    function rd_custom_error_filter($errno, $errstr, $errfile, $errline) {
        // Oculta SOLO los avisos de hotspot_offset_x/y y load_textdomain_just_in_time
        $ocultar = [
            'Undefined array key "hotspot_offset_x"',
            'Undefined array key "hotspot_offset_y"',
            'Trying to access array offset on null',
            'Function _load_textdomain_just_in_time was called <strong>incorrectly</strong>'
        ];
        foreach ($ocultar as $filtro) {
            if (strpos($errstr, $filtro) !== false) {
                // Retorna true para indicar que el error ya fue gestionado (no mostrar)
                return true;
            }
        }
        // Para otros errores, usa el handler de WordPress
        return false; // Deja que WordPress maneje los demás
    }
    set_error_handler('rd_custom_error_filter', E_ALL);
}

?>