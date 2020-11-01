<?php
/**
 * funadmin
 * ============================================================================
 * 版权所有 2018-2027 funadmin，并保留所有权利。
 * 网站地址: https://www.funadmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/8/2
 */
namespace addons\cms\backend\controller;

use app\common\controller\Backend;
use app\common\traits\Curd;
use think\facade\Db;
use think\facade\Request;
use think\facade\View;
use app\common\model\CmsTags as TagsModel;
use think\Validate;

class CmsTags extends Backend
{

    use Curd;
    public function initialize()
    {
        parent::initialize(); // TODO: Change the autogenerated stub
    }

    public function index()
    {
        if (Request::isPost()) {
            $keys = $this->request->post('keys', '', 'trim');
            $page = $this->request->post('page') ? $this->request->post('page') : 1;
            $list = TagsModel::where('name', 'like', '%' . $keys . '%')
                ->paginate(['list_rows' => $this->pageSize, 'page' => $page])
                ->toArray();

            return $result = ['code' => 0, 'msg' => lang('get info success'), 'data' => $list['data'], 'count' => $list['total']];
        }

        return view();

    }

    public function add()
    {
        if (Request::isPost()) {
            $post = $this->request->post();
            if($post){
                $this->error(lang('data can not empty'));
            }
            $model = new \app\common\model\CmsTags();
            $res = $model->addTags($post,$id='');
            if ($res) {
                $this->success(lang('add success'),url('index'));
            } else {
                $this->error(lang('add fail'));
            }
        }
        $view = [
            'info' => '',
            'title' => lang('add'),
        ];
        View::assign($view);
        return view();
    }

    public function edit()
    {
        if (Request::isPost()) {
            $post = $this->request->post();
            if(!$post){
                $this->error(lang('data can not empty'));
            }
            $model = new \app\common\model\CmsTags();
            $res = $model->addTags($post,$id='');
            if ($res) {
                $this->success(lang('edit success'), url('index'));
            } else {
                $this->error(lang('edit fail'));
            }
        }
        $info = TagsModel::find(Request::get('id'));
        $view = [
            'info' => $info,
            'title' => lang('edit'),
        ];
        View::assign($view);
        return view('add');

    }
    public function delete()
    {
        $ids = $this->request->post('ids');
        if ($ids) {
            $model = new TagsModel();
            $model->del($ids);
            $this->success(lang('delete success'));
        } else {
            $this->error(lang('delete fail'));

        }
    }

}