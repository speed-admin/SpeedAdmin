<?php
/**
 * lemocms
 * ============================================================================
 * 版权所有 2018-2027 lemocms，并保留所有权利。
 * 网站地址: https://www.lemocms.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/11/27
 */
namespace app\bbs\controller;

use app\common\controller\Frontend;

use lemo\helper\MailHelper;
use lemo\helper\SignHelper;
use think\captcha\facade\Captcha;
use app\common\model\User as UserModel;
use app\common\validate\User as UserValidate;
use think\facade\View;
use think\exception\ValidateException;

class Login extends Frontend {

    public function initialize()
    {
        parent::initialize(); // TODO: Change the autogenerated stub
        if(session('user')){
            $this->redirect('/index');
        }
        $BASE_URL = get_http_type().$_SERVER["SERVER_NAME"].'/'.app('http')->getName();
        View::assign('BASE_URL', $BASE_URL);
        $user = [];
        View::assign('user',$user);
    }
    /************************************注册登陆*******************************/

    /**
     * @return \think\Response
     * 登录
     */
    public function login(){
        if(session('user.id')){
            return redirect()->restore();
        }
        if($this->request->isPost()){
            $data = $this->request->post();
            $user = UserModel::where('email',$data['email'])->find();
            if(!$user) $this->error('邮箱还未注册！');
            if($user && $user->status==0) $this->error('账号被禁用,联系管理管...');
            if(strlen($data['password'])<6) $this->error('密码长度不能少于6位');
            if(!captcha_check($data['vercode'])) $this->error('验证码错误');
            if(!password_verify($data['password'],$user->password)) $this->error('密码错误');
            $data['password'] = password_hash($data['password'],PASSWORD_BCRYPT,SignHelper::passwordSalt());
            $user->login_num = $user->login_num+1;
            $user->last_ip = $_SERVER['REMOTE_ADDR'];
            $user->last_login = time();
            $user->update = time();
            if(!$user->save())  $this->error('登录失败！');
            session('user',$user);
            $this->success('登录成功！',url('index/index'));

        }
        return view();
    }

    /**
     * @return \think\Response
     * 注册
     */
    public function reg(){
        if($this->request->isPost()){
            $data = $this->request->post();
            $user = UserModel::where('email',$data['email'])->find();
            if($user) $this->error('邮箱已经存在');
            if($user && $user->status==0) $this->error('账号被禁用,联系管理管...');
            if($data['password']!=$data['repassword']) $this->error('密码不一致');
            try {
                validate(userValidate::class)
                    ->scene('Reg')
                    ->check($data);
            }catch (ValidateException $e){
                $this->error($e->getError());
            }
            if(!captcha_check($data['vercode'])) $this->error('验证码错误');
            $num = rand(0,13);
            $data['avatar'] = '/static/addons/bbs/images/avatar/'.$num.'.jpg';
            $data['password'] = password_hash($data['password'],PASSWORD_BCRYPT,SignHelper::passwordSalt());
            session('regData',$data);
            $code = mt_rand('100000','999999');
            $time = 10*60;
            $content = '亲爱的lemo社区用户:'.$data['username'].'<br>您正在激活邮箱，您的验证码为:'.$code .'，请在'.$time/60 .'分钟内进行验证';
            $mail = MailHelper::sendEmail($data['email'], 'lemocms 社区邮箱激活邮件', $content);
            if($mail['code']>0){
                cookie('code',$code,$time);
                cookie('email',$data['email'],$time);
                cookie('username',$data['username'],$time);
                $this->success('激活邮件已经发送成功,请输入邮箱验证码，激活邮件',url('login/reg'));
            }else{
                $this->error('发送失败');
            }

//            $this->success('注册成功！',url('login/reg'));
        }
        if($this->request->param('type')==1){
            cookie('code',null);
            cookie('email',null);
            cookie('username',null);
        }
        return view();

    }

    //注册激活
    public function regActive(){
        if($this->request->isPost()){

            $data = $this->request->post();
            //校验场景中重置密码的方法
            try{
                validate(userValidate::class)
                    ->scene('RegActive')
                    ->check($data);
            } catch (ValidateException $e) {
                $this->error($e->getError());
            }
            if(!cookie('code')){
                $this->error('验证码错误！',url('login/reg'));

            }
            if($data['vercode'] != cookie('code')){
                $this->error('验证码错误！');
            }
            $data = cookie('regData');
            $data['email_validated'] = 1;
            $user = UserModel::create($data);
            if ($user) {
                cookie('code',null);
                cookie('email',null);
                cookie('username',null);
                $this->success('激活成功',url('login/login'));
            } else {
                $this->error('激活失败');
            }
        }

    }

    /*
     * 忘记密码
     */


    public function forget(){

        if($this->request->isPost()){
            $data = $this->request->post();
            if(!captcha_check($data['vercode'])) $this->error('验证码错误');
            $user = UserModel::where('email',$data['email'])->find();
            if(!$user) $this->error('邮箱不存在');
            $code = mt_rand('100000','999999');
            $time = 10*60;
            $content = '亲爱的lemobbs用户:'.$user->name.'<br>您正在重置密码，您的验证码为:'.$code .'，请在'.$time/60  .'分钟内进行验证';
            $mail = MailHelper::sendEmail($user->email, 'lemobbs 重置密码邮件', $content);
            if($mail['code']>0){
                cookie('forget_code',$code,$time);
                cookie('forget_uid',$user->id,$time);
                cookie('forget_email',$user->email,$time);
                $this->success('发送成功',url('login/forget'));
            }else{
                $this->error('发送失败');
            }
        }
        if($this->request->param('type')==1){
            cookie('forget_code',null);
            cookie('forget_uid',null);
            cookie('forget_email',null);
        }
        return view();
    }
    //重置密码
    public function repass(){
        if($this->request->isPost()){

            $data = $this->request->post();
            //校验场景中重置密码的方法
            try{
                validate(userValidate::class)
                    ->scene('Repass')
                    ->check($data);
            } catch (ValidateException $e) {
                $this->error($e->getError());
            }
            if(!cookie('forget_code')){
                $this->error('验证码错误！',url('login/forget'));

            }
            if($data['vercode']!=cookie('forget_code')){
                $this->error('验证码错误！');
            }
            $data['password'] = password_hash($data['password'],PASSWORD_BCRYPT,SignHelper::passwordSalt());
            $user = UserModel::find($data['id']);
            $user->password =  $data['password'];
            $res = $user->save();
            cookie('forget_code',null);
            cookie('forget_uid',null);
            cookie('forget_email',null);
            if ($res) {

                $this->success('修改成功',url('login/login'));
            } else {
                $this->error('修改失败');
            }
        }
    }
    /**
     * @return \think\Response
     * 验证码
     */
    public function verify()
    {
        return Captcha::create();
    }

}