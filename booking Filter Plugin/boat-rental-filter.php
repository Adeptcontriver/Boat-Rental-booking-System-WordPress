<?php
/*
Plugin Name: Boat Rental Filter Plugin
Description: A plugin to handle boat rental filtering based on ACF fields.
Version: 1.0
Author: Savvy Programmers
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

    // Fetch the first boat rental post with the 'with_captain' tag
    $with_captain_post = get_posts(array(
        'post_type' => 'boat-rental',
        'posts_per_page' => 1,
        'tax_query' => array(
            array(
                'taxonomy' => 'post_tag',
                'field'    => 'slug',
                'terms'    => $with_captain_tag,
            ),
        ),
    ));

    $with_captain_url = !empty($with_captain_post) ? get_permalink($with_captain_post[0]->ID) : '#';

    ob_start();
    ?>
    <div id="brf-filter-buttons">
    <div class="rental-withcaptain">
            <h3> Looking to book a rental with a captain? </h3>
         <button id="brf-book-with-captain-btn" class="filter-btn" data-url="<?php echo esc_url($with_captain_url); ?>">
         Click Here To Book
        </button>
</div>
  <button id="brf-with-captain-btn" class="filter-btn  " data-tag="<?php echo esc_attr($with_captain_tag); ?>">With Captain</button>   
    <button id="brf-without-captain-btn" class="filter-btn active  " data-tag="<?php echo esc_attr($without_captain_tag); ?>">Without Captain</button>  
 
  
      
         </div>
       
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
        <input type="hidden" id="boat-id" value="">
        <input type="hidden" id="selected-time" value="">
    </div>
    <div style="clear: both;"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('brf-book-with-captain-btn').addEventListener('click', function() {
                let postUrl = this.getAttribute('data-url');
                if (postUrl !== '#') {
                    window.location.href = postUrl;
                } else {
                    alert('No available rentals with a captain at the moment.');
                }
            });
        });
    </script>
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
            $boat_price = get_field('hourly_rental_price', $boat_id);
            $boat_hours = get_field('booking_hours' , $boat_id);
            ?>
            <div class="brf-post-item" data-boat-id="<?php echo esc_attr($boat_id); ?>">
                <h3><?php the_title(); ?></h3>
                <h3>Rental Price: $<?php echo esc_html($boat_price); ?></h3>
                <input type="hidden" id="booking-hours" value=" <?php echo esc_html($boat_hours); ?>">
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

// New funtionality




// function brf_plugin_fetch_time_slots() {
//     $selectedDate = sanitize_text_field($_POST['date']);
//     $boat_id = intval($_POST['boat_id']);
//     $time_slots = [];
//     $booked_ranges = [];

//     // Get interlinked boat from hourly boat rental
//     $interlinked_boat = get_field('interlinked_boat', $boat_id);
//     $interlinked_boat_id = is_object($interlinked_boat) ? $interlinked_boat->ID : $interlinked_boat;
//     $is_interlinked = !empty($interlinked_boat_id);

//     // Fetch Drop-Off Rental post with the same interlinked_boat_id
//     $dropoff_rental = get_posts([
//         'post_type' => 'drop-off-rental',
//         'posts_per_page' => 1,
//         'meta_query' => [
//             [
//                 'key' => 'interlinked_boat',
//                 'value' => $interlinked_boat_id,
//                 'compare' => '='
//             ]
//         ]
//     ]);
//     $dropoff_rental_id = !empty($dropoff_rental) ? $dropoff_rental[0]->ID : null;
//     $dropoff_boats_available = 0;

//     // Get drop-off rental time slots
//     if ($dropoff_rental_id && have_rows('boat_time_slots', $dropoff_rental_id)) {
//         while (have_rows('boat_time_slots', $dropoff_rental_id)) {
//             the_row();
//             $slot_time = get_sub_field('time_slot');
//             $dropoff_boats_available = intval(get_sub_field('boats_available'));
//         }
//     }

//     // Collect all bookings to determine booked time ranges
//     $booked_time_ranges = [];
//     $boat_hours = intval(get_field('booking_hours', $boat_id)) ?: 2; // Default to 2 hours if not set

//     // Query hourly rental bookings
//     $booking_query = new WP_Query([
//         'post_type' => 'booking',
//         'posts_per_page' => -1,
//         'meta_query' => [
//             ['key' => 'boat_id', 'value' => $boat_id, 'compare' => '='],
//             ['key' => 'booking_date', 'value' => $selectedDate, 'compare' => '='],
//             ['key' => 'booking_status', 'value' => 'confirmed', 'compare' => '='],
//         ]
//     ]);

//     while ($booking_query->have_posts()) {
//         $booking_query->the_post();
//         $time_slot = get_post_meta(get_the_ID(), 'time_slot', true);
//         $booking_duration = $boat_hours;
//         $start_time = strtotime($time_slot);
//         $end_time = strtotime("+$booking_duration hours", $start_time);
//         $booked_time_ranges[] = [
//             'start' => $time_slot,
//             'start_time' => date('Y-m-d H:i:s', $start_time),
//             'end_time' => date('Y-m-d H:i:s', $end_time),
//             'type' => 'hourly'
//         ];
//     }
//     wp_reset_postdata();

//     // Query drop-off bookings
//     if ($dropoff_rental_id) {
//         $dropoff_booking_query = new WP_Query([
//             'post_type' => 'drop-off-rental-book',
//             'posts_per_page' => -1,
//             'meta_query' => [
//                 ['key' => 'interlinked_boat', 'value' => $interlinked_boat_id, 'compare' => '='],
//                 ['key' => 'booking_date', 'value' => $selectedDate, 'compare' => '<=', 'type' => 'DATE'],
//                 ['key' => 'booking_end_date', 'value' => $selectedDate, 'compare' => '>=', 'type' => 'DATE'],
//                 ['key' => 'booking_status', 'value' => 'confirmed', 'compare' => '='],
//             ]
//         ]);

//         while ($dropoff_booking_query->have_posts()) {
//             $dropoff_booking_query->the_post();
//             $time_slot = get_post_meta(get_the_ID(), 'time_slot', true);
//             $booking_duration = $boat_hours;
//             $start_time = strtotime($time_slot);
//             $end_time = strtotime("+$booking_duration hours", $start_time);
//             $booked_time_ranges[] = [
//                 'start' => $time_slot,
//                 'start_time' => date('Y-m-d H:i:s', $start_time),
//                 'end_time' => date('Y-m-d H:i:s', $end_time),
//                 'type' => 'dropoff'
//             ];
//         }
//         wp_reset_postdata();
//     }

//     // Process time slots
//     if (have_rows('boat_time_slots', $boat_id)) {
//         while (have_rows('boat_time_slots', $boat_id)) {
//             the_row();
//             $time_slot = get_sub_field('time_slot');
//             $configured_quantity = intval(get_sub_field('boats_available'));
//             $is_available = true;
//             $booked_count1 = 0; // Hourly bookings
//             $booked_count2 = 0; // Drop-off bookings

//             // Convert time slot to timestamp
//             $slot_time = strtotime($time_slot);

//             // Check if this time slot is within any booked range
//             $booked_count = 0;
//             foreach ($booked_time_ranges as $range) {
//                 $range_start = strtotime($range['start_time']);
//                 $range_end = strtotime($range['end_time']);
//                 if ($slot_time >= $range_start && $slot_time < $range_end) {
//                     $booked_count++;
//                     if ($range['type'] === 'hourly') {
//                         $booked_count1++;
//                     } else {
//                         $booked_count2++;
//                     }
//                 }
//             }

//             // Determine availability
//             $boats_available = $configured_quantity - $booked_count;
//             if ($boats_available <= 0 || $booked_count >= $configured_quantity) {
//                 $is_available = false;
//                 $boats_available = 0;
//             }

//             $time_slots[] = [
//                 'time' => $time_slot,
//                 'available' => $is_available,
//                 'boat_quantity' => $configured_quantity,
//                 'boats_available' => $boats_available,
//                 'booking_hours' => $boat_hours,
//                 'booked_count1' => $booked_count1,
//                 'booked_count2' => $booked_count2,
//                 'booked_ranges' => $booked_time_ranges
//             ];
//         }
//     }

//     // Return JSON response
//     wp_send_json_success([
//         'hourly_boat_rental_id' => $boat_id,
//         'interlinked_boat_id' => $interlinked_boat_id,
//         'drop_off_rental_id' => $dropoff_rental_id,
//         'boats_available_in_hourly_boat_rental' => $configured_quantity,
//         'boats_available_in_drop_off_rental' => $dropoff_boats_available,
//         'slots' => $time_slots,
//         'booked_count' => array_sum(array_column($time_slots, 'boats_available'))
//     ]);
// }
// add_action('wp_ajax_brf_plugin_fetch_time_slots', 'brf_plugin_fetch_time_slots');
// add_action('wp_ajax_nopriv_brf_plugin_fetch_time_slots', 'brf_plugin_fetch_time_slots');




function brf_plugin_fetch_time_slots() {
    $selectedDate = sanitize_text_field($_POST['date']);
    $boat_id = intval($_POST['boat_id']);
    $time_slots = [];
    $booked_ranges = [];

    // Get interlinked boat from hourly boat rental
    $interlinked_boat = get_field('interlinked_boat', $boat_id);
    $interlinked_boat_id = is_object($interlinked_boat) ? $interlinked_boat->ID : $interlinked_boat;
    $is_interlinked = !empty($interlinked_boat_id);

    // Fetch Drop-Off Rental post with the same interlinked_boat_id
    $dropoff_rental = get_posts([
        'post_type' => 'drop-off-rental',
        'posts_per_page' => 1,
        'meta_query' => [
            [
                'key' => 'interlinked_boat',
                'value' => $interlinked_boat_id,
                'compare' => '='
            ]
        ]
    ]);
    $dropoff_rental_id = !empty($dropoff_rental) ? $dropoff_rental[0]->ID : null;
    $dropoff_boats_available = 0;

    // Get drop-off rental time slots
    if ($dropoff_rental_id && have_rows('boat_time_slots', $dropoff_rental_id)) {
        while (have_rows('boat_time_slots', $dropoff_rental_id)) {
            the_row();
            $slot_time = get_sub_field('time_slot');
            $dropoff_boats_available = intval(get_sub_field('boats_available')) ?: 3; // Fallback for drop-off
        }
    }

    // Get boats available and booking hours from ACF fields
    $boats_available = intval(get_field('boats_available', $boat_id)) ?: 3; // Use new field, fallback to 3
    $boat_hours = intval(get_field('booking_hours', $boat_id)) ?: 2; // Default to 2 hours

    // Collect all bookings to determine booked time ranges
    $booked_time_ranges = [];

    // Query hourly rental bookings
    $booking_query = new WP_Query([
        'post_type' => 'booking',
        'posts_per_page' => -1,
        'meta_query' => [
            ['key' => 'boat_id', 'value' => $boat_id, 'compare' => '='],
            ['key' => 'booking_date', 'value' => $selectedDate, 'compare' => '='],
            ['key' => 'booking_status', 'value' => 'confirmed', 'compare' => '='],
        ]
    ]);

    while ($booking_query->have_posts()) {
        $booking_query->the_post();
        $time_slot = get_post_meta(get_the_ID(), 'time_slot', true);
        $booking_duration = $boat_hours;
        $start_time = strtotime($time_slot);
        $end_time = strtotime("+$booking_duration hours", $start_time);
        $booked_time_ranges[] = [
            'start' => $time_slot,
            'start_time' => date('Y-m-d H:i:s', $start_time),
            'end_time' => date('Y-m-d H:i:s', $end_time),
            'type' => 'hourly'
        ];
    }
    wp_reset_postdata();

    // Query drop-off bookings
    if ($dropoff_rental_id) {
        $dropoff_booking_query = new WP_Query([
            'post_type' => 'drop-off-rental-book',
            'posts_per_page' => -1,
            'meta_query' => [
                ['key' => 'interlinked_boat', 'value' => $interlinked_boat_id, 'compare' => '='],
                ['key' => 'booking_date', 'value' => $selectedDate, 'compare' => '<=', 'type' => 'DATE'],
                ['key' => 'booking_end_date', 'value' => $selectedDate, 'compare' => '>=', 'type' => 'DATE'],
                ['key' => 'booking_status', 'value' => 'confirmed', 'compare' => '='],
            ]
        ]);

        while ($dropoff_booking_query->have_posts()) {
            $dropoff_booking_query->the_post();
            $time_slot = get_post_meta(get_the_ID(), 'time_slot', true);
            $booking_duration = $boat_hours;
            $start_time = strtotime($time_slot);
            $end_time = strtotime("+$booking_duration hours", $start_time);
            $booked_time_ranges[] = [
                'start' => $time_slot,
                'start_time' => date('Y-m-d H:i:s', $start_time),
                'end_time' => date('Y-m-d H:i:s', $end_time),
                'type' => 'dropoff'
            ];
        }
        wp_reset_postdata();
    }

    // Process time slots
    if (have_rows('boat_time_slots', $boat_id)) {
        while (have_rows('boat_time_slots', $boat_id)) {
            the_row();
            $time_slot = get_sub_field('time_slot');

            // Use boats_available from the new field
            $booked_count1 = 0; // Hourly bookings
            $booked_count2 = 0; // Drop-off bookings

            // Convert time slot to timestamp
            $slot_time = strtotime($time_slot);
            $slot_end_time = strtotime("+$boat_hours hours", $slot_time);

            // Check availability for a new 2-hour booking starting at this slot
            $is_available = false;
            $boats_available_count = 0;

            // Count boats booked for each 30-minute slot in the 2-hour window
            $max_booked = 0;
            for ($check_time = $slot_time; $check_time < $slot_end_time; $check_time += 1800) { // 1800 seconds = 30 minutes
                $booked_in_slot = 0;
                foreach ($booked_time_ranges as $range) {
                    $range_start = strtotime($range['start_time']);
                    $range_end = strtotime($range['end_time']);
                    if ($check_time >= $range_start && $check_time < $range_end) {
                        $booked_in_slot++;
                        if ($range['type'] === 'hourly' && $check_time === $slot_time) {
                            $booked_count1++;
                        } elseif ($range['type'] === 'dropoff' && $check_time === $slot_time) {
                            $booked_count2++;
                        }
                    }
                }
                $max_booked = max($max_booked, $booked_in_slot);
            }

            // If max_booked < boats_available, at least one boat is available for the entire 2-hour period
            $boats_available_count = $boats_available - $max_booked;
            $is_available = $boats_available_count > 0;

            $time_slots[] = [
                'time' => $time_slot,
                'available' => $is_available,
                'boat_quantity' => $boats_available, // Use the new field
                'boats_available' => $boats_available_count,
                'booking_hours' => $boat_hours,
                'booked_count1' => $booked_count1,
                'booked_count2' => $booked_count2,
                'booked_ranges' => $booked_time_ranges
            ];
        }
    }

    // Return JSON response
    wp_send_json_success([
        'hourly_boat_rental_id' => $boat_id,
        'interlinked_boat_id' => $interlinked_boat_id,
        'drop_off_rental_id' => $dropoff_rental_id,
        'boats_available_in_hourly_boat_rental' => $boats_available,
        'boats_available_in_drop_off_rental' => $dropoff_boats_available,
        'slots' => $time_slots,
        'booked_count' => array_sum(array_column($time_slots, 'boats_available'))
    ]);
}
add_action('wp_ajax_brf_plugin_fetch_time_slots', 'brf_plugin_fetch_time_slots');
add_action('wp_ajax_nopriv_brf_plugin_fetch_time_slots', 'brf_plugin_fetch_time_slots');

function brf_validate_booking($boat_id, $date, $time_slot, $booking_type = 'hourly') {
    $boat_hours = intval(get_field('booking_hours', $boat_id)) ?: 2;
    $boats_available = intval(get_field('boats_available', $boat_id)) ?: 3; // Use new field
    $start_time = strtotime($time_slot);
    $end_time = strtotime("+$boat_hours hours", $start_time);

    // Collect booked ranges
    $booked_ranges = [];
    $booking_query = new WP_Query([
        'post_type' => 'booking',
        'posts_per_page' => -1,
        'meta_query' => [
            ['key' => 'boat_id', 'value' => $boat_id, 'compare' => '='],
            ['key' => 'booking_date', 'value' => $date, 'compare' => '='],
            ['key' => 'booking_status', 'value' => 'confirmed', 'compare' => '='],
        ]
    ]);

    while ($booking_query->have_posts()) {
        $booking_query->the_post();
        $slot = get_post_meta(get_the_ID(), 'time_slot', true);
        $booked_start = strtotime($slot);
        $booked_end = strtotime("+$boat_hours hours", $booked_start);
        $booked_ranges[] = ['start' => $booked_start, 'end' => $booked_end];
    }
    wp_reset_postdata();

    $interlinked_boat = get_field('interlinked_boat', $boat_id);
    $interlinked_boat_id = is_object($interlinked_boat) ? $interlinked_boat->ID : $interlinked_boat;
    if ($interlinked_boat_id) {
        $dropoff_query = new WP_Query([
            'post_type' => 'drop-off-rental-book',
            'posts_per_page' => -1,
            'meta_query' => [
                ['key' => 'interlinked_boat', 'value' => $interlinked_boat_id, 'compare' => '='],
                ['key' => 'booking_date', 'value' => $date, 'compare' => '<=', 'type' => 'DATE'],
                ['key' => 'booking_end_date', 'value' => $date, 'compare' => '>=', 'type' => 'DATE'],
                ['key' => 'booking_status', 'value' => 'confirmed', 'compare' => '='],
            ]
        ]);

        while ($dropoff_query->have_posts()) {
            $dropoff_query->the_post();
            $slot = get_post_meta(get_the_ID(), 'time_slot', true);
            $booked_start = strtotime($slot);
            $booked_end = strtotime("+$boat_hours hours", $booked_start);
            $booked_ranges[] = ['start' => $booked_start, 'end' => $booked_end];
        }
        wp_reset_postdata();
    }

    // Check availability for the 2-hour window
    $max_booked = 0;
    for ($check_time = $start_time; $check_time < $end_time; $check_time += 1800) {
        $booked_in_slot = 0;
        foreach ($booked_ranges as $range) {
            if ($check_time >= $range['start'] && $check_time < $range['end']) {
                $booked_in_slot++;
            }
        }
        $max_booked = max($max_booked, $booked_in_slot);
    }

    return $max_booked < $boats_available;
}
