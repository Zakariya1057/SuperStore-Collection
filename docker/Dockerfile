FROM mattrayner/lamp:latest-1804

RUN apt-get update && sudo apt-get upgrade -y \
    && apt install curl php-xml php-mbstring php-mysql git unzip -y 

RUN mkdir -p /home/user/

WORKDIR /home/user/

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php -r "if (hash_file('sha384', 'composer-setup.php') === '756890a4488ce9024fc62c56153228907f1545c228516cbf63f885e036d37e9a59d27d63f46af1d4d07ee0f76181c7d3') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" \
    && php composer-setup.php \
    && php -r "unlink('composer-setup.php');" \
    && mv composer.phar /usr/local/bin/composer \
    && rm -rf /var/www/html

WORKDIR /var/www

RUN git clone https://github.com/Zakariya1057/SuperStore-API \
    && mv SuperStore-API /var/www/html \

WORKDIR /var/www/html

RUN composer install

COPY 
# composer install
# Create env file (get from computer). -> Change host name
# sudo vim /etc/apache2/sites-enabled/000-default.conf (change html dir to /var/www/html/public)
# sudo vim /etc/apache2/apache2.conf (Allow override ALL in /var/www) 
# sudo a2enmod rewrite
# sudo service apache2 restart
# sudo chown -R $USER:www-data storage
# sudo chown -R $USER:www-data bootstrap/cache
# chmod -R 775 storage
# chmod -R 775 bootstrap/cache
# sudo chown ubuntu:www-data /var/www/html/storage/ -R
# sudo apt-get install nodejs npm -y
# npm install -g npm@latest
# npm install && sudo npm run dev
# npm audit fix
# https://laravel.com/docs/8.x/queues#supervisor-configuration
# sudo apt install redis-server -y
# https://www.digitalocean.com/community/tutorials/how-to-install-and-secure-redis-on-ubuntu-18-04
# sudo nano /etc/redis/redis.conf ( supervised systemd )
# sudo systemctl restart redis.service
# crontab ( * * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1 )
# cd /var/www/html/ 
# sudo chmod +x scripts/backup.sh

# (Change AWS Access IP)
