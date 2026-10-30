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

        $option_keys = $themeInfo['settings']['option_keys'] ?? [];
        if(!is_array($option_keys)){
            $option_keys = [$option_keys];
        }
        // 注意：这里必须是「一维字符串数组」，多包一层会导致 Option::join 时 Array to string conversion
        if(empty($option_keys)){
            $option_keys = [$theme];
        }
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
        if(in_array("{$theme}.option_keys",$themeSettingsKeys)){
            Option::update("{$theme}.option_keys",json_encode($option_keys));
        }else{
            Option::add("{$theme}.option_keys",json_encode($option_keys));
        }
    }
}