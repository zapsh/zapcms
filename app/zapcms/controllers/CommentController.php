<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS - All Rights Reserved
 * @author Allen
 * @email zapcms@zap.cn
 */

namespace zapcms\controllers;

use zap\DB;
use zap\facades\Url;
use zap\helpers\Pagination;
use zap\http\Request;
use zap\http\Response;
use zapcms\models\AdminLog;
use zapcms\models\Comment;

/**
 * 后台评论管理（多态关联）
 *
 * comments 表通过 (object_type, object_id) 关联任意模块：
 *   - node 内容模块：  object_type = 'node'
 *   - 其他模块：       object_type = 模块标识（product/video/...）
 */
class CommentController extends AdminController
{
    /**
     * 评论列表
     */
    public function index()
    {
        $keyword    = Request::get('keyword', '');
        $status     = Request::get('status', '');
        $objectType = Request::get('object_type', '');
        $page       = max(1, (int)Request::get('page', 1));
        $perPage    = 20;

        $query = DB::table('comments');

        // 关键词：评论内容 / 作者 / 标题
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('content', 'LIKE', "%{$keyword}%")
                    ->orWhere('author', 'LIKE', "%{$keyword}%")
                    ->orWhere('title', 'LIKE', "%{$keyword}%");
            });
        }
        // 状态筛选
        if ($status !== '' && in_array($status, [Comment::APPROVED_PENDING, Comment::APPROVED_YES, Comment::APPROVED_SPAM], true)) {
            $query->where('approved', (int)$status);
        }
        // 关联模块筛选
        if ($objectType !== '') {
            $query->where('object_type', $objectType);
        }

        $total = (int)$query->count();
        $query->orderBy('comment_id', 'DESC');
        $comments = $query->limit($perPage)->offset(($page - 1) * $perPage)->fetchAll(FETCH_ASSOC);

        // 统计
        $totalAll    = (int)DB::table('comments')->count();
        $pendingCnt  = (int)DB::table('comments')->where('approved', Comment::APPROVED_PENDING)->count();
        $approvedCnt = (int)DB::table('comments')->where('approved', Comment::APPROVED_YES)->count();
        $spamCnt     = (int)DB::table('comments')->where('approved', Comment::APPROVED_SPAM)->count();

        // 批量补充分页信息
        $queryParams = Request::get();
        unset($queryParams['page']);
        $pageHelper = new Pagination($page, $perPage, $queryParams);
        $pageHelper->setTotal($total);
        // 显式指定分页基准 URL，保留筛选参数，强制 ?page=N 形式，避免 path 为空时生成 "/N" 错误链接
        $pageHelper->withPath(Url::action('Comment@index') . '?' . ($queryParams ? http_build_query($queryParams) . '&' : '') . 'page={page}');

        // 关联对象信息（每条评论挂在哪个内容下）
        $objectInfos = [];
        foreach ($comments as $comment) {
            $key = $comment['object_type'] . ':' . $comment['object_id'];
            if (!isset($objectInfos[$key])) {
                $objectInfos[$key] = Comment::getObjectInfo($comment['object_type'], (int)$comment['object_id']);
            }
        }

        view('comment.index', [
            'page_title'  => '评论管理',
            'comments'    => $comments,
            'objectInfos' => $objectInfos,
            'pageHelper'  => $pageHelper,
            'total'       => $total,
            'totalAll'    => $totalAll,
            'pendingCnt'  => $pendingCnt,
            'approvedCnt' => $approvedCnt,
            'spamCnt'     => $spamCnt,
            'keyword'     => $keyword,
            'status'      => $status,
            'objectType'  => $objectType,
            'objectTypes' => Comment::getObjectTypes(),
            'statusOptions' => Comment::getStatusOptions(),
        ]);
    }

    /**
     * 评论编辑表单（Modal 内嵌）
     */
    public function form()
    {
        $id      = (int)Request::get('id', 0);
        $comment = $id > 0 ? DB::table('comments')->where('comment_id', $id)->fetch(FETCH_ASSOC) : null;
        if (!$comment) {
            return '评论不存在';
        }

        // 关联对象信息 + 父评论
        $comment['object_info'] = Comment::getObjectInfo($comment['object_type'], (int)$comment['object_id']);
        $comment['parent_title'] = '';
        if ((int)$comment['parent'] > 0) {
            $parent = DB::table('comments')
                ->where('comment_id', (int)$comment['parent'])
                ->fetch(FETCH_ASSOC);
            $comment['parent_title'] = $parent ? mb_substr(strip_tags($parent['content'] ?? ''), 0, 50) : '(已删除)';
        }

        view('comment.form', [
            'comment'   => $comment,
            'page_title' => '编辑评论',
        ]);
    }

    /**
     * 保存评论（编辑内容 / 状态）
     */
    public function save()
    {
        $id = (int)Request::post('comment_id', 0);
        if ($id <= 0) {
            Response::json(['code' => 1, 'msg' => '参数错误']);
        }

        $comment = DB::table('comments')->where('comment_id', $id)->fetch(FETCH_ASSOC);
        if (!$comment) {
            Response::json(['code' => 1, 'msg' => '评论不存在']);
        }

        $content = trim(Request::post('content', ''));
        $author  = trim(Request::post('author', ''));
        $approved = (int)Request::post('approved', Comment::APPROVED_PENDING);
        if ($content === '') {
            Response::json(['code' => 1, 'msg' => '评论内容不能为空']);
        }
        if (!in_array($approved, [Comment::APPROVED_PENDING, Comment::APPROVED_YES, Comment::APPROVED_SPAM], true)) {
            $approved = Comment::APPROVED_PENDING;
        }

        $oldApproved = (int)$comment['approved'];
        DB::table('comments')->where('comment_id', $id)->update([
            'content'  => $content,
            'author'   => $author,
            'approved' => $approved,
        ]);

        AdminLog::log('编辑评论', "修改了评论 #{$id}：{$author}");

        // 审核状态变化时同步关联内容的评论计数
        if ($oldApproved !== $approved) {
            $this->syncObjectCount($comment['object_type'], (int)$comment['object_id']);
        }

        Response::json(['code' => 0, 'msg' => '保存成功']);
    }

    /**
     * 批量设置评论状态
     */
    public function setStatus()
    {
        $ids    = Request::post('ids', []);
        $status = (int)Request::post('status', -1);
        if (empty($ids) || !is_array($ids)) {
            Response::json(['code' => 1, 'msg' => '请选择评论']);
        }
        if (!in_array($status, [Comment::APPROVED_PENDING, Comment::APPROVED_YES, Comment::APPROVED_SPAM], true)) {
            Response::json(['code' => 1, 'msg' => '状态无效']);
        }

        $ids = array_map('intval', $ids);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        // 记录受影响的对象，用于同步计数
        $rows = DB::query(
            "SELECT object_type, object_id FROM {comments} WHERE comment_id IN ({$placeholders})",
            $ids
        )->fetchAll(FETCH_ASSOC);

        DB::table('comments')->whereIn('comment_id', $ids)->update(['approved' => $status]);

        AdminLog::log('评论审核', "批量设置 " . count($ids) . " 条评论状态为 " . Comment::getStatusTitle($status));

        foreach ($rows as $row) {
            $this->syncObjectCount($row['object_type'], (int)$row['object_id']);
        }

        Response::json(['code' => 0, 'msg' => '操作成功']);
    }

    /**
     * 删除评论（支持批量）
     */
    public function remove()
    {
        $ids = Request::post('ids', []);
        if (empty($ids) || !is_array($ids)) {
            Response::json(['code' => 1, 'msg' => '请选择评论']);
        }

        $ids = array_map('intval', $ids);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $rows = DB::query(
            "SELECT object_type, object_id FROM {comments} WHERE comment_id IN ({$placeholders})",
            $ids
        )->fetchAll(FETCH_ASSOC);

        DB::query("DELETE FROM {comments} WHERE comment_id IN ({$placeholders})", $ids);

        AdminLog::log('删除评论', '删除了 ' . count($ids) . ' 条评论');

        foreach ($rows as $row) {
            $this->syncObjectCount($row['object_type'], (int)$row['object_id']);
        }

        Response::json(['code' => 0, 'msg' => '删除成功']);
    }

    /**
     * 同步关联内容的评论计数（目前仅 node 模块有 comment_count 字段）
     */
    private function syncObjectCount(string $objectType, int $objectId): void
    {
        if ($objectType !== 'node' || $objectId <= 0) {
            return;
        }
        $count = Comment::countByObject('node', $objectId, Comment::APPROVED_YES);
        DB::table('node')->where('id', $objectId)->update(['comment_count' => $count]);
    }
}
