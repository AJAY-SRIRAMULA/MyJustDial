# MyJustDial

PHP and MySQL-based directory website and administration project.

## Local setup

1. Place the project folder in your XAMPP `htdocs` directory.
2. Start Apache and MySQL.
3. Create the `launc58415_directory` database in phpMyAdmin and import the project's database SQL file if you have an authorized clean database export.
4. Configure your local database credentials in `config/config.php` (use `config/config.example.php` as a reference).
5. Open the project through `http://localhost/MyJustDialNew/`.

## Git hygiene

Local configuration, uploaded/member files, logs, IDE files, and temporary artifacts are excluded by `.gitignore`. Do not commit real database exports, credentials, or personal/member upload data.
