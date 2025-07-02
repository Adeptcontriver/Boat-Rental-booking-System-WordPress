<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

// BEGIN ENQUEUE PARENT ACTION
// AUTO GENERATED - Do not modify or remove comment markers above or below:

if (!function_exists('chld_thm_cfg_locale_css')):
    function chld_thm_cfg_locale_css($uri) {
        if (empty($uri) && is_rtl() && file_exists(get_template_directory() . '/rtl.css'))
            $uri = get_template_directory_uri() . '/rtl.css';
        return $uri;
    }
endif;
add_filter('locale_stylesheet_uri', 'chld_thm_cfg_locale_css');

if (!function_exists('child_theme_configurator_css')):
    function child_theme_configurator_css() {
        wp_enqueue_style('chld_thm_cfg_child', trailingslashit(get_stylesheet_directory_uri()) . 'style.css', array('hello-elementor', 'hello-elementor-theme-style', 'hello-elementor-header-footer'));
    }
endif;
add_action('wp_enqueue_scripts', 'child_theme_configurator_css', 10);

// END ENQUEUE PARENT ACTION

// Custom calendar script
function enqueue_fullcalendar_assets() {
    wp_enqueue_style('fullcalendar-css', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css');
    wp_enqueue_script('fullcalendar-js', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.js', array(), '6.1.8', true);
}
add_action('wp_enqueue_scripts', 'enqueue_fullcalendar_assets');

function enqueue_booking_calendar_script() {
    wp_enqueue_script('booking-calendar', get_stylesheet_directory_uri() . '/js/booking-calendar.js', array('jquery', 'fullcalendar-js'), '1.0', true);
    wp_localize_script('booking-calendar', 'boatRentalAjax', array(
        'ajax_url' => admin_url('admin-ajax.php')
    ));
}
add_action('wp_enqueue_scripts', 'enqueue_booking_calendar_script');

function fetch_time_slots() {
    $selected_date = sanitize_text_field($_POST['date']);
    $boat_id = intval($_POST['boat_id']);
    $time_slots = array();

    error_log("Fetching time slots for date: $selected_date and boat ID: $boat_id");

    if (have_rows('boat_time_slots', $boat_id)) {
        while (have_rows('boat_time_slots', $boat_id)) {
            the_row();
            $time_slot = get_sub_field('time_slot');

            error_log("Checking time slot: $time_slot");

            $booking_query = new WP_Query(array(
                'post_type' => 'booking',
                'meta_query' => array(
                    array('key' => 'boat_id', 'value' => $boat_id, 'compare' => '='),
                    array('key' => 'time_duration', 'value' => get_the_title($boat_id), 'compare' => '='),
                    array('key' => 'booking_date', 'value' => $selected_date, 'compare' => '='),
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

    error_log("Time slots fetched: " . print_r($time_slots, true));

    wp_send_json_success(array('slots' => $time_slots));
}
add_action('wp_ajax_fetch_time_slots', 'fetch_time_slots');
add_action('wp_ajax_nopriv_fetch_time_slots', 'fetch_time_slots');

add_action('gform_after_submission_1', 'save_booking_after_submission', 10, 2);
function save_booking_after_submission($entry, $form) {

    error_log('Insert 1  interlinked_boat: ' .  $interlinked_boat);
    $user_name = rgar($entry, '60');
    $user_email = rgar($entry, '16');
    $boat_id = rgar($entry, '57');
    $time_duration = get_the_title($boat_id);
    $booking_date = rgar($entry, '50');
    $interlinked_boat = rgar($entry, '103');
    $time_slot = rgar($entry, '54');

    if (empty($boat_id) || empty($booking_date) || empty($time_slot)) {
        return;
    }

    $existing_booking = new WP_Query(array(
        'post_type' => 'booking',
        'meta_query' => array(
            array('key' => 'boat_id', 'value' => $boat_id, 'compare' => '='),
            array('key' => 'time_duration', 'value' => $time_duration, 'compare' => '='),
            array('key' => 'client_name', 'value' => $user_name, 'compare' => '='),
            array('key' => 'booking_email', 'value' => $user_email, 'compare' => '='),
            array('key' => 'booking_date', 'value' => $booking_date, 'compare' => '='),
            array('key' => 'time_slot', 'value' => $time_slot, 'compare' => '='),
            array('key' => 'interlinked_boat', 'value' => $interlinked_boat, 'compare' => '='),
            // We check for confirmed bookings only
            array('key' => 'booking_status', 'value' => 'confirmed', 'compare' => '='), 
        ),
    ));
    
    if ($existing_booking->have_posts()) {
        return;
    }

    $booking_post = array(
        'post_title'   => "Booking: {$user_name} - Boat {$boat_id} on {$booking_date} at {$time_slot}",
        'post_type'    => 'booking',
        'post_status'  => 'publish',
    );
    $booking_id = wp_insert_post($booking_post);
    
    if (!$booking_id) {
        return;
    }
    
    // Save the booking fields
    update_field('boat_id', $boat_id, $booking_id);
    update_field('time_duration', $time_duration, $booking_id);
    update_field('client_name',  $user_name, $booking_id);
    update_field('booking_email', $user_email, $booking_id);
    update_field('booking_date', $booking_date, $booking_id);
    update_field('time_slot', $time_slot, $booking_id);
    update_field('interlinked_boat', $interlinked_boat, $booking_id);
    // Set initial status to pending for admin approval
    update_field('booking_status', 'confirmed', $booking_id);

    // 2sri wali to update kr dy
    // Get interlinked boat from hourly boat rental
    $interlinked_boat = get_field('interlinked_boat', $boat_id);
    $interlinked_boat_id = is_object($interlinked_boat) ? $interlinked_boat->ID : $interlinked_boat;


    error_log('Insert 1 interlinked_boat: ' .  $interlinked_boat);
    


    $is_interlinked = !empty($interlinked_boat_id);
    error_log('Insert 1 interlinked_boat_id: ' .  $interlinked_boat_id);
    // Fetch Drop-Off Rental post with the same interlinked_boat_id
    $dropoff_rental = get_posts([
        'post_type' => 'drop-off-rental',
        'posts_per_page' => 1,
        'meta_query' => [
            [
                'key' => 'interlinked_boat',
                'value' => $interlinked_boat,
                'compare' => '='
            ]
        ]
    ]);
    $dropoff_rental_id = !empty($dropoff_rental) ? $dropoff_rental[0]->ID : null;
    $dropoff_boats_available = 0;

    // error_log($dropoff_rental_id . "Error 7");
    error_log('Insert 1 dropoff_rental_id: ' .  $dropoff_rental_id);

    // Get drop-off rental time slots
    if ($dropoff_rental_id && have_rows('boat_time_slots', $dropoff_rental_id)) {

        // error_log($dropoff_rental_id . "Error 8");

        while (have_rows('boat_time_slots', $dropoff_rental_id)) {

            
            the_row();

            
            $dropoff_slot_time = get_sub_field('time_slot');
            $dropoff_boats_available = intval(get_field('boats_available'));

            // error_log($time_slot . " -  Time slot value");
            // error_log($dropoff_slot_time . " - Dropoff time slot value");

            error_log('Insert 1 time_slot: ' .  $time_slot);
            error_log('Insert 1 dropoff_slot_time: ' .  $dropoff_slot_time);
            error_log('Insert 1 dropoff_boats_available: ' .  $dropoff_boats_available);

            if($time_slot === $dropoff_slot_time) {
                $new_quantity_boat = intval($dropoff_boats_available) - 1; // Reduce the quantity by 1 (for 1 booking)
                error_log('Insert 1 new_quantity_boat: ' .  $new_quantity_boat);
                // update_sub_field('boats_available', $new_quantity_boat, $dropoff_rental_id); // Update the quantity in the time slot
                break;
            }


            // error_log($dropoff_boats_available . "Error 9");// Get the available boats for this time slot
        }
        wp_reset_postdata();
    }

    


    error_log($boat_id . ' Booking Field Boat rental error 0 ');
     // Reduce boat quantity for the selected time slot
     if (have_rows('boat_time_slots', $boat_id)) {
        //   error_log($boat_id . ' Booking Field Boat rental error 1 ');
          error_log('Insert 1 boat_id: ' .  $boat_id);
        while (have_rows('boat_time_slots', $boat_id)) {
            the_row();
            $existing_time_slot = get_sub_field('time_slot');
            $boat_quantity = get_field('boats_available');


            // error_log($boat_quantity . ' Booking Field Boat rental error 3 ');
            error_log('Insert 1 quantity_boat: ' .  $boat_quantity);

            // Check if this is the correct time slot
            if ($existing_time_slot === $time_slot) {

                error_log($time_slot . ' Booking Field Boat rental error 4 ');
                error_log('Insert 1 time_slot: ' .  $time_slot);

                $new_quantity = intval($boat_quantity) - 1; // Reduce the quantity by 1 (for 1 booking)
                // update_sub_field('boats_available', $new_quantity, $boat_id); // Update the quantity in the time slot
                break;
            }
        }
    }


}

add_filter('gform_confirmation_1', 'custom_confirmation_alert', 10, 4);

// Populate the Hourly Rental Price field
add_filter('gform_field_value_hourly_rental_price', 'populate_hourly_rental_price', 10, 3);
function populate_hourly_rental_price($value, $field, $name) {
    // Get the boat_id from the URL parameter
    $boat_id = isset($_GET['boat_id']) ? intval($_GET['boat_id']) : 0;

    // Debug: Log the boat_id
    error_log("Boat ID from URL: $boat_id");

    // Check if the boat_id is valid
    if ($boat_id > 0 && get_post_type($boat_id) === 'boat-rental') {
        // Get the value of the ACF field 'hourly_rental_price'
        $hourly_rental_price = get_field('hourly_rental_price', $boat_id);

        // Debug: Log the ACF field value
        error_log("Hourly Rental Price for Boat ID $boat_id: $hourly_rental_price");

        // Format the price for Gravity Forms Product Field
        if ($hourly_rental_price) {
            return number_format((float)$hourly_rental_price, 2, '.', '');
        }
    }

    // Debug: Log if the boat_id is invalid or the post type is not 'boat-rental'
    error_log("Invalid Boat ID or post type is not 'boat-rental'. Returning empty value.");

    // Return an empty string if the boat_id is invalid or the post type is not 'boat-rental'
    return '';
}

// Populate the Boat Name field
add_filter('gform_field_value_boat_name', 'populate_boat_name', 10, 3);
function populate_boat_name($value, $field, $name) {
    // Get the boat_id from the URL parameter
    $boat_id = isset($_GET['boat_id']) ? intval($_GET['boat_id']) : 0;

    // Debug: Log the boat_id
    error_log("Boat ID from URL: $boat_id");

    // Check if the boat_id is valid
    if ($boat_id > 0 && get_post_type($boat_id) === 'boat-rental') {
        // Get the value of the ACF field 'boat_name'
        $boat_name = get_field('boat_name', $boat_id);

        // Debug: Log the ACF field value
        error_log("Boat Name for Boat ID $boat_id: $boat_name");

        // Return the boat name
        return $boat_name;
    }

    // Debug: Log if the boat_id is invalid or the post type is not 'boat-rental'
    error_log("Invalid Boat ID or post type is not 'boat-rental'. Returning empty value.");

    // Return an empty string if the boat_id is invalid or the post type is not 'boat-rental'
    return '';
}

function custom_confirmation_alert($confirmation, $form, $entry, $is_ajax) {
    if (is_string($confirmation)) {
        $script = "<script>alert('Thank you! Your booking has been processed.');</script>";
        $confirmation .= $script;
    }
    return $confirmation;
}
function booking_calendar_shortcode() {
    $boat_id = get_the_ID();
    $boat_price = get_field('hourly_rental_price', $boat_id);
    $boat_hours = get_field('booking_hours' , $boat_id);
    
    ob_start();
    ?>
    <div class="brf-post-item">
        <h3><?php the_title(); ?></h3>
        <h3>Rental Price: $<?php echo esc_html($boat_price); ?></h3>
        <input type="hidden" id="booking-hours" value=" <?php echo esc_html($boat_hours); ?>">
    </div>
    <div id="booking-calendar" style="width: 60%; float: left; min-height: 400px;"></div>
    <div id="time-slots-section" style="width: 35%; float: right; margin-left: 5%;">
        <h3 id="selected-date-title">Selected Date:</h3>
        <div id="dynamic-time-slots-container">
            <p>Please select a date to see available time slots.</p>
            <ul id="time-slot-list"></ul>
        </div>
        <button id="book-now-btn" disabled>Book Now</button>
        <button id="check-availability-btn">Check Next Availability</button>
        <input type="hidden" id="boat-id" value="<?php echo esc_attr($boat_id); ?>">
        <input type="hidden" id="selected-time" value="">
    </div>
    <div style="clear: both;"></div>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            document.getElementById("time-slot-list").innerHTML = ""; // Clear time slots on load
        });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('booking_calendar', 'booking_calendar_shortcode');

function brf_enqueue_scripts() {
    wp_enqueue_script('jquery');
    wp_localize_script('jquery', 'boatRentalAjax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
    ));
}
add_action('wp_enqueue_scripts', 'brf_enqueue_scripts');

function fetch_filtered_posts() {
    $tag = sanitize_text_field($_POST['tag']);

    $args = array(
        'post_type' => 'boat-rental',
        'posts_per_page' => -1,
        'tax_query' => array(
            array(
                'taxonomy' => 'post_tag',
                'field' => 'slug',
                'terms' => $tag,
            ),
        ),
    );

    $query = new WP_Query($args);

    if ($query->have_posts()) {
        ob_start();
        while ($query->have_posts()) {
            $query->the_post();
         
            
            ?>
            <div class="post-item">
                <h2><?php the_title(); ?></h2>
                <div><?php the_content(); ?></div>
              
                <?php echo do_shortcode('[booking_calendar]'); ?>
            </div>
            <?php
        }
        wp_reset_postdata();
        $output = ob_get_clean();
        wp_send_json_success($output);
    } else {
        ob_start();
        ?>
        <div class="post-item">
            <p>No posts found for the selected tag.</p>
        </div>
        <?php
        $output = ob_get_clean();
        wp_send_json_success($output);
    }
}
add_action('wp_ajax_fetch_filtered_posts', 'fetch_filtered_posts');
add_action('wp_ajax_nopriv_fetch_filtered_posts', 'fetch_filtered_posts');

function brf_filter_shortcode() {
    $page_id = get_the_ID();
    $with_captain_tag = get_field('with_captain', $page_id);
    $without_captain_tag = get_field('without_captain', $page_id);

    ob_start();
    ?>
    <div id="filter-buttons">
        <button id="with-captain-btn" class="active" data-tag="<?php echo esc_attr($with_captain_tag); ?>">With Captain</button>
        <button id="without-captain-btn" data-tag="<?php echo esc_attr($without_captain_tag); ?>">Without Captain</button>
    </div>
    <div id="post-results">
        <!-- Related posts will be displayed here -->
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('boat_rental_filter', 'brf_filter_shortcode');



// new filter



function booking_calendar_filter_shortcode() {
    static $calendar_count = 0;
    $calendar_count++;
    ob_start();
    ?>
    <div id="booking-calendar-filter-<?php echo $calendar_count; ?>" class="booking-calendar-filter" style="width: 60%; float: left; min-height: 400px;"></div>
    <div id="time-slots-section-filter-<?php echo $calendar_count; ?>" style="width: 35%; float: right; margin-left: 5%;">
        <h3 id="selected-date-title-filter-<?php echo $calendar_count; ?>">Selected Date:</h3>
        <div id="dynamic-time-slots-container-filter-<?php echo $calendar_count; ?>">
            <p>Please select a date to see available time slots.</p>
        </div>
        <button id="book-now-btn-filter-<?php echo $calendar_count; ?>" disabled>Book Now</button>
        <button id="check-availability-btn-filter-<?php echo $calendar_count; ?>">Check Next Availability</button>
        <input type="hidden" id="boat-id-filter-<?php echo $calendar_count; ?>" value="<?php echo get_the_ID(); ?>">
        <input type="hidden" id="selected-time-filter-<?php echo $calendar_count; ?>" value="">
    </div>
    <div style="clear: both;"></div>
    <?php
    return ob_get_clean();
}
add_shortcode('booking_calendar_filter', 'booking_calendar_filter_shortcode');




// admin Aprroval Backend


/**
 * Add a Booking Approval meta box to the booking edit screen.
 */
// Add custom columns
function add_booking_columns($columns) {
    $columns['booking_status'] = 'Status';
    $columns['booking_actions'] = 'Actions';
    return $columns;
}
add_filter('manage_booking_posts_columns', 'add_booking_columns');
// Populate the columns with status and action buttons
function render_booking_columns($column, $post_id) {
    if ($column === 'booking_status') {
        $status = get_field('booking_status', $post_id);
        echo ucfirst($status); // Show status
    }
    
    if ($column === 'booking_actions') {
        $approve_url = admin_url("admin-post.php?action=approve_booking&booking_id={$post_id}&_wpnonce=" . wp_create_nonce('approve_booking_' . $post_id));
        $cancel_url = admin_url("admin-post.php?action=cancel_booking&booking_id={$post_id}&_wpnonce=" . wp_create_nonce('cancel_booking_' . $post_id));

        //echo '<a href="' . esc_url($approve_url) . '" class="button button-primary" style="margin-right:5px;">Approve</a>';
        echo '<a href="' . esc_url($cancel_url) . '" class="button" style="background-color:#dc3232;color:#fff;">Cancel</a>';
    }
}
add_action('manage_booking_posts_custom_column', 'render_booking_columns', 10, 2);

// Handle Approve Action
function handle_approve_booking() {
    if ( ! isset($_GET['_wpnonce']) || ! wp_verify_nonce($_GET['_wpnonce'], 'approve_booking_' . $_GET['booking_id']) ) {
        wp_die('Security check failed.');
    }

    $booking_id = intval($_GET['booking_id']);
    
    // Update booking status to confirmed.
    update_field('booking_status', 'confirmed', $booking_id);

    // Redirect back to the bookings list (or wherever you want).
    wp_redirect(admin_url('edit.php?post_type=booking'));
    exit;
}
add_action('admin_post_approve_booking', 'handle_approve_booking');

// Handle Cancel Action
function handle_cancel_booking() {
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'cancel_booking_' . $_GET['booking_id'])) {
        wp_die('Security check failed.');
    }

    $booking_id = intval($_GET['booking_id']);
    update_field('booking_status', 'rejected', $booking_id);

    wp_redirect(admin_url('edit.php?post_type=booking')); // Redirect to bookings list
    exit;
}
add_action('admin_post_cancel_booking', 'handle_cancel_booking');




// Admin Approval From Frontent
/**
 * Comprehensive Bookings Management with Search Bar
 *
 * Shortcodes:
 *   1) [todays_pending_bookings]
 *   2) [todays_confirmed_bookings]
 *   3) [future_pending_bookings]
 *   4) [future_confirmed_bookings]
 *   5) [all_pending_bookings]
 *   6) [all_confirmed_bookings]
 *   7) [all_rejected_bookings]
 *
 * Action Handlers:
 *   - Approve: pending -> confirmed
 *   - Reject: pending -> rejected
 *   - Cancel: confirmed -> rejected
 *
 * ACF Fields:
 *   - booking_status (pending, confirmed, rejected)
 *   - booking_date (YYYY-MM-DD)
 *   - client_name
 *   - time_slot
 *   - time_duration (Booking Service)
 *
 * Post Type: booking
 */

/* -------------------------------------------------------------------------
   1. Utility: Query Bookings by Status & Date Category
------------------------------------------------------------------------- */
function get_bookings_by_status_and_date( $status, $date_compare = '' ) {
    $today = current_time('Y-m-d');

    // Build meta query
    $meta_query = array(
        array(
            'key'     => 'booking_status',
            'value'   => $status,
            'compare' => '=',
        ),
    );

    if ( 'today' === $date_compare ) {
        $meta_query[] = array(
            'key'     => 'booking_date',
            'value'   => $today,
            'compare' => '=',
            'type'    => 'DATE',
        );
    } elseif ( 'future' === $date_compare ) {
        $meta_query[] = array(
            'key'     => 'booking_date',
            'value'   => $today,
            'compare' => '>',
            'type'    => 'DATE',
        );
    }
    // If $date_compare is empty, then no date filter is applied.

    $args = array(
        'post_type'      => 'booking',
        'posts_per_page' => -1,
        'meta_query'     => $meta_query,
        'orderby'        => 'meta_value',
        'meta_key'       => 'booking_date',
        'order'          => 'ASC',
    );

    return new WP_Query( $args );
}

/* -------------------------------------------------------------------------
   2. Utility: Render a Booking Table with Search Bar
------------------------------------------------------------------------- */
function render_booking_table( $query, $table_title, $button_type = '' ) {
    /**
     * $button_type:
     *   'pending'   -> shows Approve + Reject buttons
     *   'confirmed' -> shows Cancel button
     *   ''          -> no buttons
     */
    ob_start();
    ?>
    <div class="booking-table-container" style="margin-bottom:30px;">
      <h3><?php echo esc_html( $table_title ); ?></h3>
      <!-- Search Bar -->
      <input type="text" class="booking-search-input" placeholder="Search <?php echo esc_attr($table_title); ?>..." style="margin-bottom:10px; width:100%; padding:5px;"/>
      <table class="booking-table" style="width:100%; border-collapse:collapse;">
        <thead>
          <tr>
            <th style="border:1px solid #ddd; padding:8px;">Booking ID</th>
            <th style="border:1px solid #ddd; padding:8px;">Booking Service</th>
            <th style="border:1px solid #ddd; padding:8px;">Client Name</th>
            <th style="border:1px solid #ddd; padding:8px;">Booking Date</th>
            <th style="border:1px solid #ddd; padding:8px;">Time Slot</th>
            <th style="border:1px solid #ddd; padding:8px;">Status</th>
            <?php if ( $button_type ) : ?>
              <th style="border:1px solid #ddd; padding:8px; margin:10px 0px;">Actions</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php
          if ( $query->have_posts() ) :
              while ( $query->have_posts() ) : $query->the_post();
                  $booking_id      = get_the_ID();
                  $booking_service = get_field( 'time_duration', $booking_id );
                  $client_name     = get_field( 'client_name', $booking_id );
                  $booking_date    = get_field( 'booking_date', $booking_id );
                  $time_slot       = get_field( 'time_slot', $booking_id );
                  $status          = get_field( 'booking_status', $booking_id );
                 
                  ?>
                  <tr>
                    <td style="border:1px solid #ddd; padding:8px;"><?php echo esc_html( $booking_id ); ?></td>
                    <td style="border:1px solid #ddd; padding:8px;"><?php echo esc_html( $booking_service ); ?></td>
                    <td style="border:1px solid #ddd; padding:8px;"><?php echo esc_html( $client_name ); ?></td>
                    <td style="border:1px solid #ddd; padding:8px;"><?php echo esc_html( $booking_date ); ?></td>
                    <td style="border:1px solid #ddd; padding:8px;"><?php echo esc_html( $time_slot ); ?></td>
                    <td style="border:1px solid #ddd; padding:8px;"><?php echo ucfirst( esc_html( $status ) ); ?></td>
                    <?php if ( $button_type ) : ?>
  <td style="border:1px solid #ddd; padding:8px;">
    <?php if ( 'pending' === $button_type ) : ?>
      <?php
        $approve_url = admin_url( 'admin-post.php?action=approve_booking_frontend&booking_id=' . $booking_id . '&_wpnonce=' . wp_create_nonce( 'approve_booking_frontend_' . $booking_id ) );
        $reject_url  = admin_url( 'admin-post.php?action=reject_booking_frontend&booking_id=' . $booking_id . '&_wpnonce=' . wp_create_nonce( 'reject_booking_frontend_' . $booking_id ) );
      ?>
      <a href="<?php echo esc_url( $approve_url ); ?>" class="button button-primary" style="margin-right:5px;">Approve</a>
      <a href="<?php echo esc_url( $reject_url ); ?>" class="button" style="background-color:#dc3232; color:#fff;">Reject</a>
      
    <?php elseif ( 'confirmed' === $button_type ) : ?>
      <?php
        $cancel_url = admin_url( 'admin-post.php?action=cancel_booking_frontend&booking_id=' . $booking_id . '&_wpnonce=' . wp_create_nonce( 'cancel_booking_frontend_' . $booking_id ) );
      ?>
      <a href="<?php echo esc_url( $cancel_url ); ?>" class="button" style="background-color:#dc3232; color:#fff;">Cancel</a>


    <?php endif; ?>
  </td>
<?php endif; ?>
                  </tr>
              <?php endwhile;
          else :
              echo '<tr><td colspan="8" style="padding:8px;">No bookings found.</td></tr>';
          endif;
          wp_reset_postdata();
          ?>
        </tbody>
      </table>
    </div>
    <?php
    return ob_get_clean();
}

/* -------------------------------------------------------------------------
   3. Shortcodes
------------------------------------------------------------------------- */
/* 1) Today's Pending Bookings */
function shortcode_todays_pending_bookings() {
    if ( ! current_user_can('manage_options') ) {
        return '<p>Access denied.</p>';
    }
    $query = get_bookings_by_status_and_date( 'pending', 'today' );
    return render_booking_table( $query, "Today's Pending Bookings", 'pending' );
}
add_shortcode( 'todays_pending_bookings', 'shortcode_todays_pending_bookings' );

/* 2) Today's Confirmed Bookings */
function shortcode_todays_confirmed_bookings() {
    if ( ! current_user_can('manage_options') ) {
        return '<p>Access denied.</p>';
    }
    $query = get_bookings_by_status_and_date( 'confirmed', 'today' );
    return render_booking_table( $query, "Today's Confirmed Bookings", 'confirmed' );
}
add_shortcode( 'todays_confirmed_bookings', 'shortcode_todays_confirmed_bookings' );

/* 3) Future Pending Bookings */
function shortcode_future_pending_bookings() {
    if ( ! current_user_can('manage_options') ) {
        return '<p>Access denied.</p>';
    }
    $query = get_bookings_by_status_and_date( 'pending', 'future' );
    return render_booking_table( $query, "Future Pending Bookings", 'pending' );
}
add_shortcode( 'future_pending_bookings', 'shortcode_future_pending_bookings' );

/* 4) Future Confirmed Bookings */
function shortcode_future_confirmed_bookings() {
    if ( ! current_user_can('manage_options') ) {
        return '<p>Access denied.</p>';
    }
    $query = get_bookings_by_status_and_date( 'confirmed', 'future' );
    return render_booking_table( $query, "Future Confirmed Bookings", 'confirmed' );
}
add_shortcode( 'future_confirmed_bookings', 'shortcode_future_confirmed_bookings' );

/* 5) All Pending Bookings */
function shortcode_all_pending_bookings() {
    if ( ! current_user_can('manage_options') ) {
        return '<p>Access denied.</p>';
    }
    $query = get_bookings_by_status_and_date( 'pending' );
    return render_booking_table( $query, "All Pending Bookings", 'pending' );
}
add_shortcode( 'all_pending_bookings', 'shortcode_all_pending_bookings' );

/* 6) All Confirmed Bookings */
function shortcode_all_confirmed_bookings() {
    if ( ! current_user_can('manage_options') ) {
        return '<p>Access denied.</p>';
    }
    $query = get_bookings_by_status_and_date( 'confirmed' );
    return render_booking_table( $query, "All Confirmed Bookings", 'confirmed' );
}
add_shortcode( 'all_confirmed_bookings', 'shortcode_all_confirmed_bookings' );

/* 7) All Rejected Bookings */
function shortcode_all_rejected_bookings() {
    if ( ! current_user_can('manage_options') ) {
        return '<p>Access denied.</p>';
    }
    $query = get_bookings_by_status_and_date( 'rejected' );
    return render_booking_table( $query, "All Rejected Bookings" );
}
add_shortcode( 'all_rejected_bookings', 'shortcode_all_rejected_bookings' );

/* -------------------------------------------------------------------------
   4. Action Handlers
------------------------------------------------------------------------- */
/* Approve Booking: pending -> confirmed */
function handle_approve_booking_frontend() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die('Unauthorized access.'); }
    if ( ! isset($_GET['_wpnonce'], $_GET['booking_id']) ) { wp_die('Missing parameters.'); }
    $booking_id = intval( $_GET['booking_id'] );
    if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'approve_booking_frontend_' . $booking_id ) ) { wp_die('Security check failed.'); }

    update_field( 'booking_status', 'confirmed', $booking_id );
    wp_redirect( add_query_arg( 'updated', time(), wp_get_referer() ) );
    exit;
}
add_action( 'admin_post_approve_booking_frontend', 'handle_approve_booking_frontend' );

/* Reject Booking: pending -> rejected */
function handle_reject_booking_frontend() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die('Unauthorized access.'); }
    if ( ! isset($_GET['_wpnonce'], $_GET['booking_id']) ) { wp_die('Missing parameters.'); }
    $booking_id = intval( $_GET['booking_id'] );
    if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'reject_booking_frontend_' . $booking_id ) ) { wp_die('Security check failed.'); }

    update_field( 'booking_status', 'rejected', $booking_id );
    wp_redirect( add_query_arg( 'updated', time(), wp_get_referer() ) );
    exit;
}
add_action( 'admin_post_reject_booking_frontend', 'handle_reject_booking_frontend' );

/* Cancel Booking: confirmed -> rejected */
function handle_cancel_booking_frontend() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die('Unauthorized access.'); }
    if ( ! isset($_GET['_wpnonce'], $_GET['booking_id']) ) { wp_die('Missing parameters.'); }
    $booking_id = intval( $_GET['booking_id'] );
    if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'cancel_booking_frontend_' . $booking_id ) ) { wp_die('Security check failed.'); }

    update_field( 'booking_status', 'rejected', $booking_id );
    wp_redirect( add_query_arg( 'updated', time(), wp_get_referer() ) );
    exit;
}
add_action( 'admin_post_cancel_booking_frontend', 'handle_cancel_booking_frontend' );

/* -------------------------------------------------------------------------
   5. (Optional) Enqueue Basic JavaScript for Live Search
   This script filters table rows in real time.
------------------------------------------------------------------------- */
function enqueue_booking_search_script() {
    if ( current_user_can('manage_options') ) {
        ?>
        <script type="text/javascript">
        document.addEventListener("DOMContentLoaded", function(){
          var searchInputs = document.querySelectorAll('.booking-search-input');
          searchInputs.forEach(function(input) {
            input.addEventListener('keyup', function(){
              var filter = input.value.toLowerCase();
              var table = input.parentElement.querySelector('.booking-table');
              var rows = table.querySelectorAll('tbody tr');
              rows.forEach(function(row){
                row.style.display = ( row.textContent.toLowerCase().indexOf(filter) > -1 ) ? '' : 'none';
              });
            });
          });
        });
        </script>
        <?php
    }
}
add_action('wp_footer', 'enqueue_booking_search_script');




// Drop Off Rental:
function drop_off_rental_fetch_time_slots() {
    $selectedDate = sanitize_text_field($_POST['date']);
    $boat_id = intval($_POST['boat_id']);
    $quantity_requested = intval($_POST['quantity']) ?: 1; // Default to 1 if not provided
    $time_slots = [];

    // Get interlinked boat from drop-off rental
    $interlinked_boat = get_field('interlinked_boat', $boat_id);
    $interlinked_boat_id = is_object($interlinked_boat) ? $interlinked_boat->ID : $interlinked_boat;
    $is_interlinked = !empty($interlinked_boat_id);

    // Fetch Hourly Rental post with the same interlinked_boat_id
    $hourly_rental = get_posts([
        'post_type' => 'boat-rental', // Adjust if this should be a different post type
        'posts_per_page' => 1,
        'meta_query' => [
            [
                'key' => 'interlinked_boat',
                'value' => $interlinked_boat_id,
                'compare' => '='
            ]
        ]
    ]);
    $hourly_rental_id = !empty($hourly_rental) ? $hourly_rental[0]->ID : null;

    // Get boats available and booking hours from ACF fields
    $boats_available = intval(get_field('boats_available', $boat_id)) ?: 3; // New field, fallback to 3
    $boat_hours = intval(get_field('booking_hours', $boat_id)) ?: 2; // Default to 2 hours

    // Collect all bookings to determine booked time ranges
    $booked_time_ranges = [];

    // Query drop-off bookings
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
        $start_time = strtotime($time_slot);
        $end_time = strtotime("+$boat_hours hours", $start_time);
        $booked_time_ranges[] = [
            'start' => $time_slot,
            'start_time' => date('Y-m-d H:i:s', $start_time),
            'end_time' => date('Y-m-d H:i:s', $end_time),
            'type' => 'dropoff'
        ];
    }
    wp_reset_postdata();

    // Query hourly bookings if interlinked
    if ($hourly_rental_id) {
        $booking_query = new WP_Query([
            'post_type' => 'booking',
            'posts_per_page' => -1,
            'meta_query' => [
                ['key' => 'boat_id', 'value' => $hourly_rental_id, 'compare' => '='],
                ['key' => 'booking_date', 'value' => $selectedDate, 'compare' => '='],
                ['key' => 'booking_status', 'value' => 'confirmed', 'compare' => '='],
            ]
        ]);

        while ($booking_query->have_posts()) {
            $booking_query->the_post();
            $time_slot = get_post_meta(get_the_ID(), 'time_slot', true);
            $start_time = strtotime($time_slot);
            $end_time = strtotime("+$boat_hours hours", $start_time);
            $booked_time_ranges[] = [
                'start' => $time_slot,
                'start_time' => date('Y-m-d H:i:s', $start_time),
                'end_time' => date('Y-m-d H:i:s', $end_time),
                'type' => 'hourly'
            ];
        }
        wp_reset_postdata();
    }

    // Process time slots
    if (have_rows('boat_time_slots', $boat_id)) {
        while (have_rows('boat_time_slots', $boat_id)) {
            the_row();
            $time_slot = get_sub_field('time_slot');
            $booked_count1 = 0; // Drop-off bookings starting at this slot
            $booked_count2 = 0; // Hourly bookings starting at this slot

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
                        if ($range['type'] === 'dropoff' && $check_time === $slot_time) {
                            $booked_count1++;
                        } elseif ($range['type'] === 'hourly' && $check_time === $slot_time) {
                            $booked_count2++;
                        }
                    }
                }
                $max_booked = max($max_booked, $booked_in_slot);
            }

            // Calculate available boats
            $boats_available_count = $boats_available - $max_booked;
            $is_available = $boats_available_count >= $quantity_requested; // Check if enough boats for requested quantity

            $time_slots[] = [
                'time' => $time_slot,
                'available' => $is_available,
                'boat_quantity' => $boats_available,
                'boats_available' => $boats_available_count,
                'booking_hours' => $boat_hours,
                'booked_count_dropoff' => $booked_count1,
                'booked_count_hourly' => $booked_count2
            ];
        }
    }

    wp_send_json_success([
        'hourly_rental_id' => $hourly_rental_id,
        'drop_off_rental_id' => $boat_id,
        'interlinked_boat_id' => $interlinked_boat_id,
        'boats_available' => $boats_available,
        'slots' => $time_slots
    ]);
}
add_action('wp_ajax_drop_off_rental_fetch_time_slots', 'drop_off_rental_fetch_time_slots');
add_action('wp_ajax_nopriv_drop_off_rental_fetch_time_slots', 'drop_off_rental_fetch_time_slots');


function drop_off_validate_booking($boat_id, $date, $time_slot, $quantity = 1, $booking_type = 'dropoff') {
    $boat_hours = intval(get_field('booking_hours', $boat_id)) ?: 2;
    $boats_available = intval(get_field('boats_available', $boat_id)) ?: 3;
    $start_time = strtotime($time_slot);
    $end_time = strtotime("+$boat_hours hours", $start_time);

    // Get interlinked boat
    $interlinked_boat = get_field('interlinked_boat', $boat_id);
    $interlinked_boat_id = is_object($interlinked_boat) ? $interlinked_boat->ID : $interlinked_boat;

    // Collect booked ranges
    $booked_ranges = [];

    // Query drop-off bookings
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

    // Query hourly bookings
    $hourly_rental = get_posts([
        'post_type' => 'boat-rental',
        'posts_per_page' => 1,
        'meta_query' => [
            ['key' => 'interlinked_boat', 'value' => $interlinked_boat_id, 'compare' => '=']
        ]
    ]);
    $hourly_rental_id = !empty($hourly_rental) ? $hourly_rental[0]->ID : null;

    if ($hourly_rental_id) {
        $booking_query = new WP_Query([
            'post_type' => 'booking',
            'posts_per_page' => -1,
            'meta_query' => [
                ['key' => 'boat_id', 'value' => $hourly_rental_id, 'compare' => '='],
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

    return ($boats_available - $max_booked) >= $quantity;
}


// Payment Authorization i
// add_filter( 'gform_ppcp_authorization_only', '__return_true' );
// add_filter( 'gform_stripe_payment_element_authorization_only', '__return_true');
add_action('gform_after_submission_3', 'dropp_off_rental_save_booking_after_submission', 10, 2);
function dropp_off_rental_save_booking_after_submission($entry, $form) {
    $user_name = rgar($entry, '60');
    $user_email = rgar($entry, '16');
    $boat_id = rgar($entry, '57');
    $time_duration = get_the_title($boat_id);
    $booking_date_actual = rgar($entry, '50');
    $time_slot = rgar($entry, '54');
    $days = rgar($entry, '78');
    $interlinked_boat = rgar($entry, '101');
    $entry_id = $entry['id'];

    // Create a DateTime object using the correct format (Y-m-d)
    $date = DateTime::createFromFormat('Y-m-d', $booking_date_actual);

    if ($date) {
        // Add the specified days
        $date->modify('+' . (int)$days . ' days');

        // Format and output the modified date    
        $booking_end_date = $date->format('Y-m-d');  

        error_log('Booking Start Date: ' . $booking_date_actual);
        error_log('Days to Add: ' . $days);
        error_log('Booking End Date: ' . $booking_end_date);
    }

    if (empty($boat_id) || empty($booking_date_actual) || empty($time_slot)) {
        return;
    }

    // Check if this booking already exists
    $existing_booking = new WP_Query(array(
        'post_type' => 'drop-off-rental-book',
        'meta_query' => array(
            array('key' => 'boat_id', 'value' => $boat_id, 'compare' => '='),
            array('key' => 'time_duration', 'value' => $time_duration, 'compare' => '='),
            array('key' => 'client_name', 'value' => $user_name, 'compare' => '='),
            array('key' => 'booking_email', 'value' => $user_email, 'compare' => '='),
            array('key' => 'booking_date', 'value' => $booking_date_actual, 'compare' => '='),
            array('key' => 'time_slot', 'value' => $time_slot, 'compare' => '='),
            array('key' => 'interlinked_boat', 'value' => $interlinked_boat, 'compare' => '='),
            array('key' => 'booking_status', 'value' => 'confirmed', 'compare' => '='), 
        ),
    ));
    
    if ($existing_booking->have_posts()) {
        return;
    }

    // Create new booking post
    $booking_post = array(
        'post_title'   => "Booking: {$user_name} - Boat {$boat_id} on {$booking_date_actual} at {$time_slot}",
        'post_type'    => 'drop-off-rental-book',
        'post_status'  => 'publish',
    );
    $booking_id = wp_insert_post($booking_post);
    
    if (!$booking_id) {
        return;
    }

    // Save custom fields for the booking
    update_field('boat_id', $boat_id, $booking_id);
    update_field('boats_available', $boat_id, $booking_id);
    update_field('time_duration', $time_duration, $booking_id);
    update_field('client_name',  $user_name, $booking_id);
    update_field('booking_email', $user_email, $booking_id);
    update_field('booking_date', $booking_date_actual, $booking_id);
    update_field('booking_end_date', $booking_end_date, $booking_id);
    update_field('time_slot', $time_slot, $booking_id);
    update_field('interlinked_boat', $interlinked_boat, $booking_id);
    update_field('booking_status', 'confirmed', $booking_id);
    update_field('entry_id', $entry_id, $booking_id);


    // 2sri wali to update kr dy
    // Get interlinked boat from hourly boat rental
    $interlinked_boat = get_field('interlinked_boat', $boat_id);
    $interlinked_boat_id = is_object($interlinked_boat) ? $interlinked_boat->ID : $interlinked_boat;


    error_log('Insert 2 interlinked_boat: ' .  $interlinked_boat);
    error_log('Insert 2 interlinked_boat_id: ' .  $interlinked_boat_id);
    


    $is_interlinked = !empty($interlinked_boat_id);
    // error_log($interlinked_boat_id . "Error 6");
    // Fetch Drop-Off Rental post with the same interlinked_boat_id
    $dropoff_rental = get_posts([
        'post_type' => 'boat-rental',
        'posts_per_page' => 1,
        'meta_query' => [
            [
                'key' => 'interlinked_boat',
                'value' => $interlinked_boat,
                'compare' => '='
            ]
        ]
    ]);
    $dropoff_rental_id = !empty($dropoff_rental) ? $dropoff_rental[0]->ID : null;
    $dropoff_boats_available = 0;

    // error_log($dropoff_rental_id . "Error 7");
    error_log('Insert 2 dropoff_rental_id: ' .  $dropoff_rental_id);


    // Get drop-off rental time slots
    if ($dropoff_rental_id && have_rows('boat_time_slots', $dropoff_rental_id)) {

        // error_log($dropoff_rental_id . "Error 8");

        while (have_rows('boat_time_slots', $dropoff_rental_id)) {

            
            the_row();

            
            $dropoff_slot_time = get_sub_field('time_slot');
            $dropoff_boats_available = get_sub_field('boats_available');

            error_log('Insert 2 time_slot: ' .  $time_slot);
            error_log('Insert 2 dropoff_slot_time: ' .  $dropoff_slot_time);


            if($time_slot === $dropoff_slot_time) {
                $new_quantity_boat = $dropoff_boats_available - 1; // Reduce the quantity by 1 (for 1 booking)
                // error_log($new_quantity . 'New Quantity Drop Off');
                error_log('Insert 2 new_quantity: ' .  $new_quantity_boat);
                // update_sub_field('boats_available', $new_quantity_boat, $dropoff_rental_id); // Update the quantity in the time slot
                break;
            }


            // error_log($dropoff_boats_available . "Error 9");// Get the available boats for this time slot
        }
        wp_reset_postdata();
    }




    // Reduce boat quantity for the selected time slot
    if (have_rows('boat_time_slots', $boat_id)) {
        while (have_rows('boat_time_slots', $boat_id)) {
            the_row();
            $existing_time_slot = get_sub_field('time_slot');
            $boat_quantity = get_sub_field('boats_available');

            // Check if this is the correct time slot
            if ($existing_time_slot === $time_slot) {
                // error_log($existing_time_slot . 'Exisiting Time slot error 9');
                // error_log($time_slot . 'Time Slot error 10');

                error_log('Insert 2 time_slot: ' .  $time_slot);
                error_log('Insert 2 existing_time_slot: ' .  $existing_time_slot);
                $new_quantity = intval($boat_quantity) - 1; // Reduce the quantity by 1 (for 1 booking)
                // update_sub_field('boats_available', $new_quantity); // Update the quantity in the time slot
                break;
            }
        }
    }
}
add_filter('gform_confirmation_3', 'dropp_off_rental_custom_confirmation_alert', 10, 4);
function dropp_off_rental_custom_confirmation_alert($confirmation, $form, $entry, $is_ajax) {
    if (is_string($confirmation)) {
        $script = "<script>alert('Thank you! Your booking has been processed.');</script>";
        $confirmation .= $script;
    }
    return $confirmation;
}

add_filter('gform_field_value_drop_off_rental_price', 'dropp_off_rental_populate_hourly_rental_price', 10, 3);
function dropp_off_rental_populate_hourly_rental_price($value, $field, $name) {
    $boat_id = isset($_GET['boat_id']) ? intval($_GET['boat_id']) : 0;
    error_log("Boat ID from URL: $boat_id");

    if ($boat_id > 0 && get_post_type($boat_id) === 'drop-off-rental') {
        $hourly_rental_price = get_field('drop_off_rental_price', $boat_id);
        error_log("Hourly Rental Price for Boat ID $boat_id: $hourly_rental_price");

        if ($hourly_rental_price) {
            return number_format((float)$hourly_rental_price, 2, '.', '');
        }
    }

    error_log("Invalid Boat ID or post type is not 'drop-off-rental'. Returning empty value.");
    return '';
}

add_filter('gform_field_value_boat_name', 'dropp_off_rental_populate_boat_name', 10, 3);
function dropp_off_rental_populate_boat_name($value, $field, $name) {
    $boat_id = isset($_GET['boat_id']) ? intval($_GET['boat_id']) : 0;
    error_log("Boat ID from URL: $boat_id");

    if ($boat_id > 0 && get_post_type($boat_id) === 'drop-off-rental') {
        $boat_name = get_field('boat_name', $boat_id);
        error_log("Boat Name for Boat ID $boat_id: $boat_name");
        return $boat_name;
    }

    error_log("Invalid Boat ID or post type is not 'drop-off-rental'. Returning empty value.");
    return '';
}

function dropp_off_rental_booking_calendar_shortcode() {
    $boat_id = get_the_ID();
    $boat_price = get_field('drop_off_rental_price', $boat_id);
    
    ob_start();
    ?>
    <div class="brf-post-item">
        <h3><?php the_title(); ?></h3>
        <h3>Rental Price: $<?php echo esc_html($boat_price); ?></h3>
    </div>
    <div id="dor-booking-calendar" style="width: 60%; float: left; min-height: 400px;"></div>
<div id="dor-time-slots-section" style="width: 35%; float: right; margin-left: 5%;">
    <h3 id="dor-selected-date-title">Selected Date:</h3>
    <div id="dor-dynamic-time-slots-container">
        <p>Please select a date to see available time slots.</p>
        <ul id="dor-time-slot-list">
        </ul>          
    </div>
    <button id="dor-book-now-btn" disabled>Book Now</button>
    <button id="dor-check-availability-btn">Check Next Availability</button>
    <input type="hidden" id="dor-boat-id" value="<?php echo esc_attr($boat_id); ?>">
    <input type="hidden" id="dor-selected-time" value="">
</div>
    <div style="clear: both;"></div>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            document.getElementById("time-slot-list").innerHTML = ""; // Clear time slots on load
        });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('dropp_off_rental_booking_calendar', 'dropp_off_rental_booking_calendar_shortcode');

function dropp_off_rental_enqueue_scripts() {
    wp_enqueue_script('jquery');
    wp_localize_script('jquery', 'boatRentalAjax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
    ));
}
add_action('wp_enqueue_scripts', 'dropp_off_rental_enqueue_scripts');

function dropp_off_rental_fetch_filtered_posts() {
    $tag = sanitize_text_field($_POST['tag']);

    $args = array(
        'post_type' => 'drop-off-rental',
        'posts_per_page' => -1,
        'tax_query' => array(
            array(
                'taxonomy' => 'post_tag',
                'field' => 'slug',
                'terms' => $tag,
            ),
        ),
    );

    $query = new WP_Query($args);

    if ($query->have_posts()) {
        ob_start();
        while ($query->have_posts()) {
            $query->the_post();
            ?>
            <div class="post-item">
                <h2><?php the_title(); ?></h2>
                <div><?php the_content(); ?></div>
                <?php echo do_shortcode('[dropp_off_rental_booking_calendar]'); ?>
            </div>
            <?php
        }
        wp_reset_postdata();
        $output = ob_get_clean();
        wp_send_json_success($output);
    } else {
        ob_start();
        ?>
        <div class="post-item">
            <p>No posts found for the selected tag.</p>
        </div>
        <?php
        $output = ob_get_clean();
        wp_send_json_success($output);
    }
}
add_action('wp_ajax_dropp_off_rental_fetch_filtered_posts', 'dropp_off_rental_fetch_filtered_posts');
add_action('wp_ajax_nopriv_dropp_off_rental_fetch_filtered_posts', 'dropp_off_rental_fetch_filtered_posts');

add_action('wp_ajax_drop_off_rental_fetch_time_slots', 'fetch_drop_off_rental_time_slots');
add_action('wp_ajax_nopriv_drop_off_rental_fetch_time_slots', 'fetch_drop_off_rental_time_slots');

function fetch_drop_off_rental_time_slots() {
    $boat_id = intval($_POST['boat_id']);
    $date = sanitize_text_field($_POST['date']);

    $slots = array();
    $time_slots = get_field('boat_time_slots', $boat_id);

    if ($time_slots && is_array($time_slots)) {
        foreach ($time_slots as $slot) {
            $time = isset($slot['time']) ? $slot['time'] : '';
            $quantity = isset($slot['boats_available']) ? intval($slot['boats_available']) : 0;

            // Ensure we're not getting boolean values
            if ($quantity !== true && $quantity !== false) {
                $slots[] = array(
                    'time' => $time,
                    'available' => $quantity
                );
            }
        }
    }

    wp_send_json(array(
        'success' => true,
        'data' => array(
            'slots' => $slots
        )
    ));

    wp_die();
}

function dropp_off_rental_filter_shortcode() {
    $page_id = get_the_ID();
    $with_captain_tag = get_field('with_captain', $page_id);
    $without_captain_tag = get_field('without_captain', $page_id);

    ob_start();
    ?>
    <div id="filter-buttons">
        <button id="with-captain-btn" class="active" data-tag="<?php echo esc_attr($with_captain_tag); ?>">With Captain</button>
        <button id="without-captain-btn" data-tag="<?php echo esc_attr($without_captain_tag); ?>">Without Captain</button>
    </div>
    <div id="post-results">
        <!-- Related posts will be displayed here -->
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('dropp_off_rental_filter', 'dropp_off_rental_filter_shortcode');




// Drop Off Booking Approval From Backend

// Automatically update booking_status to "confirmed" when payment is completed
add_action('gform_post_payment_action', 'auto_confirm_booking_on_payment', 10, 2);
function auto_confirm_booking_on_payment($entry, $action) {
    if ($action['type'] === 'complete_payment') {
        $entry_id = $entry['id'];

        $args = array(
            'post_type'      => 'drop-off-rental-book',
            'posts_per_page' => 1,
            'meta_query'     => array(
                array(
                    'key'     => 'entry_id',
                    'value'   => strval($entry_id),
                    'compare' => '=',
                ),
            ),
        );

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            $booking_post_id = $query->posts[0]->ID;

            // Only update if not already confirmed
            $current_status = get_field('booking_status', $booking_post_id);
            if ($current_status !== 'confirmed') {
                update_field('booking_status', 'confirmed', $booking_post_id);
            }
        }
    }
}
// Debug helper for admin to verify entry and booking status
// add_action('admin_init', function () {
//     if (
//         isset($_GET['page']) &&
//         $_GET['page'] === 'gf_entries' &&
//         isset($_GET['lid']) // ✅ Entry ID
//     ) {
//         $gravity_entry_id = intval($_GET['lid']);
//         $entry = GFAPI::get_entry($gravity_entry_id);
//         $payment_status = rgar($entry, 'payment_status');

//         echo '<div style="padding-left:200px; padding-top:20px; font-family:monospace;">';
//         echo '<h2>Gravity Entry Debug Info</h2>';
//         echo '<p><strong>Gravity Forms Entry ID:</strong> ' . esc_html($gravity_entry_id) . '</p>';
//         echo '<p><strong>Payment Status:</strong> ' . esc_html($payment_status) . '</p>';

//         $args = array(
//             'post_type'      => 'drop-off-rental-book',
//             'posts_per_page' => 1,
//             'meta_query'     => array(
//                 array(
//                     'key'     => 'entry_id',
//                     'value'   => strval($gravity_entry_id),
//                     'compare' => '=',
//                 ),
//             ),
//         );

//         $query = new WP_Query($args);

//         if ($query->have_posts()) {
//             $booking_post_id = $query->posts[0]->ID;
//             $booking_status = get_field('booking_status', $booking_post_id);
//             $booking_entry_id = get_field('entry_id', $booking_post_id); // ✅ ACF field value

//             echo '<p><strong>Booking Post ID:</strong> ' . esc_html($booking_post_id) . '</p>';
//             echo '<p><strong>Booking ACF Entry ID (entry_id):</strong> ' . esc_html($booking_entry_id) . '</p>';
//             echo '<p><strong>Booking Status:</strong> ' . esc_html($booking_status) . '</p>';

//             if ($payment_status === 'Paid' && $booking_status !== 'confirmed') {
//                 update_field('booking_status', 'confirmed', $booking_post_id);
//                 echo '<p style="color:green;"><strong>✅ Booking status updated to: Confirmed</strong></p>';
//             }
//         } else {
//             echo '<p style="color:red;"><strong>❌ No booking found for Entry ID: ' . $gravity_entry_id . '</strong></p>';
//         }

//         echo '</div>';
//     }
// });


/**
 * Add custom columns for booking status and actions.
 */
function dropp_off_rental_add_booking_columns($columns) {
    $columns['booking_status'] = 'Status';
    $columns['booking_actions'] = 'Actions';
    return $columns;
}
add_filter('manage_drop-off-rental-book_posts_columns', 'dropp_off_rental_add_booking_columns');

function dropp_off_rental_render_booking_columns($column, $post_id) {
    if ($column === 'booking_status') {
        $status = get_field('booking_status', $post_id);
        echo ucfirst($status);
    }

    if ($column === 'booking_actions') {
        // $approve_url = admin_url("admin-post.php?action=dropp_off_rental_approve_booking&booking_id={$post_id}&_wpnonce=" . wp_create_nonce('dropp_off_rental_approve_booking_' . $post_id));
        $cancel_url = admin_url("admin-post.php?action=dropp_off_rental_cancel_booking&booking_id={$post_id}&_wpnonce=" . wp_create_nonce('dropp_off_rental_cancel_booking_' . $post_id));
        
        // 🔄 Get the entry_id from ACF
        $entry_id = get_field('entry_id', $post_id);
        $form_id = 3; // Set your form ID here
        $entry_url = $entry_id ? admin_url("admin.php?page=gf_entries&view=entry&id={$form_id}&lid={$entry_id}") : '';

       
        if ($entry_id) {
            echo '<a href="' . esc_url($entry_url) . '" class="button" style="background-color:#21759b; color:#fff;" target="_blank">View Entry</a>';
        }
         // 🎯 Output buttons
        //  echo '<a href="' . esc_url($approve_url) . '" class="button button-primary" style="margin-right:5px;">Approve</a>';
         echo '<a href="' . esc_url($cancel_url) . '" class="button" style="background-color:#dc3232;color:#fff;margin-right:5px;">Cancel</a>';
 
    }
}
add_action('manage_drop-off-rental-book_posts_custom_column', 'dropp_off_rental_render_booking_columns', 10, 2);
/**
 * Handle Approve Action.
 */
function dropp_off_rental_handle_approve_booking() {
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'dropp_off_rental_approve_booking_' . $_GET['booking_id'])) {
        wp_die('Security check failed.');
    }

    $booking_id = intval($_GET['booking_id']);
    
    // Update booking status to confirmed.
    update_field('booking_status', 'confirmed', $booking_id);

    // Redirect back to the bookings list.
    wp_redirect(admin_url('edit.php?post_type=drop-off-rental-book'));
    exit;
}
add_action('admin_post_dropp_off_rental_approve_booking', 'dropp_off_rental_handle_approve_booking');

/**
 * Handle Cancel Action.
 */
function dropp_off_rental_handle_cancel_booking() {
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'dropp_off_rental_cancel_booking_' . $_GET['booking_id'])) {
        wp_die('Security check failed.');
    }

    $booking_id = intval($_GET['booking_id']);
    update_field('booking_status', 'rejected', $booking_id);

    // Redirect back to the bookings list.
    wp_redirect(admin_url('edit.php?post_type=drop-off-rental-book'));
    exit;
}
add_action('admin_post_dropp_off_rental_cancel_booking', 'dropp_off_rental_handle_cancel_booking');


// Damage Drop Off rental





// Fishing Charter Booking System
// ACF : Meta Fields
// Custom Post Type : Fishing Charter & Fishing Charter Booking
// Booking System
function fishing_charter_fetch_time_slots() {
    $selectedDate = sanitize_text_field($_POST['date']);
    $boat_id = intval($_POST['boat_id']);
    $time_slots = array();

    error_log("Fetching time slots for date: $selectedDate and boat ID: $boat_id");

    if (have_rows('boat_time_slots', $boat_id)) {
        while (have_rows('boat_time_slots', $boat_id)) {
            the_row();
            $time_slot = get_sub_field('time_slot');

            error_log("Checking time slot: $time_slot");

            $booking_query = new WP_Query(array(
                'post_type' => 'fishing-charter-book', // Ensure this matches the correct post type
                'meta_query' => array(
                    array('key' => 'boat_id', 'value' => $boat_id, 'compare' => '='),
                    array('key' => 'booking_date', 'value' => $selectedDate, 'compare' => '='),
                    array('key' => 'time_slot', 'value' => $time_slot, 'compare' => '='),
                    array('key' => 'booking_status', 'value' => 'confirmed', 'compare' => '=')
                ),
            ));

            // Debug: Log the query results
            error_log("Query results for time slot $time_slot: " . print_r($booking_query->posts, true));

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

    error_log("Time slots fetched: " . print_r($time_slots, true));

    wp_send_json_success(array('slots' => $time_slots));
}
add_action('wp_ajax_fishing_charter_fetch_time_slots', 'fishing_charter_fetch_time_slots');
add_action('wp_ajax_nopriv_fishing_charter_fetch_time_slots', 'fishing_charter_fetch_time_slots');

add_action('gform_after_submission_4', 'fishing_charter_save_booking_after_submission', 10, 2);
function fishing_charter_save_booking_after_submission($entry, $form) {
    $user_name = rgar($entry, '60');
    $user_email = rgar($entry, '16');
    $boat_id = rgar($entry, '57');
    $time_duration = get_the_title($boat_id);
    $booking_date = rgar($entry, '50');
    $time_slot = rgar($entry, '54');

    if (empty($boat_id) || empty($booking_date) || empty($time_slot)) {
        return;
    }

    $existing_booking = new WP_Query(array(
        'post_type' => 'fishing-charter-book',
        'meta_query' => array(
            array('key' => 'boat_id', 'value' => $boat_id, 'compare' => '='),
            array('key' => 'time_duration', 'value' => $time_duration, 'compare' => '='),
            array('key' => 'client_name', 'value' => $user_name, 'compare' => '='),
            array('key' => 'booking_email', 'value' => $user_email, 'compare' => '='),
            array('key' => 'booking_date', 'value' => $booking_date, 'compare' => '='),
            array('key' => 'time_slot', 'value' => $time_slot, 'compare' => '='),
            array('key' => 'booking_status', 'value' => 'confirmed', 'compare' => '='), 
        ),
    ));
    
    if ($existing_booking->have_posts()) {
        return;
    }

    $booking_post = array(
        'post_title'   => "Booking: {$user_name} - Boat {$boat_id} on {$booking_date} at {$time_slot}",
        'post_type'    => 'fishing-charter-book',
        'post_status'  => 'publish',
    );
    $booking_id = wp_insert_post($booking_post);
    
    if (!$booking_id) {
        return;
    }
    
    update_field('boat_id', $boat_id, $booking_id);
    update_field('time_duration', $time_duration, $booking_id);
    update_field('client_name',  $user_name, $booking_id);
    update_field('booking_email', $user_email, $booking_id);
    update_field('booking_date', $booking_date, $booking_id);
    update_field('time_slot', $time_slot, $booking_id);
    update_field('booking_status', 'confirmed', $booking_id);
}

add_filter('gform_confirmation_4', 'fishing_charter_custom_confirmation_alert', 10, 4);
function fishing_charter_custom_confirmation_alert($confirmation, $form, $entry, $is_ajax) {
    if (is_string($confirmation)) {
        $script = "<script>alert('Thank you! Your booking has been processed.');</script>";
        $confirmation .= $script;
    }
    return $confirmation;
}

add_filter('gform_field_value_fishing_charter_price', 'fishing_charter_populate_hourly_rental_price', 10, 3);
function fishing_charter_populate_hourly_rental_price($value, $field, $name) {
    $boat_id = isset($_GET['boat_id']) ? intval($_GET['boat_id']) : 0;
    error_log("Boat ID from URL: $boat_id");

    if ($boat_id > 0 && get_post_type($boat_id) === 'fishing-charter') {
        $fishing_charter_rental_price = get_field('fishing_charter_price', $boat_id);
        error_log("Hourly Rental Price for Boat ID $boat_id: $fishing_charter_rental_price");

        if ($fishing_charter_rental_price) {
            return number_format((float)$fishing_charter_rental_price, 2, '.', '');
        }
    }

    error_log("Invalid Boat ID or post type is not 'fishing-charter'. Returning empty value.");
    return '';
}

add_filter('gform_field_value_boat_name', 'fishing_charter_populate_boat_name', 10, 3);
function fishing_charter_populate_boat_name($value, $field, $name) {
    $boat_id = isset($_GET['boat_id']) ? intval($_GET['boat_id']) : 0;
    error_log("Boat ID from URL: $boat_id");

    if ($boat_id > 0 && get_post_type($boat_id) === 'fishing-charter') {
        $boat_name = get_field('boat_name', $boat_id);
        error_log("Boat Name for Boat ID $boat_id: $boat_name");
        return $boat_name;
    }

    error_log("Invalid Boat ID or post type is not 'fishing-charter'. Returning empty value.");
    return '';
}

function fishing_charter_booking_calendar_shortcode() {
    $boat_id = get_the_ID();
    $boat_price = get_field('fishing_charter_price', $boat_id);
    $fishing_description = get_field('fishing_charter_100_word_text', $boat_id);
    
    ob_start();
    ?>
    <div class="brf-post-item">
        <h3><?php the_title(); ?></h3>
        <h3>Rental Price: $<?php echo esc_html($boat_price); ?></h3>
    </div>
    <div id="fc-booking-calendar" style="width: 60%; float: left; min-height: 400px;"></div>
<div id="fc-time-slots-section" style="width: 35%; float: right; margin-left: 5%;">
    <h3 id="fc-selected-date-title">Selected Date:</h3>
    <div id="fc-dynamic-time-slots-container">
        <p>Please select a date to see available time slots.</p>
        <ul id="fc-time-slot-list"></ul>
    </div>
    <button id="fc-book-now-btn" disabled>Book Now</button>
    <button id="fc-check-availability-btn">Check Next Availability</button>
    <input type="hidden" id="fc-boat-id" value="<?php echo esc_attr($boat_id); ?>">
    <input type="hidden" id="fc-selected-time" value="">
    <?php if (!empty($fishing_description)) : ?>
    <div class="fishing-description">
        <p><?php echo esc_html($fishing_description); ?></p>
    </div>
<?php endif; ?>
</div>
    <div style="clear: both;"></div>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            document.getElementById("time-slot-list").innerHTML = ""; // Clear time slots on load
        });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('fishing_charter_booking_calendar', 'fishing_charter_booking_calendar_shortcode');

function fishing_charter_enqueue_scripts() {
    wp_enqueue_script('jquery');
    wp_localize_script('jquery', 'boatRentalAjax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
    ));
}
add_action('wp_enqueue_scripts', 'fishing_charter_enqueue_scripts');

function fishing_charter_fetch_filtered_posts() {
    $tag = sanitize_text_field($_POST['tag']);

    $args = array(
        'post_type' => 'fishing-charter',
        'posts_per_page' => -1,
        'tax_query' => array(
            array(
                'taxonomy' => 'post_tag',
                'field' => 'slug',
                'terms' => $tag,
            ),
        ),
    );

    $query = new WP_Query($args);

    if ($query->have_posts()) {
        ob_start();
        while ($query->have_posts()) {
            $query->the_post();
            ?>
            <div class="post-item">
                <h2><?php the_title(); ?></h2>
                <div><?php the_content(); ?></div>
                <?php echo do_shortcode('[fishing_charter_booking_calendar]'); ?>
            </div>
            <?php
        }
        wp_reset_postdata();
        $output = ob_get_clean();
        wp_send_json_success($output);
    } else {
        ob_start();
        ?>
        <div class="post-item">
            <p>No posts found for the selected tag.</p>
        </div>
        <?php
        $output = ob_get_clean();
        wp_send_json_success($output);
    }
}
add_action('wp_ajax_fishing_charter_fetch_filtered_posts', 'fishing_charter_fetch_filtered_posts');
add_action('wp_ajax_nopriv_fishing_charter_fetch_filtered_posts', 'fishing_charter_fetch_filtered_posts');

function fishing_charter_filter_shortcode() {
    $page_id = get_the_ID();
    $with_captain_tag = get_field('with_captain', $page_id);
    $without_captain_tag = get_field('without_captain', $page_id);

    ob_start();
    ?>
    <div id="filter-buttons">
        <button id="with-captain-btn" class="active" data-tag="<?php echo esc_attr($with_captain_tag); ?>">With Captain</button>
        <button id="without-captain-btn" data-tag="<?php echo esc_attr($without_captain_tag); ?>">Without Captain</button>
    </div>
    <div id="post-results">
        <!-- Related posts will be displayed here -->
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('fishing_charter_filter', 'fishing_charter_filter_shortcode');

// Fishing Charter Booking Approval From Backend

/**
 * Add custom columns for booking status and actions.
 */
function fishing_charter_add_booking_columns($columns) {
    $columns['booking_status'] = 'Status';
    $columns['booking_actions'] = 'Actions';
    return $columns;
}
add_filter('manage_fishing-charter-book_posts_columns', 'fishing_charter_add_booking_columns');

/**
 * Populate the custom columns with status and action buttons.
 */
function fishing_charter_render_booking_columns($column, $post_id) {
    if ($column === 'booking_status') {
        $status = get_field('booking_status', $post_id);
        echo ucfirst($status); // Show status
    }
    
    if ($column === 'booking_actions') {
        $approve_url = admin_url("admin-post.php?action=fishing_charter_approve_booking&booking_id={$post_id}&_wpnonce=" . wp_create_nonce('fishing_charter_approve_booking_' . $post_id));
        $cancel_url = admin_url("admin-post.php?action=fishing_charter_cancel_booking&booking_id={$post_id}&_wpnonce=" . wp_create_nonce('fishing_charter_cancel_booking_' . $post_id));

        //echo '<a href="' . esc_url($approve_url) . '" class="button button-primary" style="margin-right:5px;">Approve</a>';
        echo '<a href="' . esc_url($cancel_url) . '" class="button" style="background-color:#dc3232;color:#fff;">Cancel</a>';
    }
}
add_action('manage_fishing-charter-book_posts_custom_column', 'fishing_charter_render_booking_columns', 10, 2);

/**
 * Handle Approve Action.
 */
function fishing_charter_handle_approve_booking() {
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'fishing_charter_approve_booking_' . $_GET['booking_id'])) {
        wp_die('Security check failed.');
    }

    $booking_id = intval($_GET['booking_id']);
    
    // Update booking status to confirmed.
    update_field('booking_status', 'confirmed', $booking_id);

    // Redirect back to the bookings list.
    wp_redirect(admin_url('edit.php?post_type=fishing-charter-book'));
    exit;
}
add_action('admin_post_fishing_charter_approve_booking', 'fishing_charter_handle_approve_booking');

/**
 * Handle Cancel Action.
 */
function fishing_charter_handle_cancel_booking() {
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'fishing_charter_cancel_booking_' . $_GET['booking_id'])) {
        wp_die('Security check failed.');
    }

    $booking_id = intval($_GET['booking_id']);
    update_field('booking_status', 'rejected', $booking_id);

    // Redirect back to the bookings list.
    wp_redirect(admin_url('edit.php?post_type=fishing-charter-book'));
    exit;
}
add_action('admin_post_fishing_charter_cancel_booking', 'fishing_charter_handle_cancel_booking');




// Lake Booking System
// ACF : Meta Fields
// Custom Post Type : Lake Services & Lake Services Booking
// Booking System
function service_lake_fetch_time_slots() {
    $selectedDate = sanitize_text_field($_POST['date']);
    $boat_id = intval($_POST['boat_id']);
    $time_slots = array();

    error_log("Fetching time slots for date: $selectedDate and boat ID: $boat_id");

    if (have_rows('boat_time_slots', $boat_id)) {
        while (have_rows('boat_time_slots', $boat_id)) {
            the_row();
            $time_slot = get_sub_field('time_slot');

            error_log("Checking time slot: $time_slot");

            // Check for bookings with status 'confirmed'
            $booking_query = new WP_Query(array(
                'post_type' => 'service-lake-booking',
                'posts_per_page' => 1, // Limit to one result for efficiency
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'key' => 'boat_id',
                        'value' => $boat_id,
                        'compare' => '=',
                    ),
                    array(
                        'key' => 'booking_date',
                        'value' => $selectedDate,
                        'compare' => '=',
                    ),
                    array(
                        'key' => 'time_slot',
                        'value' => $time_slot,
                        'compare' => '=',
                    ),
                    array(
                        'key' => 'booking_status',
                        'value' => 'confirmed',
                        'compare' => '=',
                    ),
                ),
            ));

            // Debug: Log the query results
            error_log("Query results for time slot $time_slot: " . print_r($booking_query->posts, true));

            if (!$booking_query->have_posts()) {
                $time_slots[] = array(
                    'time' => $time_slot,
                    'available' => true,
                );
            } else {
                $time_slots[] = array(
                    'time' => $time_slot,
                    'available' => false, // Mark it as booked
                );
            }
        }
    }

    error_log("Time slots fetched: " . print_r($time_slots, true));

    wp_send_json_success(array('slots' => $time_slots));
}
add_action('wp_ajax_service_lake_fetch_time_slots', 'service_lake_fetch_time_slots');
add_action('wp_ajax_nopriv_service_lake_fetch_time_slots', 'service_lake_fetch_time_slots');

add_action('gform_after_submission_5', 'service_lake_save_booking_after_submission', 10, 2);
function service_lake_save_booking_after_submission($entry, $form) {
    $user_name = rgar($entry, '60');
    $user_email = rgar($entry, '16');
    $boat_id = rgar($entry, '57');
    $time_duration = get_the_title($boat_id);
    $booking_date = rgar($entry, '50');
    $time_slot = rgar($entry, '54');

    if (empty($boat_id) || empty($booking_date) || empty($time_slot)) {
        return;
    }

    $existing_booking = new WP_Query(array(
        'post_type' => 'service-lake-booking',
        'meta_query' => array(
            array('key' => 'boat_id', 'value' => $boat_id, 'compare' => '='),
            array('key' => 'time_duration', 'value' => $time_duration, 'compare' => '='),
            array('key' => 'client_name', 'value' => $user_name, 'compare' => '='),
            array('key' => 'booking_email', 'value' => $user_email, 'compare' => '='),
            array('key' => 'booking_date', 'value' => $booking_date, 'compare' => '='),
            array('key' => 'time_slot', 'value' => $time_slot, 'compare' => '='),
            array('key' => 'booking_status', 'value' => 'confirmed', 'compare' => '='), 
        ),
    ));
    
    if ($existing_booking->have_posts()) {
        return;
    }

    $booking_post = array(
        'post_title'   => "Booking: {$user_name} - Boat {$boat_id} on {$booking_date} at {$time_slot}",
        'post_type'    => 'service-lake-booking',
        'post_status'  => 'publish',
    );
    $booking_id = wp_insert_post($booking_post);
    
    if (!$booking_id) {
        return;
    }
    
    update_field('boat_id', $boat_id, $booking_id);
    update_field('time_duration', $time_duration, $booking_id);
    update_field('client_name',  $user_name, $booking_id);
    update_field('booking_email', $user_email, $booking_id);
    update_field('booking_date', $booking_date, $booking_id);
    update_field('time_slot', $time_slot, $booking_id);
    update_field('booking_status', 'confirmed', $booking_id);
}

add_filter('gform_confirmation_5', 'service_lake_custom_confirmation_alert', 10, 4);
function service_lake_custom_confirmation_alert($confirmation, $form, $entry, $is_ajax) {
    if (is_string($confirmation)) {
        $script = "<script>alert('Thank you! Your booking has been processed.');</script>";
        $confirmation .= $script;
    }
    return $confirmation;
}

add_filter('gform_field_value_service_lake_price', 'service_lake_populate_hourly_rental_price', 10, 3);
function service_lake_populate_hourly_rental_price($value, $field, $name) {
    $boat_id = isset($_GET['boat_id']) ? intval($_GET['boat_id']) : 0;
    error_log("Boat ID from URL: $boat_id");

    if ($boat_id > 0 && get_post_type($boat_id) === 'service-lake') {
        $service_lake_rental_price = get_field('service_lake_price', $boat_id);
        error_log("Hourly Rental Price for Boat ID $boat_id: $service_lake_rental_price");

        if ($service_lake_rental_price) {
            return number_format((float)$service_lake_rental_price, 2, '.', '');
        }
    }

    error_log("Invalid Boat ID or post type is not 'Service Lake'. Returning empty value.");
    return '';
}

add_filter('gform_field_value_boat_name', 'service_lake_populate_boat_name', 10, 3);
function service_lake_populate_boat_name($value, $field, $name) {
    $boat_id = isset($_GET['boat_id']) ? intval($_GET['boat_id']) : 0;
    error_log("Boat ID from URL: $boat_id");

    if ($boat_id > 0 && get_post_type($boat_id) === 'service-lake') {
        $boat_name = get_field('boat_name', $boat_id);
        error_log("Boat Name for Boat ID $boat_id: $boat_name");
        return $boat_name;
    }

    error_log("Invalid Boat ID or post type is not 'service-lake'. Returning empty value.");
    return '';
}

function service_lake_booking_calendar_shortcode() {
    $boat_id = get_the_ID();
    $boat_price = get_field('service_lake_price', $boat_id);
    
    ob_start();
    ?>
    <div class="brf-post-item">
        <h3><?php the_title(); ?></h3>
        <h3>Rental Price: $<?php echo esc_html($boat_price); ?></h3>
    </div>
    <div id="sl-booking-calendar" style="width: 60%; float: left; min-height: 400px;"></div>
<div id="sl-time-slots-section" style="width: 35%; float: right; margin-left: 5%;">
    <h3 id="sl-selected-date-title">Selected Date:</h3>
    <div id="sl-dynamic-time-slots-container">
        <p>Please select a date to see available time slots.</p>
        <ul id="sl-time-slot-list"></ul>
    </div>
    <button id="sl-book-now-btn" disabled>Book Now</button>
    <button id="sl-check-availability-btn">Check Next Availability</button>
    <input type="hidden" id="sl-boat-id" value="<?php echo esc_attr($boat_id); ?>">
    <input type="hidden" id="sl-selected-time" value="">
</div>
    <div style="clear: both;"></div>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            document.getElementById("time-slot-list").innerHTML = ""; // Clear time slots on load
        });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('service_lake_booking_calendar', 'service_lake_booking_calendar_shortcode');

function service_lake_enqueue_scripts() {
    wp_enqueue_script('jquery');
    wp_localize_script('jquery', 'boatRentalAjax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
    ));
}
add_action('wp_enqueue_scripts', 'service_lake_enqueue_scripts');

function service_lake_fetch_filtered_posts() {
    $tag = sanitize_text_field($_POST['tag']);

    $args = array(
        'post_type' => 'service-lake',
        'posts_per_page' => -1,
        'tax_query' => array(
            array(
                'taxonomy' => 'post_tag',
                'field' => 'slug',
                'terms' => $tag,
            ),
        ),
    );

    $query = new WP_Query($args);

    if ($query->have_posts()) {
        ob_start();
        while ($query->have_posts()) {
            $query->the_post();
            ?>
            <div class="post-item">
                <h2><?php the_title(); ?></h2>
                <div><?php the_content(); ?></div>
                <?php echo do_shortcode('[service_lake_booking_calendar]'); ?>
            </div>
            <?php
        }
        wp_reset_postdata();
        $output = ob_get_clean();
        wp_send_json_success($output);
    } else {
        ob_start();
        ?>
        <div class="post-item">
            <p>No posts found for the selected tag.</p>
        </div>
        <?php
        $output = ob_get_clean();
        wp_send_json_success($output);
    }
}
add_action('wp_ajax_service_lake_fetch_filtered_posts', 'service_lake_fetch_filtered_posts');
add_action('wp_ajax_nopriv_service_lake_fetch_filtered_posts', 'service_lake_fetch_filtered_posts');

function service_lake_filter_shortcode() {
    $page_id = get_the_ID();
    $with_captain_tag = get_field('with_captain', $page_id);
    $without_captain_tag = get_field('without_captain', $page_id);

    ob_start();
    ?>
    <div id="filter-buttons">
        <button id="with-captain-btn" class="active" data-tag="<?php echo esc_attr($with_captain_tag); ?>">With Captain</button>
        <button id="without-captain-btn" data-tag="<?php echo esc_attr($without_captain_tag); ?>">Without Captain</button>
    </div>
    <div id="post-results">
        <!-- Related posts will be displayed here -->
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('service_lake_filter', 'service_lake_filter_shortcode');

// Fishing Charter Booking Approval From Backend
/**
 * Add custom columns for booking status and actions.
 */
function service_lake_add_booking_columns($columns) {
    $columns['booking_status'] = 'Status';
    $columns['booking_actions'] = 'Actions';
    return $columns;
}
add_filter('manage_service-lake-booking_posts_columns', 'service_lake_add_booking_columns');

/**
 * Populate the custom columns with status and action buttons.
 */
function service_lake_render_booking_columns($column, $post_id) {
    if ($column === 'booking_status') {
        $status = get_field('booking_status', $post_id);
        echo ucfirst($status); // Show status
    }
    
    if ($column === 'booking_actions') {
        $approve_url = admin_url("admin-post.php?action=service_lake_approve_booking&booking_id={$post_id}&_wpnonce=" . wp_create_nonce('service_lake_approve_booking_' . $post_id));
        $cancel_url = admin_url("admin-post.php?action=service_lake_cancel_booking&booking_id={$post_id}&_wpnonce=" . wp_create_nonce('service_lake_cancel_booking_' . $post_id));

        //echo '<a href="' . esc_url($approve_url) . '" class="button button-primary" style="margin-right:5px;">Approve</a>';
        echo '<a href="' . esc_url($cancel_url) . '" class="button" style="background-color:#dc3232;color:#fff;">Cancel</a>';
    }
}
add_action('manage_service-lake-booking_posts_custom_column', 'service_lake_render_booking_columns', 10, 2);

/**
 * Handle Approve Action.
 */
function service_lake_handle_approve_booking() {
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'service_lake_approve_booking_' . $_GET['booking_id'])) {
        wp_die('Security check failed.');
    }

    $booking_id = intval($_GET['booking_id']);
    
    // Update booking status to confirmed.
    update_field('booking_status', 'confirmed', $booking_id);

    // Redirect back to the bookings list.
    wp_redirect(admin_url('edit.php?post_type=service-lake-booking'));
    exit;
}
add_action('admin_post_service_lake_approve_booking', 'service_lake_handle_approve_booking');

/**
 * Handle Cancel Action.
 */
function service_lake_handle_cancel_booking() {
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'service_lake_cancel_booking_' . $_GET['booking_id'])) {
        wp_die('Security check failed.');
    }

    $booking_id = intval($_GET['booking_id']);
    update_field('booking_status', 'rejected', $booking_id);

    // Redirect back to the bookings list.
    wp_redirect(admin_url('edit.php?post_type=service-lake-booking'));
    exit;
}
add_action('admin_post_service_lake_cancel_booking', 'service_lake_handle_cancel_booking');




// Category fetched in the gform


add_filter('gform_field_value_boat_category', function($value) {
    if (isset($_GET['boat_id'])) {
        $boat_id = intval($_GET['boat_id']); // Get boat ID from URL
        $categories = wp_get_post_terms($boat_id, 'category'); // Fetch categories

        if (!empty($categories) && !is_wp_error($categories)) {
            return $categories[0]->slug; // Return category slug (with-captain or without-captain)
        }
    }
    return '';
});

add_filter('gform_field_value_boat_title', function($value) {
    if (isset($_GET['boat_id'])) {
        $boat_id = intval($_GET['boat_id']); // Get boat ID from URL
        $post_title = get_the_title($boat_id); // Get the post title

        if (!empty($post_title)) {
            return $post_title; // Return the boat title
        }
    }
    return '';
});



function content_condition_shortcode() {
    $content_type = get_field('content_type'); // Get the selected radio button value

    if ($content_type == 'calender_content') {
        return do_shortcode('[fishing_charter_booking_calendar]'); // Show Calendar
    } elseif ($content_type == 'call_availability_content') {
        $post_title = get_the_title(); // Get the post title
        $call_content = get_field('call_for_availability_content'); 
        $call_other_content = get_field('fishing_charter_service'); // Get ACF content
        
        return '<div class="call-availability">
                    <h2>' . esc_html($call_other_content) . '</h2>    
                    <p>' . esc_html($call_content) . '</p>
                </div>';
    }
}
add_shortcode('content_condition', 'content_condition_shortcode');

add_filter('gform_field_value_booking_days', function($value) {
    if (isset($_GET['boat_id'])) {
        $boat_id = intval($_GET['boat_id']); // Get boat ID from URL
        $booking_days = get_field('booking_days', $boat_id); // Fetch booking days ACF field

        if (!empty($booking_days)) {
            return $booking_days; // Return the value
        }
    }
    return ''; // Default empty value if no data found
});



function service_content_condition_shortcode() {
    $content_type = get_field('content_type'); // Get the selected ACF field value

    if ($content_type == 'services-lake-calender') {
        return do_shortcode('[service_lake_booking_calendar]'); // Show Calendar
    } elseif ($content_type == 'service_lakes_content') {
        return get_field('service_lakes_content'); // Show the selected content from ACF
    }

    return ''; // Return nothing if no condition matches
}
add_shortcode('service_content_condition', 'service_content_condition_shortcode');





// Coupon Code
add_action('wp_ajax_gf_process_coupon', 'gf_handle_coupon');
add_action('wp_ajax_nopriv_gf_process_coupon', 'gf_handle_coupon');
function gf_handle_coupon() {
    try {
        // Verify nonce (uncomment when ready)
        // check_ajax_referer('gf_coupon_nonce', 'security');
        
        // Validate input
        if (empty($_POST['coupon'])) {
            wp_send_json_error([
                'message' => 'Please enter a coupon code'
            ], 400);
            return;
        }
        
        $coupon_code = sanitize_text_field($_POST['coupon']);
        $coupons = get_field('coupon_codes', 'option') ?: [];
        
        foreach ($coupons as $coupon) {
            if (!empty($coupon['codes']) && 
                strcasecmp(trim($coupon['codes']), trim($coupon_code)) === 0) {
                
                // Validate percentage value
                $percentage = is_numeric($coupon['percentage'] ?? null) 
                    ? (float)$coupon['percentage'] 
                    : 0;
                
                wp_send_json_success([
                    'percentage' => $percentage,
                    'message' => $percentage ? sprintf('%d%% discount applied', $percentage) : 'Coupon has no discount'
                ]);
                return;
            }
        }
        
        wp_send_json_error([
            'message' => 'Invalid coupon code'
        ], 404);
        
    } catch (Exception $e) {
        wp_send_json_error([
            'message' => 'Server error: ' . $e->getMessage()
        ], 500);
    }
}






add_filter('gform_field_value_booking_hours', function($value) {
    // First, check if booking_hours is already passed in URL
    if (isset($_GET['booking_hours'])) {
        return sanitize_text_field($_GET['booking_hours']);
    }

    // If not, try to fetch booking_hours from ACF using boat_id
    if (isset($_GET['boat_id'])) {
        $boat_id = intval($_GET['boat_id']);
        $booking_hours = get_field('booking_hours', $boat_id);

        if (!empty($booking_hours)) {
            return $booking_hours;
        }
    }

    return ''; // Default empty value
});





// 

/**
 * Automatically append ?source=captained-cruises to all relevant links
 */
function stjoseph_add_source_to_links($content) {
    if (isset($_GET['source']) && $_GET['source'] === 'captained-cruises') {
        $source = 'captained-cruises';
        
        // Process content links
        $content = preg_replace_callback(
            '/(href=["\'])(https?:\/\/www\.stjosephboatrentals\.com\/(boat-rental|boat-booking)[^"\']*)/',
            function($matches) use ($source) {
                $url = $matches[2];
                $separator = (strpos($url, '?') === false) ? '?' : '&';
                return $matches[1] . $url . $separator . 'source=' . $source;
            },
            $content
        );
        
        // Process menu items
        if (did_action('wp_nav_menu')) {
            $content = preg_replace_callback(
                '/(href=["\'])(\/boat-rental\/|\/boat-booking\/)[^"\']*/',
                function($matches) use ($source) {
                    return $matches[0] . (strpos($matches[0], '?') === false ? '?' : '&') . 'source=' . $source;
                },
                $content
            );
        }
    }
    return $content;
}

// Apply to content
add_filter('the_content', 'stjoseph_add_source_to_links', 100);

// Apply to widgets
add_filter('widget_text', 'stjoseph_add_source_to_links', 100);

// Apply to menus
add_filter('wp_nav_menu', 'stjoseph_add_source_to_links', 100);

// Force redirect if coming from captained-cruises but missing parameter
function stjoseph_force_source_parameter() {
    if (!is_admin() && (is_singular('boat-rental') || strpos($_SERVER['REQUEST_URI'], '/boat-booking/') !== false)) {
        if (strpos(wp_get_referer(), 'captained-cruises') !== false && !isset($_GET['source'])) {
            wp_redirect(add_query_arg('source', 'captained-cruises', $_SERVER['REQUEST_URI']));
            exit;
        }
    }
}
add_action('template_redirect', 'stjoseph_force_source_parameter');




// Rental


function stjoseph_add_source_to_links_rental($content) {
    if (isset($_GET['source']) && $_GET['source'] === 'rentals') {
        $source = 'rentals';
        
        // Process content links
        $content = preg_replace_callback(
            '/(href=["\'])(https?:\/\/www\.stjosephboatrentals\.com\/(boat-rental|boat-booking)[^"\']*)/',
            function($matches) use ($source) {
                $url = $matches[2];
                $separator = (strpos($url, '?') === false) ? '?' : '&';
                return $matches[1] . $url . $separator . 'source=' . $source;
            },
            $content
        );
        
        // Process menu items
        if (did_action('wp_nav_menu')) {
            $content = preg_replace_callback(
                '/(href=["\'])(\/boat-rental\/|\/boat-booking\/)[^"\']*/',
                function($matches) use ($source) {
                    return $matches[0] . (strpos($matches[0], '?') === false ? '?' : '&') . 'source=' . $source;
                },
                $content
            );
        }
    }
    return $content;
}

// Apply to content
add_filter('the_content', 'stjoseph_add_source_to_links_rental', 100);

// Apply to widgets
add_filter('widget_text', 'stjoseph_add_source_to_links_rental', 100);

// Apply to menus
add_filter('wp_nav_menu', 'stjoseph_add_source_to_links_rental', 100);

// Force redirect if coming from captained-cruises but missing parameter
function stjoseph_force_source_parameter_rental() {
    if (!is_admin() && (is_singular('boat-rental') || strpos($_SERVER['REQUEST_URI'], '/boat-booking/') !== false)) {
        if (strpos(wp_get_referer(), 'rentals') !== false && !isset($_GET['source'])) {
            wp_redirect(add_query_arg('source', 'rentals', $_SERVER['REQUEST_URI']));
            exit;
        }
    }
}
add_action('template_redirect', 'stjoseph_force_source_parameter_rental');



