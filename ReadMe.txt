---------------------------------------------------------------
httpd.conf
---------------------------------------------------------------
แก้ :
Listen 192.168.16.232:8080
LoadModule rewrite_module modules/mod_rewrite.so (เอา # ออก)
ServerName 192.168.16.232:8080
Include conf/extra/httpd-vhosts.conf (เอา # ออก)

เพิ่ม:
<Directory "D:/- User/Siwanart/Documents/IT HTML">
    AllowOverride All
    Require all granted
</Directory>
---------------------------------------------------------------
httpd-ssl.conf
---------------------------------------------------------------
เพิ่ม:
<VirtualHost _default_:443>

DocumentRoot "c:/xampp/htdocs"
ServerName localhost
ServerAdmin localhost
ErrorLog "c:/xampp/apache/logs/error.log"
TransferLog "c:/xampp/apache/logs/access.log"

DocumentRoot "D:/- User/Siwanart/Documents/IT HTML"
ServerName reports.bew.co.th
ServerAdmin admin@reports.bew.co.th
ErrorLog "c:/xampp/apache/logs/error.log"
TransferLog "c:/xampp/apache/logs/access.log"
---------------------------------------------------------------
httpd-vhosts.conf
---------------------------------------------------------------
# Default localhost virtual host
<VirtualHost *:8080>
    DocumentRoot "C:/xampp/htdocs"
    ServerName localhost
</VirtualHost>

# Your custom virtual host
<VirtualHost 192.168.16.232:8080>
    DocumentRoot "D:/- User/Siwanart/Documents/IT HTML"
    ServerName reports.bew.co.th
    <Directory "D:/- User/Siwanart/Documents/IT HTML">
	DirectoryIndex Home.php Home.html
        Options Indexes FollowSymLinks ExecCGI Includes
        Require all granted
        AllowOverride All
	Order allow,deny
        Allow from all 
    </Directory>
</VirtualHost>
---------------------------------------------------------------