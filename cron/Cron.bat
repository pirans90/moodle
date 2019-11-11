@echo off
:loop
php C:\xampp\htdocs\moodle\admin\cli\cron.php
@timeout /t 180
@goto loop
