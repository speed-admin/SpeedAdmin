<?php
/**
 * SpeedAdmin
 * ============================================================================
 * 版权所有 2018-2027 SpeedAdmin，并保留所有权利。
 * 网站地址: https://www.SpeedAdmin.cn
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/9/21
 */

namespace app\common\controller;
use app\backend\lib\AuthService;
use app\backend\model\Admin;
use app\backend\model\AuthRule;
use app\common\traits\Jump;
use speed\helper\SignHelper;
use think\App;
use think\captcha\facade\Captcha;
use think\facade\Cookie;
use think\facade\Db;
use think\facade\Lang;
use think\facade\Request;
use think\facade\Session;
use think\facade\View;
use think\helper\Str;

class Backend extends \app\common\controller\CommonController
{
    use Jump;

    /**
    * @var
     * 模型
     */
    protected $modelClass;
    /**
     * @var
     * 页面大小
     */
    protected $pageSize;
    /**
     * @var
     * 页数
     */
    protected $page;

    /**
     * 模板布局, false取消
     * @var string|bool
     */
    protected $layout = 'layout/main';

    /**
     * 快速搜索时执行查找的字段
     */
    protected $searchFields = 'id';


    protected $allowModifyFileds = [
        'status',
        'sort',
        'title',
        'auth_open',
    ];
    /**
     * 是否是关联查询
     */
    protected $relationSearch = false;

    public function __construct(App $app)
    {

        parent::__construct($app);
        //模板管理
        $this->layout && $this->app->view->engine()->layout($this->layout);
        $controller = parse_name($this->request->controller());
        $controller = Str::camel($controller);
        //过滤参数
        $this->request->filter('trim,strip_tags,htmlspecialchars');
        $this->pageSize = input('limit', 15);
        //加载语言包
        $this->loadlang(strtolower($controller));

    }

    //自动加载语言
    protected function loadlang($name,$addon=null)
    {

        $lang = Cookie::get('think_lang');
        if($addon){
            Lang::load([
                app()->getRootPath().'addons'.DS.$addon .DS.'backend'.DS . 'lang' . DS . $lang . DS . str_replace('.', DS, $name) . '.php'
            ]);
        }else{
            Lang::load([
                $this->app->getAppPath() . 'lang' . DS . $lang . DS . str_replace('.', DS, $name) . '.php'
            ]);
        }

    }


    /**
     * 组合参数
     * @param null $searchfields
     * @param null $relationSearch
     * @return array
     */
    protected function buildParames($searchfields=null,$relationSearch=null)
    {
        $searchfields = is_null($searchfields) ? $this->searchFields : $searchfields;
        $relationSearch = is_null($relationSearch) ? $this->relationSearch : $relationSearch;
        $search = $this->request->get("search", '');
        $param = $this->request->param();
        $page = isset($param['page']) && !empty($param['page']) ? $param['page'] : 1;
        $limit = isset($param['limit']) && !empty($param['limit']) ? $param['limit'] : 15;
        $filters = isset($param['filter']) && !empty($param['filter']) ? $param['filter'] : '{}';
        $ops = isset($param['op']) && !empty($param['op']) ? $param['op'] : '{}';
        $sort = $this->request->get("sort", !empty($this->modelClass) && $this->modelClass->getPk() ? $this->modelClass->getPk() : 'id');
        $order = $this->request->get("order", "DESC");
        $filters = htmlspecialchars_decode(iconv('GBK','utf-8',$filters));
        $filters = json_decode($filters,true);
        $ops = json_decode($ops, true);
        $tableName = '';
        if ($relationSearch) {
            if (!empty($this->modelClass)) {
                $name = parse_name(basename(str_replace('\\', '/', get_class($this->modelClass))));
                $name = $this->modelClass->getTable();
                $tableName = $name . '.';
            }
            $sortArr = explode(',', $sort);
            foreach ($sortArr as $index => & $item) {
                $item = stripos($item, ".") === false ? $tableName . trim($item) : $item;
            }
            unset($item);
            $sort= implode(',', $sortArr);
        }else{
            $sort = ["$sort"=>$order];
        }
        $where = [];
        if ($search) {
            $searcharr = is_array($searchfields) ? $searchfields : explode(',', $searchfields);
            foreach ($searcharr as $k => &$v) {
                $v = stripos($v, ".") === false ? $tableName . $v : $v;
            }
            unset($v);
            $where[] = [implode("|", $searcharr), "LIKE", "%{$search}%"];
        }
        foreach ($filters as $key => $val) {
            $op = isset($ops[$key]) && !empty($ops[$key]) ? $ops[$key] : '%*%';
            switch (strtolower($op)) {
                case '=':
                    $where[] = [$key, '=', $val];
                    break;
                case '%*%':
                    $where[] = [$key, 'LIKE', "%{$val}%"];
                    break;
                case '*%':
                    $where[] = [$key, 'LIKE', "{$val}%"];
                    break;
                case '%*':
                    $where[] = [$key, 'LIKE', "%{$val}"];
                    break;
                case 'BETWEEN':
                case 'NOT BETWEEN':
                    $arr = array_slice(explode(',', $val), 0, 2);
                    if (stripos($val, ',') === false || !array_filter($arr)) {
                        continue 2;
                    }
                    //当出现一边为空时改变操作符
                    if ($arr[0] === '') {
                        $op = $op == 'BETWEEN' ? '<=' : '>';
                        $arr = $arr[1];
                    } elseif ($arr[1] === '') {
                        $op = $op == 'BETWEEN' ? '>=' : '<';
                        $arr = $arr[0];
                    }
                    $where[] = [$k, $op, $arr];
                    break;
                case 'range':
                    [$beginTime, $endTime] = explode(' - ', $val);
                    $where[] = [$key, '>=', strtotime($beginTime)];
                    $where[] = [$key, '<=', strtotime($endTime)];
                    var_dump($where);die;
                    break;
                case 'NOT RANGE':
                    $val = str_replace(' - ', ',', $val);
                    $arr = array_slice(explode(',', $val), 0, 2);
                    if (stripos($val, ',') === false || !array_filter($arr)) {
                        continue 2;
                    }
                    //当出现一边为空时改变操作符
                    if ($arr[0] === '') {
                        $op = $op == 'RANGE' ? '<=' : '>';
                        $arr = $arr[1];
                    } elseif ($arr[1] === '') {
                        $op = $op == 'RANGE' ? '>=' : '<';
                        $arr = $arr[0];
                    }
                    $where[] = [$k, str_replace('RANGE', 'BETWEEN', $op) . ' time', $arr];
                    break;
                case 'NULL':
                case 'IS NULL':
                case 'NOT NULL':
                case 'IS NOT NULL':
                    $where[] = [$k, strtolower(str_replace('IS ', '', $op))];
                    break;
                default:
                    $where[] = [$key, $op, "%{$val}%"];
            }
        }
        return [$page, $limit,$sort,$where];
    }


    /**
     * 刷新Token
     */
    protected function token()
    {
        return $this->request->buildToken('__token__','sha1');
    }






}