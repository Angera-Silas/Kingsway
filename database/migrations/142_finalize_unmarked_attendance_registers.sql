ALTER TABLE attendance_registers
    MODIFY status ENUM('scheduled','open','overdue','not_marked','completed','closed','not_required') NOT NULL DEFAULT 'scheduled';

ALTER TABLE attendance_register_events
    MODIFY event_type ENUM('opened','reminder','overdue','escalated','completed','not_marked','closed') NOT NULL;
