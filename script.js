

// Header js
document.addEventListener('DOMContentLoaded', function () {
    console.log("Fishing Charter Booking Plugin script loaded");

    // Initialize FullCalendar
    let calendar;
    let currentSelectedDate = ''; // Track the currently selected date

    function initializeCalendar() {
        const calendarEl = document.getElementById('fc-booking-calendar'); // Unique ID
        if (calendarEl) {
            console.log("DEBUG: Calendar element found");

            // Get today's date in YYYY-MM-DD format
            const today = new Date().toISOString().split('T')[0];
            currentSelectedDate = today; // Set the initial selected date

            calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                initialDate: today, // Set the initial date to today
                selectable: true,
                validRange: {
                    start: today, // Disable past dates
                },
                dateClick: function (info) {
                    const selectedDate = info.dateStr;
                    console.log("DEBUG: Selected date:", selectedDate);
                    updateSelectedDateTitle(selectedDate);
                    fetchTimeSlots(selectedDate);
                    highlightSelectedDate(selectedDate); // Highlight the selected date
                },
            });

            calendar.render();
            console.log("DEBUG: Calendar initialized");

            // Automatically select today's date
            updateSelectedDateTitle(today);
            fetchTimeSlots(today);
            highlightSelectedDate(today); // Highlight today's date
        } else {
            console.error("DEBUG: Calendar container not found");
        }
    }

    // Initialize the calendar on page load
    initializeCalendar();

    // Function to update the selected date title
    function updateSelectedDateTitle(selectedDate) {
        const selectedDateTitle = document.getElementById('fc-selected-date-title'); // Unique ID
        if (selectedDateTitle) {
            // Convert the date from YYYY-MM-DD to DD-MM-YY
            const dateParts = selectedDate.split('-');
            const formattedDate = `${dateParts[2]}-${dateParts[1]}-${dateParts[0].slice(-2)}`;
            selectedDateTitle.innerText = 'Selected Date: ' + formattedDate;
        }
    }

    // Function to fetch time slots for the selected date
    function fetchTimeSlots(selectedDate) {
        const boatId = document.getElementById('fc-boat-id').value; // Unique ID
        if (!boatId) {
            console.error("DEBUG: Boat ID is not set.");
            return;
        }

        // Clear the existing time slots
        const timeSlotsContainer = document.getElementById('fc-dynamic-time-slots-container'); // Unique ID
        if (timeSlotsContainer) {
            timeSlotsContainer.innerHTML = '<p>Loading time slots...</p>';
        }

        jQuery.ajax({
            url: boatRentalAjax.ajax_url, // Ensure this is defined in your localized script
            type: 'POST',
            data: {
                action: 'fishing_charter_fetch_time_slots', // Use the correct action for fishing-charter
                date: selectedDate,
                boat_id: boatId,
            },
            success: function (response) {
                console.log("DEBUG: AJAX response for time slots:", response);
                if (response.success) {
                    const slots = response.data.slots;
                    let slotsHtml = '';

                    if (slots.length > 0) {
                        slotsHtml = '<ul id="fc-time-slot-list">'; // Unique ID
                        slots.forEach(slot => {
                            if (slot.available) {
                                slotsHtml += `
                                    <li>
                                        <button class="fc-time-slot" data-time="${slot.time}"> <!-- Unique Class -->
                                            ${slot.time}
                                        </button>
                                    </li>
                                `;
                            } else {
                                slotsHtml += `
                                    <li>
                                        <button class="fc-time-slot unavailable" data-time="${slot.time}" disabled> <!-- Unique Class -->
                                            ${slot.time} (Booked)
                                        </button>
                                    </li>
                                `;
                            }
                        });
                        slotsHtml += '</ul>';
                    } else {
                        slotsHtml = '<p>No slots available for ' + formatDate(selectedDate) + '</p>';
                    }

                    if (timeSlotsContainer) {
                        timeSlotsContainer.innerHTML = slotsHtml;

                        // Add event listeners to time slot buttons
                        document.querySelectorAll('.fc-time-slot').forEach(button => { // Unique Class
                            button.addEventListener('click', function () {
                                // Deselect previously selected buttons
                                document.querySelectorAll('.fc-time-slot').forEach(btn => btn.classList.remove('selected')); // Unique Class

                                // Mark this button as selected
                                this.classList.add('selected');

                                // Enable the "Book Now" button
                                const bookNowBtn = document.getElementById('fc-book-now-btn'); // Unique ID
                                if (bookNowBtn) {
                                    bookNowBtn.disabled = false;
                                }

                                // Set the selected time in the hidden field
                                const selectedTimeInput = document.getElementById('fc-selected-time'); // Unique ID
                                if (selectedTimeInput) {
                                    selectedTimeInput.value = this.dataset.time;
                                }
                            });
                        });
                    }
                } else {
                    if (timeSlotsContainer) {
                        timeSlotsContainer.innerHTML = '<p>Error fetching time slots.</p>';
                    }
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.error("AJAX Error:", textStatus, errorThrown);
                const timeSlotsContainer = document.getElementById('fc-dynamic-time-slots-container'); // Unique ID
                if (timeSlotsContainer) {
                    timeSlotsContainer.innerHTML = '<p>Error fetching time slots.</p>';
                }
            },
        });
    }

    // Function to highlight the selected date in the calendar
    function highlightSelectedDate(selectedDate) {
        // Remove highlight from previously selected date
        document.querySelectorAll('.selected-day').forEach(function (el) {
            el.classList.remove('selected-day');
        });

        // Highlight the new selected date
        const selectedCell = document.querySelector('[data-date="' + selectedDate + '"]');
        if (selectedCell) {
            selectedCell.classList.add('selected-day');
        }
    }

    // Add event listener for the "Book Now" button
    const bookNowBtn = document.getElementById('fc-book-now-btn'); // Unique ID
    if (bookNowBtn) {
        bookNowBtn.addEventListener('click', function () {
            const selectedTime = document.getElementById('fc-selected-time').value; // Unique ID
            const boatId = document.getElementById('fc-boat-id').value; // Unique ID
            const dateTitle = document.getElementById('fc-selected-date-title').textContent; // Unique ID
            const selectedDate = dateTitle.replace('Selected Date: ', '');

            // Build query string
            const query = `?boat_id=${encodeURIComponent(boatId)}&time_slot=${encodeURIComponent(selectedTime)}&date=${encodeURIComponent(selectedDate)}`;

            // Redirect to the booking form page
            window.location.href = '/fishing-charters-booking/' + query;
        });
    }

    // Add event listener for the "Check Availability" button
    const checkAvailabilityBtn = document.getElementById('fc-check-availability-btn'); // Unique ID
    if (checkAvailabilityBtn) {
        checkAvailabilityBtn.addEventListener('click', function () {
            const selectedDateText = document.getElementById('fc-selected-date-title').textContent.replace('Selected Date: ', '');
            checkNextAvailability(selectedDateText);
        });
    }

    // Function: Check Next Availability (increments date by one day)
    function checkNextAvailability(currentDate) {
        // Convert currentDate to YYYY-MM-DD format if it's in DD-MM-YY format
        const dateParts = currentDate.split('-');
        const formattedDate = dateParts.length === 3 && dateParts[2].length === 2
            ? `20${dateParts[2]}-${dateParts[1]}-${dateParts[0]}` // Convert DD-MM-YY to YYYY-MM-DD
            : currentDate; // Assume it's already in YYYY-MM-DD format

        const dateObj = new Date(formattedDate);
        dateObj.setDate(dateObj.getDate() + 1);
        const nextDate = dateObj.toISOString().split('T')[0];

        // Compare the next date with the current selected date before updating
        if (nextDate === currentSelectedDate) {
            console.log("Already displaying that date, skipping re-fetch.");
            return;
        }

        // Update the current selected date
        currentSelectedDate = nextDate;

        // Fetch time slots for the next date
        fetchTimeSlots(nextDate);

        // Update the selected date title
        updateSelectedDateTitle(nextDate);

        // Highlight the next date in the calendar
        highlightSelectedDate(nextDate);
    }

    // Helper function to format date as DD-MM-YY
    function formatDate(date) {
        const dateParts = date.split('-');
        return `${dateParts[2]}-${dateParts[1]}-${dateParts[0].slice(-2)}`;
    }
});



// Footer Js


document.addEventListener('DOMContentLoaded', function () {
    console.log("Drop-Off Rental Booking Plugin script loaded");

    // Initialize FullCalendar
    let calendar;
    let currentSelectedDate = ''; // Track the currently selected date

    function initializeCalendar() {
        const calendarEl = document.getElementById('dor-booking-calendar'); // Unique ID
        if (calendarEl) {
            console.log("DEBUG: Calendar element found");

            // Get today's date in YYYY-MM-DD format
            const today = new Date().toISOString().split('T')[0];
            currentSelectedDate = today; // Set the initial selected date

            calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                initialDate: today, // Set the initial date to today
                selectable: true,
                validRange: {
                    start: today, // Disable past dates
                },
                dateClick: function (info) {
                    const selectedDate = info.dateStr;
                    console.log("DEBUG: Selected date:", selectedDate);
                    updateSelectedDateTitle(selectedDate);
                    fetchTimeSlots(selectedDate);
                    highlightSelectedDate(selectedDate); // Highlight the selected date
                },
            });

            calendar.render();
            console.log("DEBUG: Calendar initialized");

            // Automatically select today's date
            updateSelectedDateTitle(today);
            fetchTimeSlots(today);
            highlightSelectedDate(today); // Highlight today's date
        } else {
            console.error("DEBUG: Calendar container not found");
        }
    }

    // Initialize the calendar on page load
    initializeCalendar();

    // Function to update the selected date title
    function updateSelectedDateTitle(selectedDate) {
        const selectedDateTitle = document.getElementById('dor-selected-date-title'); // Unique ID
        if (selectedDateTitle) {
            // Format the date as DD-MM-YY
            const formattedDate = formatDate(selectedDate);
            selectedDateTitle.innerText = 'Selected Date: ' + formattedDate;
        }
    }

    // Function to fetch time slots for the selected date
    function fetchTimeSlots(selectedDate) {
        const boatId = document.getElementById('dor-boat-id').value; // Unique ID
        if (!boatId) {
            console.error("DEBUG: Boat ID is not set.");
            return;
        }

        // Clear the existing time slots
        const timeSlotsContainer = document.getElementById('dor-dynamic-time-slots-container'); // Unique ID
        if (timeSlotsContainer) {
            timeSlotsContainer.innerHTML = '<p>Loading time slots...</p>';
        }

        jQuery.ajax({
            url: boatRentalAjax.ajax_url, // Ensure this is defined in your localized script
            type: 'POST',
            data: {
                action: 'drop_off_rental_fetch_time_slots', // Use the correct action for drop-off-rental
                date: selectedDate,
                boat_id: boatId,
            },
            success: function (response) {
                console.log("DEBUG: AJAX response for time slots:", response);
                if (response.success) {
                    const slots = response.data.slots;
                    let slotsHtml = '';

                    if (slots.length > 0) {
                        slotsHtml = '<ul id="dor-time-slot-list">'; // Unique ID
                        slots.forEach(slot => {
                            if (slot.available) {
                                slotsHtml += `
                                    <li>
                                        <button class="dor-time-slot" data-time="${slot.time}"> <!-- Unique Class -->
                                            ${slot.time}
                                        </button>
                                    </li>
                                `;
                            } else {
                                slotsHtml += `
                                    <li>
                                        <button class="dor-time-slot unavailable" data-time="${slot.time}" disabled> <!-- Unique Class -->
                                            ${slot.time} (Booked)
                                        </button>
                                    </li>
                                `;
                            }
                        });
                        slotsHtml += '</ul>';
                    } else {
                        // Format the date as DD-MM-YY
                        const formattedDate = formatDate(selectedDate);
                        slotsHtml = '<p>No slots available for ' + formattedDate + '</p>';
                    }

                    if (timeSlotsContainer) {
                        timeSlotsContainer.innerHTML = slotsHtml;

                        // Add event listeners to time slot buttons
                        document.querySelectorAll('.dor-time-slot').forEach(button => { // Unique Class
                            button.addEventListener('click', function () {
                                // Deselect previously selected buttons
                                document.querySelectorAll('.dor-time-slot').forEach(btn => btn.classList.remove('selected')); // Unique Class

                                // Mark this button as selected
                                this.classList.add('selected');

                                // Enable the "Book Now" button
                                const bookNowBtn = document.getElementById('dor-book-now-btn'); // Unique ID
                                if (bookNowBtn) {
                                    bookNowBtn.disabled = false;
                                }

                                // Set the selected time in the hidden field
                                const selectedTimeInput = document.getElementById('dor-selected-time'); // Unique ID
                                if (selectedTimeInput) {
                                    selectedTimeInput.value = this.dataset.time;
                                }
                            });
                        });
                    }
                } else {
                    if (timeSlotsContainer) {
                        timeSlotsContainer.innerHTML = '<p>Error fetching time slots.</p>';
                    }
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.error("AJAX Error:", textStatus, errorThrown);
                const timeSlotsContainer = document.getElementById('dor-dynamic-time-slots-container'); // Unique ID
                if (timeSlotsContainer) {
                    timeSlotsContainer.innerHTML = '<p>Error fetching time slots.</p>';
                }
            },
        });
    }

    // Function to highlight the selected date in the calendar
    function highlightSelectedDate(selectedDate) {
        // Remove highlight from previously selected date
        document.querySelectorAll('.selected-day').forEach(function (el) {
            el.classList.remove('selected-day');
        });

        // Highlight the new selected date
        const selectedCell = document.querySelector('[data-date="' + selectedDate + '"]');
        if (selectedCell) {
            selectedCell.classList.add('selected-day');
        }
    }

    // Add event listener for the "Book Now" button
    const bookNowBtn = document.getElementById('dor-book-now-btn'); // Unique ID
    if (bookNowBtn) {
        bookNowBtn.addEventListener('click', function () {
            const selectedTime = document.getElementById('dor-selected-time').value; // Unique ID
            const boatId = document.getElementById('dor-boat-id').value; // Unique ID
            const dateTitle = document.getElementById('dor-selected-date-title').textContent; // Unique ID
            const selectedDate = dateTitle.replace('Selected Date: ', '');

            // Build query string
            const query = `?boat_id=${encodeURIComponent(boatId)}&time_slot=${encodeURIComponent(selectedTime)}&date=${encodeURIComponent(selectedDate)}`;

            // Redirect to the booking form page
            window.location.href = '/drop-off-rental-boat-booking/' + query;
        });
    }

    // Add event listener for the "Check Availability" button
    const checkAvailabilityBtn = document.getElementById('dor-check-availability-btn'); // Unique ID
    if (checkAvailabilityBtn) {
        checkAvailabilityBtn.addEventListener('click', function () {
            const selectedDateText = document.getElementById('dor-selected-date-title').textContent.replace('Selected Date: ', '');
            checkNextAvailability(selectedDateText);
        });
    }

    // Function: Check Next Availability (increments date by one day)
    function checkNextAvailability(currentDate) {
        // Convert currentDate to YYYY-MM-DD format if it's in DD-MM-YY format
        const dateParts = currentDate.split('-');
        const formattedDate = dateParts.length === 3 && dateParts[2].length === 2
            ? `20${dateParts[2]}-${dateParts[1]}-${dateParts[0]}` // Convert DD-MM-YY to YYYY-MM-DD
            : currentDate; // Assume it's already in YYYY-MM-DD format

        const dateObj = new Date(formattedDate);
        dateObj.setDate(dateObj.getDate() + 1);
        const nextDate = dateObj.toISOString().split('T')[0];

        // Compare the next date with the current selected date before updating
        if (nextDate === currentSelectedDate) {
            console.log("Already displaying that date, skipping re-fetch.");
            return;
        }

        // Update the current selected date
        currentSelectedDate = nextDate;

        // Fetch time slots for the next date
        fetchTimeSlots(nextDate);

        // Update the selected date title
        updateSelectedDateTitle(nextDate);

        // Highlight the next date in the calendar
        highlightSelectedDate(nextDate);
    }

    // Helper function to format date as DD-MM-YY
    function formatDate(date) {
        const dateParts = date.split('-');
        return `${dateParts[2]}-${dateParts[1]}-${dateParts[0].slice(-2)}`;
    }
});
