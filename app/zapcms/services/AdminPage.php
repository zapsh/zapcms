<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS - All Rights Reserved
 * @author Allen
 * @email zapcms@zap.cn
 * @date 2023/12/27 上午11:29
 * @lastModified 2023/12/27 上午11:10
 *
 */

namespace zapcms\services;

use zap\traits\SingletonTrait;

class AdminPage
{
    use SingletonTrait;

    public static function breadcrumb(): BreadCrumb
    {
        return BreadCrumb::instance();
    }

    public function showFlashMessages(){
        // key → bgColor 映射（常见键名自动匹配样式）
        $colorMap = [
            'error'   => 'bgDanger',
            'warning' => 'bgWarning',
            'success' => 'bgSuccess',
            'info'    => 'bgInfo',
        ];

        $allFlash = session()->flash();
        if (empty($allFlash)) {
            return;
        }

        echo '$(function(){';
        foreach ($allFlash as $key => $message) {
            if (in_array($key, ['__old__', '_validation_errors'], true)) {
                continue;
            }
            $bgColor = $colorMap[$key] ?? 'bgInfo';
            $msg = addslashes((string)$message);
            echo "ZapToast.alert('{$msg}', {bgColor: {$bgColor}, position: Toast_Pos_Center});";
        }
        echo '})';
    }

}