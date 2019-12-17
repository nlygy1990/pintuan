<?php
namespace app\admin\controller;
use \think\Controller;
use app\admin\controller\Base;
use \think\Db;
use \think\Cookie;
use \think\Session;
use \think\Request;
use PHPExcel_IOFactory;
use PHPExcel;
class School extends Base{
	public function __construct(){
		parent::__construct(); //使用父类的构造方法
	}
/************************************************************************/
/** 学校管理
/************************************************************************/
	public function lists(){
		$where[] = ['h.is_del','=','n'];
		$sheng = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>1])->order('region_order asc,region_id asc')->select();
    	$this->assign('sheng',$sheng);
		$sheng_id = $this->request->param('sheng') ? $this->request->param('sheng') : '';
		if($sheng_id!=''){
			$where[] = ['h.sheng','=',$sheng_id];
			$shi = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>$sheng_id])->order('region_order asc,region_id asc')->select();
			$this->assign('shi',$shi);
			$shi_id = $this->request->param('shi') ? $this->request->param('shi') : '';
			if($shi_id!=''){
				$where[] = ['h.shi','=',$shi_id];
				$qu = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>$shi_id])->order('region_order asc,region_id asc')->select();
				$this->assign('qu',$qu);
				$qu_id = $this->request->param('qu') ? $this->request->param('qu') : '';
				if($qu_id!=''){
					$where[] = ['h.qu','=',$qu_id];
				}
				$this->assign('qu_id',$qu_id);
			}
			$this->assign('shi_id',$shi_id);
		}
		$this->assign('sheng_id',$sheng_id);

		$admininfo = $this->admininfo;
		if($admininfo['school_id']>0){
			$where[] = ['h.id','=',$admininfo['school_id']];
		}
		if($admininfo['schools']!='0'){
			$school = explode(",",$admininfo['schools']);
			$where[] = ['h.id','in',$school];
		}

		$keys = $this->request->param('keys') ? $this->request->param('keys') : '';
		if($keys!=''){
			$where[] = ['h.title|h.description','like','%'.$keys.'%'];
		}
		$this->assign('keys',$keys);
		$list = Db::name('school')
				->field('h.id,h.logo,h.title,h.no,h.diqu,h.address,h.orders,h.is_show,s.title as level_title')
				->alias('h')
				->where($where)
				->join('school_level s', 's.id = h.level', 'left')
				->order('h.orders asc,h.addtime desc')->paginate(20);
		$this->assign('list',$list);
		$count = Db::name('school')->field('h.id')->alias('h')->where($where)->count();
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
            	'level'  => 'require',
                'title'  => 'require',
                'no'     => 'require',
                'sheng'  => 'require',
                'shi'    => 'require',
                'qu'     => 'require',
                'address'=> 'require'
            ];
            $message  =   [
            	'level.require' => '请选择学校类型',
                'title.require' => '学校名称不能为空',
                'no.require'    => '学校代码不能为空',
                'sheng.require' => '请选择所在地',
                'shi.require'   => '请选择所在地',
                'qu.require'    => '请选择所在地',
                'address.require'=> '学校地址不能为空'
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
	            $menu = Db::name('school')->field('id')->where('id',$post['id'])->find();
	            if(empty($menu)) {
	            	return json(['code'=>3,'msg'=>'ID不正确','returnData'=>'']);die;
	            }
	       		$post['updatetime']   = time();
	       		$post['update_admin'] = $admininfo['id'];
                $res = Db::name('school')->where('id',$post['id'])->update($post);
	       		if($res) {
	            	return json(['code'=>0,'msg'=>'修改成功','returnData'=>'']);die;
	        	} else {
	            	return json(['code'=>4,'msg'=>'修改失败','returnData'=>'']);die;
	       		}
	       	}else{
	       		$post['addtime']  = $post['updatetime']   = time();
	       		$post['admin_id'] = $post['update_admin'] = $admininfo['id'];
                $res = Db::name('school')->insert($post);
	       		if($res) {
                    return json(['code'=>0,'msg'=>'添加成功','returnData'=>'']);die;
                } else {
                    return json(['code'=>5,'msg'=>'添加失败','returnData'=>'']);die;
                }
	       	}
    	}else{
    		$sheng = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>1])->order('region_order asc,region_id asc')->select();
    		$this->assign('sheng',$sheng);
    		$level = Db::name('school_level')->order('orders asc')->select();
    		$this->assign('level',$level);
    		$one = Db::name('school')->where('id',$id)->find();
    		if($one){
    			if($one['sheng']>0){
    				$shi = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>$one['sheng']])->order('region_order asc,region_id asc')->select();
    				$this->assign('shi',$shi);
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
		if($this->request->isPost()){
    		$post = $this->request->post();
    		if($post['va']=='n'){
    			$va = 'y';
    		}else{
    			$va = 'n';
    		}
    		$data['is_show'] = $va;
    		$res = Db::name('school')->where('id',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'操作成功','va'=>$va]);
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
    	}
	}
	public function orders(){
		if($this->request->isPost()){
    		$post = $this->request->post();
    		$data['orders'] = $post['va'];
    		$res = Db::name('school')->where('id',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'操作成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
    	}
	}
	public function del(){
		if($this->request->isPost()){
    		$post = $this->request->post();
            if(empty($post['id'])){
                return json(['code'=>2,'msg'=>'请选择要删除的学校']);
            }
    		$data['is_del'] = 'y';
    		$res = Db::name('school')->where('id','in',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'删除成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'删除失败']);
    		}
    	}
	}
	public function guanliyuan(){
		$school_id = $this->request->param('school_id') ? $this->request->param('school_id') : '';
		$this->assign('school_id',$school_id);
		$where[] = ["school_id","=",$school_id];
		$where[] = ['is_del','=','n'];
		$keys = $this->request->param('keys') ? $this->request->param('keys') : '';
		if($keys!=""){
			$where[] = ['username|nickname|phone','like',"%".$keys."%"];
		}
		$list = Db::name('admin')->field('id,username,nickname,phone,school_type,is_show')->where($where)->order('school_type desc,addtime desc')->paginate(20);
		$this->assign('list',$list);
		$count = Db::name('admin')->where($where)->count();
		$this->assign('count',$count);
		return $this->fetch();
	}
	public function admins_publish(){
		$id  = $this->request->param('id')  ? $this->request->param('id')  : '0';
		$school_id = $this->request->param('school_id') ? $this->request->param('school_id') : '';
		$this->assign('school_id',$school_id);
    	if($this->request->isPost()){
    		$post = $this->request->post();
    		$validate = new \think\Validate();
            $rule =   [
                'school_id' => 'require',
                'nickname'  => 'require',
                'username'  => 'require|min:3|max:32',
                'phone'     => 'require'
            ];
            $message  =   [
                'school_id.require'=> '请选择所属学校',
                'nickname.require' => '学校管理员昵称不能为空',
                'username.require' => '学校管理员账号不能为空',
                'username.max'     => '学校管理员账号最多不超过32个字符',
        		'username.min'     => '学校管理员账号最少3个字符',
                'phone.require'    => '学校管理员手机号码不能为空'
            ];
            $validate->message($message);
            //验证部分数据合法性
            if (!$validate->check($post,$rule)) {
                return json(['code'=>2,'msg'=>$validate->getError(),'returnData'=>'']);die;
            }
            if(empty($post['is_show'])){
	       		$post['is_show'] = 'n';
	       	}
	       	if(isset($post['class_id'])){
	       		$post['class_id'] = implode(',',$post['class_id']);
	       	}else{
	       		$post['class_id'] = 0;
	       	}
	       	if(isset($post['id'])){
	       		//检测账号是否存在
	       		$chadmin = Db::name('admin')->field('id')->where('username',$post['username'])->where('is_del','n')->where('id','neq',$post['id'])->find();
	       		if($chadmin){
	       			return json(['code'=>6,'msg'=>'该账号已存在，请换一个账号','returnData'=>'']);die;
	       		}
	       		$post['updatetime'] = time();
	       		if($post['password']!=''){
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
	       		if($post['password']!=''){
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
    		$class = Db::name('school_class')->field('id,title')->where(['school_id'=>$school_id,'is_del'=>'n'])->order('orders asc,addtime desc')->select();
    		$this->assign('class',$class);
    		$one = Db::name('admin')->where('id',$id)->find();
    		$one['class_id'] = explode(",",$one['class_id']);
    		$this->assign('one',$one);
    		return $this->fetch();
    	}
	}
	public function admins_show(){
		if($this->request->isPost()){
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
		if($this->request->isPost()){
    		$post = $this->request->post();
            if(empty($post['id'])){
                return json(['code'=>2,'msg'=>'请选择要删除的学校管理员']);
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
	public function admins_zhuguan(){
		if($this->request->isPost()){
    		$post = $this->request->post();
    		$one = Db::name('admin')->field('id,school_id')->where('id',$post['id'])->find();
    		$cc = Db::name('admin')->where(['school_id'=>$one['school_id']])->update(['school_type'=>0]);
    		$res = Db::name('admin')->where('id',$post['id'])->update(['school_type'=>1,'class_id'=>0]);
    		if($res){
    			return json(['code'=>0,'msg'=>'操作成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
    	}
	}
/************************************************************************/
/** 班级管理
/************************************************************************/
	public function classs(){
		$where[] = ['sc.is_del','=','n'];
		$sheng = Db::name('region')->where(['region_parent_id'=>1])->order('region_order asc,region_id asc')->select();
    	$this->assign('sheng',$sheng);
		$sheng_id = $this->request->param('sheng') ? $this->request->param('sheng') : '';
		$whereschool[] = ['is_del','=','n'];
		if($sheng_id!=''){
			$whereschool[] = ['sheng','=',$sheng_id];
			$shi = Db::name('region')->where(['region_parent_id'=>$sheng_id])->order('region_order asc,region_id asc')->select();
			$this->assign('shi',$shi);
			$shi_id = $this->request->param('shi') ? $this->request->param('shi') : '';
			if($shi_id!=''){
				$whereschool[] = ['shi','=',$shi_id];
				$qu = Db::name('region')->where(['region_parent_id'=>$shi_id])->order('region_order asc,region_id asc')->select();
				$this->assign('qu',$qu);
				$qu_id = $this->request->param('qu') ? $this->request->param('qu') : '';
				if($qu_id!=''){
					$whereschool[] = ['qu','=',$qu_id];
				}
				$this->assign('qu_id',$qu_id);
			}
			$this->assign('shi_id',$shi_id);
		}
		$this->assign('sheng_id',$sheng_id);
		$keys = $this->request->param('keys') ? $this->request->param('keys') : '';
		if($keys!=''){
			$where[] = ['sc.title|sc.description','like','%'.$keys.'%'];
		}
		$this->assign('keys',$keys);
		$school_id = $this->request->param('school_id') ? $this->request->param('school_id') : '';
		if($school_id!=''){
			$where[] = ['sc.school_id','=', $school_id];
		}
		$this->assign('school_id',$school_id);
		$admininfo = $this->admininfo;
		if($admininfo['school_id']>0){
			$whereschool[] = ['id','=',$admininfo['school_id']];
		}
		if($admininfo['class_id']!="0"){
			$where[] = ['sc.id','in',$admininfo['class_id']];
		}
		if($admininfo['schools']!='0'){
			$school = explode(",",$admininfo['schools']);
			$whereschool[] = ['id','in',$school];
		}
		$school = Db::name('school')->field('id,title')->where($whereschool)->order('orders asc,addtime desc')->select();
		$st_id = [];
		foreach($school as $k=>$v){
			$st_id[] = $v['id'];
		}
		if($st_id){
			$st_id = implode(",",$st_id);
			$where[] = ['sc.school_id','in', $st_id];
		}else{
			$where[] = ['sc.school_id','in', 0];
		}
    	$this->assign('school',$school);

		$list = Db::name('school_class')->alias('sc')
				->field('sc.id,sc.title,s.title as school_title,sc.is_show,sc.orders')
				->where($where)
				->join('school s','s.id = sc.school_id','left')
				->order('sc.orders asc,sc.addtime desc')
				->paginate(20);
		$count = Db::name('school_class')->alias('sc')->where($where)->count();
		$this->assign('list',$list);
		$this->assign('count',$count);
		return $this->fetch();
	}
	public function class_publish(){
		$id = $this->request->param('id') ? $this->request->param('id') : '0';
		if($this->request->isPost()){
			$post = $this->request->post();
    		$admininfo = $this->admininfo;
    		$validate = new \think\Validate();
            $rule =   [
            	'school_id'  => 'require',
                'title'      => 'require'
            ];
            $message  =   [
            	'school_id.require' => '请选择学校',
                'title.require'     => '班级名称不能为空'
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
	            $menu = Db::name('school_class')->field('id')->where('id',$post['id'])->find();
	            if(empty($menu)) {
	            	return json(['code'=>3,'msg'=>'ID不正确','returnData'=>'']);die;
	            }
	       		$post['updatetime']   = time();
	       		$post['update_admin'] = $admininfo['id'];
                $res = Db::name('school_class')->where('id',$post['id'])->update($post);
	       		if($res) {
	            	return json(['code'=>0,'msg'=>'修改成功','returnData'=>'']);die;
	        	} else {
	            	return json(['code'=>4,'msg'=>'修改失败','returnData'=>'']);die;
	       		}
	       	}else{
	       		$post['addtime']  = $post['updatetime']   = time();
	       		$post['admin_id'] = $post['update_admin'] = $admininfo['id'];
                $res = Db::name('school_class')->insert($post);
	       		if($res) {
                    return json(['code'=>0,'msg'=>'添加成功','returnData'=>'']);die;
                } else {
                    return json(['code'=>5,'msg'=>'添加失败','returnData'=>'']);die;
                }
	       	}
		}else{
			$school = Db::name('school')->field('id,title')->where('is_del','n')->order('orders asc,addtime desc')->select();
			$this->assign('school',$school);
			$one = Db::name('school_class')->where('id',$id)->find();
			$this->assign('one',$one);
			return $this->fetch();
		}
	}
	public function class_show(){
		if($this->request->isPost()){
    		$post = $this->request->post();
    		if($post['va']=='n'){
    			$va = 'y';
    		}else{
    			$va = 'n';
    		}
    		$data['is_show'] = $va;
    		$res = Db::name('school_class')->where('id',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'操作成功','va'=>$va]);
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
    	}
	}
	public function class_orders(){
		if($this->request->isPost()){
    		$post = $this->request->post();
    		$data['orders'] = $post['va'];
    		$res = Db::name('school_class')->where('id',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'操作成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
    	}
	}
	public function class_del(){
		if($this->request->isPost()){
    		$post = $this->request->post();
            if(empty($post['id'])){
                return json(['code'=>2,'msg'=>'请选择要删除的班级']);
            }
    		$data['is_del'] = 'y';
    		$res = Db::name('school_class')->where('id','in',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'删除成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'删除失败']);
    		}
    	}
	}
/************************************************************************/
/** 学生管理
/************************************************************************/
	public function student(){
		$admininfo = $this->admininfo;
		$where[] = ['m.is_del','=','n'];
		$keys = $this->request->param('keys') ? $this->request->param('keys') : '';
		if($keys!=''){
			$where[] = ['m.name|m.no','like','%'.$keys.'%'];
		}
		$this->assign('keys',$keys);
		if($admininfo['school_id']==0){
			$sheng = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>1])->order('region_order asc,region_id asc')->select();
	    	$this->assign('sheng',$sheng);
			$sheng_id = $this->request->param('sheng') ? $this->request->param('sheng') : '';
			$whereschool[] = ['is_del','=','n'];
			if($sheng_id!=''){
				$whereschool[] = ['sheng','=',$sheng_id];
				$shi = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>$sheng_id])->order('region_order asc,region_id asc')->select();
				$this->assign('shi',$shi);
				$shi_id = $this->request->param('shi') ? $this->request->param('shi') : '';
				if($shi_id!=''){
					$whereschool[] = ['shi','=',$shi_id];
					$qu = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>$shi_id])->order('region_order asc,region_id asc')->select();
					$this->assign('qu',$qu);
					$qu_id = $this->request->param('qu') ? $this->request->param('qu') : '';
					if($qu_id!=''){
						$whereschool[] = ['qu','=',$qu_id];
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
				$whereschool[] = ['id','=',$admininfo['school_id']];
			}
			if($admininfo['class_id']!="0"){
				$where[] = ['m.class_id','in',$admininfo['class_id']];
			}
			if($admininfo['schools']!='0'){
				$school = explode(",",$admininfo['schools']);
				$whereschool[] = ['id','in',$school];
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

		$list = Db::name('school_student')->alias('m')
				->field('m.id,m.no,m.class_id,m.name,m.sex,m.is_show,s.title as school_title')
				->where($where)
				->join('school s','s.id=m.school_id','left')
			    ->order('m.addtime desc')
			    ->paginate(20);
		$this->assign('list',$list);
		$count = Db::name('school_student')->alias('m')->where($where)->count();
		$this->assign('count',$count);
		return $this->fetch();
	}
	public function student_publish(){
		$id = $this->request->param('id') ? $this->request->param('id') : '0';
		if($this->request->isPost()){
			$post = $this->request->post();
			$validate = new \think\Validate();
            $rule =   [
            	'school_id'  => 'require',
            	'class_id'   => 'require',
            	'no'         => 'require',
                'name'       => 'require',
                'birthday'   => 'require'
            ];
            $message  =   [
            	'school_id.require' => '请选择学校',
            	'class_id.require'  => '请选择班级',
            	'no.require'        => '学号不能为空',
                'name.require'      => '姓名不能为空',
                'birthday.require'  => '生日不能为空'
            ];
            $validate->message($message);
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
            //验证部分数据合法性
            if (!$validate->check($post,$rule)) {
                return json(['code'=>2,'msg'=>$validate->getError(),'returnData'=>'']);die;
            }
            if(isset($post['id'])){
	       		//验证学生是否存在
	            $menu = Db::name('school_student')->field('id')->where('id',$post['id'])->find();
	            if(empty($menu)) {
	            	return json(['code'=>3,'msg'=>'ID不正确','returnData'=>'']);die;
	            }
	            //检查学号是否存在
	       		$chno = Db::name('school_student')->field('id')->where('no',$post['no'])->where('id','neq',$post['id'])->find();
	       		if($chno){
	       			return json(['code'=>6,'msg'=>'学号已存在','returnData'=>'']);die;
	       		}
	       		$post['updatetime']   = time();
                $res = Db::name('school_student')->where('id',$post['id'])->update($post);
	       		if($res) {
	            	return json(['code'=>0,'msg'=>'修改成功','returnData'=>'']);die;
	        	} else {
	            	return json(['code'=>4,'msg'=>'修改失败','returnData'=>'']);die;
	       		}
	       	}else{
	       		$post['addtime']  = $post['updatetime']   = time();
	       		//检查学号是否存在
	       		$chno = Db::name('school_student')->field('id')->where('no',$post['no'])->find();
	       		if($chno){
	       			return json(['code'=>6,'msg'=>'学号已存在','returnData'=>'']);die;
	       		}
                $res = Db::name('school_student')->insert($post);
	       		if($res) {
                    return json(['code'=>0,'msg'=>'添加成功','returnData'=>'']);die;
                } else {
                    return json(['code'=>5,'msg'=>'添加失败','returnData'=>'']);die;
                }
	       	}
		}else{
			$admininfo = $this->admininfo;
			$one = Db::name('school_student')->where('id',$id)->find();
			$sheng = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>1])->order('region_order asc,region_id asc')->select();
    		$this->assign('sheng',$sheng);
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
			if($one){
				if($one['sheng']>0){
    				$shi = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>$one['sheng']])->order('region_order asc,region_id asc')->select();
    				$this->assign('shi',$shi);
    			}
    			if($one['shi']>0){
    				$qu = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>$one['shi']])->order('region_order asc,region_id asc')->select();
    				$this->assign('qu',$qu);
    			}
			}
			$user = Db::name('member')->field('id,username,nickname,phone')->where('is_del','n')->select();
			$this->assign('user',$user);
			$this->assign('school',$school);
			$this->assign('class',$class);
			$this->assign('one',$one);
			return $this->fetch();
		}
	}
	public function student_show(){
		if($this->request->isPost()){
    		$post = $this->request->post();
    		if($post['va']=='n'){
    			$va = 'y';
    		}else{
    			$va = 'n';
    		}
    		$data['is_show'] = $va;
    		$res = Db::name('school_student')->where('id',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'操作成功','va'=>$va]);
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
    	}
	}
	public function student_del(){
		if($this->request->isPost()){
    		$post = $this->request->post();
            if(empty($post['id'])){
                return json(['code'=>2,'msg'=>'请选择要删除的学生']);
            }
    		$data['is_del'] = 'y';
    		$res = Db::name('school_student')->where('id','in',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'删除成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'删除失败']);
    		}
    	}
	}
/************************************************************************/
/** 档案管理
/************************************************************************/
	public function archives(){
		$admininfo = $this->admininfo;
		$where[] = ['m.is_del','=','n'];
		$keys = $this->request->param('keys') ? $this->request->param('keys') : '';
		if($keys!=''){
			$where[] = ['m.c_name|m.c_number','like','%'.$keys.'%'];
		}
		$this->assign('keys',$keys);
		if($admininfo['school_id']==0){
			$sheng = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>1])->order('region_order asc,region_id asc')->select();
	    	$this->assign('sheng',$sheng);
			$sheng_id = $this->request->param('sheng') ? $this->request->param('sheng') : '';
			$whereschool[] = ['is_del','=','n'];
			if($sheng_id!=''){
				$whereschool[] = ['sheng','=',$sheng_id];
				$shi = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>$sheng_id])->order('region_order asc,region_id asc')->select();
				$this->assign('shi',$shi);
				$shi_id = $this->request->param('shi') ? $this->request->param('shi') : '';
				if($shi_id!=''){
					$whereschool[] = ['shi','=',$shi_id];
					$qu = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>$shi_id])->order('region_order asc,region_id asc')->select();
					$this->assign('qu',$qu);
					$qu_id = $this->request->param('qu') ? $this->request->param('qu') : '';
					if($qu_id!=''){
						$whereschool[] = ['qu','=',$qu_id];
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
				$whereschool[] = ['id','=',$admininfo['school_id']];
			}
			if($admininfo['class_id']!="0"){
				$where[] = ['m.class_id','in',$admininfo['class_id']];
			}
			if($admininfo['schools']!='0'){
				$school = explode(",",$admininfo['schools']);
				$whereschool[] = ['id','in',$school];
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
	    $list = Db::name('school_student_shili')->alias('m')->where($where)->paginate(20);
	    $count = Db::name('school_student_shili')->alias('m')->where($where)->count();
	    $this->assign('list',$list);
	    $this->assign('count',$count);
		return $this->fetch();
	}
	public function archives_publish(){
		$id = $this->request->param('id') ? $this->request->param('id') : '0';
		if($this->request->isPost()){
			$post = $this->request->post();
			$validate = new \think\Validate();
            $rule =   [
            	'school_id'  => 'require',
            	'class_id'   => 'require',
            	'c_name'     => 'require'
            ];
            $message  =   [
            	'school_id.require' => '请选择学校',
            	'class_id.require'  => '请选择班级',
            	'c_name.require'    => '姓名不能为空'
            ];
            $validate->message($message);
            if (!$validate->check($post,$rule)) {
                return json(['code'=>2,'msg'=>$validate->getError(),'returnData'=>'']);die;
            }
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
	public function archives_pl(){
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
	public function archives_del(){
		if($this->request->isPost()){
    		$post = $this->request->post();
            if(empty($post['id'])){
                return json(['code'=>2,'msg'=>'请选择要删除的学生档案']);
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
/** 地区管理员
/************************************************************************/
	public function area(){
		$pid = $this->request->param('pid') ? $this->request->param('pid') : 2;
		if($pid!=0){
			$where[] = ['a.pid','=',$pid];
		}
        $this->assign('pid',$pid);
        $keys = $this->request->param('keys') ? $this->request->param('keys') : '';
        if($keys!=''){
            $where[] = ['a.username|a.nickname|a.phone|a.email','like',"%".$keys."%"];
        }
        $this->assign('keys',$keys);
        $catelist = Db::name('admin_cate')->field('id,title,desc,is_show,addtime')->where(['is_del'=>'n'])->order('id asc,addtime desc')->select();
        $this->assign('catelist',$catelist);
        $where[] = ['a.id','neq',1];
        $where[] = ['a.is_del','=','n'];
        $where[] = ['a.shop_id','=','0'];
        $where[] = ['a.stores_id','=','0'];
        $where[] = ['a.school_id','=','0'];
        $where[] = ['a.hospital_id','=','0'];
        $list = Db::name('admin')->alias('a')
                ->field('a.id,a.username,a.nickname,a.addtime,a.last_ip,a.last_login,a.is_show,ac.title as title')
                ->where($where)
                ->join('admin_cate ac', 'ac.id=a.pid', 'left')
                ->order('a.addtime desc')
                ->paginate(20);
        $count = Db::name('admin')->alias('a')->where($where)->count();
        $this->assign('list',$list);
        $this->assign('count',$count);
    	return $this->fetch();
	}
	public function area_publish(){
        $id  = $this->request->param('id')  ? $this->request->param('id')  : '0';
        if($this->request->isPost()){
            $post = $this->request->post();
            $admininfo = $this->admininfo;
            $validate = new \think\Validate();
            $rule =   [
                'username'  => 'require',
                'nickname'  => 'require',
                'phone'    => 'require|mobile',
                'email'     => 'require|email'
            ];
            $message  =   [
                'username.require' => '登录账号不能为空',
                'nickname.require' => '管理员昵称不能为空',
                'phone.require'   => '管理员手机不能为空',
                'phone.mobile'    => '手机格式不正确',
                'email.require'   => '管理员邮箱不能为空',
                'email.email'     => '邮箱格式不正确'

            ];
            $validate->message($message);
            //验证部分数据合法性
            if (!$validate->check($post,$rule)) {
                return json(['code'=>2,'msg'=>$validate->getError(),'returnData'=>'']);die;
            }

            if(empty($post['is_show'])){
                $post['is_show'] = 'n';
            }
            $data =[];
            if(isset($post['id'])){
                $chadmin = Db::name('admin')->field('id')->where('username',$post['username'])->where('is_del','n')->where('id','neq',$post['id'])->find();
                if(!empty($chadmin)){
                    return json(['code'=>3,'msg'=>'提交失败：管理员登录账号已被占用','returnData'=>'']);die;
                }
                $data['updatetime'] = time();
                if(!empty($post['password'])){
                    if($post['password']!=$post['repass']){
                        return json(['code'=>4,'msg'=>'提交失败：两次密码不一致','returnData'=>'']);die;
                    }else{
                        $data['password'] = MD5(MD5($post['password']));
                    }
                }
                foreach($post as $key=>$v){
                    if($key=='repass'){
                    }else if($key=='password'){
                    }else{
                        $data[$key] = $v;
                    }
                }
                $res = Db::name('admin')->where('id',$post['id'])->update($data);
                if($res){
                    return json(['code'=>0,'msg'=>'修改成功','returnData'=>'']);die;
                }else{
                    return json(['code'=>4,'msg'=>'修改失败','returnData'=>'']);die;
                }
            }else{
                $chadmin = Db::name('admin')->field('id')->where(['username'=>$post['username'],'is_del'=>'n'])->find();
                if(!empty($chadmin)){
                    return json(['code'=>3,'msg'=>'提交失败：管理员登录账号已被占用','returnData'=>'']);die;
                }
                $data['addtime'] = $data['updatetime'] = time();
                if(!empty($post['password'])){
                    if($post['password']!=$post['repass']){
                        return json(['code'=>4,'msg'=>'提交失败：两次密码不一致','returnData'=>'']);die;
                    }else{
                        $data['password'] = MD5(MD5($post['password']));
                    }
                }else{
                    $data['password'] = MD5(MD5('88888888'));
                }
                foreach($post as $key=>$v){
                    if($key=='repass'){
                    }else if($key=='password'){
                    }else{
                        $data[$key] = $v;
                    }
                }
                $res = Db::name('admin')->insert($data);
                if($res){
                    return json(['code'=>0,'msg'=>'添加成功','returnData'=>'']);die;
                }else{
                    return json(['code'=>5,'msg'=>'添加失败','returnData'=>'']);die;
                }
            }
        }else{
            if($id>0){
                $one = Db::name('admin')->where(['id'=>$id])->find();
                $this->assign('one',$one);
            }
            $list = Db::name('admin_cate')->field(['id','title'])->where(['is_show'=>'y','is_del'=>'n','id'=>'2'])->order('addtime asc')->select();
            $this->assign('list',$list);
            return $this->fetch();
        }
    }
    public function area_quanxian(){
    	$id  = $this->request->param('id')  ? $this->request->param('id')  : '0';
        if($this->request->isPost()){
        	$post = $this->request->post();
        	if(isset($post['school'])){
        		$school = implode(",",$post['school']);
        		$uparr['schools'] = $school;
        	}else{
        		$uparr['schools'] = 0;
        	}
        	$res = Db::name('admin')->where('id',$post['id'])->update($uparr);
        	if($res){
        		return json(['code'=>0,'msg'=>'操设置成功']);
        	}else{
        		return json(['code'=>1,'msg'=>'设置失败']);
        	}
        }else{
        	if($id>0){
                $one = Db::name('admin')->field('id,schools')->where(['id'=>$id])->find();
                if($one){
                	$one['school'] = explode(",",$one['schools']);
                }
                $this->assign('one',$one);
            }
            $school = Db::name('school')->field('id,title')->where('is_del','n')->select();
            $this->assign('school',$school);
            return $this->fetch();
        }
    }
    public function area_show(){
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
    public function area_del(){
        if($this->request->isAjax()){
            $post = $this->request->post();
            if(empty($post['id'])){
                return json(['code'=>2,'msg'=>'请选择要删除的管理员']);
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