<script>
document.addEventListener('DOMContentLoaded', function() {
  console.log("Booking calendar script loaded");

  // Global variable to track the currently selected date.
  let currentSelectedDate = null;

  // Get the calendar container.
  var calendarEl = document.getElementById('booking-calendar');
  if (!calendarEl) {
    console.error("Calendar container not found");
    return;
  }

  // Get today's date in YYYY-MM-DD format.
  var today = new Date();
  var isoToday = today.toISOString().split('T')[0];

  // Initialize FullCalendar.
  var calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: 'dayGridMonth',
    firstDay: 1, // Monday.
    headerToolbar: {
      left: 'prev,next today',
      center: 'title',
      right: ''
    },
    // Optionally, disable past dates:
    validRange: {
      start: isoToday
    },
    dateClick: function(info) {
      var selectedDate = info.dateStr; // Format: YYYY-MM-DD.

      // If the same date is clicked again, do nothing.
      if (selectedDate === currentSelectedDate) {
        console.log("Same date clicked, skipping re-fetch.");
        return;
      }
      currentSelectedDate = selectedDate;

      // Remove previous highlight.
      document.querySelectorAll('.selected-day').forEach(function(el) {
        el.classList.remove('selected-day');
      });
      if (info.dayEl) {
        info.dayEl.classList.add('selected-day');
      }

      // Update selected date display.
      var dateTitleEl = document.getElementById('selected-date-title');
      if (dateTitleEl) {
        dateTitleEl.textContent = 'Selected Date: ' + selectedDate;
      }

      // Fetch available time slots for the selected date.
      fetchDynamicTimeSlots(selectedDate);
    }
  });
  calendar.render();

  // Auto-select today's date.
  currentSelectedDate = isoToday;
  var dateTitleEl = document.getElementById('selected-date-title');
  if (dateTitleEl) {
    dateTitleEl.textContent = 'Selected Date: ' + isoToday;
  }
  var todayCell = document.querySelector('[data-date="' + isoToday + '"]');
  if (todayCell) {
    todayCell.classList.add('selected-day');
  }
  fetchDynamicTimeSlots(isoToday);

  // "Check Next Availability" button.
  var checkBtn = document.getElementById('check-availability-btn');
  if (checkBtn) {
    checkBtn.addEventListener('click', function() {
      var currentDateText = dateTitleEl ? dateTitleEl.textContent : '';
      var currentDate = currentDateText.replace('Selected Date: ', '');
      checkNextAvailability(currentDate);
    });
  }

  // Function: Check Next Availability (increments date by one day).
  function checkNextAvailability(currentDate) {
    var dateObj = new Date(currentDate);
    dateObj.setDate(dateObj.getDate() + 1);
    var nextDate = dateObj.toISOString().split('T')[0];
    if (nextDate === currentSelectedDate) {
      console.log("Already displaying that date, skipping re-fetch.");
      return;
    }
    currentSelectedDate = nextDate;
    fetchDynamicTimeSlots(nextDate);
    if (dateTitleEl) {
      dateTitleEl.textContent = 'Selected Date: ' + nextDate;
    }
    // Update cell highlight.
    document.querySelectorAll('.selected-day').forEach(function(el) {
      el.classList.remove('selected-day');
    });
    var nextCell = document.querySelector('[data-date="' + nextDate + '"]');
    if (nextCell) {
      nextCell.classList.add('selected-day');
    }
  }

  // Function: Fetch dynamic time slots via AJAX.
  function fetchDynamicTimeSlots(selectedDate) {
    var boatIdEl = document.getElementById('boat-id');
    if (!boatIdEl) {
      console.error("Boat ID field not found");
      return;
    }
    var boatId = boatIdEl.value;

    // Disable the Book Now button until a slot is selected.
    jQuery('#book-now-btn').prop('disabled', true);

    // Get and clear the container.
    var container = document.getElementById('dynamic-time-slots-container');
    if (!container) {
      console.error("Dynamic time slots container not found");
      return;
    }
    container.innerHTML = '';

    jQuery.ajax({
      url: boatRentalAjax.ajax_url,
      type: 'POST',
      data: {
        action: 'fetch_time_slots',
        date: selectedDate,
        boat_id: boatId
      },
      success: function(response) {
        if (response.success) {
          var slots = response.data.slots;
          if (slots && slots.length > 0) {
            slots.forEach(function(slotObj) {
              var timeStr = slotObj.time;
              var btn = document.createElement('button');
              btn.classList.add('time-slot');
              btn.textContent = timeStr + (slotObj.available ? '' : ' (Not Available)');
              btn.dataset.time = timeStr;
              if (!slotObj.available) {
                btn.disabled = true;
                btn.classList.add('booked-slot');
              }
              // Add click event.
              btn.addEventListener('click', function() {
                // Remove active class from other slots.
                document.querySelectorAll('#dynamic-time-slots-container .time-slot').forEach(function(b) {
                  b.classList.remove('active');
                });
                btn.classList.add('active');
                var selectedTimeField = document.getElementById('selected-time');
                if (selectedTimeField) {
                  selectedTimeField.value = timeStr;
                }
                // Enable Book Now if slot is available.
                jQuery('#book-now-btn').prop('disabled', !slotObj.available);
              });
              container.appendChild(btn);
            });
          } else {
            container.innerHTML = '<p>No slots available for ' + selectedDate + '</p>';
          }
        } else {
          alert('Error fetching time slots.');
        }
      },
      error: function() {
        alert('Error fetching time slots.');
      }
    });
  }
});

jQuery(document).ready(function($) {
    $(".boat-filter-btn").on("click", function() {
        // Get the filter from the data attribute
        var filter = $(this).data("filter");

        $.ajax({
            type: "POST",
            url: boatFilter.ajaxurl, // from wp_localize_script
            data: {
                action: "load_boat_single_post",
                filter: filter
            },
            beforeSend: function() {
                $("#boat-posts-container").html("<p>Loading...</p>");
            },
            success: function(response) {
                // Display the single post content
                $("#boat-posts-container").html(response);
            }
        });
    });
});

document.addEventListener('DOMContentLoaded', function() {
    console.log("Filtering script loaded");

    // Initialize FullCalendar
    var calendarEl = document.getElementById('booking-calendar');
    if (calendarEl) {
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            firstDay: 1, // Monday
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: ''
            },
            validRange: {
                start: new Date().toISOString().split('T')[0] // Disable past dates
            },
            dateClick: function(info) {
                var selectedDate = info.dateStr;
                fetchDynamicTimeSlots(selectedDate);
            }
        });
        calendar.render();
    }

    // Default button is active
    const defaultButton = document.getElementById('with-captain-btn');
    if (defaultButton) {
        defaultButton.classList.add('active');
        const defaultTag = defaultButton.getAttribute('data-tag');
        fetchPosts(defaultTag);
        fetchDynamicTimeSlots(new Date().toISOString().split('T')[0]); // Fetch time slots for today
    }

    // Add event listeners to buttons
    document.getElementById('with-captain-btn').addEventListener('click', function() {
        const tag = this.getAttribute('data-tag');
        console.log("DEBUG: Fetching posts for tag:", tag);
        fetchPosts(tag);
        setActiveButton('with-captain-btn');
    });

    document.getElementById('without-captain-btn').addEventListener('click', function() {
        const tag = this.getAttribute('data-tag');
        console.log("DEBUG: Fetching posts for tag:", tag);
        fetchPosts(tag);
        setActiveButton('without-captain-btn');
    });

    // Function to fetch posts based on tag
    function fetchPosts(tag) {
        jQuery.ajax({
            url: boatRentalAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'fetch_filtered_posts',
                tag: tag
            },
            success: function(response) {
                console.log("DEBUG: AJAX response:", response);
                if (response.success) {
                    document.getElementById('post-results').innerHTML = response.data;
                } else {
                    document.getElementById('post-results').innerHTML = '<p>Error fetching posts.</p>';
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("AJAX Error:", textStatus, errorThrown);
                document.getElementById('post-results').innerHTML = '<p>Error fetching posts.</p>';
            }
        });
    }

    // Function to fetch dynamic time slots
    function fetchDynamicTimeSlots(selectedDate) {
        var boatIdEl = document.getElementById('boat-id');
        if (!boatIdEl) {
            console.error("Boat ID field not found");
            return;
        }
        var boatId = boatIdEl.value;

        console.log("DEBUG: Fetching time slots for date=" + selectedDate + ", boat_id=" + boatId);

        // Disable the Book Now button until a slot is selected
        jQuery('#book-now-btn').prop('disabled', true);

        // Get and clear the container
        var container = document.getElementById('dynamic-time-slots-container');
        if (!container) {
            console.error("Dynamic time slots container not found");
            return;
        }
        container.innerHTML = '';

        jQuery.ajax({
            url: boatRentalAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'fetch_time_slots',
                date: selectedDate,
                boat_id: boatId
            },
            success: function(response) {
                console.log("DEBUG: AJAX response received:", response);
                if (response.success) {
                    var slots = response.data.slots;
                    if (slots && slots.length > 0) {
                        console.log("DEBUG: Time slots found:", slots);
                        slots.forEach(function(slotObj) {
                            var timeStr = slotObj.time;
                            var btn = document.createElement('button');
                            btn.classList.add('time-slot');
                            btn.textContent = timeStr + (slotObj.available ? '' : ' (Not Available)');
                            btn.dataset.time = timeStr;
                            if (!slotObj.available) {
                                btn.disabled = true;
                                btn.classList.add('booked-slot');
                            }
                            // Add click event
                            btn.addEventListener('click', function() {
                                // Remove active class from other slots
                                document.querySelectorAll('#dynamic-time-slots-container .time-slot').forEach(function(b) {
                                    b.classList.remove('active');
                                });
                                btn.classList.add('active');
                                var selectedTimeField = document.getElementById('selected-time');
                                if (selectedTimeField) {
                                    selectedTimeField.value = timeStr;
                                }
                                // Enable Book Now if slot is available
                                jQuery('#book-now-btn').prop('disabled', !slotObj.available);
                            });
                            container.appendChild(btn);
                        });
                    } else {
                        console.log("DEBUG: No slots available for " + selectedDate);
                        container.innerHTML = '<p>No slots available for ' + selectedDate + '</p>';
                    }
                } else {
                    console.error("DEBUG: AJAX response error:", response);
                    alert('Error fetching time slots.');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("DEBUG: AJAX request failed:", textStatus, errorThrown);
                alert('Error fetching time slots.');
            }
        });
    }

    // Function to set the active button
    function setActiveButton(buttonId) {
        document.querySelectorAll('#filter-buttons button').forEach(function(btn) {
            btn.classList.remove('active');
        });
        document.getElementById(buttonId).classList.add('active');
    }
});




<!-- Booking System Plugin -->

jQuery(document).ready(function ($) {
	console.log("Booking System Plugin Script Loaded");
    // Search functionality
    $('#bas-booking-search').on('keyup', function () {
        let input = $(this).val().toLowerCase();
        $('#bas-booking-table tbody tr').each(function () {
            let rowText = $(this).text().toLowerCase();
            $(this).toggle(rowText.includes(input));
        });
    });

    // Approve booking
    $(document).on('click', '.bas-approve-btn', function () {
        if (confirm('Are you sure you want to approve this booking?')) {
            let postId = $(this).data('id');
            let button = $(this);

            $.ajax({
                url: bas_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'approve_booking',
                    post_id: postId,
                    security: bas_ajax.approve_nonce
                },
                success: function (response) {
                    if (response.success) {
                        $('#status-' + postId).text('approved');
                        button.replaceWith('Approved');
                    } else {
                        alert('Failed to approve booking.');
                    }
                },
                error: function () {
                    alert('An error occurred. Please try again.');
                }
            });
        }
    });

    // Reject booking
    $(document).on('click', '.bas-reject-btn', function () {
        if (confirm('Are you sure you want to reject this booking?')) {
            let postId = $(this).data('id');
            let button = $(this);

            $.ajax({
                url: bas_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'reject_booking',
                    post_id: postId,
                    security: bas_ajax.reject_nonce
                },
                success: function (response) {
                    if (response.success) {
                        $('#status-' + postId).text('rejected');
                        button.replaceWith('Rejected');
                    } else {
                        alert('Failed to reject booking.');
                    }
                },
                error: function () {
                    alert('An error occurred. Please try again.');
                }
            });
        }
    });
});



<!-- admin approval -->

jQuery(document).ready(function($){
  // Live search for each booking table
  $('.booking-search-input').on('keyup', function() {
    var value = $(this).val().toLowerCase();
    $(this).siblings('.booking-table').find('tbody tr').filter(function() {
      $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
    });
  });
  
  // (Optional) If you want to remove the row immediately when an action button is clicked:
  $(document).on('click', '.booking-action-btn', function(e) {
    // Do not prevent default if you want a full page reload
    // Uncomment the next two lines if you prefer an immediate visual removal:
    // e.preventDefault();
    // $(this).closest('tr').fadeOut(300);
  });
});

