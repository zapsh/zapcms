<?php

namespace zapcms\widgets;

/**
 * 文本 / HTML 小工具
 */
class TextWidget extends Widget
{
    public static function key(): string
    {
        return 'text';
    }

    public static function title(): string
    {
        return '文本';
    }

    public static function description(): string
    {
        return '任意文本或 HTML 内容';
    }

    public static function icon(): string
    {
        return 'fa-solid fa-file-lines';
    }

    public static function defaults(): array
    {
        return ['content' => ''];
    }

    public static function fields(): array
    {
        return [
            ['name' => 'content', 'label' => '内容', 'type' => 'textarea', 'placeholder' => '支持 HTML'],
        ];
    }

    public function render(): string
    {
        return (string) $this->config('content', '');
    }
}
