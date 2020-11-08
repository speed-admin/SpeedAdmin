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
namespace addons\cms\frontend\controller;

use app\common\model\Addon;
use addons\cms\common\model\CmsCategory;
use addons\cms\common\model\CmsModule;
use addons\cms\common\model\CmsTags;
use app\common\model\Config;
use think\App;
use think\facade\Cache;
use think\facade\Request;
use think\facade\View;
use think\facade\Db;

class Index extends CmsBase {
    public $tablename = null;
    public $module = null;
    public $moduleid = null;
    public $category = null;
    public $cateid = null;
    public $top_cateid = null;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->cateid = $cateid = input('cateid/d');
        if($cateid){
            $this->category = CmsCategory::find($cateid);
            if($this->category){
                redirect(url('Error/404'));
            }
            $this->moduleid = $this->category ->moduleid;
            $this->module = CmsModule::find( $this->category ->moduleid);
            $this->tablename =   $this->module ->modulename;
            if($this->category->pid == 0){
                $this->top_cateid = $this->cateid;
            }else{
                $parentids = explode(',',$this->category->arrpid);
                $this->top_cateid = isset($parentids[1]) ? $parentids[1] : $this->cateid;
            }
        }
        View::assign('cateid',$cateid);
        View::assign('top_cateid', $this->top_cateid);
        View::assign('moduleid', $this->moduleid);
        View::assign('category',$this->category);
    }

    //首页

    public function index(){

        return view('index_index');
    }

    public function tags(){
        $id  = input('id/d');
        if(!$id){
            $this->redirect(url('error/err',['message'=>'id不存在']));
        }
        $tag = CmsTags::find($id);
        if(!$tag){
            $this->redirect(url('error/err',['message'=>'标签不存在']));
        }
        $tag->hits =   $tag->hits+1;
        $tag->save();
        $list = Db::name('addons_cms_article')->where('tags','like','%'.$tag->name.'%')->paginate(
            5, false,
            ['query' => $this->request->param()]
        );
        $view = ['list'=>$list,'tag'=>$tag];
        return view('index_tags',$view);

    }

    public function lists(){
        //单页
        if($this->category->type==1){
            $list = Db::name($this->tablename)->where('cateid',$this->cateid)->find();
            if(!$list) $this->redirect(url('error/err',['message'=>'没有内容']));
            $template = $this->category->template_show;
            //更新点击量
            Db::name($this->tablename)->where('cateid', $this->cateid)->inc('hits')->update();
            $seo = ['title'=>$list['title'],'keywords'=>$list['keywords'],'description'=>$list['intro']];

        }else{ //列表

            $page = $this->category->page_size;
            $list = Db::name($this->tablename)->where('status',1)->whereIn('cateid',$this->category->arrchildid)->cache(3600)
                ->order('sort asc,id desc')->paginate(
                $page, false,
                ['query' => $this->request->param()]
            );
            $template = $this->category->template_list;
            $seo = ['title'=>$this->category->title,'keywords'=>$this->category->keywords,'description'=>$this->category->description];
        };
        $template = substr($template,0,strlen($template)-5);
        $view = ['list'=>$list,'seo'=>$seo,'top_cateid'=>$this->top_cateid];
        return view($template,$view);
    }
    //单页
    public function page(){
        $id = input('id/d');
        $list = Db::name($this->tablename)->where('status',1)->cache(3600)->find($id);
        Db::name($this->tablename)->where('id',$list['id'])->inc('hits')->update();
        $template = $this->category->template_show;
        $seo = ['title'=>$list['title'],'keywords'=>$list['keywords'],'description'=>$list['intro']];
        $template = substr($template,0,strlen($template)-5);
        var_dump($template);
        //上一篇
        $front=Db::name($this->tablename)->where('status', 1)->where('id','>',$id)->order('id asc')->limit(1)->find();
        //下一篇
        $after=Db::name($this->tablename)->where('status', 1)->where('id','<',$id)->order('id desc')->limit(1)->find();
        $view = ['list'=>$list,'seo'=>$seo,'top_cateid'=>$this->top_cateid,'front'=>$front,'after'=>$after];
        return view($template,$view);
    }


    //表单
    public function diyform(){

        $data = $this->request->post();
        foreach ($data as $k=>$v){
            if(empty($v)){
                $this->redirect(url('error/err',['message'=>'信息不能为空']));
            }
        }
        $data['create_time'] = time();
        Db::name('addons_cms_message')->insert($data);
        return view('tips');

    }

    //搜索
    public function search(){
        $keys= urldecode(input('keys'));
        if($this->moduleid){

            $page = $this->category->page_size;
            $searchField = Db::name('addons_cms_field')->where('moduleid', $this->moduleid)->where('is_search', 1)->column('field');
            if (empty($searchField)) {
                $list = [];
            }else{
                $searchFieldStr = '';
                foreach ($searchField as $k=>$v){
                    $searchFieldStr .= $v.'|';
                }
                $searchFieldStr = trim($searchFieldStr,'|');
                $list = Db::name($this->tablename)->where('status',1)
                    ->where($searchFieldStr,'like','%'.$keys.'%')
                    ->cache(3600)
                    ->order('sort asc,id desc')
                    ->paginate(
                        $page, false,
                        ['query' => $this->request->param()]
                    );
            }


        }else{
            $this->tablename = 'addons_cms_article';
            $page = 10;
            $modelList = CmsModule::where('status',1)->column('name','id');
            foreach ($modelList as $key => $vo) {
                $searchField = Db::name('addons_cms_field')->where('moduleid', $key)->where('is_search', 1)->column('field');
                if (empty($searchField)) {
                    continue;
                }
                $searchFieldStr = '';
                foreach ($searchField as $k=>$v){
                    $searchFieldStr .= $v.'|';
                }
                $searchFieldStr = trim($searchFieldStr,'|');
                $list = Db::name($vo)->where('status',1)
                    ->where($searchFieldStr,'like','%'.$keys.'%')
                    ->cache(3600)
                    ->order('sort asc,id desc')
                    ->paginate(
                        $page, false,
                        ['query' => $this->request->param()]
                    );
                if (!$list) {
                    continue;
                } else {
                    $moduleid = $key;
                    break;
                }
            }

        }
        $view = ['list'=>$list,'keys'=>$keys,'moduleid'=>$moduleid];
        return  view('index_search',$view);
    }


}