<?php
namespace app\admin\controller;
use \think\Controller;
use app\admin\controller\Base;
use \think\Db;
use \think\Cookie;
use \think\Session;
use \think\Request;
class System extends Base{
	public function __construct(){
		parent::__construct(); //使用父类的构造方法
	}

	public function web(){
		if($this->request->isPost()){
			$post = $this->request->post();
			$res = Db::name('webconfig')->where(['id'=>'1'])->update($post);
			if($res){
				return json(['code'=>0,'msg'=>'修改成功','returnData'=>'']);die;
			}else{
				return json(['code'=>1,'msg'=>'修改失败','returnData'=>'']);die;
			}
		}else{
			$one = Db::name('webconfig')->where('id',1)->find();
			$this->assign('one',$one);
			return $this->fetch();
		}
	}
	public function publish(){
		if($this->request->isPost()){
			$post = $this->request->post();
			$post['updatetime'] = time();
			$res = Db::name('webconfig')->where(['id'=>'1'])->update($post);
			if($res){
				return json(['code'=>0,'msg'=>'修改成功','returnData'=>'']);die;
			}else{
				return json(['code'=>1,'msg'=>'修改失败','returnData'=>'']);die;
			}
		}
	}
}