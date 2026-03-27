@echo off
set cmd=%1
set arg1=%2
set arg2=%3

C:\xampp\php\php.exe -f .\controllers\JobTableRunner.php -- %cmd% %arg1% %arg2%
