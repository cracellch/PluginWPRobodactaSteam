<?php
/**
 * Plugin Name: Tutor Tools LearnDash
 * Description: Funcionalidades para tutores de grupos en LearnDash.
 * Version: 1.0
 * Author: Tu Nombre
 */

// 1) Otorgar capacidades al rol group_leader
function sd_add_group_leader_caps() {
    $role = get_role( 'group_leader' );
    if ( ! $role ) return;

    $role->add_cap( 'groups_assign_groups' );
    $role->add_cap( 'groups_manage_groups' );
    $role->add_cap( 'read' );
    $role->add_cap( 'edit_posts' );
}
add_action( 'init', 'sd_add_group_leader_caps' );

// 2) Registrar shortcodes en el hook adecuado
add_action( 'init', function() {
    add_shortcode( 'tutor_group_progress', 'sd_tutor_group_progress_shortcode' );
    add_shortcode( 'tutor_enroll_user', 'sd_tutor_enroll_user_shortcode' );
});

// 3) Shortcode: [tutor_group_progress]
function sd_tutor_group_progress_shortcode( $atts ) {
    if ( ! is_user_logged_in() || ! current_user_can( 'groups_manage_groups' ) ) {
        return '<p>No tienes permiso para ver este panel.</p>';
    }

    if (
        ! function_exists( 'learndash_get_administrators_group_ids' ) ||
        ! function_exists( 'learndash_get_groups_courses_ids' ) ||
        ! function_exists( 'learndash_get_course_groups' ) ||
        ! shortcode_exists( 'learndash_group_user_list' )
    ) {
        return '<p>LearnDash no está activo o tu versión no lo soporta.</p>';
    }

    $user_id   = get_current_user_id();
    $group_ids = learndash_get_administrators_group_ids( $user_id );
    if ( empty( $group_ids ) ) {
        return '<p>No lideras ningún grupo.</p>';
    }

    $all_course_ids = learndash_get_groups_courses_ids( $user_id, $group_ids );
    $out  = '<div class="tutor-dashboard">';

    foreach ( $group_ids as $group_id ) {
        $out .= '<h2>Grupo: ' . esc_html( get_the_title( $group_id ) ) . '</h2>';

        $course_ids = array_filter( $all_course_ids, function( $cid ) use ( $group_id ) {
            return in_array( $group_id, learndash_get_course_groups( $cid ), true );
        } );

        if ( empty( $course_ids ) ) {
            $out .= '<p>Este grupo no tiene cursos asignados.</p>';
            continue;
        }

        foreach ( $course_ids as $course_id ) {
            $out .= '<h3>Curso: ' . esc_html( get_the_title( $course_id ) ) . '</h3>';
            $out .= do_shortcode( sprintf(
                '[learndash_group_user_list group_id="%d" course_id="%d"]',
                $group_id, $course_id
            ) );
        }
    }

    $out .= '</div>';
    return $out;
}

// 4) Shortcode: [tutor_enroll_user]
function sd_tutor_enroll_user_shortcode( $atts ) {
    if ( ! is_user_logged_in() || ! current_user_can( 'groups_assign_groups' ) ) {
        return '<p>No tienes permiso para ver este formulario.</p>';
    }

    if (
        ! function_exists( 'learndash_get_administrators_group_ids' ) ||
        ! function_exists( 'learndash_get_groups_courses_ids' ) ||
        ! function_exists( 'ld_update_course_access' )
    ) {
        return '<p>LearnDash no está activo o tu versión no lo soporta.</p>';
    }

    $current_user_id = get_current_user_id();
    $group_ids = learndash_get_administrators_group_ids( $current_user_id );
    if ( empty( $group_ids ) ) {
        return '<p>No lideras ningún grupo. No hay cursos que administrar.</p>';
    }

    $all_course_ids = learndash_get_groups_courses_ids( $current_user_id, $group_ids );
    $output  = '';
    $message = '';

    if (
        isset( $_POST['sd_tutor_enroll_nonce'], $_POST['sd_course_id'], $_POST['sd_student_identifier'] )
        && wp_verify_nonce( $_POST['sd_tutor_enroll_nonce'], 'sd_tutor_enroll_action' )
    ) {
        $curso_id   = intval( $_POST['sd_course_id'] );
        $identifier = trim( sanitize_text_field( wp_unslash( $_POST['sd_student_identifier'] ) ) );

        if ( ! in_array( $curso_id, $all_course_ids, true ) ) {
            $message = '<p style="color:red;">Curso inválido o no administrado por ti.</p>';
        } else {
            $found_user = is_email( $identifier )
                ? get_user_by( 'email', $identifier )
                : get_user_by( 'login', $identifier );

            if ( ! $found_user ) {
                $message = '<p style="color:red;">No se encontró ningún usuario con ese correo o nombre de usuario.</p>';
            } else {
                $target_user_id = intval( $found_user->ID );
                $is_enrolled = function_exists( 'learndash_is_user_enrolled' ) ?
                    learndash_is_user_enrolled( $target_user_id, $curso_id ) : false;

                if ( $is_enrolled ) {
                    $message = '<p style="color:orange;">El usuario ya está inscrito en el curso.</p>';
                } else {
                    try {
                        $success = ld_update_course_access( $target_user_id, $curso_id, true );
                        $message = $success
                            ? '<p style="color:green;">Éxito: el usuario fue matriculado en el curso.</p>'
                            : '<p style="color:red;">Error: no se pudo matricular al usuario.</p>';
                    } catch ( \Throwable $e ) {
                        $message = '<p style="color:red;">Excepción: ' . esc_html( $e->getMessage() ) . '</p>';
                    }
                }
            }
        }
    }

    if ( $message ) {
        $output .= '<div class="sd-tutor-message">' . $message . '</div>';
    }

    $output .= '<div class="sd-tutor-enroll-wrapper">';
    $output .= '<p>Introduce el correo o nombre de usuario del alumno y pulsa “Matricular” para inscribirlo en ese curso.</p>';

    foreach ( $all_course_ids as $curso_id ) {
        $titulo = get_the_title( $curso_id );
        $output .= '<div class="sd-course-block" style="border:1px solid #ddd; padding:10px; margin-bottom:10px;">';
        $output .= '<h3>Curso: ' . esc_html( $titulo ) . ' (ID ' . intval( $curso_id ) . ')</h3>';
        $output .= '<form method="post">';
        $output .= wp_nonce_field( 'sd_tutor_enroll_action', 'sd_tutor_enroll_nonce', true, false );
        $output .= '<input type="hidden" name="sd_course_id" value="' . esc_attr( $curso_id ) . '">';
        $output .= '<label for="sd_student_identifier_' . intval( $curso_id ) . '">Correo o usuario:</label> ';
        $output .= '<input type="text" id="sd_student_identifier_' . intval( $curso_id ) . '" name="sd_student_identifier" placeholder="ejemplo@dominio.com o usuario" required style="width:240px;"> ';
        $output .= '<button type="submit" style="padding:4px 10px; margin-left:6px;">Matricular</button>';
        $output .= '</form>';
        $output .= '</div>';
    }

    $output .= '</div>';
    return $output;
}
