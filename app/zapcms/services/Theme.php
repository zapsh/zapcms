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
            $themeInfo = json_decode(file_get_contents(themes_path("{$theme['dirname']}/theme.json")),true) ;
            if(json_last_error() !== JSON_ERROR_NONE){
                continue;
            }
            if(!is_array($themeInfo)){
                $themeInfo = [];
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

    function saveThemeOptions($theme){
        $themeFile = themes_path("{$theme}/theme.json");
        if(!is_file($themeFile)){
            return;
        }
        $themeInfo = IOUtils::readJsonFile($themeFile,true) ?:[];

        // 归属列表：统一以「主题名」为前缀，从 theme.json 顶层 options 数组的键解析得到。
        // 不再依赖 settings.option_keys 声明；非前缀键（如全局缩略图尺寸）不计入本主题。
        $option_keys = array_keys($themeInfo['options'] ?? []);
        $option_keys = array_filter($option_keys, fn($k) => str_starts_with((string)$k, $theme));
        if(empty($option_keys)){
            // 老主题无顶层 options：回退到主题名前缀
            $option_keys = [$theme];
        }
        // 注意：getKeys 的 REGEXP 入参必须是「一维字符串数组」，多包一层会导致 Array to string conversion
        $themeSettingsKeys = Option::getKeys($option_keys,'REGEXP');
        foreach ($themeInfo['options'] ?? [] as $key=>$value){
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
        // 回退：解析 theme.json 的 options 数组，取所有以主题名为前缀的键
        $info = IOUtils::readJsonFile(themes_path("{$theme}/theme.json"), true) ?: [];
        $options = $info['options'] ?? [];
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
        $themeFile = themes_path("{$theme}/theme.json");
        $info = IOUtils::readJsonFile($themeFile, true) ?: [];
        $defaults = $info['options'] ?? [];
        $values = [];
        foreach ($this->ownedOptionKeys($theme) as $key) {
            if (substr($key, -12) === '.option_keys') {
                continue;
            }
            $values[$key] = Option::get($key);
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
}