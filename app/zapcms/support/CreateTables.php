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
            $table->varchar('title', 255);
            $table->text('content');
            $table->varchar('ipaddress', 45);
            $table->text('request_url');
            $table->text('user_agent');
            $table->integer('request_time');

            $table->addPrimaryKey('id');
            $table->addIndex('idx_uid',['uid']);

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

            $table->setTableEngine(TableSchema::ENGINE_INNODB);
        });

        Schema::dropIfExists('node_meta');
        Schema::create('node_meta',function(TableSchema $table){
            $table->integer('meta_id')->autoIncrement();
            $table->integer('object_id');
            $table->varchar('meta_name', 255);
            $table->text('meta_value');

            $table->addPrimaryKey('meta_id');

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
