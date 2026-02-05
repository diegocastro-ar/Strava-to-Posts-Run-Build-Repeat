<?php
/**
 * Plugin Name: Strava to Posts – Run Build Repeat
 * Description: Convierte actividades de Strava (via NMR) en posts de WordPress y los muestra en una página dedicada.
 * Version: 1.6
 */

if (!defined('ABSPATH'))
    exit;

add_action('init', function () {
    register_post_type('strava_activity', array(
        'labels' => array(
            'name' => 'Strava Activities',
            'singular_name' => 'Strava Activity',
            'menu_name' => 'Strava',
            'all_items' => 'Todas las actividades',
            'add_new' => 'Agregar nueva',
            'add_new_item' => 'Agregar actividad',
            'edit_item' => 'Editar actividad',
        ),
        'public' => true,
        'has_archive' => false,
        'show_in_menu' => true,
        'menu_icon' => 'dashicons-chart-line',
        'supports' => array('title', 'editor', 'custom-fields'),
        'rewrite' => array('slug' => 'actividad'),
        'exclude_from_search' => true,
        'publicly_queryable' => true,
        'show_in_rest' => true,
    ));
});

add_action('strava_nmr_activity_changed', 'dc_strava_create_post_from_activity', 10, 2);

function dc_strava_create_post_from_activity($action, $activity_data)
{
    if ($action !== 'update' && $action !== 'create') {
        return;
    }

    if (empty($activity_data) || !isset($activity_data['id'])) {
        return;
    }

    $existing = get_posts(array(
        'post_type' => 'strava_activity',
        'meta_key' => '_strava_activity_id',
        'meta_value' => $activity_data['id'],
        'post_status' => 'any',
        'numberposts' => 1
    ));

    if (!empty($existing)) {
        $post_id = $existing[0]->ID;
        wp_update_post(array(
            'ID' => $post_id,
            'post_title' => sanitize_text_field($activity_data['name']),
            'post_content' => dc_strava_format_activity_content($activity_data),
        ));
        update_post_meta($post_id, '_strava_activity_data', $activity_data);
        return;
    }

    $post_data = array(
        'post_title' => sanitize_text_field($activity_data['name']),
        'post_content' => dc_strava_format_activity_content($activity_data),
        'post_status' => 'publish',
        'post_type' => 'strava_activity',
        'post_author' => 1,
    );

    if (!empty($activity_data['start_date'])) {
        $post_data['post_date'] = date('Y-m-d H:i:s', strtotime($activity_data['start_date']));
    } elseif (!empty($activity_data['start_date_local'])) {
        $post_data['post_date'] = date('Y-m-d H:i:s', strtotime($activity_data['start_date_local']));
    }

    $post_id = wp_insert_post($post_data);

    if ($post_id && !is_wp_error($post_id)) {
        update_post_meta($post_id, '_strava_activity_id', $activity_data['id']);
        update_post_meta($post_id, '_strava_activity_data', $activity_data);
        update_post_meta($post_id, '_strava_activity_type', $activity_data['type'] ?? $activity_data['sport_type'] ?? 'Unknown');
    }
}

function dc_strava_format_activity_content($activity)
{
    $type = $activity['type'] ?? $activity['sport_type'] ?? 'Actividad';
    $distance = isset($activity['distance']) ? round($activity['distance'] / 1000, 2) : 0;
    $moving_time = isset($activity['moving_time']) ? dc_strava_format_time($activity['moving_time']) : '00:00';
    $elevation = isset($activity['total_elevation_gain']) ? round($activity['total_elevation_gain']) : 0;
    $calories = $activity['calories'] ?? 0;
    $avg_speed = isset($activity['average_speed']) ? round($activity['average_speed'] * 3.6, 1) : 0;
    $avg_hr = $activity['average_heartrate'] ?? 0;

    $pace = '';
    if ($distance > 0 && isset($activity['moving_time'])) {
        $pace_seconds = $activity['moving_time'] / $distance;
        $pace_min = floor($pace_seconds / 60);
        $pace_sec = round($pace_seconds % 60);
        $pace = sprintf("%d:%02d", $pace_min, $pace_sec);
    }

    $strava_url = !empty($activity['id']) ? "https://www.strava.com/activities/" . $activity['id'] : '';

    // Diseño minimalista
    $content = '<div class="strava-card">';

    // Stats en línea
    $stats = array();
    if ($distance > 0)
        $stats[] = "<span class='strava-stat'><strong>{$distance}</strong> km</span>";
    $stats[] = "<span class='strava-stat'><strong>{$moving_time}</strong></span>";
    if ($pace && in_array(strtolower($type), ['run', 'virtualrun', 'walk', 'hike'])) {
        $stats[] = "<span class='strava-stat'><strong>{$pace}</strong> /km</span>";
    }
    if ($elevation > 0)
        $stats[] = "<span class='strava-stat'><strong>{$elevation}</strong> m↑</span>";

    $content .= '<div class="strava-stats">' . implode('', $stats) . '</div>';

    if ($strava_url) {
        $content .= "<a href='{$strava_url}' target='_blank' rel='noopener' class='strava-link'>Ver en Strava →</a>";
    }

    $content .= '</div>';

    return $content;
}

function dc_strava_format_time($seconds)
{
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;

    if ($hours > 0) {
        return sprintf("%d:%02d:%02d", $hours, $minutes, $secs);
    }
    return sprintf("%d:%02d", $minutes, $secs);
}

function dc_strava_get_activity_emoji($type)
{
    $emojis = array(
        'run' => '🏃',
        'virtualrun' => '🏃',
        'ride' => '🚴',
        'virtualride' => '🚴',
        'swim' => '🏊',
        'walk' => '🚶',
        'hike' => '🥾',
        'workout' => '💪',
        'weighttraining' => '🏋️',
        'yoga' => '🧘',
    );
    return $emojis[strtolower($type)] ?? '🏅';
}

function dc_strava_ultimas_sesiones_shortcode($atts)
{
    $atts = shortcode_atts(array('cantidad' => 10), $atts);

    $query = new WP_Query(array(
        'post_type' => 'strava_activity',
        'posts_per_page' => intval($atts['cantidad']),
        'post_status' => 'publish',
        'orderby' => 'date',
        'order' => 'DESC',
    ));

    if (!$query->have_posts()) {
        return '<p style="text-align: center; color: #888; padding: 40px 0;">No hay actividades todavía.</p>';
    }

    // CSS minimalista inline
    $css = '
    <style>
    .strava-list { max-width: 100%; }
    .strava-item { 
        border-bottom: 1px solid #eee; 
        padding: 1.5rem 0;
    }
    .strava-item:last-child { border-bottom: none; }
    .strava-header {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        margin-bottom: 0.5rem;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    .strava-title {
        font-family: inherit;
        font-size: 1.1rem;
        font-weight: 600;
        color: #1a1a1a;
        margin: 0;
    }
    .strava-date {
        font-size: 0.85rem;
        color: #888;
    }
    .strava-card {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
    }
    .strava-stats {
        display: flex;
        gap: 1.5rem;
        flex-wrap: wrap;
    }
    .strava-stat {
        font-size: 0.95rem;
        color: #444;
    }
    .strava-stat strong {
        font-weight: 600;
        color: #1a1a1a;
    }
    .strava-link {
        font-size: 0.85rem;
        color: #fc4c02;
        text-decoration: none;
        transition: opacity 0.2s;
    }
    .strava-link:hover {
        opacity: 0.7;
    }
    @media (max-width: 600px) {
        .strava-header { flex-direction: column; gap: 0.25rem; }
        .strava-card { flex-direction: column; align-items: flex-start; }
        .strava-stats { gap: 1rem; }
    }
    </style>';

    ob_start();
    echo $css;
    echo '<div class="strava-list">';

    while ($query->have_posts()) {
        $query->the_post();
        $type = get_post_meta(get_the_ID(), '_strava_activity_type', true);
        $emoji = dc_strava_get_activity_emoji($type);
        ?>
        <article class="strava-item">
            <div class="strava-header">
                <h3 class="strava-title"><?php echo $emoji; ?>         <?php the_title(); ?></h3>
                <span class="strava-date"><?php echo get_the_date('j M Y'); ?></span>
            </div>
            <?php the_content(); ?>
        </article>
        <?php
    }

    echo '</div>';
    wp_reset_postdata();
    return ob_get_clean();
}
add_shortcode('strava_ultimas_sesiones', 'dc_strava_ultimas_sesiones_shortcode');
add_shortcode('strava_run_build_repeat', 'dc_strava_ultimas_sesiones_shortcode');

add_action('admin_menu', function () {
    add_submenu_page('options-general.php', 'Importar Strava', 'Importar Strava', 'manage_options', 'import-strava-activities', 'dc_strava_import_page');
});

function dc_strava_import_page()
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'nmr_strava_activities';
    $table_raw = $wpdb->prefix . 'nmr_strava_activities_raw';

    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
    $table_raw_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_raw}'") === $table_raw;

    $total_nmr = $table_exists ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}") : 0;
    $total_raw = $table_raw_exists ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_raw}") : 0;
    $total_imported = (int) wp_count_posts('strava_activity')->publish;

    if (isset($_POST['import_strava']) && check_admin_referer('import_strava_nonce')) {
        $use_raw = isset($_POST['use_raw']) && $table_raw_exists;
        $limit = isset($_POST['import_all']) ? 99999 : 50;
        $imported = 0;
        $skipped = 0;
        $errors = 0;

        if ($use_raw) {
            $rows = $wpdb->get_results("SELECT id, raw_activity FROM {$table_raw} ORDER BY date_added DESC LIMIT {$limit}");
            foreach ($rows as $row) {
                if (empty($row->raw_activity)) {
                    $errors++;
                    continue;
                }
                $activity = json_decode($row->raw_activity, true);
                if (!$activity || !isset($activity['id'])) {
                    $errors++;
                    continue;
                }
                $existing = get_posts(array('post_type' => 'strava_activity', 'meta_key' => '_strava_activity_id', 'meta_value' => $activity['id'], 'post_status' => 'any', 'numberposts' => 1));
                if (empty($existing)) {
                    dc_strava_create_post_from_activity('create', $activity);
                    $imported++;
                } else {
                    $skipped++;
                }
            }
        } else {
            $activities = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY start_date DESC LIMIT {$limit}");
            foreach ($activities as $activity) {
                $activity_id = $activity->strava_activity_id ?? $activity->activity_id ?? $activity->id ?? null;
                if (!$activity_id) {
                    $errors++;
                    continue;
                }
                $activity_data = array('id' => $activity_id, 'name' => $activity->name ?? 'Actividad', 'type' => $activity->type ?? 'Workout', 'distance' => $activity->distance ?? 0, 'moving_time' => $activity->moving_time ?? 0, 'total_elevation_gain' => $activity->total_elevation_gain ?? 0, 'start_date' => $activity->start_date ?? current_time('mysql'), 'average_speed' => $activity->average_speed ?? 0, 'average_heartrate' => $activity->average_heartrate ?? 0, 'calories' => $activity->calories ?? 0);
                $existing = get_posts(array('post_type' => 'strava_activity', 'meta_key' => '_strava_activity_id', 'meta_value' => $activity_id, 'post_status' => 'any', 'numberposts' => 1));
                if (empty($existing)) {
                    dc_strava_create_post_from_activity('create', $activity_data);
                    $imported++;
                } else {
                    $skipped++;
                }
            }
        }
        $total_imported = (int) wp_count_posts('strava_activity')->publish;
        echo '<div class="notice notice-success"><p>✅ ' . $imported . ' importadas' . ($skipped ? ' , ' . $skipped . ' existían' : '') . '</p></div>';
    }

    if (isset($_POST['delete_strava_posts']) && check_admin_referer('import_strava_nonce')) {
        $posts = get_posts(array('post_type' => 'strava_activity', 'numberposts' => -1, 'post_status' => 'any'));
        foreach ($posts as $p)
            wp_delete_post($p->ID, true);
        echo '<div class="notice notice-warning"><p>🗑️ Eliminadas ' . count($posts) . ' actividades</p></div>';
        $total_imported = 0;
    }

    if (isset($_POST['refresh_content']) && check_admin_referer('import_strava_nonce')) {
        $posts = get_posts(array('post_type' => 'strava_activity', 'numberposts' => -1, 'post_status' => 'publish'));
        $updated = 0;
        foreach ($posts as $p) {
            $data = get_post_meta($p->ID, '_strava_activity_data', true);
            if ($data) {
                wp_update_post(array('ID' => $p->ID, 'post_content' => dc_strava_format_activity_content($data)));
                $updated++;
            }
        }
        echo '<div class="notice notice-success"><p>✅ Actualizadas ' . $updated . ' actividades con el nuevo diseño</p></div>';
    }
    ?>
    <div class="wrap">
        <h1>🏃 Strava Activities</h1>
        <div style="background:#fff;padding:20px;border:1px solid #ccd0d4;margin:20px 0;border-radius:4px;">
            <h2 style="margin-top:0;">Estado</h2>
            <p>Tabla RAW: <strong><?php echo $total_raw; ?></strong> | Importadas:
                <strong><?php echo $total_imported; ?></strong></p>
        </div>
        <div style="background:#fff;padding:20px;border:1px solid #ccd0d4;margin:20px 0;border-radius:4px;">
            <h2 style="margin-top:0;">Acciones</h2>
            <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;">
                <?php wp_nonce_field('import_strava_nonce'); ?>
                <input type="hidden" name="use_raw" value="1">
                <input type="hidden" name="import_all" value="1">
                <button type="submit" name="import_strava" class="button button-primary">Importar todas</button>
                <button type="submit" name="refresh_content" class="button">Actualizar diseño</button>
                <?php if ($total_imported > 0): ?>
                    <button type="submit" name="delete_strava_posts" class="button" style="color:#d63638;"
                        onclick="return confirm('¿Eliminar todas?');">Eliminar todas</button>
                <?php endif; ?>
            </form>
        </div>
        <p>Shortcode: <code>[strava_ultimas_sesiones cantidad="10"]</code></p>
    </div>
    <?php
}

register_activation_hook(__FILE__, function () {
    register_post_type('strava_activity', array('public' => true, 'rewrite' => array('slug' => 'actividad')));
    flush_rewrite_rules();
});
register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules(); });
