ALTER TABLE rentals
  MODIFY user_id int(11) DEFAULT NULL,
  ADD renter_name varchar(100) DEFAULT NULL AFTER user_id,
  ADD renter_email varchar(100) DEFAULT NULL AFTER renter_name,
  ADD renter_phone varchar(20) DEFAULT NULL AFTER renter_email,
  ADD renter_address text DEFAULT NULL AFTER renter_phone;
