/**
 * 1) Dar al rol 'administrator' (o 'tutor') capacidad de Group Leader
 */
function sd_tutor_add_caps() {
    $role = get_role( 'group_leader' );
    if ( ! $role ) {
        return;
    }
    $role->add_cap( 'groups_manage_groups' );   // crear/editar grupos
    $role->add_cap( 'groups_assign_groups' );   // añadir/quitar usuarios
}
add_action( 'init', 'sd_tutor_add_caps', 10 );

/**
 * 2) Shortcode [tutor_group_progress]
 *    Muestra para el tutor todos sus grupos, los cursos de cada grupo
 *    y una tabla con cada alumno y su progreso.
 */
function sd_tutor_group_progress_shortcode( $atts ) {
    // 1. Permiso mínimo
    if ( ! is_user_logged_in() || ! current_user_can( 'groups_manage_groups' ) ) {
        return '<p>No tienes permiso para ver este panel.</p>';
    }

    // 2. Comprobamos APIs de LearnDash
    if (
        ! function_exists( 'learndash_get_administrators_group_ids' ) ||
        ! function_exists( 'learndash_get_groups_courses_ids' ) ||
        ! function_exists( 'learndash_get_course_groups' ) ||
        ! shortcode_exists( 'learndash_group_user_list' )
    ) {
        return '<p>LearnDash no está activo o tu versión no lo soporta.</p>';
    }

    $user_id   = get_current_user_id();
    // 3. Grupos que administra
    $group_ids = learndash_get_administrators_group_ids( $user_id );
    if ( empty( $group_ids ) ) {
        return '<p>No lideras ningún grupo.</p>';
    }

    // 4. Recuperar todos los cursos de esos grupos de una vez
    $all_course_ids = learndash_get_groups_courses_ids( $user_id, $group_ids );

    $out  = '<div class="tutor-dashboard">';
    foreach ( $group_ids as $group_id ) {
        $out .= '<h2>Grupo: ' . esc_html( get_the_title( $group_id ) ) . '</h2>';

        // 5. Filtrar solo los cursos de ESTE grupo
        $course_ids = array_filter( $all_course_ids, function( $cid ) use ( $group_id ) {
            return in_array( $group_id, learndash_get_course_groups( $cid ), true );
        } );

        if ( empty( $course_ids ) ) {
            $out .= '<p>Este grupo no tiene cursos asignados.</p>';
            continue;
        }

        // 6. Para cada curso, título + tabla de progreso
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
add_shortcode( 'tutor_group_progress', 'sd_tutor_group_progress_shortcode' );

/**
 * 1) Dar al rol 'group_leader' (o 'administrator') capacidad de Group Leader
 */
function sd_add_group_leader_caps() {
    // Cambia 'group_leader' por el rol que estés utilizando realmente:
    // si tu "tutor" ya usa el slug 'group_leader', déjalo así;
    // de lo contrario, cambia por 'administrator' o 'tutor', según corresponda.
    $role = get_role( 'group_leader' );
    if ( ! $role ) {
        return;
    }
    // Permisos necesarios para manejar grupos y asignar usuarios
    $role->add_cap( 'groups_assign_groups' );
    $role->add_cap( 'groups_manage_groups' );
    // También permitimos que ejecute ld_update_course_access()
    $role->add_cap( 'read' );
    $role->add_cap( 'edit_posts' );
}
add_action( 'init', 'sd_add_group_leader_caps', 10 );

/**
 * 2) Shortcode [tutor_enroll_user]
 *    Muestra para cada curso de los grupos que lidera el tutor:
 *      • Un bloque con formulario para matricular a un alumno
 *      • Captura errores para que no rompa la página
 */
function sd_tutor_enroll_user_shortcode( $atts ) {
    // 1. Sólo para group_leaders logueados
    if ( ! is_user_logged_in() || ! current_user_can( 'groups_assign_groups' ) ) {
        return '<p>No tienes permiso para ver este formulario.</p>';
    }

    // 2. Verificamos que LearnDash y sus funciones existen
    if (
        ! function_exists( 'learndash_get_administrators_group_ids' ) ||
        ! function_exists( 'learndash_get_groups_courses_ids' ) ||
        ! function_exists( 'ld_update_course_access' )
    ) {
        return '<p>LearnDash no está activo o tu versión no lo soporta.</p>';
    }

    $current_user_id = get_current_user_id();

    // Obtenemos los IDs de los grupos que este user administra
    $group_ids = learndash_get_administrators_group_ids( $current_user_id );
    if ( empty( $group_ids ) ) {
        return '<p>No lideras ningún grupo. No hay cursos que administrar.</p>';
    }

    /**
     * learndash_get_groups_courses_ids( $user_id, $group_ids ) devuelve un arreglo
     * con todos los course_id para los grupos que administra el usuario. 
     */
    $all_course_ids = learndash_get_groups_courses_ids( $current_user_id, $group_ids );
    if ( empty( $all_course_ids ) ) {
        return '<p>No existen cursos asociados a tus grupos.</p>';
    }

    // 3. Procesamos el envío de formulario (si se hizo)
    $output  = '';
    $message = ''; // Mensaje de validación o error

    if (
        isset( $_POST['sd_tutor_enroll_nonce'], $_POST['sd_course_id'], $_POST['sd_student_identifier'] )
        && wp_verify_nonce( $_POST['sd_tutor_enroll_nonce'], 'sd_tutor_enroll_action' )
    ) {
        $curso_id   = intval( $_POST['sd_course_id'] );
        $identifier = trim( sanitize_text_field( wp_unslash( $_POST['sd_student_identifier'] ) ) );

        // 3a. Verificamos que $curso_id sea uno de los cursos que el tutor administra
        if ( ! in_array( $curso_id, $all_course_ids, true ) ) {
            $message = '<p style="color:red;">Curso inválido o no administrado por ti.</p>';
        } else {
            // 3b. Buscamos al usuario por email o login
            $found_user = false;
            if ( is_email( $identifier ) ) {
                $found_user = get_user_by( 'email', $identifier );
            }
            if ( ! $found_user ) {
                $found_user = get_user_by( 'login', $identifier );
            }

            if ( ! $found_user ) {
                $message = '<p style="color:red;">No se encontró ningún usuario con ese correo o nombre de usuario.</p>';
            } else {
                $target_user_id = intval( $found_user->ID );

                // 3c. Verificamos si ya está inscrito
                if ( function_exists( 'learndash_is_user_enrolled' ) ) {
                    $is_enrolled = learndash_is_user_enrolled( $target_user_id, $curso_id );
                } else {
                    // Si no existe esa función, asumimos que NO está inscrito
                    $is_enrolled = false;
                }

                if ( $is_enrolled ) {
                    $message = '<p style="color:orange;">El usuario ya está inscrito en el curso.</p>';
                } else {
                    // 3d. Llamada a API de LearnDash dentro de try/catch
                    try {
                        // ld_update_course_access retorna true/false. El cuarto parámetro
                        // (remove_access) hay que pasar false para INSCRIBIR.
                        $success = ld_update_course_access( $target_user_id, $curso_id, true );
                        if ( $success ) {
                            $message = '<p style="color:green;">Éxito: el usuario fue matriculado en el curso.</p>';
                        } else {
                            $message = '<p style="color:red;">Error: no se pudo matricular al usuario. Verifica permisos o estado del curso.</p>';
                        }
                    } catch ( \Throwable $e ) {
                        // Capturamos cualquier excepción / error grave
                        $msg_err = esc_html( $e->getMessage() );
                        $message = '<p style="color:red;">Excepción al matricular: ' . $msg_err . '</p>';
                    }
                }
            }
        }
    }

    // 4. Si hay mensaje, lo mostramos
    if ( ! empty( $message ) ) {
        $output .= '<div class="sd-tutor-message">' . $message . '</div>';
    }

    // 5. Mostramos formulario para cada curso
    $output .= '<div class="sd-tutor-enroll-wrapper">';
    $output .= '<p>Introduce el correo o nombre de usuario del alumno y pulsa “Matricular” para inscribirlo en ese curso.</p>';

    foreach ( $all_course_ids as $curso_id ) {
        $titulo = get_the_title( $curso_id );
        $output .= '<div class="sd-course-block" style="border:1px solid #ddd; padding:10px; margin-bottom:10px;">';
        $output .= '<h3 style="margin:0 0 6px;">Curso: ' . esc_html( $titulo ) . ' (ID ' . intval( $curso_id ) . ')</h3>';
        $output .= '<form method="post">';
        // Nonce
        $output .= wp_nonce_field( 'sd_tutor_enroll_action', 'sd_tutor_enroll_nonce', true, false );
        // Campo oculto con ID de curso
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
add_shortcode( 'tutor_enroll_user', 'sd_tutor_enroll_user_shortcode' );
