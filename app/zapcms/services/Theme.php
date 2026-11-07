<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS - All Rights Reserved
 * @author Allen
 * @email zapcms@zap.cn
 * @date 2023/12/27 上午11:28
 * @lastModified 2023/11/1 下午5:01
 *
 */

namespace zapcms\services;

use FilesystemIterator;
use zap\traits\SingletonTrait;
use zapcms\support\IOUtils;

class Theme
{

    use SingletonTrait;

    public function getAllThemes(): array
    {
        $themeDir = themes_path();
        $data = [];
        $fsIter = new FilesystemIterator($themeDir,FilesystemIterator::KEY_AS_PATHNAME|FilesystemIterator::CURRENT_AS_FILEINFO|FilesystemIterator::SKIP_DOTS);
        while($fsIter->valid()){
            if($fsIter->getType() !== 'dir') continue;
            $data[] = [
                'dirname'=>$fsIter->getFilename(),
                'perms'=> substr(sprintf('%o', $fsIter->getPerms()), -4),
            ];
            $fsIter->next();
        }
        $fileNames = array_column($data,'dirname');
//        $fileTypes = array_column($data,'type');
        array_multisort($fileNames,SORT_ASC ,$data);
        return $data;
    }

    public function getAllThemesInfo(): array
    {
        $themes = $this->getAllThemes();
        $themes_info = [];
        foreach ($themes as $theme){
            $themeInfo = IOUtils::readJsonFile(
                themes_path("{$theme['dirname']}/theme.json"), true, null, true
            );
            if (!is_array($themeInfo)) {
                continue;
            }
            $themeInfo['dirname'] = $theme['dirname'];
            $themeInfo['name'] = $themeInfo['name'] ?? $theme['dirname'];
            $themeInfo['title'] = $themeInfo['title'] ?? $themeInfo['label'] ?? $theme['dirname'];
            $themeInfo['screenshot'] = $themeInfo['screenshot'] ?? '';
            $themeInfo['description'] = $themeInfo['description'] ?? '';
            $themeInfo['version'] = $themeInfo['version'] ?? '';
            $themes_info[$theme['dirname']] = $themeInfo;
        }
        return $themes_info;
    }

    /**
     * 合并主题默认选项：theme.json['options'] 与可选的 themes/<theme>/defaults.php（PHP 数组）。
     * defaults.php 优先（用于承载大段 HTML / 结构化默认值，避免 theme.json 膨胀）。
     */
    protected static function loadDefaults(string $theme): array
    {
        static $cache = [];
        if (array_key_exists($theme, $cache)) {
            return $cache[$theme];
        }

        $themeFile = themes_path("{$theme}/theme.json");
        $info = IOUtils::readJsonFile($themeFile, true, [], true);
        $jsonDefaults = $info['options'] ?? [];

        $phpFile = themes_path("{$theme}/defaults.php");
        $phpDefaults = is_file($phpFile) ? IOUtils::readPhpFile($phpFile, []) : [];
        if (!is_array($phpDefaults)) {
            $phpDefaults = [];
        }

        $cache[$theme] = array_merge($jsonDefaults, $phpDefaults);
        return $cache[$theme];
    }

    function saveThemeOptions($theme){
        $themeFile = themes_path("{$theme}/theme.json");
        if(!is_file($themeFile)){
            return;
        }
        // 合并 theme.json['options'] 与可选的 themes/<theme>/defaults.php（PHP 默认值优先）
        $defaults = self::loadDefaults($theme);

        // 归属列表：统一以「主题名」为前缀；非前缀键（如全局缩略图尺寸）不计入本主题。
        $option_keys = array_keys($defaults);
        $option_keys = array_filter($option_keys, fn($k) => str_starts_with((string)$k, $theme));
        if(empty($option_keys)){
            // 老主题无顶层 options：回退到主题名前缀
            $option_keys = [$theme];
        }
        // 注意：getKeys 的 REGEXP 入参必须是「一维字符串数组」，多包一层会导致 Array to string conversion
        $themeSettingsKeys = Option::getKeys($option_keys,'REGEXP');
        foreach ($defaults as $key=>$value){
            if(is_object($value) || is_array($value)){
                $value = json_encode($value);
            }
            if(in_array($key, $themeSettingsKeys)){
                Option::update($key,$value);
            }else{
                Option::add($key,$value);
            }

        }
        $option_keys[] = "{$theme}.option_keys";
        Option::replace("{$theme}.option_keys", json_encode($option_keys));
    }

    /**
     * 返回属于某主题的 option key 列表。
     *
     * 约定：主题选项统一以「主题名」为前缀（如 zap2026_*），归属列表通过解析
     * theme.json 顶层 options 数组的键得到（仅保留以主题名为前缀的键），用于保存白名单
     * 与卸载时删除。老主题若未声明顶层 options，则回退到「主题名前缀」REGEXP，保证兼容。
     */
    public function ownedOptionKeys(string $theme): array
    {
        // 优先使用启用主题时持久化的归属标记
        $stored = Option::get("{$theme}.option_keys");
        if ($stored !== null) {
            $keys = is_array($stored) ? $stored : @json_decode($stored, true);
            if (is_array($keys) && !empty($keys)) {
                return array_values(array_unique($keys));
            }
        }
        // 回退：解析默认选项（theme.json + 可选的 defaults.php），取所有以主题名为前缀的键
        $options = self::loadDefaults($theme);
        if (is_array($options) && !empty($options)) {
            $keys = array_keys($options);
            $keys = array_filter($keys, fn($k) => str_starts_with((string) $k, $theme));
            $keys[] = "{$theme}.option_keys";
            return array_values(array_unique($keys));
        }
        // 老主题无 options 声明：回退主题名前缀 REGEXP（兼容 basic）
        return Option::getKeys($theme, 'REGEXP');
    }

    /**
     * 读取主题全部选项值，以 theme.json 顶层 options 默认值兜底。
     */
    public function getOptions(string $theme): array
    {
        $defaults = self::loadDefaults($theme);

        // 归属键（排除归属标记键）
        $owned = [];
        foreach ($this->ownedOptionKeys($theme) as $key) {
            if (substr((string)$key, -12) === '.option_keys') {
                continue;
            }
            $owned[] = (string)$key;
        }

        // 一次性按前缀批量读取全部归属键，避免逐键 Option::get 产生 N+1 查询。
        // 归属键通常共享同一前缀（如 basic_home.*），直接按该前缀 REGEXP 取全，
        // 无需把每个 key 拼成超长的 alternation；前缀不唯一时退回主题名。
        $prefixes = [];
        foreach ($owned as $key) {
            $dot = strpos($key, '.');
            $prefixes[] = $dot === false ? $key : substr($key, 0, $dot);
        }
        $prefixes = array_values(array_unique($prefixes));
        $prefix = count($prefixes) === 1 ? $prefixes[0] : $theme;
        $stored = !empty($owned) ? Option::getArray($prefix, 'REGEXP') : [];

        $values = [];
        foreach ($owned as $key) {
            $values[$key] = $stored[$key] ?? null;
        }
        foreach ($defaults as $k => $v) {
            if (!array_key_exists($k, $values) || $values[$k] === null) {
                $values[$k] = $v;
            }
        }
        return $values;
    }

    /**
     * 保存主题选项：仅写入「归属本主题」的键，insert-or-update。
     * 非本主题声明的键（防止越权写入）会被忽略。
     *
     * @param string $theme
     * @param array  $data  key => value
     */
    public function saveOptions(string $theme, array $data): void
    {
        $owned = $this->ownedOptionKeys($theme);
        foreach ($data as $key => $value) {
            if (!is_scalar($key) || !in_array($key, $owned, true)) {
                continue;
            }
            if (is_object($value) || is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            Option::replace($key, $value);
        }
    }

    /**
     * 主题声明的小工具区域（theme.json 的 widget_areas，key => title）。
     * 未声明时以「sidebar / footer + 内容模型列表」作为默认区域（排除 link-url 等虚拟模型）。
     *
     * @param string|null $theme 指定主题，默认取当前启用主题
     */
    public function getWidgetAreas(?string $theme = null): array
    {
        // 前台由 Startup 写入 config.theme；后台 initAdminEnv() 会把它设为 false，
        // 因此后台请求需回退到站点启用主题（option），否则永远只读到默认区域
        $theme = $theme ?? (string) (config('config.theme') ?? '');
        if ($theme === '') {
            $theme = (string) option('website.theme', 'basic');
        }

        $info  = $theme === '' ? [] : IOUtils::readJsonFile(themes_path("{$theme}/theme.json"), true, [], true);
        $areas = $info['widget_areas'] ?? [];
        if (is_array($areas) && $areas !== []) {
            return $areas;
        }

        // 未声明时才查内容模型表，避免每次调用都多一条 SQL
        return $this->defaultWidgetAreas();
    }

    /**
     * 默认小工具区域：sidebar / footer + 内容模型列表（type_name => title）
     *
     * link-url 是虚拟模型（仅用于栏目快捷链接），不产生真实内容，排除掉。
     */
    protected function defaultWidgetAreas(): array
    {
        $areas = [
            'sidebar' => '侧边栏',
            'footer'  => '页脚',
        ];
        foreach (NodeType::getNodeTypes() as $key => $row) {
            $type = (string) ($row['type_name'] ?? $key);
            if ($type === '' || NodeType::isVirtual($type) || isset($areas[$type])) {
                continue;
            }
            $areas[$type] = (string) ($row['title'] ?? $type);
        }
        return $areas;
    }
}