SELECT ASARisk, count(*)
FROM `Operation`
GROUP BY ASARisk
ORDER BY ASARisk


SELECT *
FROM Operation
WHERE 1 IN (`rel_anamie`, `rel_diabetes`, `rel_adipositas`, `rel_gerinnungsstoerung`, `rel_allergie`, `rel_immunsuppression`, `rel_medikamente`, `rel_malignom`, `rel_schwangerschaft`)

SELECT PatGender, count(*) as amount
FROM Operation
WHERE SGARCode1 IN ('A20.1','A20.2')
	OR SGARCode2 IN ('A20.1','A20.2')
	OR SGARCode3 IN ('A20.1','A20.2')
	AND PatGender = 2
GROUP BY PatGender;

SELECT PatGender, count(*) as amount
FROM Operation
WHERE SGARCode1 IN ('A40.5')
	OR SGARCode2 IN ('A40.5')
	OR SGARCode3 IN ('A40.5')
GROUP BY PatGender;


