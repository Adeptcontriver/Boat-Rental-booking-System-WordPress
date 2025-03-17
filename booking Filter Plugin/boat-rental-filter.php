<?php
/*
Plugin Name: Boat Rental Filter Plugin
Description: A plugin to handle boat rental filtering based on ACF fields.
Version: 1.0
Author: Shahid Kamal
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Enqueue scripts and styles
function brf_plugin_enqueue_scripts() {
    // Enqueue jQuery (if not already loaded)
    wp_enqueue_script('jquery');

    // Enqueue FullCalendar CSS and JS
    wp_enqueue_style('brf-fullcalendar-css', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css');
    wp_enqueue_script('brf-fullcalendar-js', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.js', array('jquery'), '6.1.8', true);

    $uniq = uniqid();
    // Enqueue custom script
    wp_enqueue_script('brf-custom-script', plugin_dir_url(__FILE__) . 'assets/boat-rental-filter.js?v=' . $uniq, array('jquery', 'brf-fullcalendar-js'), '1.0', true);

    // Localize AJAX URL
    wp_localize_script('brf-custom-script', 'brfAjax', array(
        'ajax_url' => admin_url('admin-ajax.php')
    ));
}
add_action('wp_enqueue_scripts', 'brf_plugin_enqueue_scripts');

// Shortcode for filter page
function brf_plugin_filter_shortcode() {
    $page_id = get_the_ID(); // Get the current post ID
    $with_captain_tag = get_field('with_captain', $page_id);
    $without_captain_tag = get_field('without_captain', $page_id);

    ob_start();
    ?>
    <div id="brf-filter-buttons">
        <button id="brf-with-captain-btn" class="filter-btn active" data-tag="<?php echo esc_attr($with_captain_tag); ?>">With Captain</button>
        <button id="brf-without-captain-btn" class="filter-btn" data-tag="<?php echo esc_attr($without_captain_tag); ?>">Without Captain</button>
    </div>
    <div id="brf-post-results">
        <!-- Related posts will be displayed here -->
    </div>
    <!-- Add the calendar container -->
    <div id="booking-calendar" style="width: 60%; float: left; min-height: 400px;"></div>
    <div id="time-slots-section" style="width: 35%; float: right; margin-left: 5%;">
        <h3 id="selected-date-title">Selected Date:</h3>
        <p>Please select your preferred time for booking.</p>
        <div id="dynamic-time-slots-container">
            <p>Please select a date to see available time slots.</p>
        </div>
        <button id="book-now-btn" disabled>Book Now</button>
        <button id="check-availability-btn">Check Next Availability</button>
        <input type="hidden" id="boat-id"  value="">
        <input type="hidden" id="selected-time" value="">
    </div>
    <div style="clear: both;"></div>
    <?php
    return ob_get_clean();
}
add_shortcode('brf_plugin_filter', 'brf_plugin_filter_shortcode');

// AJAX handler for filtering posts
function brf_plugin_fetch_filtered_posts() {
    if (!isset($_POST['tag'])) {
        wp_send_json_error(array('message' => 'Missing tag parameter.'));
        return;
    }

    $tag = sanitize_text_field($_POST['tag']);
    error_log("DEBUG: Received tag: " . $tag);

    $args = array(
        'post_type' => 'boat-rental',
        'posts_per_page' => -1,
        'tax_query' => array(
            array(
                'taxonomy' => 'post_tag',
                'field'    => 'slug',
                'terms'    => $tag,
            ),
        ),
    );

    $query = new WP_Query($args);
    error_log("DEBUG: Number of posts found: " . $query->found_posts);

    if ($query->have_posts()) {
        ob_start();
        while ($query->have_posts()) {
            $query->the_post();
            $boat_id = get_the_ID();
            ?>
            <div class="brf-post-item" data-boat-id="<?php echo esc_attr($boat_id); ?>">
                <h3><?php the_title(); ?></h3>
                <p><?php the_content(); ?></p> <!-- Fixed typo: the_contentt() to the_content() -->
            </div>
            <?php
        }
        wp_reset_postdata();
        wp_send_json_success(ob_get_clean());
    } else {
        wp_send_json_error(array('message' => 'No boats found.'));
    }
}
add_action('wp_ajax_brf_plugin_fetch_filtered_posts', 'brf_plugin_fetch_filtered_posts');
add_action('wp_ajax_nopriv_brf_plugin_fetch_filtered_posts', 'brf_plugin_fetch_filtered_posts');

// AJAX handler for fetching time slots
function brf_plugin_fetch_time_slots() {
    $selectedDate = sanitize_text_field($_POST['date']);
    $boat_id = intval($_POST['boat_id']);
    $time_slots = array();

    // Fetch time slots from ACF repeater field
    if (have_rows('boat_time_slots', $boat_id)) {
        while (have_rows('boat_time_slots', $boat_id)) {
            the_row();
            $time_slot = get_sub_field('time_slot'); // Fetch the time slot from the repeater field

            // Check if the time slot is already booked
            $booking_query = new WP_Query(array(
                'post_type' => 'booking',
                'meta_query' => array(
                    array('key' => 'boat_id', 'value' => $boat_id, 'compare' => '='),
                    array('key' => 'booking_date', 'value' => $selectedDate, 'compare' => '='),
                    array('key' => 'time_slot', 'value' => $time_slot, 'compare' => '='),
                    array('key' => 'booking_status', 'value' => 'confirmed', 'compare' => '=')
                ),
            ));

            if (!$booking_query->have_posts()) {
                $time_slots[] = array(
                    'time' => $time_slot,
                    'available' => true,
                );
            } else {
                $time_slots[] = array(
                    'time' => $time_slot,
                    'available' => false,
                );
            }
        }
    }

    // Send JSON response
    wp_send_json_success(array('slots' => $time_slots));
}
add_action('wp_ajax_brf_plugin_fetch_time_slots', 'brf_plugin_fetch_time_slots');
add_action('wp_ajax_nopriv_brf_plugin_fetch_time_slots', 'brf_plugin_fetch_time_slots');