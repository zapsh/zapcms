<?php

namespace zapcms\node\controllers;

use zapcms\models\Node;
use zap\DB;
use zap\db\Query;
use zap\helpers\Pagination;
use zapcms\node\AbstractNodeType;
use zap\view\View;

class PageController extends AbstractNodeType
{
    public function index()
    {
        if($this->catalogId==0){
//            $this->redirectTo('Node');
            $this->title = '单页';
            $pageTitle  = $this->getTitle('%s管理');
            $queryParams = req()->get();

            $perPage = (int) ($queryParams['per_page'] ?? 20);
            if (!in_array($perPage, [10, 20, 50, 100], true)) {
                $perPage = 20;
            }
            $status = trim((string) req()->get('status', ''));
            if (!in_array($status, [Node::STATUS_PUBLISH, Node::STATUS_DRAFT, Node::STATUS_TRASH], true)) {
                $status = '';
            }
            $keyword    = trim((string) req()->get('s', ''));
            $filterCid  = (int) req()->get('filter_cid', 0);
            $withChild  = !empty($queryParams['with_child']);

            $statusCounts = [];
            foreach ([
                'all'     => null,
                'publish' => Node::STATUS_PUBLISH,
                'draft'   => Node::STATUS_DRAFT,
                'trash'   => Node::STATUS_TRASH,
            ] as $statusKey => $statusValue) {
                $statusCounts[$statusKey] = (int) $this->getPageTotal($statusValue, $keyword, $filterCid, $withChild);
            }

            $page = new Pagination((int) ($queryParams['page'] ?? 1), $perPage, $queryParams);
            $page->setTotal($this->getPageTotal($status, $keyword, $filterCid, $withChild));
            unset($queryParams['page']);
            $queryString = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);
            $page->withPath(current_url() . '?' . ($queryString !== '' ? $queryString . '&' : '') . 'page={page}');
            View::share('page', $page);

            $rows = $this->getPages($page, $status, $keyword, $filterCid, $withChild);
            $rowCatalogPaths = [];
            $nodeIds = array_map('intval', array_column($rows, 'id'));
            if ($nodeIds !== []) {
                $relations = DB::table('node_relation', 'nr')
                    ->leftJoin(['node', 'c'], 'nr.catalog_id=c.id')
                    ->whereIn('nr.node_id', $nodeIds)
                    ->orderBy('nr.level', 'ASC')
                    ->get(FETCH_ASSOC);
                foreach ($relations as $rel) {
                    if (!empty($rel['title'])) {
                        $rowCatalogPaths[(int) $rel['node_id']][] = [
                            'id'    => (int) ($rel['catalog_id'] ?? 0),
                            'title' => (string) $rel['title'],
                        ];
                    }
                }
            }

            $this->display([
                'page'=> $page,
                'title'=> $pageTitle,
                'page_title'=> $pageTitle,
                'status_counts'=> $statusCounts,
                'row_catalog_paths'=> $rowCatalogPaths,
                'data'=> $rows
            ]);
            return;
        }
        View::share('catalog',$this->getCatalogById($this->catalogId));
        $node = $this->getNodeByCatalogId($this->catalogId);
        if($node){
            $this->title = $node['title'];
            parent::edit($node['id']);
        }else{
            // 单页栏目（迁移后 catalog 并入 node，正文存在分类节点自身，无挂靠内容节点）：
            // 直接编辑分类节点本身，否则会误入「新增」表单
            $catalogNode = Node::findById($this->catalogId);
            if ($catalogNode
                && $catalogNode->node_type === 'category'
                && (string) ($catalogNode->mime_type ?? '') === 'page') {
                $this->title = $catalogNode->title;
                parent::edit($this->catalogId);
            } else {
                parent::add();
            }
        }
    }

    public function getPageTotal(?string $status = null, string $keyword = '', int $filterCid = 0, bool $withChild = false)
    {
        $query = $this->pageQuery($status, $keyword, $filterCid, $withChild);
        return (int) $query->select('count(DISTINCT n.id) as rowcount')->fetchColumn();
    }

    public function getPages(?Pagination $page = null, ?string $status = null, string $keyword = '', int $filterCid = 0, bool $withChild = false)
    {
        $query = $this->pageQuery($status, $keyword, $filterCid, $withChild)
            ->distinct()
            ->select('n.*')
            ->orderBy('n.is_top', 'DESC')
            ->orderBy('n.id', 'DESC');
        if($page){
            $query->limit($page->getLimit())->offset($page->getOffset());
        }
        return $query->get(FETCH_ASSOC);
    }

    private function pageQuery(?string $status, string $keyword, int $filterCid, bool $withChild)
    {
        $query = DB::table('node', 'n')
            ->leftJoin(['node_relation', 'nr'], 'nr.node_id=n.id')
            ->where(function ($q) {
                $q->where('n.node_type', 'page')
                  ->orWhere(function ($q2) {
                      $q2->where('n.node_type', 'category')->where('n.mime_type', 'page');
                  });
            });
        if ($filterCid > 0) {
            $catalogIds = [$filterCid];
            if ($withChild) {
                $descRows = DB::table('node')
                    ->where(function ($q) use ($filterCid) {
                        $q->where('id', $filterCid)
                          ->orWhere('path', 'LIKE', $filterCid . ',%')
                          ->orWhere('path', 'LIKE', '%,' . $filterCid . ',%');
                    })
                    ->select('id')->get(FETCH_ASSOC);
                foreach ($descRows as $row) {
                    $catalogIds[(int) $row['id']] = (int) $row['id'];
                }
            }
            $query->whereIn('nr.catalog_id', $catalogIds);
        }
        if ($status !== null && $status !== '') {
            $query->where('n.status', $status);
        }
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('n.title', 'LIKE', "%{$keyword}%")
                  ->orWhere('n.slug', 'LIKE', "%{$keyword}%");
            });
        }
        return $query;
    }


}