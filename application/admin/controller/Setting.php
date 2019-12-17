<?php
namespace app\admin\controller;
use \think\Controller;
use app\admin\controller\Base;
use \think\Db;
use \think\Cookie;
use \think\Session;
use \think\Request;
class Setting extends Base{
	public function __construct(){
		parent::__construct(); //使用父类的构造方法
		$admininfo = $this->admininfo;
		if($admininfo['id']!='1'){
			return $this->error('您没有权限进行该操作');die;
		}
	}
/************************************************************************/
/** 后台菜单
/************************************************************************/
    public function menus(){
    	$list = DB::name('admin_menus')->where(['pid'=>'0','is_del'=>'n'])->order('orders asc,addtime asc')->select();
    	foreach($list as $k=>$v){
    		$child = DB::name('admin_menus')->where(['pid'=>$v['id'],'is_del'=>'n'])->order('orders asc,addtime asc')->select();
    		foreach($child as $key=>$val){
    			$childa = DB::name('admin_menus')->where(['pid'=>$val['id'],'is_del'=>'n'])->order('orders asc,addtime asc')->select();
    			$child[$key]['childa'] = $childa;
    		}
    		$list[$k]['child'] = $child;
    	}
    	$this->assign('list',$list);
    	$count = DB::name('admin_menus')->where(['is_del'=>'n'])->count();
    	$this->assign('count',$count);
    	return $this->fetch();
    }
    public function menus_publish(){
    	$adminmenus = Db::name('admin_menus');
    	$pid = $this->request->param('pid') ? $this->request->param('pid') : '0';
    	$id  = $this->request->param('id')  ? $this->request->param('id')  : '0';
    	if($this->request->isPost()){
    		$post = $this->request->post();
    		$admininfo = $this->admininfo;

    		$validate = new \think\Validate();
            $rule =   [
                'title'  => 'require',
                'mm'     => 'require',
                'cc'     => 'require',
                'aa'     => 'require',  
            ];
            $message  =   [
                'title.require' => '标题不能为空',
                'mm.require'    => '模块名不能为空',
                'cc.require'    => '控制器名不能为空',
                'aa.require'    => '方法名不能为空'
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
	            $menu = $adminmenus->where('id',$post['id'])->find();
	            if(empty($menu)) {
	            	return json(['code'=>3,'msg'=>'ID不正确','returnData'=>'']);die;
	            }
	       		$post['updatetime']   = time();
	       		$post['update_admin'] = $admininfo['id'];
                $res = $adminmenus->where('id',$post['id'])->update($post);
	       		if($res) {
	            	return json(['code'=>0,'msg'=>'修改成功','returnData'=>'']);die;
	        	} else {
	            	return json(['code'=>4,'msg'=>'修改失败','returnData'=>'']);die;
	       		}
	       	}else{
	       		$post['addtime']  = $post['updatetime']   = time();
	       		$post['admin_id'] = $post['update_admin'] = $admininfo['id'];
                $res = $adminmenus->insert($post);
	       		if($res) {
                    return json(['code'=>0,'msg'=>'添加成功','returnData'=>'']);die;
                } else {
                    return json(['code'=>5,'msg'=>'添加失败','returnData'=>'']);die;
                }
	       	}
    	}else{
	    	if($id>0){
	    		$one = Db::name('admin_menus')->where(['id'=>$id])->find();
	    		$this->assign('one',$one);
                $pid = $one['pid'];
	    	}
	    	$tylist = Db::name('admin_menus')->field(['id','title'])->where(['pid'=>'0','is_del'=>'n'])->select();
	    	foreach($tylist as $k=>$v){
	    		$tylist[$k]['child'] = Db::name('admin_menus')->field(['id','title'])->where(['pid'=>$v['id'],'is_del'=>'n'])->select();
	    	}
	    	$this->assign('tylist',$tylist);
	    	$this->assign('pid',$pid);
	    	return $this->fetch();
	    }
    }
    public function menus_order(){
    	if($this->request->isAjax()){
    		$adminmenus = Db::name('admin_menus');
    		$post = $this->request->post();
    		$data['orders'] = $post['va'];
    		$res = $adminmenus->where('id',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'操作成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
    	}
    }
    public function menus_show(){
    	if($this->request->isAjax()){
    		$adminmenus = Db::name('admin_menus');
    		$post = $this->request->post();
    		if($post['va']=='n'){
    			$va = 'y';
    		}else{
    			$va = 'n';
    		}
    		$data['is_show'] = $va;
    		$res = $adminmenus->where('id',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'操作成功','va'=>$va]);
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
    	}
    }
    public function menus_del(){
    	if($this->request->isAjax()){
    		$adminmenus = Db::name('admin_menus');
    		$post = $this->request->post();
            if(empty($post['id'])){
                return json(['code'=>2,'msg'=>'请选择要删除的栏目']);
            }
    		$data['is_del'] = 'y';
    		$res = $adminmenus->where(['id'=>['in',$post['id']]])->save($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'删除成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'删除失败']);
    		}
    	}
    }
/************************************************************************/
/** URL美化
/************************************************************************/
	public function urls(){
		$list = Db::name('urlconfig')->select();
		$this->assign('list',$list);
		return $this->fetch();
	}
	public function urls_publish(){
    	$table = Db::name('urlconfig');
    	$id  = $this->request->param('id')  ? $this->request->param('id')  : '0';
    	if($this->request->isPost()){
    		$post = $this->request->post();
    		$admininfo = $this->admininfo;

            $validate = new \think\Validate();
            $rule =   [
                'url'     => 'require',
                'aliases' => 'require'
            ];
            $message  =   [
                'url.require'     => '美化前URL不能为空',
                'aliases.require' => '美化后URL不能为空'
            ];
            $validate->message($message);
            //验证部分数据合法性
            if (!$validate->check($post,$rule)) {
                return json(['code'=>2,'msg'=>$validate->getError(),'returnData'=>'']);die;
            }

	       	if(empty($post['status'])){
	       		$post['status'] = 0;
	       	}
	       	if(isset($post['id'])){
	       		//验证菜单是否存在
	            $urls = $table->where('id',$post['id'])->find();
	            if(empty($urls)) {
	            	return $this->error('id不正确');
	            }
	       		$post['updatetime']   = time();
	       		$res = $table->where(['id'=>$post['id']])->update($post);
	       		if($res) {
                    return json(['code'=>0,'msg'=>'修改成功']);
                } else {
                    return json(['code'=>1,'msg'=>'修改失败']);
                }
	       	}else{
	       		$post['addtime']  = $post['updatetime']   = time();
	       		$res = $table->insert($post);
	       		if($res) {
	            	return json(['code'=>0,'msg'=>'添加成功']);
	        	} else {
	            	return json(['code'=>1,'msg'=>'添加失败']);
	       		}
	       	}
    	}else{
	    	if($id>0){
	    		$one = $table->where(['id'=>$id])->find();
	    		$this->assign('one',$one);
	    	}
	    	return $this->fetch();
	    }
    }
	public function urls_show(){
    	if($this->request->isAjax()){
    		$table = Db::name('urlconfig');
    		$post = $this->request->post();
    		if($post['va']=='1'){
    			$va = '0';
    		}else{
    			$va = '1';
    		}
    		$data['status'] = $va;
    		$res = $table->where(['id'=>$post['id']])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'操作成功','va'=>$va]);
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
    	}
    }
	public function urls_del(){
		if($this->request->isAjax()){
    		$table = Db::name('urlconfig');
    		$post = $this->request->post();
            if(empty($post['id'])){
                return json(['code'=>2,'msg'=>'请选择要删除的URL']);
            }
    		$res = $table->where(['id'=>['in',$post['id']]])->delete();
    		if($res){
    			return json(['code'=>0,'msg'=>'删除成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'删除失败']);
    		}
    	}
	}
}