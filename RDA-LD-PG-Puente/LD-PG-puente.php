<?php
/**
 * Plugin Name: RDASTEAM Puente LD-PG
 * Description: Restringe directorios/búsquedas y solicitudes de amistad de ProfileGrid a usuarios que compartan grupos de LearnDash (v4.23.2.1). Probado con ProfileGrid 5.9.5.6.
 * Version:     1.0.0
 * Author: Robodacta Miguel Alfonso
 * Author URI: https://robodacta-steam.mx
 * License: GPL2
 */

if ( ! defined('ABSPATH') ) exit;

class LD_PG_Bridge {

    const CACHE_TTL = 300; // 5 min

    public static function init(){
        // Filtrar listados de usuarios en el front (directorios/búsquedas de ProfileGrid)
        add_action('pre_get_users', [__CLASS__, 'filter_profilegrid_user_queries'], 20);

        // Interceptar solicitudes de amistad (vía AJAX o POST normales) si no comparten grupo
        add_action('init', [__CLASS__, 'guard_friend_requests'], 1);

        // Como “última red” (muchos temas usan admin-ajax.php):
        add_action('rest_request_before_callbacks', [__CLASS__, 'rest_guard_friend_requests'], 1, 3);

        // (Opcional) Helper para limpiar cache al cambiar grupos LD
        add_action('ld_added_user_to_group', [__CLASS__, 'invalidate_cache_on_group_change'], 10, 2);
        add_action('ld_removed_user_from_group', [__CLASS__, 'invalidate_cache_on_group_change'], 10, 2);
    }

    /** ========== LEARNDASH HELPERS ========== */

    public static function ld_user_group_ids( $user_id ){
        if ( ! function_exists('learndash_get_users_group_ids') ) {
            return [];
        }
        $ids = (array) learndash_get_users_group_ids( (int) $user_id, true ); // true => include_legacy = true
        return array_values( array_filter( array_map('intval', $ids) ) );
    }

    public static function ld_group_user_ids( $group_id ){
        $uids = [];
        if ( function_exists('learndash_get_groups_user_ids') ) {
            // LD 4.x
            $uids = (array) learndash_get_groups_user_ids( (int) $group_id );
        } elseif ( function_exists('learndash_get_groups_users') ) {
            // Fallback viejo
            $users = (array) learndash_get_groups_users( (int) $group_id );
            $uids  = wp_list_pluck( $users, 'ID' );
        }
        return array_values( array_filter( array_map('intval', $uids) ) );
    }

    public static function peers_for_user( $user_id ){
        $user_id = (int) $user_id;
        if ( ! $user_id ) return [0];

        // Cache transitorio para aliviar carga en grupos grandes
        $key = "ldpg_peers_{$user_id}";
        $cached = get_transient( $key );
        if ( is_array($cached) ) return $cached;

        $gids = self::ld_user_group_ids( $user_id );
        if ( empty($gids) ) {
            $peers = [0]; // sin grupo => no ver a nadie
            set_transient($key, $peers, self::CACHE_TTL);
            return $peers;
        }

        $pool = [];
        foreach( $gids as $gid ){
            $pool = array_merge( $pool, self::ld_group_user_ids( $gid ) );
        }
        $pool[] = $user_id; // incluirte a ti
        $peers = array_values( array_unique( array_map('intval', $pool) ) );

        set_transient($key, $peers, self::CACHE_TTL);
        return $peers;
    }

    public static function invalidate_cache_on_group_change( $user_id ){
        delete_transient( "ldpg_peers_".(int)$user_id );
    }

    /** ========== DETECCIÓN DE CONTEXTO PROFILEGRID ========== */

    // Shortcodes típicos de ProfileGrid que muestran usuarios
    protected static function pg_shortcodes(){
        return [
            'profilegrid_user_all',
            'profilegrid_member_list',
            'profilegrid_user_map',
            'profilegrid_groups',   // por si tu plantila lista miembros del grupo
            // agrega otros si los usas
        ];
    }

    protected static function is_profilegrid_context(){
        // 1) AJAX de ProfileGrid (acciones suelen empezar por pm_ o contener "profilegrid")
        if ( defined('DOING_AJAX') && DOING_AJAX ) {
            if ( isset($_REQUEST['action']) ) {
                $a = (string) $_REQUEST['action'];
                if ( strpos($a, 'pm_') === 0 || stripos($a, 'profilegrid') !== false ) {
                    return true;
                }
            }
        }

        // 2) Página con shortcodes de ProfileGrid
        if ( is_singular() ) {
            $pid = get_queried_object_id();
            if ( $pid ) {
                $content = get_post_field('post_content', $pid);
                if ( $content ) {
                    foreach( self::pg_shortcodes() as $sc ){
                        if ( has_shortcode($content, $sc) ) return true;
                    }
                }
            }
        }

        return false;
    }

    /** ========== FILTRO PRINCIPAL DE LISTADOS ========== */

    public static function filter_profilegrid_user_queries( WP_User_Query $q ){
        if ( is_admin() ) return;
        if ( ! is_user_logged_in() ) return;
        if ( ! self::is_profilegrid_context() ) return;

        $me = get_current_user_id();

        // Admines ven todo
        if ( user_can($me, 'manage_options') ) return;

        $allowed = self::peers_for_user( $me );
        // Si PG ya puso un include (p.ej. miembros de un grupo PG), intersectamos
        $existing_include = (array) $q->get('include');
        if ( ! empty($existing_include) ) {
            $allowed = array_values( array_intersect( $allowed, array_map('intval', $existing_include) ) );
        }

        // Sin grupos => no mostrar a nadie
        if ( empty($allowed) ) $allowed = [0];

        $q->set('include', $allowed );
    }

    /** ========== BLOQUEO DE SOLICITUDES DE AMISTAD (misma red) ========== */

    // Detecta si la request intenta crear/aceptar una amistad de ProfileGrid
    protected static function looks_like_friend_action(){
        // ProfileGrid suele usar 'action' o 'pm_action' con nombres que contienen 'friend'
        $keys = ['action','pm_action','pg_action'];
        foreach( $keys as $k ){
            if ( isset($_REQUEST[$k]) ) {
                $v = strtolower( (string) $_REQUEST[$k] );
                if ( strpos($v, 'friend') !== false ) return true;
                if ( strpos($v, 'buddy')  !== false ) return true; // por si usan buddy/amizade
            }
        }
        return false;
    }

    // Intenta obtener el ID de destino de amistad desde distintos nombres comunes
    protected static function extract_target_user_id(){
        $candidates = [
            'user_id','pm_user_id','uid','to_user','friend_id','requested_user','target','target_id'
        ];
        foreach( $candidates as $k ){
            if ( isset($_REQUEST[$k]) ) {
                $id = absint( $_REQUEST[$k] );
                if ( $id > 0 ) return $id;
            }
        }
        return 0;
    }

    public static function guard_friend_requests(){
        if ( ! is_user_logged_in() ) return;

        // Admines no restringidos
        if ( current_user_can('manage_options') ) return;

        // Solo si huele a acción de amistad (PG)
        if ( ! self::looks_like_friend_action() ) return;

        $me = get_current_user_id();
        $to = self::extract_target_user_id();

        if ( ! $to || $to === $me ) return;

        $allowed = self::peers_for_user( $me );
        if ( ! in_array( $to, $allowed, true ) ) {
            // 403 + mensaje claro
            wp_die(
                __('Solo puedes enviar/aceptar amistad con usuarios de tus mismos grupos de LearnDash.', 'ld-pg-bridge'),
                __('Acción no permitida', 'ld-pg-bridge'),
                [ 'response' => 403 ]
            );
        }
    }

    // Para REST o AJAX “encapsulado” (por si algunos add-ons van por REST)
    public static function rest_guard_friend_requests( $response, $handler, WP_REST_Request $request ){
        if ( ! is_user_logged_in() ) return $response;
        if ( current_user_can('manage_options') ) return $response;

        $route = $request->get_route();
        $looks_pg = ( stripos($route, 'profilegrid') !== false || stripos($route, 'pm_') !== false );
        $looks_friend = ( stripos($route, 'friend') !== false || stripos($route, 'buddy') !== false );

        if ( $looks_pg && $looks_friend ){
            $me = get_current_user_id();
            // mismo extractor pero desde la petición REST
            $to = 0;
            foreach( ['user_id','pm_user_id','uid','to_user','friend_id','requested_user','target','target_id'] as $k ){
                $v = $request->get_param($k);
                if ( $v ) { $to = absint($v); break; }
            }
            if ( $to && $to !== $me ){
                $allowed = self::peers_for_user( $me );
                if ( ! in_array($to, $allowed, true) ) {
                    return new WP_Error(
                        'ldpg_forbidden',
                        __('Solo puedes interactuar con amigos de tus mismos grupos de LearnDash.', 'ld-pg-bridge'),
                        [ 'status' => 403 ]
                    );
                }
            }
        }

        return $response;
    }
}
LD_PG_Bridge::init();
