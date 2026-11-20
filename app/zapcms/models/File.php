<?php

namespace zapcms\models;

use zap\DB;
use zapcms\services\Auth;
use zapcms\services\storage\StorageManager;
use zapcms\services\storage\StorageResult;

/**
 * 文件元数据模型（files 表）
 *
 * 记录通过存储层上传 / 写入的文件信息：路径、原始文件名、大小、驱动、URL、上传人等。
 * 上传统一在 StorageManager::put() / 上传控制器 forceLocal 分支中落库；
 * 删除、重命名、移动时同步维护记录，避免 DB 与磁盘/云对象不一致。
 */
class File
{
    public static function tableName(): string
    {
        return 'files';
    }

    public static function primaryKey(): string
    {
        return 'id';
    }

    /**
     * 记录 / 更新一次文件写入
     *
     * @param StorageResult $result 存储层返回的写入结果
     * @param array         $meta   可选补充：name（原始文件名）、mime、uid
     */
    public static function record(StorageResult $result, array $meta = []): void
    {
        $path = ltrim(str_replace('\\', '/', $result->key), '/');
        if ($path === '' || str_starts_with($path, 'thumbs/')) {
            return;
        }

        $now  = time();
        $ext  = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $mime = trim((string) ($meta['mime'] ?? $result->mime));
        if ($mime === '') {
            $mime = self::guessMime($ext);
        }

        $row = [
            'path'       => $path,
            'name'       => (string) ($meta['name'] ?? basename($path)),
            'ext'        => $ext,
            'mime'       => $mime,
            'size'       => (int) $result->size,
            'driver'     => $result->driver,
            'url'        => $result->url,
            'synced'     => $result->synced ? 1 : 0,
            'uid'        => (int) ($meta['uid'] ?? Auth::user('id', 0)),
            'updated_at' => $now,
        ];

        $exists = DB::table(self::tableName())->where('path', $path)->fetch(FETCH_ASSOC);
        if ($exists) {
            DB::table(self::tableName())->where('id', $exists['id'])->update($row);
        } else {
            $row['created_at'] = $now;
            DB::table(self::tableName())->insert($row);
        }
    }

    /** 删除文件记录（支持目录前缀） */
    public static function remove(string $path): void
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        if ($path === '' || str_starts_with($path, 'thumbs/')) {
            return;
        }
        DB::table(self::tableName())->where('path', $path)->delete();
        DB::table(self::tableName())->where('path', 'LIKE', $path . '/%')->delete();
    }

    /** 同步重命名 / 移动记录（支持目录前缀） */
    public static function move(string $from, string $to): void
    {
        $from = rtrim(ltrim(str_replace('\\', '/', $from), '/'), '/');
        $to   = rtrim(ltrim(str_replace('\\', '/', $to), '/'), '/');
        if ($from === '' || $to === '' || $from === $to) {
            return;
        }

        $rows = DB::table(self::tableName())->where('path', $from)->get(FETCH_ASSOC);
        $rows = array_merge($rows, DB::table(self::tableName())->where('path', 'LIKE', $from . '/%')->get(FETCH_ASSOC));
        foreach ($rows as $row) {
            $newPath = $to . substr($row['path'], strlen($from));
            DB::table(self::tableName())->where('id', $row['id'])->update([
                'path'       => $newPath,
                'name'       => basename($newPath),
                'url'        => StorageManager::url($newPath),
                'updated_at' => time(),
            ]);
        }
    }

    /** 常见扩展名 → MIME 兜底映射 */
    private static function guessMime(string $ext): string
    {
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'gif'         => 'image/gif',
            'webp'        => 'image/webp',
            'bmp'         => 'image/bmp',
            'ico'         => 'image/x-icon',
            'pdf'         => 'application/pdf',
            'zip'         => 'application/zip',
            'rar'         => 'application/vnd.rar',
            '7z'          => 'application/x-7z-compressed',
            'gz'          => 'application/gzip',
            'tar'         => 'application/x-tar',
            'mp3'         => 'audio/mpeg',
            'mp4'         => 'video/mp4',
            'webm'        => 'video/webm',
            'txt'         => 'text/plain',
            'md'          => 'text/markdown',
            'csv'         => 'text/csv',
            'json'        => 'application/json',
            'xml'         => 'application/xml',
            default       => '',
        };
    }
}
