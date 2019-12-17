<?php
namespace app\admin\controller;
use \think\Controller;
use app\admin\controller\Base;
use \think\Db;
use \think\Cookie;
use \think\Session;
use \think\Request;
class Shop extends Base{
	public function __construct(){
		parent::__construct(); //使用父类的构造方法
	}
/************************************************************************/
/** 店铺管理
/************************************************************************/
	public function lists(){
		$where[] = ['is_del','=','n'];
		$sheng = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>1])->order('region_order asc,region_id asc')->select();
    	$this->assign('sheng',$sheng);
		$sheng_id = $this->request->param('sheng') ? $this->request->param('sheng') : '';
		if($sheng_id!=''){
			$where[] = ['sheng','=',$sheng_id];
			$shi = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>$sheng_id])->order('region_order asc,region_id asc')->select();
			$this->assign('shi',$shi);
			$shi_id = $this->request->param('shi') ? $this->request->param('shi') : '';
			if($shi_id!=''){
				$where[] = ['shi','=',$shi_id];
				$qu = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>$shi_id])->order('region_order asc,region_id asc')->select();
				$this->assign('qu',$qu);
				$qu_id = $this->request->param('qu') ? $this->request->param('qu') : '';
				if($qu_id!=''){
					$where[] = ['qu','=',$qu_id];
				}
				$this->assign('qu_id',$qu_id);
			}
			$this->assign('shi_id',$shi_id);
		}
		$this->assign('sheng_id',$sheng_id);

		$admininfo = $this->admininfo;
		if($admininfo['shop_id']>0){
			$where[] = ['id','=',$admininfo['shop_id']];
		}

		$keys = $this->request->param('keys') ? $this->request->param('keys') : '';
		if($keys!=''){
			$where[] = ['title|description','like','%'.$keys.'%'];
		}
		$this->assign('keys',$keys);
		$list = Db::name('shop')->field('id,title,logo,diqu,address,orders,is_show')->where($where)->order('orders asc,addtime desc')->paginate(20);
		$this->assign('list',$list);
		$count = Db::name('shop')->where($where)->count();
		$this->assign('count',$count);
		return $this->fetch();
	}

	public function publish(){
		$id  = $this->request->param('id')  ? $this->request->param('id')  : '0';
    	if($this->request->isPost()){
    		$post = $this->request->post();
    		$admininfo = $this->admininfo;
    		$validate = new \think\Validate();
            $rule =   [
                'title'  => 'require',
                'sheng'  => 'require',
                'shi'    => 'require',
                'qu'     => 'require',
                'address'=> 'require'
            ];
            $message  =   [
                'title.require' => '店铺名称不能为空',
                'sheng.require' => '请选择所在地',
                'shi.require'   => '请选择所在地',
                'qu.require'    => '请选择所在地',
                'address.require'=> '店铺地址不能为空'
            ];
            $validate->message($message);
            //验证部分数据合法性
            if (!$validate->check($post,$rule)) {
                return json(['code'=>2,'msg'=>$validate->getError(),'returnData'=>'']);die;
            }
            if(!$post['logo']){
            	return json(['code'=>3,'msg'=>'请上传LOGO','returnData'=>'']);die;
            }
            if(isset($post['pics'])){
            	$post['pics'] = implode(",",$post['pics']);
            }
    		if(empty($post['is_show'])){
	       		$post['is_show'] = 'n';
	       	}
	       	$diqu = '';
	       	if($post['sheng']>0){
	       		$cksheng = Db::name('region')->field('region_id,region_name')->where('region_id',$post['sheng'])->find();
	       		$diqu .= $cksheng['region_name'];
	       	}else{
	       		$post['sheng'] = 0;
	       	}
	       	if($post['shi']>0){
	       		$ckshi = Db::name('region')->field('region_id,region_name')->where('region_id',$post['shi'])->find();
	       		$diqu .= '-'.$ckshi['region_name'];
	       	}else{
	       		$post['shi'] = 0;
	       	}
	       	if($post['qu']>0){
	       		$ckqu = Db::name('region')->field('region_id,region_name')->where('region_id',$post['qu'])->find();
	       		$diqu .= '-'.$ckqu['region_name'];
	       	}else{
	       		$post['qu'] = 0;
	       	}
	       	$post['diqu'] = $diqu;
	       	if(isset($post['id'])){
	       		//验证菜单是否存在
	            $menu = Db::name('shop')->field('id')->where('id',$post['id'])->find();
	            if(empty($menu)) {
	            	return json(['code'=>3,'msg'=>'ID不正确','returnData'=>'']);die;
	            }
	       		$post['updatetime']   = time();
	       		$post['update_admin'] = $admininfo['id'];
                $res = Db::name('shop')->where('id',$post['id'])->update($post);
	       		if($res) {
	            	return json(['code'=>0,'msg'=>'修改成功','returnData'=>'']);die;
	        	} else {
	            	return json(['code'=>4,'msg'=>'修改失败','returnData'=>'']);die;
	       		}
	       	}else{
	       		$post['addtime']  = $post['updatetime']   = time();
	       		$post['admin_id'] = $post['update_admin'] = $admininfo['id'];
                $res = Db::name('shop')->insert($post);
	       		if($res) {
                    return json(['code'=>0,'msg'=>'添加成功','returnData'=>'']);die;
                } else {
                    return json(['code'=>5,'msg'=>'添加失败','returnData'=>'']);die;
                }
	       	}
    	}else{
    		$sheng = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>1])->order('region_order asc,region_id asc')->select();
    		$this->assign('sheng',$sheng);
    		$one = Db::name('shop')->where('id',$id)->find();
    		if($one){
    			if($one['sheng']>0){
    				$shi = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>$one['sheng']])->order('region_order asc,region_id asc')->select();
    				$this->assign('shi',$shi);
    			}
    			if($one['shi']>0){
    				$qu = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>$one['shi']])->order('region_order asc,region_id asc')->select();
    				$this->assign('qu',$qu);
    			}
    			if($one['pics']){
	    			$one['pics'] = explode(",",$one['pics']);
	    		}
    		}
    		$this->assign('one',$one);
    		return $this->fetch();
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
    		$res = Db::name('shop')->where('id',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'操作成功','va'=>$va]);
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
    	}
	}
	public function orders(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
    		$data['orders'] = $post['va'];
    		$res = Db::name('shop')->where('id',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'操作成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
    	}
	}
	public function del(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
            if(empty($post['id'])){
                return json(['code'=>2,'msg'=>'请选择要删除的店铺']);
            }
    		$data['is_del'] = 'y';
    		$res = Db::name('shop')->where('id','in',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'删除成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'删除失败']);
    		}
    	}
	}
/************************************************************************/
/** 店铺管理员管理
/************************************************************************/
	public function admins($where=array()){
		$where[] = ['a.is_del','=','n'];
		$admininfo = $this->admininfo;
		$keys = $this->request->param('keys') ? $this->request->param('keys') : '';
		if($keys!=''){
			$where[] = ['a.username|a.nickname|a.phone','like','%'.$keys.'%'];
		}
		$this->assign('keys',$keys);
		if($admininfo['shop_id']>0){
			$where[] = ['shop_id','=',$admininfo['shop_id']];
		}else{
			$sheng = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>1])->order('region_order asc,region_id asc')->select();
	    	$this->assign('sheng',$sheng);
			$sheng_id = $this->request->param('sheng') ? $this->request->param('sheng') : '';
			$whereshop['is_del'] = 'n';
			if($sheng_id!=''){
				$whereshop['sheng'] = $sheng_id;
				$shi = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>$sheng_id])->order('region_order asc,region_id asc')->select();
				$this->assign('shi',$shi);
				$shi_id = $this->request->param('shi') ? $this->request->param('shi') : '';
				if($shi_id!=''){
					$whereshop['shi'] = $shi_id;
					$qu = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>$shi_id])->order('region_order asc,region_id asc')->select();
					$this->assign('qu',$qu);
					$qu_id = $this->request->param('qu') ? $this->request->param('qu') : '';
					if($qu_id!=''){
						$whereshop['qu'] = $qu_id;
					}
					$this->assign('qu_id',$qu_id);
				}
				$this->assign('shi_id',$shi_id);
			}
			$this->assign('sheng_id',$sheng_id);
			$shop_id = $this->request->param('shop_id') ? $this->request->param('shop_id') : '';
			if($shop_id!=''){
				$where[] = ['a.shop_id','=', $shop_id];
			}
			$this->assign('shop_id',$shop_id);
			$shop = Db::name('shop')->field('id,title')->where($whereshop)->order('orders asc,addtime desc')->select();
			$st_id = [];
			foreach($shop as $k=>$v){
				$st_id[] = $v['id'];
			}
			if($st_id){
				$st_id = implode(",",$st_id);
				$where[] = ['a.shop_id','in', $st_id];
			}else{
				$where[] = ['a.shop_id','in', 0];
			}
			$this->assign('shop',$shop);
		}
		$list = Db::name('admin')->alias('a')
				->field('a.id,a.username,a.nickname,a.phone,a.shop_type,a.is_show,s.title as shop_title,s.logo')
				->where('a.shop_id','neq','0')
				->where($where)
				->join('shop s', 's.id = a.shop_id', 'left')
				->order('a.shop_type desc,a.addtime desc')
				->paginate(20);
		$this->assign('list',$list);
		$count = Db::name('admin')->alias('a')->where('a.shop_id','neq','0')->where($where)->count();
		$this->assign('count',$count);
		return $this->fetch();
	}
	public function admins_publish(){
		$id  = $this->request->param('id')  ? $this->request->param('id')  : '0';
    	if($this->request->isPost()){
    		$post = $this->request->post();
    		$validate = new \think\Validate();
            $rule =   [
                'shop_id'   => 'require',
                'nickname'  => 'require',
                'username'  => 'require|min:3|max:32',
                'phone'     => 'require'
            ];
            $message  =   [
                'shop_id.require'  => '请选择所属店铺',
                'nickname.require' => '店铺管理员昵称不能为空',
                'username.require' => '店铺管理员账号不能为空',
                'username.max'     => '店铺管理员账号最多不超过32个字符',
        		'username.min'     => '店铺管理员账号最少3个字符',
                'phone.require'    => '店铺管理员手机号码不能为空'
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
	       		//检测账号是否存在
	       		$chadmin = Db::name('admin')->field('id')->where('username',$post['username'])->where('is_del','n')->where('id','neq',$post['id'])->find();
	       		if($chadmin){
	       			return json(['code'=>6,'msg'=>'该账号已存在，请换一个账号','returnData'=>'']);die;
	       		}
	       		$post['updatetime'] = time();
	       		if(isset($post['password']) && $post['password']!=''){
	       			$post['password'] = MD5(MD5($post['password']));
	       		}else{
	       			unset($post['password']);
	       		}
	            $menu = Db::name('admin')->field('id')->where('id',$post['id'])->find();
	            if(empty($menu)) {
	            	return json(['code'=>3,'msg'=>'ID不正确','returnData'=>'']);die;
	            }
                $res = Db::name('admin')->where('id',$post['id'])->update($post);
	       		if($res) {
	            	return json(['code'=>0,'msg'=>'修改成功','returnData'=>'']);die;
	        	} else {
	            	return json(['code'=>4,'msg'=>'修改失败','returnData'=>'']);die;
	       		}
	       	}else{
	       		//检测账号是否存在
	       		$chadmin = Db::name('admin')->field('id')->where('username',$post['username'])->find();
	       		if($chadmin){
	       			return json(['code'=>6,'msg'=>'该账号已存在，请换一个账号','returnData'=>'']);die;
	       		}
	       		$post['addtime'] = $post['updatetime'] = time();
	       		$post['addip']   = $this->request->ip();
	       		if(isset($post['password']) && $post['password']!=''){
	       			$post['password'] = MD5(MD5($post['password']));
	       		}else{
	       			$post['password'] = MD5(MD5('88888888'));
	       		}
                $res = Db::name('admin')->insert($post);
	       		if($res) {
                    return json(['code'=>0,'msg'=>'添加成功','returnData'=>'']);die;
                } else {
                    return json(['code'=>5,'msg'=>'添加失败','returnData'=>'']);die;
                }
	       	}
    	}else{
    		$shop = Db::name('shop')->field('id,title')->where('is_del','n')->order('orders asc,addtime desc')->select();
    		$this->assign('shop',$shop);
    		$one = Db::name('admin')->where('id',$id)->find();
    		$this->assign('one',$one);
    		return $this->fetch();
    	}
	}
	public function admins_show(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
    		if($post['va']=='n'){
    			$va = 'y';
    		}else{
    			$va = 'n';
    		}
    		$data['is_show'] = $va;
    		$res = Db::name('admin')->where('id',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'操作成功','va'=>$va]);
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
    	}
	}
	public function admins_del(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
            if(empty($post['id'])){
                return json(['code'=>2,'msg'=>'请选择要删除的店铺管理员']);
            }
    		$data['is_del'] = 'y';
    		$res = Db::name('admin')->where('id','in',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'删除成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'删除失败']);
    		}
    	}
	}
}