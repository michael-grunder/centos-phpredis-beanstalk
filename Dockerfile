FROM centos:8.2.2004

# update yum
RUN yum clean all; yum -y update --nogpgcheck && yum -y install yum-utils wget dnf vim redis iproute-tc kernel-modules-extra

RUN dnf -y install https://rpms.remirepo.net/enterprise/remi-release-8.rpm && \
    dnf -y --enablerepo=remi install php74 php74-php-mbstring php74-php-devel php74-php-igbinary php74-php-redis && \
    dnf -y group install "Development Tools"

RUN wget -O- https://gist.githubusercontent.com/ifduyue/2ebe43be8d2ea1275abf/raw/a77c217f4b8eab0a9320deb48845cd95e2c5bf69/install-beanstalkd.sh|bash -s

RUN source scl_source enable php74 && \
    cd /root/ && git clone https://github.com/phpredis/phpredis.git && cd phpredis && \
    phpize && ./configure && make -j16

COPY get-composer.sh /root/

# Composer
RUN source scl_source enable php74 && cd /root/ && ./get-composer.sh && ln -s /root/composer.phar /usr/bin/composer

COPY vimrc /root/.vimrc
COPY src /root/queue

RUN source scl_source enable php74 && cd /root/queue && composer install

RUN echo 'alias make="make -j16"' >> /root/.bashrc && \
    echo "alias vi=vim" >> /root/.bashrc && \
    echo "source scl_source enable php74" >> /root/.bashrc && \
    echo "php=php74" >> /root/.bashrc
    #echo "extension=redis.so" >> /etc/opt/remi/php74/php.d/20-redis.ini

# Neovim
RUN cd /tmp/ && \
    curl -o nvim.appimage -LO https://github.com/neovim/neovim/releases/download/stable/nvim.appimage && \
    chmod u+x nvim.appimage && ./nvim.appimage --appimage-extract && mv squashfs-root /opt/neovim && \
    ln -s /opt/neovim/AppRun /usr/local/bin/nvim

CMD ["beanstalkd"]
