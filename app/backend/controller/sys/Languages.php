<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: https://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2017/8/2
 */
namespace app\backend\controller\sys;

use app\common\controller\Backend;
use app\common\traits\Curd;
use app\backend\model\Languages as LanguagesModel;
use think\App;

class Languages extends Backend {

    use Curd;
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new LanguagesModel();

    }

//    /**
//     * @return array|string
//     * @throws \think\db\exception\DbException
//     */
//    public function index(){
//        if($this->request->isAjax()){
//                list($this->page, $this->pageSize,$sort,$where) = $this->buildParames();
//                $count = $this->modelClass
//                    ->where($where)
//                    ->count();
//                $list = $this->modelClass
//                    ->where($where)
//                    ->order($sort)
//                    ->page($this->page,$this->pageSize)
//                    ->select();
//
//                $result = ['code' => 0, 'msg' => lang('Delete Data Success'), 'data' => $list, 'count' => $count];
//                return json($result);
//
//        }
//
//        return view();
//    }

}
