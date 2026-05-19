@echo off
echo =======================================================
echo XAMPP MySQL Crash Fixer
echo =======================================================
echo This script will backup your corrupted data folder and 
echo restore the base files from XAMPP backup while preserving 
echo your databases (pms_db).
echo.
echo Please MAKE SURE you have stopped MySQL in the XAMPP Control Panel.
echo Press any key to continue...
pause > nul

set MYSQL_DIR=C:\xampp\mysql
set DATA_DIR=%MYSQL_DIR%\data
set BACKUP_DIR=%MYSQL_DIR%\backup
set OLD_DATA_DIR=%MYSQL_DIR%\data_old

if exist "%OLD_DATA_DIR%" (
    echo [ERROR] The folder "data_old" already exists in C:\xampp\mysql\
    echo Please delete or rename it before running this script.
    pause
    exit /b
)

echo.
echo [1/4] Renaming "data" folder to "data_old"...
ren "%DATA_DIR%" "data_old"

echo [2/4] Restoring fresh "data" folder from "backup"...
xcopy "%BACKUP_DIR%" "%DATA_DIR%" /E /I /H /Y > nul

echo [3/4] Restoring your custom database (pms_db)...
xcopy "%OLD_DATA_DIR%\pms_db" "%DATA_DIR%\pms_db" /E /I /H /Y > nul

echo [4/4] Restoring main InnoDB data file (ibdata1)...
copy /Y "%OLD_DATA_DIR%\ibdata1" "%DATA_DIR%\ibdata1" > nul

echo.
echo =======================================================
echo FIX COMPLETE!
echo =======================================================
echo 1. Open the XAMPP Control Panel.
echo 2. Click "Start" next to MySQL.
echo 3. Once it's running, you can safely delete the "data_old" folder in C:\xampp\mysql\
echo.
pause
