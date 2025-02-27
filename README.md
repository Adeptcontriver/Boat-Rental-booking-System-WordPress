# Boat-Rental-booking-System-WordPress

WordPress Booking Rental System Created With Custom Post types, Acf Field , Gravity Form

Summary:



Admin Approval Feature : Admin can Approve booking from the frontend and backend as well.


We Have Two Custom Post Types:
post type = boat-rental
post type = booking

Acf Field Groups

Boat Rental Details:
Boat Booking Status:
Boat Rental Filter:

Boat Rental Details:
Acf Field Names and Type:
    Label : Boat Name -> Name : boat_name -> Type : Text
    Label : Booking Hours -> Name : booking_hours -> Type : Number
    Label : Hourly Rental Price -> Name : hourly_rental_price -> Type : Number
    Label : Boat Time Slots -> Name : boat_time_slots -> Type : Repeator

            Sub Field: 
            Label : Time Slots -> Name : time_slot -> Type : Time Picker
   
    Label : Calendar Start Date -> Name : calendar_start_date -> Type : Date Picker
    Label : Calendar End Date -> Name : calendar_end_date -> Type : Date Picker


Boat Booking Status:
Acf Field Names and Type:

    Label : Boat Id -> Name : boat_id -> Type : Text
    Label : Time Duration -> Name : time_duration -> Type : Text
    Label : Client Name -> Name : client_name -> Type : Text
    Label : Booking Email -> Name : booking_email -> Type : Text
    Label : Booking Date -> Name : booking_date -> Type : Text
    Label : Time Slot -> Name : time_slot -> Type : Text
    Label : Booking Status -> Name : booking_status -> Type : Select

            Select Option: 

                Confirmed : Confirmed
                pending   : Pending
                cancelled : Cancelled

                Default Value: pending
