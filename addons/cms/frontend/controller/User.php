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

use lemo\helper\MailHelper;
use lemo\helper\SignHelper;
use think\captcha\facade\Captcha;
use think\facade\Cookie;
use think\facade\Session;

class User extends CmsBase
{
    public function initialize()
    {
        $this->uid = session('uid');

        parent::initialize(); // TODO: Change the autogenerated stub

    }

    public function login()
    {
        if ($this->uid) {
            $this->redirect(url('index/index'));
        }
        if ($this->request->isPost()) {
            $data = input('post.');
            $user = \app\common\model\Member::where('username|email|mobile', $data['username'])->find();
            if (!captcha_check($data['vercode'])) {
                $this->error('验证码错误');
            }
            if (!$user) {
                $this->error('用户不存在,请先注册');
            }
            if (!password_verify($data['password'], $user['password'])) {
                $this->error('密码错误');
            }
            session('user', $user);
            session('uid', $user->id);
            $this->success('登陆成功');

        } else {

            return view('user_login');
        }

    }

    public function register()
    {
        if ($this->uid) {
            $this->redirect(url('index_index'));
        }
        if ($this->request->isPost()) {
            $data = input('post.');
            $password = $data['password'];
            try {
                $this->validate($data, 'User');
            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }
            if (!captcha_check($data['vercode'])) {
                $this->error('验证码错误');
            }
            $data['password'] = password_hash($password, PASSWORD_BCRYPT, SignHelper::passwordSalt());
            \app\common\model\Member::create($data);
            $this->success('注册成功');
        }
        return view('user_register');

    }

    public function forget()
    {
        if ($this->uid) {
            $this->redirect(url('index/index'));
        }
        if ($this->request->isPost()) {
            $data = input('post.');
            $password = $data['password'];
            try {
                $this->validate($data, 'User');
            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }
            if (!captcha_check($data['vercode'])) {
                $this->error('验证码错误');
            }
            $data['password'] = password_hash($password, PASSWORD_BCRYPT, SignHelper::passwordSalt());
            \app\common\model\Member::create($data);
            $this->success('注册成功');
        }
        return view('user_forget');

    }


    public function reset()
    {
        if ($this->request->isPost()) {
            $data = input('post.');
            if(cookie('code')){
//                if(cookie('code') != $data['vercode']){
//                    $this->error('验证码错误');
//                }

                if(cookie('email') != $data['email']){
                    $this->error('邮箱错误');
                }
                if( strlen($data['password'])<6){
                    $this->error('密码长度不够');
                }
                $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT, SignHelper::passwordSalt());
                $user = \app\common\model\User::where('email',$data['email'])->find();
                $user->password = $data['password'];
                if(!$user->save()){
                    $this->error('重置失败');
                }else{
                    Cookie::clear();
                    cookie('code',null);
                    cookie('email',null);
                    cookie('user',null);
                    $this->success('重置成功',url('user/login'));
                }
            }else{

                if (!captcha_check($data['vercode'])) {
                    $this->error('验证码错误');
                }
                $user = \app\common\model\User::where('email',$data['email'])->find();
                cookie('user',$user);
                if(!$user){
                    $this->redirect(url('error/err', ['message' => '邮箱不存在']));
                }else{
                    $code = mt_rand('100000','999999');
                    $time = 10*60;
                    $content = '亲爱的funadmin用户:'.$user['username'].'<br>您正在找回密码，您的验证码为:'.$code .'，请在'.$time/60 .'分钟内进行验证';
                    $mail = MailHelper::sendEmail($user['email'], 'funadmin密码找回邮件', $content);
                    if($mail['code']>0){
                        cookie('code',$code,$time);
                        cookie('email',$user['email'],$time);
                        cookie('username',$user['username'],$time);
                        $this->success('邮件已经发送成功,请输入邮箱验证码，并重置密码',url('user/reset'));
                    }else{
                        $this->error('发送失败');
                    }
                }

            }

        }
        return view('reset');

    }

    public function verify()
    {

        return Captcha::create();
    }

    public function logout()
    {
        Session::set('uid', null);
        Session::set('user', null);
        $this->redirect(url('error/err', ['message' => '退出成功']));
    }

}