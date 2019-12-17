<?php
namespace app\admin\controller;
use \think\Controller;
use app\admin\controller\Base;
use \think\Db;
use \think\Cookie;
use \think\Session;
use \think\Request;
class Cms extends Base{
	public function __construct(){
		parent::__construct(); //使用父类的构造方法
	}
/************************************************************************/
/** 文章管理
/************************************************************************/
	public function news(){
		$catelist = Db::name('news_cate')->field(['id','title'])->where(['pid'=>'0','is_del'=>'n'])->select();
	    foreach($catelist as $k=>$v){
	    	$child = DB::name('news_cate')->field(['id','title'])->where(['pid'=>$v['id'],'is_del'=>'n'])->order('orders asc,addtime asc')->select();
	    	foreach($child as $key=>$val){
	    		$childa = DB::name('news_cate')->field(['id','title'])->where(['pid'=>$val['id'],'is_del'=>'n'])->order('orders asc,addtime asc')->select();
	    		$child[$key]['childa'] = $childa;
	    	}
	    	$catelist[$k]['child'] = $child;
	    }
	    $this->assign('catelist',$catelist);

		$pid = $this->request->param('pid') ? $this->request->param('pid') : '0';
		if($pid!='0'){
			$where[] = ['n.pid','=',$pid];
		}
		$this->assign('pid',$pid);
		$keys = $this->request->param('keys') ? $this->request->param('keys') : '';
		if($keys!=''){
			$where[] = ['n.title|n.seo_title|n.keywords|n.description','like','%'.$keys.'%'];
		}
		$this->assign('keys',$keys);

		$where[] = ['n.is_del','=','n'];
		$list = Db::name('news')->alias('n')
				->field('n.id,n.title,n.thumb,n.author,n.readnums,n.zannums,n.addtime,n.admin_id,n.updatetime,n.starttime,n.endtime,n.orders,n.is_show,n.is_hot,n.is_index,nc.title as cate_title')
				->where($where)
				->join('news_cate nc', 'nc.id=n.pid', 'left')
				->order('n.addtime desc')
				->paginate(20);
		$today = date('Y-m-d H:i:s',time());
		foreach($list as $k=>$v){
			$sk = false;
			if($v['starttime']=='0' || $v['starttime']<=$today){
				$sk = true;
			}	
			$ek = false;
			if($v['endtime']=='0' || $v['endtime']>=$today){
				$ek = true;
			}
			if($sk==true && $ek==true){
				if($v['is_show']=='n'){
					$resup = Db::name('banner')->where('id',$v['id'])->update(['is_show'=>'y']);
				}
			}else{
				if($v['is_show']=='y'){
					$resup = Db::name('banner')->where('id',$v['id'])->update(['is_show'=>'n']);
				}
			}
		}

		$this->assign('list', $list);
		$count  = Db::name('news')->alias('n')->where($where)->count();
		$this->assign('count',$count);
		return $this->fetch('cms/news/index');
	}
	public function news_publish(){
		$id  = $this->request->param('id')  ? $this->request->param('id')  : '0';
    	if($this->request->isPost()){
    		$post = $this->request->post();
    		$admininfo = $this->admininfo;
    		$validate = new \think\Validate();
            $rule =   [
            	'pid'  => 'require',
                'title'  => 'require'
            ];
            $message  =   [
            	'pid.require'   => '请选择文章类型',
                'title.require' => '文章标题不能为空'
            ];
            $validate->message($message);
            //验证部分数据合法性
            if (!$validate->check($post,$rule)) {
                return json(['code'=>2,'msg'=>$validate->getError(),'returnData'=>'']);die;
            }
	       	if(empty($post['is_show'])){
	       		$post['is_show'] = 'n';
	       	}
	       	if($post['description']==''){
	       		$content = strip_tags($post['content']);
	       		$post['description'] = str_cut1($content,0,54,'utf-8','');
	       	}
	       	if(empty($post['starttime'])){
	       		$post['starttime'] = '0';
	       	}
	       	if(empty($post['endtime'])){
	       		$post['endtime'] = '0';
	       	}
	       	if(isset($post['id'])){
	       		//验证菜单是否存在
	            $menu = Db::name('news')->where('id',$post['id'])->find();
	            if(empty($menu)) {
	            	return json(['code'=>3,'msg'=>'ID不正确','returnData'=>'']);die;
	            }
	       		$post['updatetime']   = time();
	       		$post['update_admin'] = $admininfo['id'];
                $res = Db::name('news')->where('id',$post['id'])->update($post);
	       		if($res) {
	            	return json(['code'=>0,'msg'=>'修改成功','returnData'=>'']);die;
	        	} else {
	            	return json(['code'=>4,'msg'=>'修改失败','returnData'=>'']);die;
	       		}
	       	}else{
	       		$post['addtime']  = $post['updatetime']   = time();
	       		$post['admin_id'] = $post['update_admin'] = $admininfo['id'];
                $res = Db::name('news')->insert($post);
	       		if($res) {
                    return json(['code'=>0,'msg'=>'添加成功','returnData'=>'']);die;
                } else {
                    return json(['code'=>5,'msg'=>'添加失败','returnData'=>'']);die;
                }
	       	}
    	}else{
    		$pid = 0;
	    	if($id>0){
	    		$one = Db::name('news')->where(['id'=>$id])->find();
	    		$this->assign('one',$one);
	    		$pid = $one['pid'];
	    	}
	    	$catelist = Db::name('news_cate')->field(['id','title'])->where(['pid'=>'0','is_del'=>'n'])->select();
	    	foreach($catelist as $k=>$v){
	    		$child = DB::name('news_cate')->field(['id','title'])->where(['pid'=>$v['id'],'is_del'=>'n'])->order('orders asc,addtime asc')->select();
	    		foreach($child as $key=>$val){
	    			$childa = DB::name('news_cate')->field(['id','title'])->where(['pid'=>$val['id'],'is_del'=>'n'])->order('orders asc,addtime asc')->select();
	    			$child[$key]['childa'] = $childa;
	    		}
	    		$catelist[$k]['child'] = $child;
	    	}
	    	$this->assign('catelist',$catelist);
	    	$this->assign('pid',$pid);
	    	return $this->fetch('cms/news/publish');
	    }
	}
	public function news_orders(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
    		$data['orders'] = $post['va'];
    		$res = Db::name('news')->where('id',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'操作成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
    	}
	}
	public function news_show(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
    		if($post['va']=='n'){
    			$va = 'y';
    		}else{
    			$va = 'n';
    		}
    		$data['is_show'] = $va;
    		$res = Db::name('news')->where('id',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'操作成功','va'=>$va]);
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
    	}
	}
	public function news_del(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
            if(empty($post['id'])){
                return json(['code'=>2,'msg'=>'请选择要删除的文章']);
            }
    		$data['is_del'] = 'y';
    		$res = Db::name('news')->where('id','in',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'删除成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'删除失败']);
    		}
    	}
	}
/************************************************************************/
/** 文章类型管理
/************************************************************************/
	public function news_cate(){
		$list = Db::name('news_cate')->where(['pid'=>0,'is_del'=>'n'])->order('orders asc,id desc')->select();
		foreach($list as $k=>$v){
			$child = DB::name('news_cate')->where(['pid'=>$v['id'],'is_del'=>'n'])->order('orders asc,addtime asc')->select();
    		foreach($child as $key=>$val){
    			$childa = DB::name('news_cate')->where(['pid'=>$val['id'],'is_del'=>'n'])->order('orders asc,addtime asc')->select();
    			$child[$key]['childa'] = $childa;
    		}
    		$list[$k]['child'] = $child;
		}
		$this->assign('list',$list);
		$count = count($list);
		$this->assign('count',$count);
		return $this->fetch('cms/news/cate');
	}
	public function news_cate_publish(){
    	$pid = $this->request->param('pid') ? $this->request->param('pid') : '0';
    	$id  = $this->request->param('id')  ? $this->request->param('id')  : '0';
    	if($this->request->isPost()){
    		$post = $this->request->post();
    		$admininfo = $this->admininfo;
    		$validate = new \think\Validate();
            $rule =   [
                'title'  => 'require'
            ];
            $message  =   [
                'title.require' => '类型名不能为空'
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
	            $menu = Db::name('news_cate')->where('id',$post['id'])->find();
	            if(empty($menu)) {
	            	return json(['code'=>3,'msg'=>'ID不正确','returnData'=>'']);die;
	            }
	       		$post['updatetime']   = time();
	       		$post['update_admin'] = $admininfo['id'];
                $res = Db::name('news_cate')->where('id',$post['id'])->update($post);
	       		if($res) {
	            	return json(['code'=>0,'msg'=>'修改成功','returnData'=>'']);die;
	        	} else {
	            	return json(['code'=>4,'msg'=>'修改失败','returnData'=>'']);die;
	       		}
	       	}else{
	       		$post['addtime']  = $post['updatetime']   = time();
	       		$post['admin_id'] = $post['update_admin'] = $admininfo['id'];
                $res = Db::name('news_cate')->insert($post);
	       		if($res) {
                    return json(['code'=>0,'msg'=>'添加成功','returnData'=>'']);die;
                } else {
                    return json(['code'=>5,'msg'=>'添加失败','returnData'=>'']);die;
                }
	       	}
    	}else{
	    	if($id>0){
	    		$one = Db::name('news_cate')->where(['id'=>$id])->find();
	    		$this->assign('one',$one);
	    	}
	    	$tylist = Db::name('news_cate')->field(['id','title'])->where(['pid'=>'0','is_del'=>'n'])->select();
	    	foreach($tylist as $k=>$v){
	    		$tylist[$k]['child'] = Db::name('news_cate')->field(['id','title'])->where(['pid'=>$v['id'],'is_del'=>'n'])->select();
	    	}
	    	$this->assign('tylist',$tylist);
	    	$this->assign('pid',$pid);
	    	return $this->fetch('cms/news/cate_publish');
	    }
	}
	public function news_cate_order(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
    		$data['orders'] = $post['va'];
    		$res = Db::name('news_cate')->where('id',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'操作成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
    	}
	}
	public function news_cate_show(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
    		if($post['va']=='n'){
    			$va = 'y';
    		}else{
    			$va = 'n';
    		}
    		$data['is_show'] = $va;
    		$res = Db::name('news_cate')->where('id',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'操作成功','va'=>$va]);
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
    	}
	}
	public function news_cate_del(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
            if(empty($post['id'])){
                return json(['code'=>2,'msg'=>'请选择要删除的类型']);
            }
    		$data['is_del'] = 'y';
    		$res = Db::name('news_cate')->where('id','in',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'删除成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'删除失败']);
    		}
    	}
	}
/************************************************************************/
/** 公告管理
/************************************************************************/
	public function notice(){
		$notice = Db::name('notice');
		$pid = $this->request->param('pid') ? $this->request->param('pid') : 'all';
		$this->assign('pid',$pid);
		if($pid!='all'){
			$where['a.pid'] = $pid;
		}
		$keys = $this->request->param('keys') ? $this->request->param('keys') : '';
		$this->assign('keys',$keys);
		if($keys!=''){
			$where['a.title'] = ['like','%'.$keys.'%'];
		}
		
		$where['a.is_del'] = 'n';
		$list = $notice->alias('a')
				->field('a.id,a.title,a.addtime,a.is_show,ac.cate_title')
				->join('notice_cate ac', 'ac.id=a.pid','left')
				->where($where)->order('a.addtime desc')->paginate(20);
		$count = $notice->alias('a')->field(['a.id'])->where($where)->count();
		$catelist = Db::name('notice_cate')->field(['id','cate_title'])->where(['is_show'=>'y','is_del'=>'n'])->order('addtime asc')->select();
		$this->assign('list',$list);
		$this->assign('count',$count);
		$this->assign('catelist',$catelist);
		return $this->fetch('cms/notice/index');
	}
	public function notice_publish(){
		$id  = $this->request->param('id')  ? $this->request->param('id')  : '0';
    	if($this->request->isPost()){
    		$post = $this->request->post();
			$admininfo = $this->admininfo;
	       	$validate = new \think\Validate();
            $rule =   [
            	'pid'  => 'require',
                'title'  => 'require',
                'content'  => 'require'
            ];
            $message  =   [
            	'pid.require'   => '请选择公告类型',
                'title.require' => '标题不能为空',
                'content.require' => '公告内容不能为空'
            ];
            $validate->message($message);
			if(isset($post['id'])){
				$post['updatetime'] = time();
				$post['update_admin'] = $admininfo['id'];
				$res = Db::name('notice')->where('id',$post['id'])->update($post);
				if($res) {
	            	return json(['code'=>0,'msg'=>'修改成功','returnData'=>'']);die;
	        	} else {
	            	return json(['code'=>4,'msg'=>'修改失败','returnData'=>'']);die;
	       		}
			}else{
				$post['author'] = $admininfo['nickname'] ? $admininfo['nickname'] : $admininfo['username'];
				$post['addtime'] = $post['updatetime'] = time();
				$post['admin_id'] = $post['update_admin'] = $admininfo['id'];
				$res = Db::name('notice')->insert($post);
				if($res) {
                    return json(['code'=>0,'msg'=>'添加成功','returnData'=>'']);die;
                } else {
                    return json(['code'=>5,'msg'=>'添加失败','returnData'=>'']);die;
                }
			}
    	}else{
    		$catelist = Db::name('notice_cate')->field(['id','cate_title'])->where(['is_show'=>'y','is_del'=>'n'])->order('addtime asc')->select();
			$this->assign('catelist',$catelist);
			if($id>0){
				$one = Db::name('notice')->where('id',$id)->find();
				$this->assign('one',$one);
			}
    		return $this->fetch('cms/notice/publish');
    	}
	}
	public function notice_show(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
    		if($post['va']=='n'){
    			$va = 'y';
    		}else{
    			$va = 'n';
    		}
    		$data['is_show'] = $va;
    		$res = Db::name('notice')->where('id',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'操作成功','va'=>$va]);
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
    	}
	}
	public function notice_del(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
            if(empty($post['id'])){
                return json(['code'=>2,'msg'=>'请选择要删除的公告']);
            }
    		$data['is_del'] = 'y';
    		$res = Db::name('notice')->where('id','in',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'删除成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'删除失败']);
    		}
    	}
	}
/************************************************************************/
/** 幻灯片
/************************************************************************/
	public function banner(){
		$where[] = ['is_del','=','n'];
		$where[] = ['pid','neq','0'];
		$pid = $this->request->param('pid') ? $this->request->param('pid') : '0';
		if($pid!='0'){
			$where[] = ['pid','=',$pid];
		}
		$this->assign('pid',$pid);
		$keys = $this->request->param('keys') ? $this->request->param('keys') : '0';
		if($keys!='0'){
			$where[] = ['title','like','%'.$keys.'%'];
		}
		$this->assign('keys',$keys);

		$list = Db::name('banner')->field('id,pid,title,thumb,url,orders,is_show,addtime,admin_id,starttime,endtime')->where($where)->order('orders asc,addtime desc')->paginate(20);
		$today = date('Y-m-d H:i:s',time());
		foreach($list as $k=>$v){
			$sk = false;
			if($v['starttime']=='0' || $v['starttime']<=$today){
				$sk = true;
			}	
			$ek = false;
			if($v['endtime']=='0' || $v['endtime']>=$today){
				$ek = true;
			}
			if($sk==true && $ek==true){
				if($v['is_show']=='n'){
					$resup = Db::name('banner')->where('id',$v['id'])->update(['is_show'=>'y']);
				}
			}else{
				if($v['is_show']=='y'){
					$resup = Db::name('banner')->where('id',$v['id'])->update(['is_show'=>'n']);
				}
			}
		}
		$this->assign('list',$list);
		$count = Db::name('banner')->where($where)->count();
		$this->assign('count',$count);
		return $this->fetch('cms/banner/index');
	}
	public function banner_publish(){
		$id  = $this->request->param('id')  ? $this->request->param('id')  : '0';
    	if($this->request->isPost()){
    		$post = $this->request->post();
    		$admininfo = $this->admininfo;
    		$validate = new \think\Validate();
            $rule =   [
            	'pid'  => 'require',
            	'thumb'  => 'require',
                'title'  => 'require'
            ];
            $message  =   [
            	'pid.require'   => '请选择类型',
            	'thumb.require'   => '请上传图片',
                'title.require' => '标题不能为空'
            ];
            $validate->message($message);
            //验证部分数据合法性
            if (!$validate->check($post,$rule)) {
                return json(['code'=>2,'msg'=>$validate->getError(),'returnData'=>'']);die;
            }
	       	if(empty($post['is_show'])){
	       		$post['is_show'] = 'n';
	       	}
	       	if(empty($post['starttime'])){
	       		$post['starttime'] = '0';
	       	}
	       	if(empty($post['endtime'])){
	       		$post['endtime'] = '0';
	       	}
	       	if(isset($post['id'])){
	       		//验证菜单是否存在
	            $menu = Db::name('banner')->where('id',$post['id'])->find();
	            if(empty($menu)) {
	            	return json(['code'=>3,'msg'=>'ID不正确','returnData'=>'']);die;
	            }
	       		$post['updatetime']   = time();
	       		$post['update_admin'] = $admininfo['id'];
                $res = Db::name('banner')->where('id',$post['id'])->update($post);
	       		if($res) {
	            	return json(['code'=>0,'msg'=>'修改成功','returnData'=>'']);die;
	        	} else {
	            	return json(['code'=>4,'msg'=>'修改失败','returnData'=>'']);die;
	       		}
	       	}else{
	       		$post['addtime']  = $post['updatetime']   = time();
	       		$post['admin_id'] = $post['update_admin'] = $admininfo['id'];
                $res = Db::name('banner')->insert($post);
	       		if($res) {
                    return json(['code'=>0,'msg'=>'添加成功','returnData'=>'']);die;
                } else {
                    return json(['code'=>5,'msg'=>'添加失败','returnData'=>'']);die;
                }
	       	}
    	}else{
    		$pid = 0;
	    	if($id>0){
	    		$one = Db::name('banner')->where(['id'=>$id])->find();
	    		$this->assign('one',$one);
	    		$pid = $one['pid'];
	    	}
	    	$this->assign('pid',$pid);
	    	return $this->fetch('cms/banner/publish');
	    }
	}
	public function banner_order(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
    		$data['orders'] = $post['va'];
    		$res = Db::name('banner')->where('id',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'操作成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
    	}
	}
	public function banner_show(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
    		if($post['va']=='n'){
    			$va = 'y';
    		}else{
    			$va = 'n';
    		}
    		$data['is_show'] = $va;
    		$res = Db::name('banner')->where('id',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'操作成功','va'=>$va]);
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
    	}
	}
	public function banner_del(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
            if(empty($post['id'])){
                return json(['code'=>2,'msg'=>'请选择要删除的幻灯片']);
            }
    		$data['is_del'] = 'y';
    		$res = Db::name('banner')->where('id','in',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'删除成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'删除失败']);
    		}
    	}
	}
/************************************************************************/
/** 广告管理
/************************************************************************/
	public function advs(){
		$banner = Db::name('banner');
		$where['is_del'] = 'n';
		$where['pid']    = ['eq','0'];
		$list = $banner->field('is_del,admin_id,update_admin',true)->where($where)->order('orders asc,addtime desc')->select();
		$this->assign('list',$list);
		return $this->fetch('cms/adv/index');
	}
	public function advs_publish(){
		$banner = Db::name('banner');
    	$id  = $this->request->param('id')  ? $this->request->param('id')  : '0';
    	if($this->request->isPost()){
    		$post = $this->request->post();
    		$admininfo = $this->admininfo;
    		$validate = new \think\Validate();
            $rule =   [
            	'pid'    => 'require',
            	'thumb'  => 'require',
                'title'  => 'require'
            ];
            $message  =   [
            	'pid.require'     => '请选择类型',
            	'thumb.require'   => '请上传图片',
                'title.require'   => '标题不能为空'
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
	            $banner = $banner->where('id',$post['id'])->find();
	            if(empty($banner)) {
	            	return $this->error('id不正确');
	            }
	       		$post['updatetime']   = time();
	       		$post['update_admin'] = $admininfo['id'];
	       		$res = Db::name('banner')->where('id',$post['id'])->update($post);
	       		if($res) {
	            	return json(['code'=>0,'msg'=>'修改成功','returnData'=>'']);die;
	        	} else {
	            	return json(['code'=>4,'msg'=>'修改失败','returnData'=>'']);die;
	       		}
	       	}else{
	       		$post['addtime']  = $post['updatetime']   = time();
	       		$post['admin_id'] = $post['update_admin'] = $admininfo['id'];
	       		$res = Db::name('banner')->insert($post);
	       		if($res) {
                    return json(['code'=>0,'msg'=>'添加成功','returnData'=>'']);die;
                } else {
                    return json(['code'=>5,'msg'=>'添加失败','returnData'=>'']);die;
                }
	       	}
    	}else{
    		if($id>0){
    			$one = $banner->where(['id'=>$id])->find();
    			$this->assign('one',$one);
    		}
    		return $this->fetch('cms/adv/publish');
    	}
	}
	public function advs_order(){
    	if($this->request->isAjax()){
    		$post = $this->request->post();
    		$data['orders'] = $post['va'];
    		$res = Db::name('banner')->where('id',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'操作成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
    	}
    }
	public function advs_show(){
    	if($this->request->isAjax()){
    		$post = $this->request->post();
    		if($post['va']=='n'){
    			$va = 'y';
    		}else{
    			$va = 'n';
    		}
    		$data['is_show'] = $va;
    		$res = Db::name('banner')->where('id',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'操作成功','va'=>$va]);
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
    	}
    }
/************************************************************************/
/** 视频讲座
/************************************************************************/
	public function video(){
		$keys = $this->request->param('keys') ? $this->request->param('keys') : '';
		$this->assign('keys',$keys);
		if($keys!=''){
			$where[] = ['title|keywords|zhujiang','like','%'.$keys.'%'];
		}
		$where[] = ['is_del','=','n'];
		$where[] = ['id','neq','1'];
		$riqi = $this->request->param('riqi') ? $this->request->param('riqi') : '';
		$this->assign('riqi',$riqi);
		if($riqi!=""){
			$riqia = explode("~",trim($riqi));
			$where[] = ['starttime',">=",strtotime($riqia[0])];
			$where[] = ['endtime','<=',strtotime($riqia[1])];
		}
		$list = Db::name('shili_video')->field('is_del',true)->where($where)->order("updatetime desc,addtime desc")->paginate(15);
		$count = Db::name('shili_video')->field(['id'])->where($where)->count();
	    $this->assign('count',$count);
	    $this->assign('list',$list);
    	return $this->fetch('cms/video/index');
	}
	public function video_publish(){
		$id  = $this->request->param('id')  ? $this->request->param('id')  : '0';
    	if($this->request->isPost()){
    		$post = $this->request->post();
    		$admininfo = $this->admininfo;
	       	$validate = new \think\Validate();
            $rule =   [
            	'img'        => 'require',
                'title'      => 'require',
                'zhujiang'   => 'require',
                'starttime'  => 'require',
                'endtime'    => 'require'
            ];
            $message  =   [
            	'img.require'   => '请上传封面',
                'title.require' => '标题不能为空',
                'zhujiang.require' => '主讲人不能为空',
                'starttime.require' => '请选择开讲时间',
                'endtime.require' => '请选择结束时间'
            ];
            $validate->message($message);
            //验证部分数据合法性
            if (!$validate->check($post,$rule)) {
                return json(['code'=>2,'msg'=>$validate->getError(),'returnData'=>'']);die;
            }
            $post['starttime'] = strtotime($post['starttime']);
            $post['endtime']   = strtotime($post['endtime']);
	       	if(isset($post['id'])){
				$post['updatetime'] = time();
				$post['update_admin'] = $admininfo['id'];
				$res = Db::name('shili_video')->where('id',$post['id'])->update($post);
				if($res) {
	            	return json(['code'=>0,'msg'=>'修改成功','returnData'=>'']);die;
	        	} else {
	            	return json(['code'=>4,'msg'=>'修改失败','returnData'=>'']);die;
	       		}
			}else{
				$post['addtime']  = $post['updatetime'] = time();
				$post['admin_id'] = $post['update_admin'] = $admininfo['id'];
				$res = Db::name('shili_video')->insert($post);
				if($res) {
                    return json(['code'=>0,'msg'=>'添加成功','returnData'=>'']);die;
                } else {
                    return json(['code'=>5,'msg'=>'添加失败','returnData'=>'']);die;
                }
			}
    	}else{
    		if($id>0){
				$one = Db::name('shili_video')->where('id',$id)->find();
				if($one){
					$one['starttime'] = date('Y-m-d H:i:s',$one['starttime']);
					$one['endtime']   = date('Y-m-d H:i:s',$one['endtime']);
				}
				$this->assign('one',$one);
			}
    		return $this->fetch('cms/video/publish');
    	}
	}
	public function video_show(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
    		if($post['va']=='n'){
    			$va = 'y';
    		}else{
    			$va = 'n';
    		}
    		$data['is_show'] = $va;
    		$res = Db::name('shili_video')->where('id',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'操作成功','va'=>$va]);
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
    	}
	}
	public function video_del(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
            if(empty($post['id'])){
                return json(['code'=>2,'msg'=>'请选择要删除的视频']);
            }
    		$data['is_del'] = 'y';
    		$res = Db::name('shili_video')->where('id','in',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'删除成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'删除失败']);
    		}
    	}
	}
}