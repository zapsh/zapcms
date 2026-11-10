<?php
/**
 * ZQuery API 控制器
 *
 * 将 ZQuery 暴露为 REST JSON API，供前端 Ajax / SPA 调用。
 *
 * 端点:
 *   POST /api/zquery          — 执行查询
 *   GET  /api/zquery?<params> — GET 方式查询
 *   GET  /api/zquery/meta     — 获取元数据列表（供下拉筛选）
 */

namespace app\controllers;

use app\ZQuery;
use zap\http\Controller;

class ZqueryController extends Controller
{
    // ═══════════════════════════════════════════════════════════
    //  POST /api/zquery
    //  GET  /api/zquery?node_type=article&posts_per_page=10
    // ═══════════════════════════════════════════════════════════

    /**
     * 执行统一查询
     *
     * 请求参数（全部可选，与 ZQuery 参数一致）：
     *
     *   node_type       string|array  节点类型 (article|product|page|faq)
     *   catalog_id      int           栏目ID
     *   catalog_id__in  int[]         多个栏目ID
     *   posts_per_page  int           每页条数（默认12，最大100）
     *   paged           int           页码（默认1）
     *   orderby         string        排序字段 (date|title|hits|sort_order|rand)
     *   order           string        ASC|DESC (默认DESC)
     *   s               string        搜索关键词（标题/内容/摘要）
     *   status          string        状态 (默认publish)
     *   include         int[]         只要这些ID
     *   exclude         int[]         排除这些ID
     *   slug            string        按slug精确查
     *   year            int           年份
     *   monthnum        int           月份
     *   thumb_only      bool          只要带封面图的
     *   meta_query      array         元数据查询
     *   fields          string        返回字段 (all|ids)
     *   no_found_rows   bool          不统计总数
     *
     * 元数据查询 (meta_query) 格式:
     *   [
     *     {"key":"color","value":"red","compare":"="},
     *     {"key":"price","value":100,"compare":">=","type":"NUMERIC"}
     *   ]
     *
     * 响应:
     *   {
     *     "success": true,
     *     "data": {
     *       "nodes": [...],
     *       "node_count": 12,
     *       "found_nodes": 58,
     *       "max_num_pages": 5,
     *       "current_page": 1
     *     }
     *   }
     */
    public function index(): void
    {
        // 支持 POST JSON 和 GET query string
        if ($this->request()->isPost()) {
            $params = $this->request()->all();
        } else {
            $params = $this->request()->get();
        }

        // 安全校验：node_type 仅允许已知类型
        $allowedTypes = ['article', 'product', 'page', 'faq'];
        if (!empty($params['node_type'])) {
            $types = (array)$params['node_type'];
            foreach ($types as $t) {
                if (!in_array($t, $allowedTypes, true)) {
                    $this->json([
                        'success' => false,
                        'message' => "Invalid node_type: {$t}",
                    ], 400);
                    return;
                }
            }
        }

        // 执行查询
        $query = new ZQuery($params);

        $this->json([
            'success' => true,
            'data'    => $query->to_array(),
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    //  GET /api/zquery/meta?key=color
    // ═══════════════════════════════════════════════════════════

    /**
     * 获取某个 meta_key 的所有不同值（用于前端下拉筛选）
     *
     * Query:
     *   key  - meta_key 名称 (必填)
     *   node_type - 限定节点类型（可选）
     */
    public function meta(): void
    {
        $key = $this->request()->get('key', '');
        $nodeType = $this->request()->get('node_type', '');

        if (empty($key)) {
            $this->json([
                'success' => false,
                'message' => 'Parameter "key" is required',
            ], 400);
            return;
        }

        $q = \zap\DB::table('node_meta', 'nm')
            ->leftJoin(['node', 'n'], 'nm.object_id=n.id')
            ->select('nm.meta_value')
            ->where('nm.meta_name', '=', $key)
            ->where('n.status', '=', 'publish')
            ->groupBy('nm.meta_value')
            ->orderBy('nm.meta_value');

        if ($nodeType) {
            $q->where('n.node_type', '=', $nodeType);
        }

        $values = $q->get(FETCH_COLUMN);

        $this->json([
            'success' => true,
            'data'    => [
                'key'    => $key,
                'values' => $values,
            ],
        ]);
    }
}
