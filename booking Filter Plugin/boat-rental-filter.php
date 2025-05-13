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


function brf_plugin_fetch_time_slots() {
    $selectedDate = sanitize_text_field($_POST['date']);
    $boat_id = intval($_POST['boat_id']);
    $time_slots = [];

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
            $dropoff_boats_available = intval(get_sub_field('boats_available')); // Get the available boats for this time slot
        }
    }

    // Hourly Boat Rental time slots
    if (have_rows('boat_time_slots', $boat_id)) {
        while (have_rows('boat_time_slots', $boat_id)) {
            the_row();
            $time_slot = get_sub_field('time_slot');
            $configured_quantity = intval(get_sub_field('boats_available'));
            $booking_hours = intval(get_field('booking_hours'));
            $boat_hours = intval(get_field('booking_hours' , $boat_id));
            $boat_quantity = $configured_quantity;
            $is_available = true;

            // Booking count from both booking types
            $booked_count = 0;
            $booked_count1 = 0;
            $booked_count2 = 0;

            // Count hourly rental bookings
            $booking_query = new WP_Query([
                'post_type' => 'booking',
                'posts_per_page' => -1,
                'meta_query' => [
                    ['key' => 'boat_id', 'value' => $boat_id, 'compare' => '='],
                    ['key' => 'booking_date', 'value' => $selectedDate, 'compare' => '='],
                    ['key' => 'time_slot', 'value' => $time_slot, 'compare' => '='],
                    ['key' => 'booking_status', 'value' => 'confirmed', 'compare' => '='],
                ]
            ]);
            $booked_count += $booking_query->found_posts;
            $booked_count1 += $booking_query->found_posts;
           
            // Count drop-off bookings
            if ($dropoff_rental_id) {
                $dropoff_booking_query = new WP_Query([
                    'post_type' => 'drop-off-rental-book',
                    'posts_per_page' => -1,
                    'meta_query' => [
                        ['key' => 'interlinked_boat', 'value' => $interlinked_boat_id, 'compare' => '='],
                        ['key' => 'booking_date', 'value' => $selectedDate, 'compare' => '<=', 'type' => 'DATE'],
                        ['key' => 'booking_end_date', 'value' => $selectedDate, 'compare' => '>=', 'type' => 'DATE'],
                        ['key' => 'time_slot', 'value' => $time_slot, 'compare' => '='],
                        ['key' => 'booking_status', 'value' => 'confirmed', 'compare' => '='],
                    ]
                ]);
                $booked_count += $dropoff_booking_query->found_posts;
                $booked_count2 += $dropoff_booking_query->found_posts;

            }

                $configured_quantity = intval(get_sub_field('boats_available'));
                $booked_count = intval($booked_count); // Make sure it's an integer

                if ($booked_count >= $configured_quantity) {
                    $boats_available = 0;
                    $is_available = false;
                } else {
                    $boats_available = $configured_quantity - $booked_count;
                    $is_available = true;
                }

            // $boats_available = max($boat_quantity - $booked_count, 0);
            //$boats_available = $boat_quantity;
            if ($boats_available <= 0) $is_available = false;

            $time_slots[] = [
                'time' => $time_slot,
                'available' => $is_available,
                'boat_quantity' => $configured_quantity, // Show full quantity
                'boats_available' => $boats_available,
                'booking_hours' => $boat_hours,
                'booked_count1' => $booked_count1,
                'booked_count2' => $booked_count2,
            ];
        }
    }

    // Return JSON response with both hourly boat rental and drop-off rental details
    wp_send_json_success([
        'hourly_boat_rental_id' => $boat_id,
        'interlinked_boat_id' => $interlinked_boat_id,
        'drop_off_rental_id' => $dropoff_rental_id, // Drop-off rental ID (may be null if not found)
        'boats_available_in_hourly_boat_rental' => $boat_quantity - $booked_count,
        'boats_available_in_hourly_boat_rental' => $boat_quantity,
        'boats_available_in_drop_off_rental' => $dropoff_boats_available,
        'slots' => $time_slots,
        'booked_count' => $booked_count
    ]);
}
add_action('wp_ajax_brf_plugin_fetch_time_slots', 'brf_plugin_fetch_time_slots');
add_action('wp_ajax_nopriv_brf_plugin_fetch_time_slots', 'brf_plugin_fetch_time_slots');



// 




