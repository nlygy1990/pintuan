<?php
namespace app\admin\controller;
use \think\Controller;
use app\admin\controller\Base;
use \think\Db;
use \think\Session;
use \think\Cookie;
use \think\Request;
use \think\AES;
class Member extends Base{
/******************************************************/
/** 会员管理
/******************************************************/
	public function lists(){
		$where[] = ['m.is_del','=','n'];
		$status = $this->request->param('status') ? $this->request->param('status') : 'all';
		$this->assign('status',$status);
		if($status!="all"){
			$where[] = ['m.is_show','=',$status];
		}

		$pid = $this->request->param('pid') ? $this->request->param('pid') : 'all';
		$this->assign('pid',$pid);
		if($pid!="all"){
			if($pid=="pt"){
				$where[] = ['m.level','=','0'];
			}else{
				$where[] = ['m.level','=',$pid];
			}
		}
		$id = $this->request->param('id') ? $this->request->param('id') : '';
		$this->assign('id',$id);
		if($id!=''){
		    $where[] = ['m.id','=',$id];
		}

		$keys = $this->request->param('keys') ? $this->request->param('keys') : '';
		$this->assign('keys',$keys);
		if($keys!=""){
			$where[] = ['m.id|m.username|m.nickname|m.phone|m.email','like',"%".$keys."%"];
		}

		$list = Db::name('member')->alias('m')
				->field('m.id,m.username,m.nickname,m.phone,m.oauth,m.head_pic,m.avatar,m.addtime,m.addip,m.last_login,m.last_ip,mc.title as cate_title,mc.thumb,m.is_show')
				->where($where)
				->join('member_cate mc','m.level=mc.id','left')
				->order('m.addtime desc')
				->paginate(20,false,['query' => request()->param()]);
		$count = Db::name('member')->alias('m')->where($where)->count();
		$this->assign('list',$list);
		$this->assign('count',$count);
		$catelist = Db::name('member_cate')->field('id,title')->where('is_del','n')->order('orders asc,id asc')->select();
		$this->assign('catelist',$catelist);
		return $this->fetch();
	}
	public function looks(){
		$catelist = Db::name('member_cate')->field('id,title')->where('is_del','n')->order('orders asc,id asc')->select();
		$this->assign('catelist',$catelist);
		
		$id = $this->request->param('id') ?$this->request->param('id') : 0;
		$one = Db::name('member')->where('id',$id)->find();
		$this->assign('one',$one);
		return $this->fetch(); 
	}
	public function looks_publish(){
		if($this->request->isPost()){
			$data = $this->request->post();
			$data['updatetime'] = time();
			$one = Db::name('member')->field('pid1,pid2,id,tz_level')->where('id',$data['id'])->find();
			if($one['tz_level']!=$one['tz_level']){ //升级或者降级

			}
			$res = Db::name('member')->where('id',$data['id'])->update($data);
			if($res){
				return json(['code'=>0,'msg'=>'保存成功']);
			}else{
				return json(['code'=>1,'msg'=>'保存失败']);
			}
		}
	}
	public function checkmember(){
		$post = $this->request->post();
		$va = $post['va'];
		$where[] = ['is_del','=','n'];
		$where[] = ['id','neq',$post['uid']];
		$where[] = ['phone|nickname','like','%'.$va.'%'];
		$list = Db::name('member')->field('id,nickname,phone')->where($where)->order('addtime desc')->select();
		if($list){
			return json(['code'=>0,'list'=>$list]);
		}else{
			return json(['code'=>0,'list'=>"暂无数据"]);
		}
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
    		$res = Db::name('member')->where('id',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'操作成功','va'=>$va]);
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
    	}
	}
/******************************************************/
/** 会员等级
/******************************************************/
	public function cate(){
		$list = Db::name('member_cate')->where('is_del','n')->order('orders asc,id asc')->select();
		$this->assign('list',$list);
		return $this->fetch();
	}
	public function cate_publish(){
		$id  = $this->request->param('id')  ? $this->request->param('id')  : '0';
    	if($this->request->isPost()){
    		$post = $this->request->post();
    		$admininfo = $this->admininfo;
    		$validate = new \think\Validate();
            $rule =   [
                'title'  => 'require'
            ];
            $message  =   [
                'title.require' => '等级名称不能为空'
            ];
            $validate->message($message);
            //验证部分数据合法性
            if (!$validate->check($post,$rule)) {
                return json(['code'=>2,'msg'=>$validate->getError(),'returnData'=>'']);die;
            }
	       	if(empty($post['is_show'])){
	       		$post['is_show'] = 'n';
	       	}
	       	if(isset($post['id'])){
	       		//验证菜单是否存在
	            $menu = Db::name('member_cate')->where('id',$post['id'])->find();
	            if(empty($menu)) {
	            	return json(['code'=>3,'msg'=>'ID不正确','returnData'=>'']);die;
	            }
	       		$post['updatetime']   = time();
	       		$post['update_admin'] = $admininfo['id'];
                $res = Db::name('member_cate')->where('id',$post['id'])->update($post);
	       		if($res) {
	            	return json(['code'=>0,'msg'=>'修改成功','returnData'=>'']);die;
	        	} else {
	            	return json(['code'=>4,'msg'=>'修改失败','returnData'=>'']);die;
	       		}
	       	}else{
	       		$post['addtime']  = $post['updatetime']   = time();
	       		$post['admin_id'] = $post['update_admin'] = $admininfo['id'];
                $res = Db::name('member_cate')->insert($post);
	       		if($res) {
                    return json(['code'=>0,'msg'=>'添加成功','returnData'=>'']);die;
                } else {
                    return json(['code'=>5,'msg'=>'添加失败','returnData'=>'']);die;
                }
	       	}
    	}else{
	    	if($id>0){
	    		$one = Db::name('member_cate')->where(['id'=>$id])->find();
	    		$this->assign('one',$one);
	    	}
	    	return $this->fetch();
	    }
	}
	public function cate_order(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
    		$data['orders'] = $post['va'];
    		$res = Db::name('member_cate')->where('id',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'操作成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
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
    		$res = Db::name('member_cate')->where('id',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'操作成功','va'=>$va]);
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
    	}
	}
	public function cate_del(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
            if(empty($post['id'])){
                return json(['code'=>2,'msg'=>'请选择要删除的等级']);
            }
    		$data['is_del'] = 'y';
    		$res = Db::name('member_cate')->where('id','in',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'删除成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'删除失败']);
    		}
    	}
	}
}