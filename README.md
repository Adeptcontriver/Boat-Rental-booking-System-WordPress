Boat Rental Booking System – WordPress

A fully customized WordPress booking system built with Custom Post Types, Advanced Custom Fields (ACF), and Gravity Forms to streamline boat rentals and booking approvals.

Key Features

✅ Admin Approval System
	•	Admins can approve, reject, or manage bookings from both the frontend and backend.
	•	Booking status updates automatically upon approval.

✅ Custom Post Types
	1.	Boat Rentals (boat-rental) – Stores details of available boats for rent.
	2.	Bookings (booking) – Manages reservations and their statuses.

ACF Field Groups & Structure

🛥 Boat Rental Details

Stores key details about each rental boat.

Label	Field Name	Type
Boat Name	boat_name	Text
Booking Hours	booking_hours	Number
Hourly Rental Price	hourly_rental_price	Number
Boat Time Slots	boat_time_slots	Repeater
Calendar Start Date	calendar_start_date	Date Picker
Calendar End Date	calendar_end_date	Date Picker

🔹 Boat Time Slots (Repeater Fields)
	•	Time Slot (time_slot) – Type: Time Picker

📅 Boat Booking Status

Tracks booking details and status updates.

Label	Field Name	Type
Boat ID	boat_id	Text
Time Duration	time_duration	Text
Client Name	client_name	Text
Booking Email	booking_email	Text
Booking Date	booking_date	Text
Time Slot	time_slot	Text
Booking Status	booking_status	Select

🔹 Booking Status Options:
	•	Confirmed
	•	Pending (Default)
	•	Cancelled

This system ensures seamless rental management, flexible scheduling, and efficient approval workflows for both admins and customers. 🚀