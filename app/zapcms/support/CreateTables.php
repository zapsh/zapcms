<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS
 *
 * Table structure for ALL database tables.
 *
 * NOTE: short string columns that used `text` were optimized to `varchar`
 * for MySQL (TEXT cannot be indexed without a prefix and is less efficient).
 * Long-text columns (content / description / meta_value / user_agent / …)
 * are intentionally kept as `text`.
 */

namespace zapcms\support;

use zap\db\Schema;
use zap\db\TableSchema;

class CreateTables
{
    public function createSchema()
    {

        Schema::dropIfExists('admin');
        Schema::create('admin',function(TableSchema $table){
            $table->integer('id')->autoIncrement();
            $table->varchar('username', 100);
            $table->varchar('password', 255);
            $table->varchar('full_name', 255);
            $table->varchar('phone_number', 40);
            $table->varchar('email', 191);
            $table->varchar('avatar_url', 255);
            $table->varchar('last_ip', 45);
            $table->integer('last_access_time');
            $table->varchar('status', 255);
            $table->integer('updated_at');
            $table->integer('created_at');

            $table->addPrimaryKey('id');
            $table->addIndex('idx_username',['username']);

            $table->setTableEngine(TableSchema::ENGINE_INNODB);
        });

        Schema::dropIfExists('admin_logs');
        Schema::create('admin_logs',function(TableSchema $table){
            $table->integer('id')->autoIncrement();
            $table->integer('uid');
            $table->varchar('username', 255);
            $table->varchar('category', 50)->default('system');
            $table->varchar('title', 255);
            $table->text('content');
            $table->varchar('level', 20)->default('INFO');
            $table->varchar('ipaddress', 45);
            $table->text('request_url');
            $table->text('user_agent');
            $table->integer('request_time');

            $table->addPrimaryKey('id');
            $table->addIndex('idx_uid',['uid']);
            $table->addIndex('idx_admin_logs_category',['category']);
            $table->addIndex('idx_admin_logs_level',['level']);
            $table->addIndex('idx_admin_logs_request_time',['request_time']);

            $table->setTableEngine(TableSchema::ENGINE_INNODB);
        });

        // 登录失败限流（5 次 / 5 分钟），对应 migration 2026_11_04_100000
        Schema::dropIfExists('admin_login_attempts');
        Schema::create('admin_login_attempts',function(TableSchema $table){
            $table->integer('id')->autoIncrement();
            $table->varchar('attempt_key', 64);
            $table->integer('attempts');
            $table->integer('first_at');
            $table->integer('last_at');

            $table->addPrimaryKey('id');
            $table->addIndex('uk_attempt_key',['attempt_key']);
            $table->addIndex('idx_last_at',['last_at']);

            $table->setTableEngine(TableSchema::ENGINE_INNODB);
        });

        Schema::dropIfExists('admin_menu');
        Schema::create('admin_menu',function(TableSchema $table){
            $table->integer('id')->autoIncrement();
            $table->varchar('title', 255);
            $table->integer('pid');
            $table->varchar('path', 255)->nullable();
            $table->integer('level')->nullable()->default('0');
            $table->varchar('icon', 255)->nullable();
            $table->varchar('link_to', 255)->nullable();
            $table->varchar('link_target', 255)->nullable();
            $table->varchar('link_type', 50)->nullable()->default('action');
            $table->varchar('active_rule', 255)->nullable();
            $table->varchar('show_position', 255)->nullable();
            $table->integer('sort_order')->nullable()->default('0');
            $table->varchar('i18n_key', 64)->nullable()->default(''); // 翻译 key（内置菜单用，如 menu.content）
            $table->integer('updated_at')->nullable();
            $table->integer('created_at')->nullable();

            $table->addPrimaryKey('id');

            $table->setTableEngine(TableSchema::ENGINE_INNODB);
        });

        Schema::dropIfExists('admin_meta');
        Schema::create('admin_meta',function(TableSchema $table){
            $table->integer('meta_id')->autoIncrement();
            $table->integer('object_id');
            $table->varchar('meta_name', 100);
            $table->text('meta_value');

            $table->addPrimaryKey('meta_id');
            $table->addIndex('idx_meta_name',['meta_name']);
            $table->addIndex('idx_object_id',['object_id']);

            $table->setTableEngine(TableSchema::ENGINE_INNODB);
        });

        Schema::dropIfExists('admin_roles');
        Schema::create('admin_roles',function(TableSchema $table){
            $table->integer('admin_id');
            $table->integer('role_id');
            $table->integer('assignment_time')->nullable();

            $table->addPrimaryKey(['admin_id', 'role_id']);

            $table->setTableEngine(TableSchema::ENGINE_INNODB);
        });

        Schema::dropIfExists('catalog');
        Schema::create('catalog',function(TableSchema $table){
            $table->integer('id')->autoIncrement();
            $table->varchar('slug', 255)->nullable();
            $table->varchar('title', 255)->nullable();
            $table->text('content')->nullable();
            $table->integer('pid')->nullable();
            $table->varchar('path', 255)->nullable();
            $table->integer('level')->nullable()->default('0');
            $table->integer('sort_order')->nullable()->default('0');
            $table->varchar('icon', 255)->nullable();
            $table->varchar('thumb_url', 255)->nullable();
            $table->varchar('link_to', 255)->nullable();
            $table->varchar('link_type', 255)->nullable();
            $table->integer('link_object')->nullable();
            $table->varchar('link_target', 255)->nullable();
            $table->varchar('show_position', 255)->nullable();
            $table->varchar('node_type', 255)->nullable();
            $table->integer('created_at')->nullable();

            $table->addPrimaryKey('id');
            // 栏目树懒加载 WHERE pid ORDER BY sort_order，对应 migration 2026_11_06_180000
            $table->addIndex('idx_catalog_pid',['pid', 'sort_order']);
            // 大规模分类 / 子树 / 按模型过滤，对应 migration 2026_11_07_120000
            $table->addIndex('idx_catalog_path',['path']);
            $table->addIndex('idx_catalog_slug',['slug']);
            $table->addIndex('idx_catalog_node_type',['node_type','pid','sort_order']);

            $table->setTableEngine(TableSchema::ENGINE_INNODB);
        });

        Schema::dropIfExists('comments');
        Schema::create('comments',function(TableSchema $table){
            $table->integer('comment_id');
            $table->varchar('object_type', 50)->default('node');
            $table->integer('object_id');
            $table->varchar('title', 255);
            $table->varchar('author', 255)->nullable();
            $table->varchar('author_email', 191)->nullable();
            $table->varchar('author_url', 255)->nullable();
            $table->varchar('author_ip', 45)->nullable();
            $table->integer('author_id')->nullable()->default(0);
            $table->text('content');
            $table->integer('approved')->nullable()->default(0);
            $table->text('agent');
            $table->integer('parent')->nullable();
            $table->integer('created_at')->nullable();

            $table->addPrimaryKey('comment_id');
            $table->addIndex('idx_approved',['approved']);
            $table->addIndex('idx_object_type_object_id',['object_type', 'object_id']);

            $table->setTableEngine(TableSchema::ENGINE_INNODB);
        });

        Schema::dropIfExists('node');
        Schema::create('node',function(TableSchema $table){
            $table->integer('id')->autoIncrement();
            $table->integer('author_id');
            $table->varchar('node_type', 50)->nullable();
            $table->varchar('title', 255);
            $table->varchar('slug', 255)->nullable();
            $table->text('excerpt')->nullable();
            $table->text('content')->nullable();
            $table->varchar('keywords', 255)->nullable();
            $table->text('description')->nullable();
            $table->varchar('image', 255)->nullable();
            $table->integer('hits')->nullable()->default('0');
            $table->integer('sort_order')->nullable()->default('0');
            $table->varchar('comment_status', 255)->nullable();
            $table->integer('comment_count')->nullable()->default('0');
            $table->varchar('mime_type', 255)->nullable();
            $table->varchar('status', 50)->nullable();
            $table->integer('pub_time')->nullable()->default('0');
            $table->integer('update_time')->nullable()->default('0');
            $table->integer('add_time')->nullable()->default('0');

            $table->addPrimaryKey('id');
            $table->addIndex('idx_node_type',['node_type']);
            $table->addIndex('idx_status',['status']);
            $table->addIndex('idx_author_id',['author_id']);
            $table->addIndex('idx_node_slug',['slug']);

            $table->setTableEngine(TableSchema::ENGINE_INNODB);
        });

        Schema::dropIfExists('node_meta');
        Schema::create('node_meta',function(TableSchema $table){
            $table->integer('meta_id')->autoIncrement();
            $table->integer('object_id');
            $table->varchar('meta_name', 255);
            $table->text('meta_value');

            $table->addPrimaryKey('meta_id');
            $table->addIndex('idx_node_meta_object',['object_id']);

            $table->setTableEngine(TableSchema::ENGINE_INNODB);
        });

        Schema::dropIfExists('translations');
        Schema::create('translations',function(TableSchema $table){
            $table->varchar('object_type', 32)->default('node');
            $table->integer('object_id')->default('0');
            $table->varchar('locale', 16);
            $table->varchar('title', 255)->nullable();
            $table->varchar('slug', 191)->nullable();
            $table->text('excerpt')->nullable();
            $table->mediumtext('content')->nullable();
            $table->varchar('keywords', 255)->nullable();
            $table->text('description')->nullable();
            $table->integer('update_time')->nullable()->default('0');

            $table->addPrimaryKey(['object_type', 'object_id', 'locale']);
            $table->addIndex('idx_trn_locale_slug',['locale','slug']);

            $table->setTableEngine(TableSchema::ENGINE_INNODB);
        });

        Schema::dropIfExists('node_relation');
        Schema::create('node_relation',function(TableSchema $table){
            $table->integer('catalog_id');
            $table->integer('node_id');
            $table->integer('level')->nullable()->default('0');

            $table->addPrimaryKey(['catalog_id', 'node_id']);
            $table->addIndex('idx_node_relation_node',['node_id']);

            $table->setTableEngine(TableSchema::ENGINE_INNODB);
        });

        Schema::dropIfExists('node_type_field');
        Schema::create('node_type_field',function(TableSchema $table){
            $table->integer('field_id')->autoIncrement();
            $table->integer('node_type_id');
            $table->varchar('field_name', 255);
            $table->varchar('field_label', 255);
            $table->text('field_value')->nullable();
            $table->integer('sort_order')->nullable()->default('0');
            $table->varchar('type', 255)->nullable();
            $table->varchar('placeholder', 255)->nullable();
            $table->integer('required')->nullable()->default('0');
            $table->varchar('help', 255)->nullable();
            $table->varchar('group_name', 100)->default('');

            $table->addPrimaryKey('field_id');
            $table->addIndex('idx_node_type_id',['node_type_id']);

            $table->setTableEngine(TableSchema::ENGINE_INNODB);
        });

        Schema::dropIfExists('node_types');
        Schema::create('node_types',function(TableSchema $table){
            $table->integer('type_id')->autoIncrement();
            $table->varchar('type_name', 100);
            $table->varchar('title', 255);
            $table->text('description')->nullable();
            $table->varchar('node_type', 255)->nullable();
            $table->varchar('version', 20)->nullable()->default('0.0.0');
            $table->integer('sort_order')->nullable()->default('0');
            $table->integer('status')->nullable()->default('1');
            $table->integer('updated_at')->nullable();
            $table->integer('created_at')->nullable();

            $table->addPrimaryKey('type_id');
            $table->addIndex('idx_type_name',['type_name']);

            $table->setTableEngine(TableSchema::ENGINE_INNODB);
        });

        Schema::dropIfExists('options');
        Schema::create('options',function(TableSchema $table){
            $table->integer('id')->autoIncrement();
            $table->varchar('option_name', 191);
            $table->text('option_value')->nullable();
            $table->integer('sort_order')->nullable()->default('0');
            $table->integer('autoload')->nullable()->default('0');

            $table->addPrimaryKey('id');
            $table->addUniqueIndex('uk_option_name',['option_name']);

            $table->setTableEngine(TableSchema::ENGINE_INNODB);
        });

        Schema::dropIfExists('permissions');
        Schema::create('permissions',function(TableSchema $table){
            $table->integer('perm_id')->autoIncrement();
            $table->varchar('title', 255);
            $table->integer('pid');
            $table->varchar('path', 255)->nullable();
            $table->integer('level')->nullable()->default('0');
            $table->varchar('perm_key', 255)->nullable();
            $table->text('extras')->nullable();
            $table->text('description')->nullable();
            $table->integer('updated_at')->nullable();
            $table->integer('created_at')->nullable();
            $table->integer('sort_order')->default('0');

            $table->addPrimaryKey('perm_id');

            $table->setTableEngine(TableSchema::ENGINE_INNODB);
        });

        Schema::dropIfExists('plugin');
        Schema::create('plugin',function(TableSchema $table){
            $table->integer('id')->autoIncrement();
            $table->varchar('name', 100);
            $table->varchar('title', 255);
            $table->varchar('version', 20)->nullable()->default('1.0.0');
            $table->varchar('author', 255)->nullable();
            $table->text('description')->nullable();
            $table->varchar('homepage', 255)->nullable();
            $table->varchar('package_name', 255)->nullable();
            $table->integer('status')->nullable()->default('1');
            $table->integer('sort_order')->nullable()->default('0');
            $table->integer('installed_at')->nullable()->default('0');
            $table->integer('updated_at')->nullable()->default('0');

            $table->addPrimaryKey('id');
            $table->addIndex('idx_name',['name']);

            $table->setTableEngine(TableSchema::ENGINE_INNODB);
        });

        Schema::dropIfExists('roles');
        Schema::create('roles',function(TableSchema $table){
            $table->integer('role_id')->autoIncrement();
            $table->varchar('name', 255);
            $table->text('description')->nullable();
            $table->integer('status')->nullable()->default('0');
            $table->integer('updated_at')->nullable()->default('0');
            $table->integer('created_at')->nullable()->default('0');

            $table->addPrimaryKey('role_id');

            $table->setTableEngine(TableSchema::ENGINE_INNODB);
        });

        Schema::dropIfExists('roles_permissions');
        Schema::create('roles_permissions',function(TableSchema $table){
            $table->integer('role_id');
            $table->varchar('perm_key', 150);
            $table->text('extras')->nullable();
            $table->integer('assignment_time')->nullable();

            $table->addPrimaryKey(['role_id', 'perm_key']);

            $table->setTableEngine(TableSchema::ENGINE_INNODB);
        });

        Schema::dropIfExists('tags');
        Schema::create('tags',function(TableSchema $table){
            $table->integer('tag_id')->autoIncrement();
            $table->varchar('title', 255);
            $table->varchar('slug', 255);
            $table->integer('count')->nullable()->default('0');

            $table->addPrimaryKey('tag_id');

            $table->setTableEngine(TableSchema::ENGINE_INNODB);
        });

        Schema::dropIfExists('update_history');
        Schema::create('update_history',function(TableSchema $table){
            $table->integer('id')->autoIncrement();
            $table->varchar('target', 100);
            $table->varchar('from_version', 255);
            $table->varchar('to_version', 255);
            $table->text('changelog')->nullable();
            $table->varchar('status', 20)->nullable()->default('success');
            $table->integer('created_at')->nullable()->default('0');

            $table->addPrimaryKey('id');
            $table->addIndex('idx_target',['target']);

            $table->setTableEngine(TableSchema::ENGINE_INNODB);
        });

        Schema::dropIfExists('menu_locations');
        Schema::create('menu_locations',function(TableSchema $table){
            $table->integer('id')->autoIncrement();
            $table->varchar('title', 255);
            $table->varchar('key', 64);
            $table->varchar('description', 255)->nullable();
            $table->tinyint('is_system')->default('0');
            $table->integer('sort_order')->nullable()->default('0');
            $table->integer('updated_at')->nullable();
            $table->integer('created_at')->nullable();
            // 分配到本位置的菜单（menus.id），对应 migration 2026_11_06_200000
            $table->integer('menu_id')->default('0');

            $table->addPrimaryKey('id');
            $table->addUniqueIndex('uk_menu_location_key',['key']);

            $table->setTableEngine(TableSchema::ENGINE_INNODB);
        });

        // 菜单项（WordPress 式，指向栏目/内容/自定义链接的指针）
        // 对应 migration 2026_11_06_120000 + 170000(mega 字段) + 200000(menu_id)
        Schema::dropIfExists('menu_items');
        Schema::create('menu_items',function(TableSchema $table){
            $table->integer('id')->autoIncrement();
            $table->integer('menu_id')->default('0');
            $table->varchar('location_key', 64);
            $table->integer('parent_id')->default('0');
            $table->varchar('item_type', 16);          // catalog | node | custom_link
            $table->integer('ref_id')->nullable();
            $table->varchar('title', 255)->nullable();  // 显示文案；NULL 回退到被引用对象
            $table->varchar('url', 1000)->nullable();   // custom_link 目标地址
            $table->varchar('link_target', 32)->nullable(); // _self | _blank
            $table->integer('sort_order')->nullable()->default('0');
            $table->integer('created_at')->nullable();
            $table->integer('updated_at')->nullable();
            // mega-menu 富字段（migration 2026_11_06_170000）
            $table->varchar('icon', 100)->nullable();
            $table->varchar('image', 255)->nullable();
            $table->varchar('badge', 50)->nullable();
            $table->varchar('css_class', 100)->nullable();
            $table->boolean('column_break')->default(0);

            $table->addPrimaryKey('id');
            $table->addIndex('idx_menu_location',['location_key']);
            $table->addIndex('idx_menu_parent',['parent_id']);
            $table->addIndex('idx_menu_menu_id',['menu_id']);
            // 前台取树 (location_key, parent_id, sort_order)，对应 migration 2026_11_06_150000
            $table->addIndex('idx_menu_loc_parent_sort',['location_key', 'parent_id', 'sort_order']);

            $table->setTableEngine(TableSchema::ENGINE_INNODB);
        });

        // 菜单实体（可自由创建多个：主导航 / 侧边栏 / 页脚…），对应 migration 2026_11_06_200000
        Schema::dropIfExists('menus');
        Schema::create('menus',function(TableSchema $table){
            $table->integer('id')->autoIncrement();
            $table->varchar('title', 255);
            $table->integer('created_at')->nullable();
            $table->integer('updated_at')->nullable();

            $table->addPrimaryKey('id');

            $table->setTableEngine(TableSchema::ENGINE_INNODB);
        });

        // 小工具实例（按区域组织，config 存 JSON），对应 migration 2026_11_07_100000
        Schema::dropIfExists('widgets');
        Schema::create('widgets',function(TableSchema $table){
            $table->integer('id')->autoIncrement();
            $table->varchar('widget_key', 64);
            $table->varchar('area', 64);
            $table->varchar('title', 255)->nullable();
            $table->text('config')->nullable();
            $table->tinyint('status')->default('1');
            $table->integer('sort_order')->default('0');
            $table->integer('created_at')->nullable();
            $table->integer('updated_at')->nullable();

            $table->addPrimaryKey('id');
            $table->addIndex('idx_widget_area',['area', 'sort_order']);

            $table->setTableEngine(TableSchema::ENGINE_INNODB);
        });

        Schema::dropIfExists('migrations');
        Schema::create('migrations',function(TableSchema $table){
            $table->integer('id')->autoIncrement();
            $table->varchar('migration', 191);
            $table->integer('batch')->default('1');
            $table->integer('executed_at')->default('0');

            $table->addPrimaryKey('id');
            $table->addIndex('idx_migration',['migration']);

            $table->setTableEngine(TableSchema::ENGINE_INNODB);
        });
    }

    public function installBaseData()
    {
        CreateBaseData::install();
    }

    public function installDemoData()
    {
        
    }
}
