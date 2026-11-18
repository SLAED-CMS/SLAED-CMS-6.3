# Installing SLAED CMS

## Requirements

- PHP 8.4 or newer with PDO MySQL, JSON, mbstring, GD and cURL, plus Zip and Zlib for archives and backups
- MySQL 8.0.16 or MariaDB 10.5.2 and newer, InnoDB and utf8mb4
- Apache or LiteSpeed with `mod_rewrite`, or nginx

## Steps

1. Unpack the archive and upload its content to the server
2. Point the document root of the site at the folder `public/`
3. Let PHP write to the folders `config/`, `storage/` and `uploads/`
4. Create an empty database and a user for it
5. Open `https://your-site/setup.php` and follow the steps of the installer
6. Save the address of the panel the last step shows

The installer does not create the database, only the tables in it.

## Document root

Only the folder `public/` may be reachable from the web. The folders `config/`, `storage/` and `uploads/` hold passwords,
logs, backups and private files.

- Apache and LiteSpeed read `public/.htaccess` and need `AllowOverride All`
- nginx reads no `.htaccess`, so copy the `server` block of `nginx.conf.example` and set `server_name`, `root` and
  `fastcgi_pass` in it
- When the host does not let you change the document root and runs Apache or LiteSpeed, upload the whole project into
  it, and its `.htaccess` sends every request into `public/`

On nginx the whole project as document root opens the private folders to everyone.

## Important

Run the installer right after the upload. Until it has finished, whoever opens `setup.php` first installs the site. At
the end the installer deletes itself. The site has no link to the panel, so keep its address.

More on https://slaed.net and in the repository https://github.com/SLAED-CMS/SLAED.
