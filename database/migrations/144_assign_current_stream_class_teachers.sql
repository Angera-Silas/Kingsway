-- Assign the current academic year's explicitly named class-teacher records
-- to every active stream.  The same class teacher may cover both A/B streams
-- where the current staff fixture has one named class teacher for that grade.
UPDATE academic_year_class_streams aycs
JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
JOIN classes c ON c.id = ayc.class_id
JOIN staff s ON s.id = CASE c.code
    WHEN 'PLAYGROUP' THEN 104
    WHEN 'PP1' THEN 105
    WHEN 'PP2' THEN 106
    WHEN 'GRADE1' THEN 101
    WHEN 'GRADE2' THEN 102
    WHEN 'GRADE3' THEN 103
    WHEN 'GRADE4' THEN 107
    WHEN 'GRADE5' THEN 108
    WHEN 'GRADE6' THEN 109
    WHEN 'GRADE7' THEN 98
    WHEN 'GRADE8' THEN 99
    WHEN 'GRADE9' THEN 100
END
SET aycs.class_teacher_id = s.id
WHERE ayc.academic_year_id = 1
  AND aycs.status = 'active'
  AND s.status = 'active';
