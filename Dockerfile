FROM wazobiatech/php-fpm:altair

# Set working directory
WORKDIR /var/www/html

RUN mkdir -p bootstrap/cache \
    storage/framework/sessions \
    storage/framework/cache \
    storage/framework/views

# Copy composer.lock and composer.json
COPY . /var/www/html

RUN composer install

RUN php /var/www/html/env.validate.php

RUN chown -R www-data:www-data \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache

RUN chmod -R 777 storage

# Change Port
# RUN sed -i "s/9000/9004/" /usr/local/etc/php-fpm.d/www.conf
RUN sed -i "s/9000/9004/" /usr/local/etc/php-fpm.d/zz-docker.conf

RUN echo 'pm = dynamic' >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo 'pm.start_servers = 100' >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo 'pm.max_children = 500' >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo 'pm.min_spare_servers = 50' >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo 'pm.max_spare_servers = 200' >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo 'pm.process_idle_timeout = 20s' >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo 'pm.max_requests = 5000' >> /usr/local/etc/php-fpm.d/zz-docker.conf

# Expose port 9004
EXPOSE 9004

# Start php-fpm server
CMD ["php-fpm"]
