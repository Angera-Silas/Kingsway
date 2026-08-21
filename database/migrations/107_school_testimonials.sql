CREATE TABLE IF NOT EXISTS school_testimonials (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  person_name   VARCHAR(150) NOT NULL,
  role_label    VARCHAR(150) NOT NULL COMMENT 'e.g. Parent, Grade 6 or Alumni, Class of 2019',
  testimonial   TEXT         NOT NULL,
  stars         TINYINT UNSIGNED NOT NULL DEFAULT 5,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  display_order INT          NOT NULL DEFAULT 0,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO school_testimonials (person_name, role_label, testimonial, stars, display_order) VALUES
('Mrs. Akinyi Otieno', 'Parent, Grade 6', 'Kingsway has transformed my daughter completely. The teachers genuinely care, the CBC teaching is excellent, and she has grown so much in confidence and character.', 5, 10),
('Brian Kiprotich', 'Alumni, Class of 2019', 'As an alumni who went through KCPE here, I can say the foundation Kingsway gave me opened doors to the best secondary schools and beyond. The values still guide me.', 5, 20),
('Mr. Samuel Cheruiyot', 'Parent, Grade 8', 'The boarding facilities and pastoral care are exceptional. My son feels at home here. The staff treats every child as their own. We are extremely satisfied.', 5, 30);
