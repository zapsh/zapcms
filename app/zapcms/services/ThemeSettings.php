<?php
/*
 * Copyright (c) 2023-2026.  ZAP.CN  - ZAP CMS
 * ThemeSettings - 主题配置读写
 *
 * 用法:
 *   $s = themeSettings();
 *   echo $s->get('productThumbWidth', 400);
 *   $s->set('homeTitle', '新标题');
 *   $s->save();
 */

namespace zapcms\services;

use zapcms\support\IOUtils;

class ThemeSettings
{
    private string $theme;
    private array $data = [];
    private array $dirty = [];

    public function __construct(?string $theme = null)
    {
        $this->theme = $theme ?: option('website.theme', 'basic');
        $this->load();
    }

    /** 从数据库加载当前主题的全部配置 */
    private function load(): void
    {
        // 主题配置存储时没有统一前缀，直接用 Option 查出所有
        // 以 theme.json 的 option_keys 来限定范围（可选）
        $themeInfo = IOUtils::readJsonFile(themes_path("{$this->theme}/theme.json"), true, [], true);
        $keys = $themeInfo['settings']['option_keys'] ?? [];

        if (!empty($keys)) {
            $this->data = Option::getArray($keys, 'REGEXP');
        }
    }

    /** 读取配置项 */
    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    /** 写入配置项（只改内存，save() 才持久化） */
    public function set(string $key, $value): self
    {
        $this->data[$key] = $value;
        $this->dirty[$key] = true;
        return $this;
    }

    /** 持久化所有变更到数据库 */
    public function save(): void
    {
        foreach ($this->dirty as $key => $_) {
            Option::upsert($key, $this->data[$key]);
        }
        $this->dirty = [];
    }

    /** 获取全部配置（用于调试） */
    public function all(): array
    {
        return $this->data;
    }
}
