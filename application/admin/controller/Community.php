<?php
namespace app\admin\controller;
use \think\Controller;
use app\admin\controller\Base;
use \think\Db;
use \think\Cookie;
use \think\Session;
use \think\Request;
class Community extends Base{
	public function __construct(){
		parent::__construct(); //使用父类的构造方法
	}
	public function index(){

	}
/******************************************************/
/** 话题列表
/******************************************************/
	public function lists(){
		$keys = $this->request->param('keys') ? $this->request->param('keys') : '';
		$this->assign('keys',$keys);
		if($keys!=''){
			$where['title'] = ['like','%'.$keys.'%'];
		}
		$where['is_del'] = 'n';
		$list = Db::name('shequ')->where($where)->order("addtime desc")->paginate(10);
		$count = Db::name('shequ')->field(['id'])->where($where)->count();
	    $this->assign('count',$count);
	    $this->assign('list',$list);
    	return $this->fetch();
	}
	public function looks(){
		$id = $this->request->param('id') ? $this->request->param('id') : '0';
		$one = Db::name('shequ')->where('id',$id)->find();
		$this->assign('one',$one);
		return $this->fetch();
	}
	public function shows(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
    		if($post['va']=='n'){
    			$va = 'y';
    		}else{
    			$va = 'n';
    		}
    		$data['is_show'] = $va;
    		$res = Db::name("shequ")->where(['id'=>$post['id']])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'操作成功','va'=>$va]);
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
    	}
	}
	public function del(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
            if(empty($post['id'])){
                return json(['code'=>2,'msg'=>'请选择要删除的社区']);
            }
    		$data['is_del'] = 'y';
    		$res = Db::name("shequ")->where('id','in',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'删除成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'删除失败']);
    		}
    	}
	}

/******************************************************/
/** 话题类型
/******************************************************/
	public function cate(){
		$list = Db::name('shequ_cate')->where(['is_del'=>'n'])->order('aorder asc')->select();
    	$this->assign('list',$list);
    	return $this->fetch();
	}
	public function cate_publish(){
		$id = $this->request->param('id') ? $this->request->param('id') : '0';
		if($this->request->isPost()){
			$post = $this->request->post();
			if($post['cate_title']==""){
				return json(['code'=>2,'msg'=>'名称不能为空']);
			}
			$admininfo = $this->admininfo;
			if(isset($post['id'])){
				$post['updatetime'] = time();
				$post['update_admin'] = $admininfo['id'];
				$res = Db::name('shequ_cate')->where(['id'=>$post['id']])->update($post);
				if($res){
		       		return json(['code'=>0,'msg'=>'操作成功']);
		       	}else{
		       		return json(['code'=>1,'msg'=>'操作失败']);
		       	}
			}else{
				$post['addtime'] = $post['updatetime'] = time();
				$post['admin_id'] = $post['update_admin'] = $admininfo['id'];
				$res = Db::name('shequ_cate')->insert($post);
				if($res){
		       		return json(['code'=>0,'msg'=>'操作成功']);
		       	}else{
		       		return json(['code'=>1,'msg'=>'操作失败']);
		       	}
			}
		}else{
			if($id>0){
				$one = Db::name('shequ_cate')->field(['id','cate_title'])->where('id',$id)->find();
				$this->assign('one',$one);
			}
			return $this->fetch();
		}
	}
	public function cate_show(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
    		if($post['va']=='n'){
    			$va = 'y';
    		}else{
    			$va = 'n';
    		}
    		$data['is_show'] = $va;
    		$res = Db::name('shequ_cate')->where(['id'=>$post['id']])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'操作成功','va'=>$va]);
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
    	}
	}
	public function cate_order(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
    		$data['aorder'] = $post['va'];
    		$res = Db::name('shequ_cate')->where(['id'=>$post['id']])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'操作成功']); 
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
    	}
	}
	public function cate_del(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
            if(empty($post['id'])){
                return json(['code'=>2,'msg'=>'请选择要删除的社区类型']);
            }
    		$data['is_del'] = 'y';
    		$res = Db::name('shequ_cate')->where('id','in',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'删除成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'删除失败']);
    		}
    	}
	}
}