<?php

namespace zapcms\support;

/**
 * Vite 构建产物解析
 *
 * 背景：后台资源由 Vite 构建到 assets/admin，文件名带 hash 以便做长期缓存。
 * 视图里如果硬编码文件名，每次构建后都会失效，因此统一通过本类把
 * 「逻辑名」解析成「当前构建产物」。
 *
 * 未构建（缺少 manifest）时自动回退到不带 hash 的文件名，
 * 保证仓库里的构建产物或手工维护的文件依然可用。
 */
class Vite
{
    /** 构建输出目录（相对站点根目录） */
    protected const BUILD_DIR = 'assets/admin';

    /** Vite manifest 相对构建目录的路径 */
    protected const MANIFEST = '.vite/manifest.json';

    /** 已解析的 manifest（null 表示尚未加载） */
    protected static ?array $manifest = null;

    /**
     * 取得某个 JS entry 的 URL
     *
     * @param string $name 逻辑名，如 bootstrap.bundle / editor
     */
    public static function js(string $name): string
    {
        return static::url(static::jsPath($name));
    }

    /**
     * 取得某个 CSS entry 的 URL
     *
     * @param string $name 逻辑名，如 zap-admin / bootstrap
     */
    public static function css(string $name): string
    {
        return static::url(static::cssPath($name));
    }

    /**
     * JS entry 相对构建目录的路径（安装向导阶段 base_url() 尚未就绪时使用）
     */
    public static function jsPath(string $name): string
    {
        return static::resolve('js', $name, 'js');
    }

    /**
     * CSS entry 相对构建目录的路径
     */
    public static function cssPath(string $name): string
    {
        return static::resolve('css', $name, 'css');
    }

    /**
     * 注册某个 entry 的 CSS 与 JS（走框架的资源注册机制）
     */
    public static function entry(string $name, $stylePosition = null, $scriptPosition = null): void
    {
        register_styles(static::css($name), $stylePosition ?? ASSETS_HEAD);
        register_scripts(static::js($name), $scriptPosition ?? ASSETS_BODY);
    }

    /**
     * manifest 是否存在（用于排查「是否执行过 npm run build」）
     */
    public static function hasManifest(): bool
    {
        return static::manifest() !== [];
    }

    /**
     * 解析逻辑名到真实文件
     */
    protected static function resolve(string $dir, string $name, string $ext): string
    {
        $pattern = '#^' . $dir . '/' . preg_quote($name, '#') . '-[A-Za-z0-9_-]{6,}\.' . $ext . '$#';

        foreach (static::manifest() as $entry) {
            if (!is_array($entry) || empty($entry['file']) || !is_string($entry['file'])) {
                continue;
            }

            if (preg_match($pattern, $entry['file'])) {
                return $entry['file'];
            }
        }

        // 没有 manifest（未构建）：回退到不带 hash 的文件名
        return $dir . '/' . $name . '.' . $ext;
    }

    protected static function url(string $file): string
    {
        return base_url('/' . static::BUILD_DIR . '/' . ltrim($file, '/'));
    }

    /**
     * 读取并缓存 manifest
     */
    protected static function manifest(): array
    {
        if (static::$manifest !== null) {
            return static::$manifest;
        }

        // app/zapcms/support → 项目根目录
        $file = dirname(__DIR__, 3) . '/' . static::BUILD_DIR . '/' . static::MANIFEST;

        if (!is_file($file)) {
            return static::$manifest = [];
        }

        $data = json_decode((string)file_get_contents($file), true);

        return static::$manifest = is_array($data) ? $data : [];
    }
}
