# cbReader - a web-based CBZ/CBR reader

![screenshot-2017-10-15 cbreader](https://user-images.githubusercontent.com/126137/31585958-9d4544a0-b1ca-11e7-9096-0692ffec9d49.png)

### What?
cbReader is my attempt at writing a simple web-based comic book reader in PHP and JS.
It does not offer any reading of metadata or even fancy management features.
Tested (and intended for use) with Mylar's directory structure.

### How?
* Download the code and put it in a folder where your PHP-enabled webserver can access it. 
* Copy `config.php-dist` to `config.php` and edit the file to point to the folder with your comics in it.
* Open the page on your webserver.

Make sure your PHP (mod_php / php-fpm) has the ImageMagick, ZIP and RAR modules installed, and that it is allowed to create a `cache` folder in the document root.
