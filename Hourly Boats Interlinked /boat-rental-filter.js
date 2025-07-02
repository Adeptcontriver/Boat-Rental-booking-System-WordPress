document.addEventListener('DOMContentLoaded', function() {
    console.log("Boat Rental Filter Plugin script loaded");

    // Initialize FullCalendar
    let calendar; // Declare calendar variable globally
    // let currentSelectedDate = new Date('2025-05-16').toISOString().split('T')[0];// Track the currently selected date

    function initializeCalendar() {
        var calendarEl = document.getElementById('booking-calendar');
        if (calendarEl) {
            console.log("DEBUG: Calendar element found");

            var blockedDates = window.boatRentalData?.blocked_dates?.hourly || [];
            console.log(blockedDates);

            // Get today's date in YYYY-MM-DD format
            // const today = new Date('2025-05-19').toISOString().split('T')[0];
            var today = new Date().toISOString().split('T')[0];

            let selectedDate = today;
            let maxCheckDays = 30; // Avoid infinite loop
            let counter = 0;

            while (blockedDates.includes(selectedDate)) {
            const nextDate = new Date(selectedDate);
            nextDate.setDate(nextDate.getDate() + 1);
            selectedDate = nextDate.toISOString().split('T')[0];
            counter++;
            if (counter > maxCheckDays) {
                console.warn("No available date found within the next 30 days.");
                break;
            }
            }

            today = selectedDate

            // console.log('today: ' + today);

            calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                initialDate: today, // Set the initial date to today
                selectable: true,
                validRange: {
                    start: today, // Disable past dates
                },
                dateClick: function(info) {
                    var selectedDate = info.dateStr;

                    // Block specific dates (format: YYYY-MM-DD)
                    // const blockedDates = ['2025-05-23', '2025-05-24', '2025-05-25', '2025-05-26'];
                    // const blockedDates = window.boatRentalData?.blocked_dates?.hourly || [];
                    if (blockedDates.includes(selectedDate)) {
                        showUnavailableMessage(selectedDate);
                        return; // Prevent further actions
                    }

                    console.log("DEBUG: Selected date:", selectedDate);
                    updateSelectedDateTitle(selectedDate);
                    fetchTimeSlots(selectedDate);
                    currentSelectedDate = selectedDate; // Update the currently selected date
                },
                dayCellDidMount: function(info) {
                    // const blockedDates = ['2025-05-23', '2025-05-24', '2025-05-25' , '2025-05-26'];
                    // const blockedDates = window.boatRentalData?.blocked_dates?.hourly || [];
                    if (blockedDates.includes(info.date.toISOString().split('T')[0])) {
                        info.el.style.backgroundColor = '#f8d7da'; // Light red
                        info.el.style.cursor = 'not-allowed';
                    }
                }
       
            });

            calendar.render();
            console.log("DEBUG: Calendar initialized");

            // Automatically select today's date
            updateSelectedDateTitle(today);
            fetchTimeSlots(today);
        } else {
            console.error("DEBUG: Calendar container not found");
        }
    }

    // Initialize the calendar on page load
    initializeCalendar();

    // Default button is active
    const defaultButton = document.getElementById('brf-without-captain-btn');
    if (defaultButton) {
        defaultButton.classList.add('active');
        const defaultTag = defaultButton.getAttribute('data-tag');
        brfFetchPosts(defaultTag);
    }

    // Add event listeners to buttons
    document.getElementById('brf-with-captain-btn').addEventListener('click', function() {
        const tag = this.getAttribute('data-tag');
        console.log("DEBUG: Fetching posts for tag:", tag);
        brfFetchPosts(tag);
        brfSetActiveButton('brf-with-captain-btn');
    });

    document.getElementById('brf-without-captain-btn').addEventListener('click', function() {
        const tag = this.getAttribute('data-tag');
        console.log("DEBUG: Fetching posts for tag:", tag);
        brfFetchPosts(tag);
        brfSetActiveButton('brf-without-captain-btn');
    });

    // Function to fetch posts based on tag
    function brfFetchPosts(tag) {
        jQuery.ajax({
            url: brfAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'brf_plugin_fetch_filtered_posts',
                tag: tag
            },
            success: function(response) {
                console.log("DEBUG: AJAX response:", response);
                if (response.success) {
                    document.getElementById('brf-post-results').innerHTML = response.data;

                    // Set the boat-id dynamically based on the first post
                    const firstPost = document.querySelector('.brf-post-item');
                    if (firstPost) {
                        const boatId = firstPost.getAttribute('data-boat-id');
                        document.getElementById('boat-id').value = boatId;
                        console.log("DEBUG: Boat ID set to:", boatId);
                        // alert("DEBUG5: Boat ID after fetching posts: " + boatId);
                    }

                    // Reinitialize the calendar after AJAX content is loaded
                    if (calendar) {
                        calendar.destroy(); // Destroy the existing calendar instance
                    }
                    initializeCalendar(); // Reinitialize the calendar
                } else {
                    document.getElementById('brf-post-results').innerHTML = '<p>Error fetching posts.</p>';
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("AJAX Error:", textStatus, errorThrown);
                document.getElementById('brf-post-results').innerHTML = '<p>Error fetching posts.</p>';
            }
        });
    }

    // Function to set the active button
    function brfSetActiveButton(buttonId) {
        document.querySelectorAll('#brf-filter-buttons button').forEach(function(btn) {
            btn.classList.remove('active');
        });
        document.getElementById(buttonId).classList.add('active');
    }

    // Function to update the selected date title
    function updateSelectedDateTitle(selectedDate) {
        document.getElementById('selected-date-title').innerText = 'Selected Date: ' + selectedDate;
    }

    // Function to fetch time slots for the selected date
    function fetchTimeSlots(selectedDate) {
        const boatId = document.getElementById('boat-id').value;
        if (!boatId) {
            console.error("DEBUG: Boat ID is not set.");
            // alert("DEBUG5: Boat ID is not set.");
            return;
        }

        jQuery.ajax({
            url: brfAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'brf_plugin_fetch_time_slots',
                date: selectedDate,
                boat_id: boatId
            },
            success: function(response) {
                console.log("DEBUG: AJAX response for time slots:", response);
                if (response.success) {
                    const slots = response.data.slots;
                    let slotsHtml = '';
            
                    if (slots.length > 0) {
                        slots.forEach(slot => {
                            const boatsAvailable = slot.boats_available !== null ? slot.boats_available : 0;
                    
                            if (slot.available) {
                                slotsHtml += `
                                    <button class="time-slot" data-time="${slot.time}">
                                        ${slot.time} - Boats Available: ${boatsAvailable}
                                    </button>
                                `;
                            } else {
                                slotsHtml += `
                                    <button class="time-slot unavailable" data-time="${slot.time}" disabled>
                                        ${slot.time} - Boats Available: ${boatsAvailable} (Booked)
                                    </button>
                                `;
                            }
                        });
                    } else {
                        slotsHtml = '<p>No slots available for ' + selectedDate + '</p>';
                    }
                    document.getElementById('dynamic-time-slots-container').innerHTML = slotsHtml;

                    // Add event listeners to time slot buttons
                    document.querySelectorAll('.time-slot').forEach(button => {
                        button.addEventListener('click', function() {
                            // Deselect previously selected buttons
                            document.querySelectorAll('.time-slot').forEach(btn => btn.classList.remove('selected'));

                            // Mark this button as selected
                            this.classList.add('selected');

                            // Enable the "Book Now" button
                            document.getElementById('book-now-btn').disabled = false;

                            // Set the selected time in the hidden field
                            document.getElementById('selected-time').value = this.dataset.time;
                        });
                    });
                } else {
                    document.getElementById('dynamic-time-slots-container').innerHTML = '<p>Error fetching time slots.</p>';
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("AJAX Error:", textStatus, errorThrown);
                document.getElementById('dynamic-time-slots-container').innerHTML = '<p>Error fetching time slots.</p>';
            }
        });
    }

    // Add event listener for the "Book Now" button
    document.getElementById('book-now-btn').addEventListener('click', function() {
        const selectedTime = document.getElementById('selected-time').value;
        const boatId = document.getElementById('boat-id').value;
        const dateTitle = document.getElementById('selected-date-title').textContent;
        const selectedDate = dateTitle.replace('Selected Date: ', '');
        const boatHours = document.getElementById('booking-hours').value.trim();
        
        

        // Build query string
        const query = `?boat_id=${encodeURIComponent(boatId)}&time_slot=${encodeURIComponent(selectedTime)}&date=${encodeURIComponent(selectedDate)}&booking_hours=${encodeURIComponent(boatHours)}`;

        // Redirect to the form page
        window.location.href = '/boat-booking' + query;
    });

    // Add event listener for the "Check Availability" button
    const checkAvailabilityBtn = document.getElementById('check-availability-btn'); // Unique ID
    if (checkAvailabilityBtn) {
        checkAvailabilityBtn.addEventListener('click', function () {
            const selectedDate = document.getElementById('selected-date-title').textContent.replace('Selected Date: ', '');
            checkNextAvailability(selectedDate);
        });
    }

    // Function: Check Next Availability (increments date by one day)
    function checkNextAvailability(currentDate) {
        const dateObj = new Date(currentDate);
        dateObj.setDate(dateObj.getDate() + 1);
        const nextDate = dateObj.toISOString().split('T')[0];

        if (nextDate === currentSelectedDate) {
            console.log("Already displaying that date, skipping re-fetch.");
            return;
        }

        currentSelectedDate = nextDate;
        fetchTimeSlots(nextDate); // Fetch time slots for the next date
        updateSelectedDateTitle(nextDate); // Update the selected date title
        highlightSelectedDate(nextDate); // Highlight the next date in the calendar
    }

    // Function to highlight the selected date in the calendar
    function highlightSelectedDate(date) {
        if (calendar) {
            calendar.gotoDate(date); // Navigate to the selected date
        }
    }
});








// 



// 


// document.getElementById('book-now-btn').addEventListener('click', function() {
//     const selectedTime = document.getElementById('selected-time').value;
//     const boatId = document.getElementById('boat-id').value;
//     // The selected date might be stored in a hidden field or extracted from the text
//     const dateTitle = document.getElementById('selected-date-title').textContent;
//     const selectedDate = dateTitle.replace('Selected Date: ', '');
//        const boatHours = document.getElementById('booking-hours').value.trim();
  
//     // Build query string
//     const query = `?boat_id=${encodeURIComponent(boatId)}&time_slot=${encodeURIComponent(selectedTime)}&date=${encodeURIComponent(selectedDate)}&booking_hours=${encodeURIComponent(boatHours)}`;
  
//     // Redirect to the form page
//     window.location.href = '/boat-booking' + query;
//   });









