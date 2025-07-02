
📌 Hourly Boat Rental System

A simple and efficient system for managing boat rentals, supporting hourly and drop-off rentals, with real-time availability tracking and overlap prevention.

⸻

📋 Overview

The Hourly Boat Rental System enables users to book boats for durations of 2, 3, 4, or 5 hours, with a maximum capacity of 2 boats per time slot. It supports:
	•	Dynamic time slot availability
	•	Blocked date management
	•	Interlinked boat IDs for shared availability
	•	Integration with Advanced Custom Fields (ACF) and Gravity Forms

The system is available as a WordPress plugin or can be adapted as a standalone web app.

⸻

✨ Features
	•	⏳ Time Slot Availability
Displays real-time 30-minute time slots based on current bookings.
	•	⏱️ Multiple Durations
Supports rental durations of 2, 3, 4, or 5 hours.
	•	🚫 Overlap Prevention
Ensures that no more than 2 boats are booked in any overlapping time slot.
	•	📅 Blocked Dates
Block specific dates for hourly, drop-off, or both types of rentals.
	•	🔗 Interlinked Boats
Boats sharing the same interlinked_id (e.g., boat-001) will follow shared availability rules and bookings.

⸻

🔧 Changes and Updates

📂 Updated Files

/wp-content/themes/hello-elementor-child/functions.php
	•	Change: Added a booking-hours field in ACF and integrated it with the booking post type. Data from Gravity Forms is used to allocate internal time slots across all boats.
	•	Purpose: Automatically assigns and manages time slots based on booking hours, improving coordination and tracking.

⸻

/wp-content/plugins/boat-rental-filter/boat-rental-filter.php
	•	Change:
	•	Implemented interlinking logic using interlinked_id.
	•	Fixed blocked dates logic to correctly isolate hourly and drop-off rentals.
	•	Purpose:
	•	Ensures boats with the same interlinked_id share availability.
	•	Drop-off blocked dates (e.g., 2025-07-25) no longer affect hourly rentals unless both is selected in ACF.

⸻

/wp-content/plugins/boat-rental-filter/assets/boat-rental-filter.js
	•	Change: Replaced entire file with updated logic.
	•	Purpose: Improves user experience by accurately showing available time slots in the calendar, aligned with backend rules.

⸻

🌟 Key Improvements
	•	📅 Type-Specific Blocking
Blocked dates now affect only the specified rental type (hourly, drop_off, or both).
	•	🔗 Interlinked Boat Availability
Boats with the same interlinked_id are treated as a shared pool, avoiding overbookings.
	•	📋 Gravity Forms Integration
Booked hours from Gravity Forms are automatically processed to calculate slot usage and update availability.

⸻

📅 Existing Bookings (Example)
	•	12:00 PM – 2:00 PM (2 hours, 2 boats)
	•	1:00 PM – 4:00 PM (3 hours, 1 boat)

⸻

✅ Testing Scenarios

Duration	Time Slot	Expected Result
2-Hour	8:00 AM	✅ Should work
2-Hour	12:00 PM	❌ Should fail (already 2 boats booked)
3-Hour	9:00 AM	❌ Should fail (overlaps with 12:00 PM)
3-Hour	2:00 PM	✅ Should work (1 boat available)
4-Hour	1:00 PM	⚠️ Might fail or work (depends on overlap logic)
4-Hour	3:00 PM	✅ Should work
5-Hour	10:00 AM	❌ Should fail
5-Hour	3:00 PM	✅ Should work (2 boats available)

Blocked Dates
	•	Test 2025-07-25:
	•	Set as drop_off: ❌ Drop-off only blocked
	•	Set as both: ❌ All rentals blocked

⸻
