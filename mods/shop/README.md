模块结构 mods/shop/
code
mods/shop/
├── mod.json                      # 模块元信息
├── Mod.php                       # 入口类（路由分发 + install/uninstall 钩子）
├── install.php                   # 建表：shop_product, shop_order, shop_order_item
├── uninstall.php                 # 删表清理
├── models/
│   ├── Product.php               # 产品模型（列表分页 / slug 查询 / 价格格式化）
│   └── Cart.php                  # 购物车模型（基于 Session）
├── controllers/
│   ├── IndexController.php       # 后台仪表盘 + 前端首页
│   ├── ProductController.php     # 产品增删改查 + 前端详情/列表
│   └── CartController.php        # 购物车（加入/更新/移除/AJAX）
└── views/
    ├── index.admin.php           # 后台仪表盘
    ├── index.home.php            # 前端首页
    ├── product.admin_list.php    # 后台产品列表
    ├── product.admin_form.php    # 后台新增/编辑表单
    ├── product.list.php          # 前端产品卡片列表
    ├── product.detail.php        # 前端产品详情页
    └── cart.index.php            # 前端购物车


```
访问方式
页面	URL	说明
后台仪表盘	/z-admin/mod/shop	统计卡片
后台产品列表	/z-admin/mod/shop/product	分页表格 + 新增/编辑/删除
前端首页	/mod/shop	推荐产品 + 购物车入口
前端产品列表	/mod/shop/product/lists	卡片展示 + 一键加购
前端产品详情	/mod/shop/product/view/{slug}	图片、价格、数量选择、加购
前端购物车	/mod/shop/cart	修改数量、删除、合计
```



启用无前缀 URL
在 config/module.php 加一行：

php
'prefixes' => [
    'shop' => 'shop',    // /shop/...  → mods/shop/
]
之后 /shop/product/view/xxx 直接访问，不需要 /mod/ 前缀。

后台菜单（自动注册）

在 mod.json 中声明 admin_menu，模块启用后会自动同步到「系统菜单」，无需写代码：

json
"admin_menu": [
    {
        "title": "商城",
        "icon": "fa-solid fa-store",
        "children": [
            {"title": "商城概况", "url": "mod/shop", "icon": "fa-solid fa-gauge-high"},
            {"title": "产品管理", "url": "mod/shop/product", "icon": "fa-solid fa-cube"}
        ]
    }
]


url 写法对照：

写法	实际地址
mod/shop/product	/{后台前缀}/mod/shop/product（推荐）
product	自动补全为 mod/shop/product
/z-admin/mod/shop/product	自动去掉写死的前缀，兼容旧写法
https://example.com	外链原样输出

要点：
- 不要写死 /z-admin/，后台路径允许用户修改，渲染时由 Url::action() 拼接当前前缀。
- 叶子菜单必须存在对应控制器与方法，否则点击会 404。
- 一级菜单给 title/icon，二级放在 children 里。
- mod.json 的菜单定义或版本号变化后，下次进入后台自动重新同步；卸载模块时自动清理其菜单。
- 同步逻辑见 app/zapcms/support/ModMenu.php。

