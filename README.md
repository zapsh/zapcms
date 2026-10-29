# ZAP CMS

##  环境要求
- PHP 8.2+
- MYSQL5.5+
- Sqlite3 可选
- PDO Driver (MySQL、Sqlite3)


### URL Rewrite

#### Apache
````apacheconf
Options +FollowSymLinks -Indexes
RewriteEngine On
 
RewriteCond %{HTTP:Authorization} .
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
 
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [QSA,PT,L]
````


#### Nginx
````text
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
````

