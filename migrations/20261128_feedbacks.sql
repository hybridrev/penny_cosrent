CREATE TABLE IF NOT EXISTS feedbacks (
  feedback_id int(11) NOT NULL AUTO_INCREMENT,
  user_id int(11) DEFAULT NULL,
  name varchar(100) DEFAULT NULL,
  email varchar(100) DEFAULT NULL,
  message text NOT NULL,
  created_at timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (feedback_id),
  KEY user_id (user_id),
  CONSTRAINT feedbacks_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
