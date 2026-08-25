-- Learning-area identities present in the assessment catalogue/KICD level
-- designs but absent from the previous rationalised list. Curriculum rows
-- are populated separately from their authoritative designs.
INSERT INTO learning_areas (name, code, level_band, description, status, levels, is_optional)
SELECT x.name, x.code, x.level_band, x.description, 'active', x.levels, x.is_optional
FROM (
    SELECT 'Language & Communication' name,'LAC' code,'playgroup' level_band,'Playgroup learning area from the assessment catalogue' description,'Playgroup' levels,0 is_optional
    UNION ALL SELECT 'Numeracy Foundations','NUMF','playgroup','Playgroup learning area from the assessment catalogue','Playgroup',0
    UNION ALL SELECT 'Environmental Exploration','ENVE','playgroup','Playgroup learning area from the assessment catalogue','Playgroup',0
    UNION ALL SELECT 'Self-Help Skills','SELF','playgroup','Playgroup learning area from the assessment catalogue','Playgroup',0
    UNION ALL SELECT 'Hygiene & Nutrition','HYNU','lower_primary','Lower primary learning area from the assessment catalogue','Grade 1, Grade 2, Grade 3',0
    UNION ALL SELECT 'Creative Arts','CART','playgroup','Playgroup learning area from the assessment catalogue','Playgroup',0
    UNION ALL SELECT 'Music & Movement','MUMO','playgroup','Playgroup learning area from the assessment catalogue','Playgroup',0
    UNION ALL SELECT 'Home Science','HSCI','upper_primary','Upper primary learning area from the KICD design catalogue','Grade 4, Grade 5, Grade 6',0
    UNION ALL SELECT 'Physical & Health Education','PHE','upper_primary','Upper primary learning area from the KICD design catalogue','Grade 4, Grade 5, Grade 6',0
    UNION ALL SELECT 'Foreign Language (Optional)','FOLA','upper_primary','Optional upper primary learning area from the KICD design catalogue','Grade 4, Grade 5, Grade 6',1
    UNION ALL SELECT 'Health Education','HLTH','junior_secondary','Junior secondary learning area from the KICD design catalogue','Grade 7, Grade 8, Grade 9',0
    UNION ALL SELECT 'Business Studies','BSTD','junior_secondary','Junior secondary learning area from the KICD design catalogue','Grade 7, Grade 8, Grade 9',0
    UNION ALL SELECT 'Life Skills','LFSL','junior_secondary','Junior secondary learning area from the KICD design catalogue','Grade 7, Grade 8, Grade 9',0
    UNION ALL SELECT 'Computer Science','COMP','junior_secondary','Junior secondary learning area from the KICD design catalogue','Grade 7, Grade 8, Grade 9',0
    UNION ALL SELECT 'Foreign Languages (Optional JSS)','FLOJ','junior_secondary','Optional junior secondary learning area from the KICD design catalogue','Grade 7, Grade 8, Grade 9',1
    UNION ALL SELECT 'Indigenous Languages (Optional JSS)','INLG','junior_secondary','Optional junior secondary learning area from the KICD design catalogue','Grade 7, Grade 8, Grade 9',1
    UNION ALL SELECT 'Performing Arts Advanced','PADA','junior_secondary','Junior secondary learning area from the KICD design catalogue','Grade 7, Grade 8, Grade 9',0
    UNION ALL SELECT 'Visual Arts Advanced','VAAD','junior_secondary','Junior secondary learning area from the KICD design catalogue','Grade 7, Grade 8, Grade 9',0
) x
LEFT JOIN learning_areas existing ON existing.code=x.code AND existing.level_band=x.level_band
WHERE existing.id IS NULL;
