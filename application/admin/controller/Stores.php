<?php
namespace app\admin\controller;
use \think\Controller;
use app\admin\controller\Base;
use \think\Db;
use \think\Cookie;
use \think\Session;
use \think\Request;
class Stores extends Base{
	public function __construct(){
		parent::__construct(); //使用父类的构造方法
		$kehutype = array(
			0 =>array(
				'id'    => 1,
				'title' => '护理客户'
			),
			1 =>array(
				'id'    => 2,
				'title' => '测试客户'
			)
		);
		$this->assign('kehutype',$kehutype);
		$this->kehutype = $kehutype;
	}
/************************************************************************/
/** 门店管理
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
		if($admininfo['stores_id']>0){
			$where[] = ['id','=',$admininfo['stores_id']];
		}

		$keys = $this->request->param('keys') ? $this->request->param('keys') : '';
		if($keys!=''){
			$where[] = ['title|description','like','%'.$keys.'%'];
		}
		$this->assign('keys',$keys);
		$list = Db::name('stores')->field('id,title,logo,diqu,address,orders,is_show,lxr_name,lxr_tel')->where($where)->order('orders asc,addtime desc')->paginate(20);
		$this->assign('list',$list);
		$count = Db::name('stores')->where($where)->count();
		$this->assign('count',$count);
		return $this->fetch();
	}
	public function map(){
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
                'title.require' => '门店名称不能为空',
                'sheng.require' => '请选择所在地',
                'shi.require'   => '请选择所在地',
                'qu.require'    => '请选择所在地',
                'address.require'=> '门店地址不能为空'
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
	            $menu = Db::name('stores')->field('id')->where('id',$post['id'])->find();
	            if(empty($menu)) {
	            	return json(['code'=>3,'msg'=>'ID不正确','returnData'=>'']);die;
	            }
	       		$post['updatetime']   = time();
	       		$post['update_admin'] = $admininfo['id'];
                $res = Db::name('stores')->where('id',$post['id'])->update($post);
	       		if($res) {
	            	return json(['code'=>0,'msg'=>'修改成功','returnData'=>'']);die;
	        	} else {
	            	return json(['code'=>4,'msg'=>'修改失败','returnData'=>'']);die;
	       		}
	       	}else{
	       		$post['addtime']  = $post['updatetime']   = time();
	       		$post['admin_id'] = $post['update_admin'] = $admininfo['id'];
                $res = Db::name('stores')->insert($post);
	       		if($res) {
                    return json(['code'=>0,'msg'=>'添加成功','returnData'=>'']);die;
                } else {
                    return json(['code'=>5,'msg'=>'添加失败','returnData'=>'']);die;
                }
	       	}
    	}else{
    		$sheng = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>1])->order('region_order asc,region_id asc')->select();
    		$this->assign('sheng',$sheng);
    		$one = Db::name('stores')->where('id',$id)->find();
    		if($one){
    			if($one['sheng']>0){
    				$shi = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>$one['sheng']])->order('region_order asc,region_id asc')->select();
    				$this->assign('shi',$shi);
    			}
    			if($one['pics']){
	    			$one['pics'] = explode(",",$one['pics']);
	    		}
    			if($one['shi']>0){
    				$qu = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>$one['shi']])->order('region_order asc,region_id asc')->select();
    				$this->assign('qu',$qu);
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
    		$res = Db::name('stores')->where('id',$post['id'])->update($data);
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
    		$res = Db::name('stores')->where('id',$post['id'])->update($data);
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
                return json(['code'=>2,'msg'=>'请选择要删除的门店']);
            }
    		$data['is_del'] = 'y';
    		$res = Db::name('stores')->where('id','in',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'删除成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'删除失败']);
    		}
    	}
	}
/************************************************************************/
/** 店员管理
/************************************************************************/
	public function assistant(){
		$admininfo = $this->admininfo;
		$where[] = ['a.is_del','=','n'];
		$keys = $this->request->param('keys') ? $this->request->param('keys') : '';
		if($keys!=''){
			$where[] = ['a.username|a.nickname|a.phone','like','%'.$keys.'%'];
		}
		$this->assign('keys',$keys);
		if($admininfo['stores_id']==0){
			$sheng = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>1])->order('region_order asc,region_id asc')->select();
	    	$this->assign('sheng',$sheng);
			$sheng_id = $this->request->param('sheng') ? $this->request->param('sheng') : '';
			$wherestores['is_del'] = 'n';
			if($sheng_id!=''){
				$wherestores['sheng'] = $sheng_id;
				$shi = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>$sheng_id])->order('region_order asc,region_id asc')->select();
				$this->assign('shi',$shi);
				$shi_id = $this->request->param('shi') ? $this->request->param('shi') : '';
				if($shi_id!=''){
					$wherestores['shi'] = $shi_id;
					$qu = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>$shi_id])->order('region_order asc,region_id asc')->select();
					$this->assign('qu',$qu);
					$qu_id = $this->request->param('qu') ? $this->request->param('qu') : '';
					if($qu_id!=''){
						$wherestores['qu'] = $qu_id;
					}
					$this->assign('qu_id',$qu_id);
				}
				$this->assign('shi_id',$shi_id);
			}
			$this->assign('sheng_id',$sheng_id);
			$stores_id = $this->request->param('stores_id') ? $this->request->param('stores_id') : '';
			if($stores_id!=''){
				$where[] = ['a.stores_id','=',$stores_id];
			}
			$this->assign('stores_id',$stores_id);

			$stores = Db::name('stores')->field('id,title')->where($wherestores)->order('orders asc,addtime desc')->select();
			$st_id = [];
			foreach($stores as $k=>$v){
				$st_id[] = $v['id'];
			}
			if($st_id){
				$st_id = implode(",",$st_id);
				$where[] = ['a.stores_id','in',$st_id];
			}else{
				$where[] = ['a.stores_id','in',0];
			}
	    	$this->assign('stores',$stores);
		}else{
			$where[] = ['a.stores_id','=',$admininfo['stores_id']];
		}

		$list = Db::name('admin')->alias('a')
				->field('a.id,a.username,a.nickname,a.stores_type,a.phone,a.is_show,s.title as stores_title,s.logo')
				->where('a.stores_id','neq','0')
				->where($where)
				->join('stores s', 's.id = a.stores_id ', 'left')
				->order('a.stores_type desc,a.addtime desc')
				->paginate(20);
		$this->assign('list',$list);
		$count = Db::name('admin')->alias('a')->where('a.stores_id','neq','0')->where($where)->count();
		$this->assign('count',$count);
		return $this->fetch();
	}
	public function assistant_publish(){
		$id  = $this->request->param('id')  ? $this->request->param('id')  : '0';
    	if($this->request->isPost()){
    		$post = $this->request->post();
    		$validate = new \think\Validate();
            $rule =   [
                'stores_id'  => 'require',
                'nickname'  => 'require',
                'username'    => 'require|min:3|max:32',
                'phone'     => 'require'
            ];
            $message  =   [
                'stores_id.require' => '请选择所属门店',
                'nickname.require' => '店员昵称不能为空',
                'username.require'   => '店员账号不能为空',
                'username.max'     => '店员账号最多不超过32个字符',
        		'username.min'     => '店员账号最少3个字符',
                'phone.require'    => '店员手机号码不能为空'
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
    		$stores = Db::name('stores')->field('id,title')->where('is_del','n')->order('orders asc,addtime desc')->select();
    		$this->assign('stores',$stores);
    		$one = Db::name('admin')->where('id',$id)->find();
    		$this->assign('one',$one);
    		return $this->fetch();
    	}
	}
	public function assistant_show(){
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
	public function assistant_del(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
            if(empty($post['id'])){
                return json(['code'=>2,'msg'=>'请选择要删除的店员']);
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
/************************************************************************/
/** 门店商品
/************************************************************************/
	public function goods(){
		$admininfo = $this->admininfo;
		$where[] = ['sg.is_del','=','n'];
		$keys = $this->request->param('keys') ? $this->request->param('keys') : '';
		if($keys!=''){
			$where[] = ['sg.title|sg.id|sg.keywords|sg.description','like','%'.$keys.'%'];
		}
		$this->assign('keys',$keys);
		if($admininfo['stores_id']>0){
			$where[] = ['sg.stores_id','=',$admininfo['stores_id']];
		}else{
			$sheng = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>1])->order('region_order asc,region_id asc')->select();
	    	$this->assign('sheng',$sheng);
			$sheng_id = $this->request->param('sheng') ? $this->request->param('sheng') : '';
			$wherestores['is_del'] = 'n';
			if($sheng_id!=''){
				$wherestores['sheng'] = $sheng_id;
				$shi = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>$sheng_id])->order('region_order asc,region_id asc')->select();
				$this->assign('shi',$shi);
				$shi_id = $this->request->param('shi') ? $this->request->param('shi') : '';
				if($shi_id!=''){
					$wherestores['shi'] = $shi_id;
					$qu = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>$shi_id])->order('region_order asc,region_id asc')->select();
					$this->assign('qu',$qu);
					$qu_id = $this->request->param('qu') ? $this->request->param('qu') : '';
					if($qu_id!=''){
						$wherestores['qu'] = $qu_id;
					}
					$this->assign('qu_id',$qu_id);
				}
				$this->assign('shi_id',$shi_id);
			}
			$this->assign('sheng_id',$sheng_id);
			$stores_id = $this->request->param('stores_id') ? $this->request->param('stores_id') : '';
			if($stores_id!=''){
				$where[] = ['sg.stores_id','=',$stores_id];
			}
			$this->assign('stores_id',$stores_id);

			$stores = Db::name('stores')->field('id,title')->where($wherestores)->order('orders asc,addtime desc')->select();
			$st_id = [];
			foreach($stores as $k=>$v){
				$st_id[] = $v['id'];
			}
			if($st_id){
				$st_id = implode(",",$st_id);
				$where[] = ['sg.stores_id','in',$st_id];
			}else{
				$where[] = ['sg.stores_id','in',0];
			}
	    	$this->assign('stores',$stores);
		}

		$list = Db::name('stores_goods')->alias('sg')
				->field('sg.id,sg.title,sg.thumb,sg.marketprice,sg.is_show,s.title as stores_title')
				->where($where)->order('sg.orders asc,sg.addtime desc')
				->join('stores s','s.id = sg.stores_id','left')
				->paginate(10);
		$count = Db::name('stores_goods')->alias('sg')->where($where)->count();
		$this->assign('list',$list);
		$this->assign('count',$count);
		return $this->fetch();
	}
	public function goods_publish(){
		$id = $this->request->param('id') ? $this->request->param('id') : 0;
		$admininfo = $this->admininfo;
		if($this->request->isPost()){
			$post = $this->request->post();
    		$validate = new \think\Validate();
            $rule =   [
                'stores_id'   => 'require',
                'title'       => 'require',
                'marketprice' => 'require',
                'goodsprice'  => 'require'
            ];
            $message  =   [
                'stores_id.require'   => '请选择所属门店',
                'title.require'       => '商品标题不能为空',
                'marketprice.require' => '售价不能为空',
                'goodsprice.require'  => '原价不能为空'
            ];
            $validate->message($message);
            //验证部分数据合法性
            if (!$validate->check($post,$rule)) {
                return json(['code'=>2,'msg'=>$validate->getError(),'returnData'=>'']);die;
            }
	       	if(isset($post['id'])){
	       		//检测账号是否存在
	       		$post['updatetime']   = time();
	       		$post['update_admin'] = $admininfo['id'];
	            $menu = Db::name('stores_goods')->field('id')->where('id',$post['id'])->find();
	            if(empty($menu)) {
	            	return json(['code'=>3,'msg'=>'ID不正确','returnData'=>'']);die;
	            }
                $res = Db::name('stores_goods')->where('id',$post['id'])->update($post);
	       		if($res) {
	            	return json(['code'=>0,'msg'=>'修改成功','returnData'=>'']);die;
	        	} else {
	            	return json(['code'=>4,'msg'=>'修改失败','returnData'=>'']);die;
	       		}
	       	}else{
	       		$post['addtime']  = $post['updatetime'] = time();
	       		$post['admin_id'] = $post['update_admin'] = $admininfo['id'];
                $res = Db::name('stores_goods')->insert($post);
	       		if($res) {
                    return json(['code'=>0,'msg'=>'添加成功','returnData'=>'']);die;
                } else {
                    return json(['code'=>5,'msg'=>'添加失败','returnData'=>'']);die;
                }
	       	}
		}else{
			if($admininfo['stores_id']>0){
				$stores = Db::name('stores')->where('id',$admininfo['stores_id'])->order('orders asc,addtime desc')->select();
			}else{
				$stores = Db::name('stores')->where('is_del','n')->order('orders asc,addtime desc')->select();
			}
			$this->assign('stores',$stores);
			$one = Db::name('stores_goods')->where('id',$id)->find();
			$this->assign('one',$one);
			return $this->fetch();
		}
	}
	public function goods_show(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
    		if($post['va']=='n'){
    			$va = 'y';
    		}else{
    			$va = 'n';
    		}
    		$data['is_show'] = $va;
    		$res = Db::name('stores_goods')->where('id',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'操作成功','va'=>$va]);
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
    	}
	}
	public function goods_order(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
    		$data['orders'] = $post['va'];
    		$res = Db::name('stores_goods')->where(['id'=>$post['id']])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'操作成功']); 
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
    	}
	}
	public function goods_del(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
            if(empty($post['id'])){
                return json(['code'=>2,'msg'=>'请选择要删除的商品']);
            }
    		$data['is_del'] = 'y';
    		$res = Db::name('stores_goods')->where('id','in',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'删除成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'删除失败']);
    		}
    	}
	}
/************************************************************************/
/** 门店订单
/************************************************************************/
	public function dingdan(){
		$admininfo = $this->admininfo;
		$where[] = ['so.is_del','=','n'];
		if($admininfo['stores_id']>0){
			$where[] = ['so.stores_id','=',$admininfo['stores_id']];
		}else{
			$sheng = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>1])->order('region_order asc,region_id asc')->select();
	    	$this->assign('sheng',$sheng);
			$sheng_id = $this->request->param('sheng') ? $this->request->param('sheng') : '';
			$wherestores['is_del'] = 'n';
			if($sheng_id!=''){
				$wherestores['sheng'] = $sheng_id;
				$shi = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>$sheng_id])->order('region_order asc,region_id asc')->select();
				$this->assign('shi',$shi);
				$shi_id = $this->request->param('shi') ? $this->request->param('shi') : '';
				if($shi_id!=''){
					$wherestores['shi'] = $shi_id;
					$qu = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>$shi_id])->order('region_order asc,region_id asc')->select();
					$this->assign('qu',$qu);
					$qu_id = $this->request->param('qu') ? $this->request->param('qu') : '';
					if($qu_id!=''){
						$wherestores['qu'] = $qu_id;
					}
					$this->assign('qu_id',$qu_id);
				}
				$this->assign('shi_id',$shi_id);
			}
			$this->assign('sheng_id',$sheng_id);
			$stores_id = $this->request->param('stores_id') ? $this->request->param('stores_id') : '';
			if($stores_id!=''){
				$where[] = ['so.stores_id','=',$stores_id];
			}
			$this->assign('stores_id',$stores_id);

			$stores = Db::name('stores')->field('id,title')->where($wherestores)->order('orders asc,addtime desc')->select();
			$st_id = [];
			foreach($stores as $k=>$v){
				$st_id[] = $v['id'];
			}
			if($st_id){
				$st_id = implode(",",$st_id);
				$where[] = ['so.stores_id','in',$st_id];
			}else{
				$where[] = ['so.stores_id','in',0];
			}
	    	$this->assign('stores',$stores);
		}
		$keys = $this->request->param('keys') ? $this->request->param('keys') : '';
		if($keys!=""){
			$where[] = ['so.no','like',"%".$keys."%"];
		}
		$this->assign('keys',$keys);
		$list = Db::name('order_stores')->alias('so')->where($where)->order('so.createtime desc')->order('so.createtime desc')
				->paginate(10);
		$count = Db::name('order_stores')->alias('so')->where($where)->count();
		$this->assign('list',$list);
		$this->assign('count',$count);
		return $this->fetch();
	}
	public function dingdan_publish(){
		$id = $this->request->param('id') ? $this->request->param('id') : '0';
		if($this->request->isAjax()){
			$post = $this->request->post();
			if(!isset($post['status'])){
				return json(['code'=>1,'msg'=>'请选择处理结果']);die;
			}else{
				if($post['status']==""){
					return json(['code'=>1,'msg'=>'请选择处理结果']);die;
				}
			}
			$ckone = Db::name('order_stores')->where('id',$post['id'])->find();
			if($post['status']>'0' && $ckone['finish_time']=='0'){
				$post['finish_time'] = time();
				$post['finish_day']  = date("Ymd");
			}
			$res = Db::name('order_stores')->where('id',$post['id'])->update($post);
			if($res){
				return json(['code'=>0,'msg'=>'操作成功']);die;
			}else{
				return json(['code'=>1,'msg'=>'操作失败']);die;
			}
		}else{
			$beizhu = $this->request->param('beizhu') ? $this->request->param('beizhu') : '0';
			$this->assign('beizhu',$beizhu);
			$one = Db::name('order_stores')->where('id',$id)->find();
			$one['goods'] = json_decode($one['goods'],1);
			$this->assign('one',$one);
			return $this->fetch();
		}
	}
	public function dingdan_detail(){
		$id = $this->request->param('id') ? $this->request->param('id') : '0';
		$one = Db::name('order_stores')->where('id',$id)->find();
		$one['goods'] = json_decode($one['goods'],1);
		$this->assign('one',$one);
		return $this->fetch();
	}
	public function dingdan_log(){ //订单服务记录
		$id = $this->request->param('id') ? $this->request->param('id') : '0';
		$one = Db::name('order_stores')->field('id,user_id,goods,usetimes,status')->where('id',$id)->find();
		$arr['order_id'] = $id;
		$arr['user_id']  = $one['user_id'];
		$arr['addtime']  = time();
		$arr['addday']   = date("Ymd");
		$arr['types']    = "del";
		$res = Db::name('order_stores_log')->insert($arr);
		if($res){
			$count = Db::name('order_stores_log')->where(['order_id'=>$id,'types'=>'del'])->count();
			$goods = json_decode($one['goods'],1);
			$nusetimes = $one['usetimes']+1;
			$uparr['usetimes'] = $nusetimes;
			if($count>=$goods['times'] && $one['status']=="0"){
				$uparr['status']      = 1;
				$uparr['finish_time'] = time();
				$uparr['finish_day']  = date("Ymd");
			}
			$upres = Db::name('order_stores')->where('id',$id)->update($uparr);
			return json(['code'=>0,'msg'=>'操作成功']);die;
		}else{
			return json(['code'=>1,'msg'=>'操作失败']);die;
		}
	}
/************************************************************************/
/** 档案管理
/************************************************************************/
	public function dangan(){
		$admininfo = $this->admininfo;
		$where[] = ['m.is_del','=','n'];
		$keys = $this->request->param('keys') ? $this->request->param('keys') : '';
		if($keys!=''){
			$where[] = ['m.c_name|m.c_number','like','%'.$keys.'%'];
		}
		if($admininfo['stores_id']>0){
			$where[] = ['store_id','=',$admininfo['stores_id']];
		}
		$this->assign('keys',$keys);
		if($admininfo['school_id']==0){
			$sheng = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>1])->order('region_order asc,region_id asc')->select();
	    	$this->assign('sheng',$sheng);
			$sheng_id = $this->request->param('sheng') ? $this->request->param('sheng') : '';
			$whereschool['is_del'] = 'n';
			if($sheng_id!=''){
				$whereschool['sheng'] = $sheng_id;
				$shi = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>$sheng_id])->order('region_order asc,region_id asc')->select();
				$this->assign('shi',$shi);
				$shi_id = $this->request->param('shi') ? $this->request->param('shi') : '';
				if($shi_id!=''){
					$whereschool['shi'] = $shi_id;
					$qu = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>$shi_id])->order('region_order asc,region_id asc')->select();
					$this->assign('qu',$qu);
					$qu_id = $this->request->param('qu') ? $this->request->param('qu') : '';
					if($qu_id!=''){
						$whereschool['qu'] = $qu_id;
					}
					$this->assign('qu_id',$qu_id);
				}
				$this->assign('shi_id',$shi_id);
			}
			$this->assign('sheng_id',$sheng_id);
			$school_id = $this->request->param('school_id') ? $this->request->param('school_id') : '';
			if($school_id!=''){
				$where[] = ['m.school_id','=', $school_id];
			}
			$this->assign('school_id',$school_id);
			$class_id = $this->request->param('class_id') ? $this->request->param('class_id') : '';
			if($class_id!=''){
				$where[] = ['m.class_id','=', $class_id];
			}
			$this->assign('class_id',$class_id);

			if($admininfo['school_id']>0){
				$whereschool['id'] = $admininfo['school_id'];
			}
			if($admininfo['class_id']!="0"){
				$where[] = ['m.class_id','in',$admininfo['class_id']];
			}
			$school = Db::name('school')->field('id,title')->where($whereschool)->order('orders asc,addtime desc')->select();
			$st_id = [];
			foreach($school as $k=>$v){
				$st_id[] = $v['id'];
			}
			if($st_id){
				$st_id = implode(",",$st_id);
				$where[] = ['m.school_id','in', $st_id];
			}else{
				$st_id = 0;
				$where[] = ['m.school_id','in', 0];
			}
	    	$this->assign('school',$school);
	    	$class = Db::name('school_class')->field('id,title')->where('school_id','in',$st_id)->where('is_del','n')->order('orders asc,addtime desc')->select();
	    	$ct_id = [];
	    	foreach($class as $k=>$v){
	    		$ct_id[] = $v['id'];
	    	}
	    	if($ct_id){
	    		$ct_id = implode(",",$ct_id);
	    		$where[] = ['m.class_id','in',$ct_id];
	    	}else{
	    		$ct_id = 0;
	    		$where[] = ['m.class_id','in',0];
	    	}
	    	$this->assign('class',$class);
	    }else{
			$class_id = $this->request->param('class_id') ? $this->request->param('class_id') : '';
			if($class_id!=''){
				$where[] = ['m.class_id','=', $class_id];
			}
			$this->assign('class_id',$class_id);

			if($admininfo['class_id']!="0"){
				$where[] = ['m.class_id','in',$admininfo['class_id']];
			}
			$class = Db::name('school_class')->field('id,title')->where('id','in',$admininfo['class_id'])->where('is_del','n')->order('orders asc,addtime desc')->select();
			$ct_id = [];
	    	foreach($class as $k=>$v){
	    		$ct_id[] = $v['id'];
	    	}
	    	if($ct_id){
	    		$ct_id = implode(",",$ct_id);
	    		$where[] = ['m.class_id','in',$ct_id];
	    	}else{
	    		$ct_id = 0;
	    		$where[] = ['m.class_id','in',0];
	    	}
	    	$this->assign('class',$class);
	    }
	    $c_types = $this->request->param('c_types') ? $this->request->param('c_types') : 'all';
		$this->assign('c_types',$c_types);
		if($c_types!="all"){
			$where[] = ['m.c_types','=',$c_types];
		}
	    $list = Db::name('school_student_shili')->alias('m')->where($where)->paginate(20);
	    $count = Db::name('school_student_shili')->alias('m')->where($where)->count();
	    $this->assign('list',$list);
	    $this->assign('count',$count);
		return $this->fetch();
	}
	public function dangan_publish(){
		$id = $this->request->param('id') ? $this->request->param('id') : '0';
		if($this->request->isPost()){
			$post = $this->request->post();
			$validate = new \think\Validate();
            $rule =   [
            	'c_number'  => 'require',
            	'c_name'     => 'require'
            ];
            $message  =   [
            	'c_number.require' => '请输入编号/学号/手机号',
            	'c_name.require'    => '姓名不能为空'
            ];
            $validate->message($message);
            if (!$validate->check($post,$rule)) {
                return json(['code'=>2,'msg'=>$validate->getError(),'returnData'=>'']);die;
            }
            $admininfo = $this->admininfo;
            $post['stores_id'] = $admininfo['stores_id'];
	       	if(isset($post['id'])){
	       		$post['updatetime'] = time();
	       		$res = Db::name('school_student_shili')->where(['id'=>$post['id']])->update($post);
	       		if($res) {
	            	return json(['code'=>0,'msg'=>'修改成功','returnData'=>'']);die;
	        	} else {
	            	return json(['code'=>4,'msg'=>'修改失败','returnData'=>'']);die;
	       		}
	       	}else{
	       		$school = Db::name('school')->field('title')->where('id',$post['school_id'])->find();
	       		$post['c_school'] = $school['title'];
	       		$class = Db::name('school_class')->field('title')->where('id',$post['class_id'])->find();
	       		$post['c_class'] = $class['title'];
	       		$post['day']     = date('Ymd');
	       		$check = Db::name('school_student_shili')->field(['id'])->where(['c_name'=>$post['c_name'],'school_id'=>$post['school_id'],'class_id'=>$post['class_id'],'c_number'=>$post['c_number'],'day'=>$post['day']])->find();
	       		if($check){
	       			$post['updatetime'] = time();
	       			$res = Db::name('school_student_shili')->where(['id'=>$check['id']])->update($post);
	       		}else{
	       			$post['addtime'] = $post['updatetime'] = time();
	       			$checkchild = Db::name('school_student')->field(['id','user_id'])->where(['name'=>$post['c_name'],'school_id'=>$post['school_id'],'class_id'=>$post['class_id'],'no'=>$post['c_number']])->find();
	       			if($checkchild){
	       				$post['uid'] = $checkchild['user_id'];
	       				$post['c_id'] = $checkchild['id'];
	       			}
	       			$res = Db::name('school_student_shili')->insert($post);
	       		}
	       		if($res) {
                    return json(['code'=>0,'msg'=>'添加成功','returnData'=>'']);die;
                } else {
                    return json(['code'=>5,'msg'=>'添加失败','returnData'=>'']);die;
                }
	       	}
		}else{
			$one = Db::name('school_student_shili')->where(['id'=>$id])->find();
			$this->assign('one',$one);
			$admininfo = $this->admininfo;
			if($admininfo['school_id']==0){
				$school = Db::name('school')->field('id,title')->where(['is_del'=>'n'])->order('orders asc,addtime desc')->select();
				$class = [];
				if($one){
					$class = Db::name('school_class')->where(['school_id'=>$one['school_id'],'is_del'=>'n'])->order('orders asc,addtime desc')->select();
				}
			}else{
				$school = Db::name('school')->field('id,title')->where(['is_del'=>'n','id'=>$admininfo['school_id']])->order('orders asc,addtime desc')->select();
				if($admininfo['class_id']==0){
					$class = Db::name('school_class')->field('id,title')->where(['is_del'=>'n'])->order('orders asc,addtime desc')->select();
				}else{
					$class = Db::name('school_class')->field('id,title')->where(['is_del'=>'n'])->where('id','in',$admininfo['class_id'])->order('orders asc,addtime desc')->select();
				}
			}
			$this->assign('school',$school);
			$this->assign('class',$class);
			return $this->fetch();
		}
	}
	public function dangan_pl(){
		if($this->request->isPost()){
			$post = $this->request->post();
			$excel = new \PHPExcel();
			if($post['txt']==""){
				return json(['code'=>1,'msg'=>'请上传文件']);die;
			}
			$path = ltrim($post['txt'],'/');
			$suffix = "xlsx";
			if($suffix=="xlsx"){
                $reader = \PHPExcel_IOFactory::createReader('Excel2007');
            }else{
                $reader = PHPExcel_IOFactory::createReader('Excel5');
            }
            $excel = $reader->load("$path",$encode = 'utf-8');
        	//读取第一张表
        	$sheet = $excel->getSheet(0);
       		//获取总行数
        	$row_num = $sheet->getHighestRow();
        	//获取总列数
        	$col_num = $sheet->getHighestColumn();
        	$today = date("Ymd");
        	$data = []; //数组形式获取表格数据
        	$add  = [];
        	for ($i = 4; $i <= $row_num; $i ++) {
        		$data[$i]['c_number'] = $add['c_number'] = $c_number = $sheet->getCell("A".$i)->getValue();
        		$data[$i]['c_name']   = $add['c_name']   = $c_name   = $sheet->getCell("B".$i)->getValue();
        		$data[$i]['c_school'] = $add['c_school'] = $c_school = $sheet->getCell("F".$i)->getValue();
        		$data[$i]['c_class']  = $add['c_school'] = $c_class = $sheet->getCell("G".$i)->getValue();
        		$check = Db::name('school_student_shili')->field('id')->where(['c_number'=>$c_number,'c_name'=>$c_name,'c_school'=>$c_school,'c_class'=>$c_class,'day'=>$today])->find();
        		if($check){
        			$add['c_age']       = $sheet->getCell("C".$i)->getValue();
	        		$add['c_sex']       = $sheet->getCell("D".$i)->getValue();
	        		$add['c_qiujing_l'] = $sheet->getCell("H".$i)->getValue();
	        		$add['c_qiujing_r'] = $sheet->getCell("L".$i)->getValue();
	        		$add['c_zhujing_l'] = $sheet->getCell("I".$i)->getValue();
	        		$add['c_zhujing_r'] = $sheet->getCell("M".$i)->getValue();
	        		$add['c_zhou_l']    = $sheet->getCell("J".$i)->getValue();
	        		$add['c_zhou_r']    = $sheet->getCell("N".$i)->getValue();
	        		$add['c_xin_l']     = $sheet->getCell("K".$i)->getValue();
	        		$add['c_xin_r']     = $sheet->getCell("O".$i)->getValue();
	        		$add['beizhu']      = $sheet->getCell("P".$i)->getValue();
	        		$add['updatetime']  = time();
	        		$res = Db::name('school_student_shili')->where(['id'=>$check['id']])->update($add);
        		}else{
        			$school = Db::name('school')->field('id')->where('title',$c_school)->find();
        			if($school){
        				$data[$i]['school_id'] = $school['id'];
        			}
	       			$class = Db::name('school_class')->field('id')->where('title',$c_class)->find();
	       			if($class){
        				$data[$i]['class_id'] = $class['id'];
        			}
        			$checkchild = Db::name('school_student')->field(['id','user_id'])->where(['name'=>$c_name,'no'=>$c_number])->find();
	       			if($checkchild){
	       				$data[$i]['uid']  = $checkchild['user_id'];
	       				$data[$i]['c_id'] = $checkchild['id'];
	       			}
        			$data[$i]['c_age']       = $sheet->getCell("C".$i)->getValue();
	        		$data[$i]['c_sex']       = $sheet->getCell("D".$i)->getValue();
	        		$data[$i]['c_qiujing_l'] = $sheet->getCell("H".$i)->getValue();
	        		$data[$i]['c_qiujing_r'] = $sheet->getCell("L".$i)->getValue();
	        		$data[$i]['c_zhujing_l'] = $sheet->getCell("I".$i)->getValue();
	        		$data[$i]['c_zhujing_r'] = $sheet->getCell("M".$i)->getValue();
	        		$data[$i]['c_zhou_l']    = $sheet->getCell("J".$i)->getValue();
	        		$data[$i]['c_zhou_r']    = $sheet->getCell("N".$i)->getValue();
	        		$data[$i]['c_xin_l']     = $sheet->getCell("K".$i)->getValue();
	        		$data[$i]['c_xin_r']     = $sheet->getCell("O".$i)->getValue();
	        		$data[$i]['beizhu']      = $sheet->getCell("P".$i)->getValue();
	        		$data[$i]['addtime']     = $data[$i]['updatetime']  = time();
	        		$data[$i]['day']         = time();
        		}
        	}
        	$res = Db::name('member_shili')->insertAll($data);
			if($res){
				return json(['code'=>0,'msg'=>'导入成功']);
			}else{
				return json(['code'=>1,'msg'=>'导出失败']);
			}
		}else{
			return $this->fetch();
		}
	}
	public function dangan_del(){
		if($this->request->isPost()){
    		$post = $this->request->post();
            if(empty($post['id'])){
                return json(['code'=>2,'msg'=>'请选择要删除的档案']);
            }
    		$data['is_del'] = 'y';
    		$res = Db::name('school_student_shili')->where('id','in',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'删除成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'删除失败']);
    		}
    	}
	}
	public function upload_excel($module='home',$use='shili'){
		if($this->request->file('file')){
            $file = $this->request->file('file');
            $module = $this->request->has('module') ? $this->request->param('module') : $module;//模块
       		if($file){
		        $info = $file->validate(['size'=>10*1024*1024,'ext'=>'xls,xlsx'])->rule('date')->move(ROOT_PATH . 'public' . DS . 'uploads' . DS . $module . DS . $use);
		        if($info){
		        	$msg['code'] = 0;
		        	$msg['msg']  = '准备就绪';
		            $msg['src']  = DS . 'uploads' . DS . $module . DS . $use . DS . $info->getSaveName();//文件路径
		            $msg['src']  = str_replace("\\","/",$msg['src']);
		            return json($msg);
		        }else{
		            // 上传失败获取错误信息
		            return json(['code'=>1,'msg'=>$file->getError()]);
		        }
		    }
        }else{
            $res['code'] = 1;
            $res['msg']  = '没有上传文件';
            return json($res);die;
        }
	}
/************************************************************************/
/** 线下订单
/************************************************************************/
	public function xianxia(){
		$admininfo = $this->admininfo;
		$where[] = ['so.is_del','=','n'];
		$keys = $this->request->param('keys') ? $this->request->param('keys') : '';
		if($keys!=''){
			$where[] = ['so.kehu_name|so.kehu_phone','like','%'.$keys.'%'];
		}
		$this->assign('keys',$keys);
		if($admininfo['stores_id']>0){
			$where[] = ['so.stores_id','=',$admininfo['stores_id']];
		}else{
			$sheng = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>1])->order('region_order asc,region_id asc')->select();
	    	$this->assign('sheng',$sheng);
			$sheng_id = $this->request->param('sheng') ? $this->request->param('sheng') : '';
			$wherestores['is_del'] = 'n';
			if($sheng_id!=''){
				$wherestores['sheng'] = $sheng_id;
				$shi = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>$sheng_id])->order('region_order asc,region_id asc')->select();
				$this->assign('shi',$shi);
				$shi_id = $this->request->param('shi') ? $this->request->param('shi') : '';
				if($shi_id!=''){
					$wherestores['shi'] = $shi_id;
					$qu = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>$shi_id])->order('region_order asc,region_id asc')->select();
					$this->assign('qu',$qu);
					$qu_id = $this->request->param('qu') ? $this->request->param('qu') : '';
					if($qu_id!=''){
						$wherestores['qu'] = $qu_id;
					}
					$this->assign('qu_id',$qu_id);
				}
				$this->assign('shi_id',$shi_id);
			}
			$this->assign('sheng_id',$sheng_id);
			$stores_id = $this->request->param('stores_id') ? $this->request->param('stores_id') : '';
			if($stores_id!=''){
				$where[] = ['so.stores_id','=',$stores_id];
			}
			$this->assign('stores_id',$stores_id);

			$stores = Db::name('stores')->field('id,title')->where($wherestores)->order('orders asc,addtime desc')->select();
			$st_id = [];
			foreach($stores as $k=>$v){
				$st_id[] = $v['id'];
			}
			if($st_id){
				$st_id = implode(",",$st_id);
				$where[] = ['so.stores_id','in',$st_id];
			}else{
				$where[] = ['so.stores_id','in',0];
			}
	    	$this->assign('stores',$stores);
		}
		$c_types = $this->request->param('c_types') ? $this->request->param('c_types') : 'all';
		$this->assign('c_types',$c_types);
		if($c_types!="all"){
			$where[] = ['so.kehu_types','=',$c_types];
		}
		$list = Db::name('order_stores_xianxia')->alias('so')->where($where)->order('so.createtime desc')->order('so.createtime desc')
				->paginate(10);
		$count = Db::name('order_stores_xianxia')->alias('so')->where($where)->count();
		$this->assign('list',$list);
		$this->assign('count',$count);
		return $this->fetch();
	}
	public function xianxia_publish(){
		if($this->request->isPost()){
			$admininfo = $this->admininfo;
			$post = $this->request->post();
			$goods = [];
			foreach($post['goods'] as $k=>$v){
				$goods[] = $v;
			}
			$cheno = Db::name('order_stores_xianxia')->count();
			$ordersn = "MTX".sprintf("%08d",$cheno+1);
			$post['ordersn']    = $ordersn;
			$post['goods']      = json_encode($goods,JSON_UNESCAPED_UNICODE);
			$post['stores_id']  = $admininfo['stores_id'];
			$buydate = strtotime($post['buydate']);
			$post['buytime']    = $buydate;
			$post['buyday']     = date("Ymd",$buydate);
			$post['admin_id']   = $admininfo['id'];
			$post['createtime'] = time();
			$post['createday']  = date("Ymd");
			$res = Db::name('order_stores_xianxia')->insert($post);
			if($res){
				return json(['code'=>0,'msg'=>'添加成功']);
			}else{
				return json(['code'=>1,'msg'=>'添加失败']);
			}
		}else{
			$cheno = Db::name('order_stores_xianxia')->count();
			$ordersn = "MTX".sprintf("%08d",$cheno+1);
			$this->assign('ordersn',$ordersn);
			return $this->fetch();
		}
	}
	public function xianxia_detail(){
		$id = $this->request->param('id') ? $this->request->param('id') : '0';
		$one = Db::name('order_stores_xianxia')->where('id',$id)->find();
		$one['goods'] = json_decode($one['goods'],1);
		$this->assign('one',$one);
		return $this->fetch();
	}
	public function xianxia_beizhu(){
		if($this->request->isPost()){
			$post = $this->request->post();
			if($post['newremark']==""){
				return json(['code'=>1,'msg'=>'请填写备注']);
			}
			$res = Db::name('order_stores_xianxia')->where('id',$post['id'])->update(['remark'=>$post['remark']]);
			if($res){
				return json(['code'=>0,'msg'=>'添加成功']);
			}else{
				return json(['code'=>1,'msg'=>'添加失败']);
			}
		}else{
			$id = $this->request->param('id') ? $this->request->param('id') : '0';
			$one = Db::name('order_stores_xianxia')->field('id,remark')->where('id',$id)->find();
			$this->assign('one',$one);
			return $this->fetch();
		}
	}
	public function xianxia_del(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
            if(empty($post['id'])){
                return json(['code'=>2,'msg'=>'请选择要删除的订单']);
            }
    		$data['is_del'] = 'y';
    		$res = Db::name('order_stores_xianxia')->where('id','in',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'删除成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'删除失败']);
    		}
    	}
	}
}