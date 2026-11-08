<?php
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="NutriPh_Import_Template.csv"');
echo "Last Name,First Name,M.I.,Gender,Grade Level,Section,Height,Weight,Height Unit,Weight Unit,Guardian Name,Guardian Number,Guardian Email,Date of Birth (YYYY-MM-DD)\n";
echo "Dela Cruz,Juan,P,Male,Grade 3,Sampaguita,120,25,cm,kg,Maria Dela Cruz,09171234567,maria@email.com,2018-01-15\n";
echo "Santos,Ana,M,Female,Grade 4,Rose,115,22,cm,kg,Pedro Santos,09181234567,pedro@email.com,2017-01-15\n";
?>
