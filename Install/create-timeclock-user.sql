CREATE USER 'timeclock'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON *.* TO 'timeclock'@'localhost' WITH GRANT OPTION;
FLUSH PRIVILEGES;
