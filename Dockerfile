# 写在最前面：强烈建议先阅读官方教程[Dockerfile最佳实践]（https://docs.docker.com/develop/develop-images/dockerfile_best-practices/）
# 选择构建用基础镜像（选择原则：在包含所有用到的依赖前提下尽可能提及小）。如需更换，请到[dockerhub官方仓库](https://hub.docker.com/_/php?tab=tags)自行选择后替换。
FROM alpine:3.18

# 容器默认时区为UTC，如需使用上海时间请启用以下时区设置命令
# RUN apk add tzdata && cp /usr/share/zoneinfo/Asia/Shanghai /etc/localtime && echo Asia/Shanghai > /etc/timezone

# 使用 HTTPS 协议访问容器云调用证书安装
RUN apk add ca-certificates

# 安装依赖包，如需其他依赖包，请到alpine依赖包管理(https://pkgs.alpinelinux.org/packages?name=php82*)查找。
# 选用国内镜像源以提高下载速度
RUN sed -i 's/dl-cdn.alpinelinux.org/mirrors.tencent.com/g' /etc/apk/repositories \
    && apk add --update --no-cache \
    php82 \
    php82-ctype \
	php82-exif \
    php82-fpm \
    php82-session \
    php82-pdo_mysql \
    php82-tokenizer \
    php82-curl \
    php82-dom \
    php82-gd \
    php82-mbstring \
    php82-openssl \
    php82-pdo_sqlite \
    php82-simplexml \
    php82-xml \
    php82-zip \
    nginx \
    && rm -f /var/cache/apk/*

# 设定工作目录
WORKDIR /app

# 将当前目录下所有文件拷贝到/app
COPY . /app

# 生成 nginx 配置
RUN cat > /etc/nginx/conf.d/default.conf << 'NGINX_EOF'
server {
    listen       80;
    server_name  localhost;
    root /app/public;
    index  index.html index.htm index.php;
    error_page  404              /404.html;
    error_page  500 502 503 504  /50x.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ /storage/.*\.php$ {
        deny all;
    }
    location ~ \.php$ {
        fastcgi_pass   127.0.0.1:9000;
        fastcgi_index  index.php;
        fastcgi_param  SCRIPT_FILENAME  $document_root$fastcgi_script_name;
        include        fastcgi_params;
    }

    location ~ /\.ht {
        deny  all;
    }
}
NGINX_EOF

# 生成 php-fpm 配置
RUN cat > /etc/php82/php-fpm.d/www.conf << 'FPM_EOF'
[www]
user = nobody
group = nobody
listen = 127.0.0.1:9000
pm = dynamic
pm.max_children = 5
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
clear_env = no
FPM_EOF

# 生成 php.ini（基于 php.ini-production，仅包含非默认项）
RUN cat > /etc/php82/php.ini << 'PHP_EOF'
[PHP]
engine = On
short_open_tag = Off
precision = 14
output_buffering = 4096
zlib.output_compression = Off
implicit_flush = Off
unserialize_callback_func =
serialize_precision = -1
disable_functions =
disable_classes =
zend.enable_gc = On
zend.exception_ignore_args = On
expose_php = Off

max_execution_time = 30
max_input_time = 60
memory_limit = 128M

error_reporting = E_ALL & ~E_DEPRECATED
display_errors = Off
display_startup_errors = Off
log_errors = On
log_errors_max_len = 1024
ignore_repeated_errors = Off
ignore_repeated_source = Off
report_memleaks = On

variables_order = "GPCS"
request_order = "GP"
register_argc_argv = Off
auto_globals_jit = On
post_max_size = 8M
default_mimetype = "text/html"
default_charset = "UTF-8"

include_path = ".:/usr/share/php82"
doc_root =
user_dir =
enable_dl = Off

file_uploads = On
upload_max_filesize = 2M
max_file_uploads = 20

allow_url_fopen = On
allow_url_include = Off
default_socket_timeout = 60

[CLI Server]
cli_server.color = On

[Pdo_mysql]
pdo_mysql.default_socket=

[mail function]
SMTP = localhost
smtp_port = 25
mail.add_x_header = Off

[ODBC]
odbc.allow_persistent = On
odbc.check_persistent = On
odbc.max_persistent = -1
odbc.max_links = -1
odbc.defaultlrl = 4096
odbc.defaultbinmode = 1

[MySQLi]
mysqli.max_persistent = -1
mysqli.allow_persistent = On
mysqli.max_links = -1
mysqli.default_port = 3306
mysqli.default_socket =
mysqli.default_host =
mysqli.default_user =
mysqli.default_pw =
mysqli.reconnect = Off

[mysqlnd]
mysqlnd.collect_statistics = On
mysqlnd.collect_memory_statistics = Off

[PostgreSQL]
pgsql.allow_persistent = On
pgsql.auto_reset_persistent = Off
pgsql.max_persistent = -1
pgsql.max_links = -1
pgsql.ignore_notice = 0
pgsql.log_notice = 0

[bcmath]
bcmath.scale = 0

[Session]
session.save_handler = files
session.use_strict_mode = 0
session.use_cookies = 1
session.use_only_cookies = 1
session.name = PHPSESSID
session.auto_start = 0
session.cookie_lifetime = 0
session.cookie_path = /
session.cookie_domain =
session.cookie_httponly =
session.cookie_samesite =
session.serialize_handler = php
session.gc_probability = 1
session.gc_divisor = 1000
session.gc_maxlifetime = 1440
session.referer_check =
session.cache_limiter = nocache
session.cache_expire = 180
session.use_trans_sid = 0
session.trans_sid_tags = "a=href,area=href,frame=src,form="

[Assertion]
zend.assertions = -1

[Tidy]
tidy.clean_output = Off

[soap]
soap.wsdl_cache_enabled=1
soap.wsdl_cache_dir="/tmp"
soap.wsdl_cache_ttl=86400
soap.wsdl_cache_limit = 5

[ldap]
ldap.max_links = -1
PHP_EOF


RUN cat > /run.sh << 'RUN_EOF'
#!/bin/sh
# 启动php-fpm
php-fpm -D
# 启动nginx
nginx -g "daemon off;"
RUN_EOF

RUN mkdir -p /run/nginx \
    && chmod -R 777 /app/storage \
    && chmod +x /run.sh \
    && mv /usr/sbin/php-fpm82 /usr/sbin/php-fpm \
    && ln -sf /usr/bin/php82 /usr/bin/php

# 暴露端口
EXPOSE 80

# 容器启动执行脚本
CMD ["sh", "run.sh"]
