<?php
namespace app\admin\controller;
use \think\Controller;
use app\admin\controller\Base;
use \think\Db;
use \think\Session;
use \think\Cookie;
use \think\Request;
use \think\AES;
class Admin extends Base{
/******************************************************/
/** 管理员管理
/******************************************************/
    public function index(){
        $pid = $this->request->param('pid') ? $this->request->param('pid') : 0;
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
    public function publish(){
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
            $list = Db::name('admin_cate')->field(['id','title'])->where(['is_show'=>'y','is_del'=>'n'])->order('addtime asc')->select();
            $this->assign('list',$list);
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
            $res = Db::name('admin')->where('id',$post['id'])->update($data);
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
/******************************************************/
/** 管理员组
/******************************************************/
    public function admin_cate(){
        $list = Db::name('admin_cate')->field('id,title,desc,is_show,addtime')->where(['is_del'=>'n'])->order('id asc,addtime desc')->select();
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
                'title'  => 'require',
            ];
            $message  =   [
                'title.require' => '管理员组标题不能为空',
            ];
            $validate->message($message);
            //验证部分数据合法性
            if (!$validate->check($post,$rule)) {
                return json(['code'=>2,'msg'=>$validate->getError(),'returnData'=>'']);die;
            }
            if(isset($post['quanxian'])){
                $post['quanxian'] = implode(',',$post['quanxian']);
            }
            if(isset($post['id'])){
                //验证菜单是否存在
                $menu = Db::name('admin_cate')->field('id')->where('id',$post['id'])->find();
                if(empty($menu)) {
                    return json(['code'=>3,'msg'=>'ID不正确','returnData'=>'']);die;
                }
                $post['updatetime']   = time();
                $post['update_admin'] = $admininfo['id'];
                $res = Db::name('admin_cate')->where('id',$post['id'])->update($post);
                if($res) {
                    return json(['code'=>0,'msg'=>'修改成功','returnData'=>'']);die;
                } else {
                    return json(['code'=>4,'msg'=>'修改失败','returnData'=>'']);die;
                }
            }else{
                $post['addtime']  = $post['updatetime']   = time();
                $post['admin_id'] = $post['update_admin'] = $admininfo['id'];
                $res = Db::name('admin_cate')->insert($post);
                if($res) {
                    return json(['code'=>0,'msg'=>'添加成功','returnData'=>'']);die;
                } else {
                    return json(['code'=>5,'msg'=>'添加失败','returnData'=>'']);die;
                }
            }
        }else{
            $one = Db::name('admin_cate')->where('id',$id)->find();
            if($one){
                if($one['quanxian']){
                    $one['quanxian'] = explode(",",$one['quanxian']);
                }
            }
            $this->assign('one',$one);
            $list = Db::name('admin_menus')->field(['id','title'])->where(['is_show'=>'y','is_del'=>'n','pid'=>'0'])->order('addtime asc')->select();
            foreach($list as $k=>$v){
                $child = Db::name('admin_menus')->field(['id','title'])->where(['is_show'=>'y','is_del'=>'n','pid'=>$v['id']])->order('addtime asc')->select();
                foreach($child as $ka=>$va){
                    $childa = Db::name('admin_menus')->field(['id','title'])->where(['is_show'=>'y','is_del'=>'n','pid'=>$va['id']])->order('addtime asc')->select();
                    $child[$ka]['childa'] = $childa;
                }
                $list[$k]['child'] = $child;
            }
            $this->assign('list',$list);
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
            $res = Db::name('admin_cate')->where('id',$post['id'])->update($data);
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
                return json(['code'=>2,'msg'=>'请选择要删除的管理员组']);
            }
            $data['is_del'] = 'y';
            $res = Db::name('admin_cate')->where('id','in',$post['id'])->update($data);
            if($res){
                return json(['code'=>0,'msg'=>'删除成功']);
            }else{
                return json(['code'=>1,'msg'=>'删除失败']);
            }
        }
    }

/******************************************************/
/** 个人信息
/******************************************************/
    public function info(){
		$admininfo = $this->admininfo;
		if($this->request->isPost()){
			$post = input();
			$validate = new \think\Validate();
			$rule =   [
		        'nickname'  => 'require|min:2|max:32',
		        'phone'     => 'require|mobile',
		        'email'     => 'require|email',  
		    ];
		    $message  =   [
        		'nickname.require' => '昵称不能为空',
        		'nickname.max'     => '昵称最多不超过32个字符',
        		'nickname.min'     => '昵称最少2个字符',
        		'phone.require'    => '手机号码不能为空',
        		'phone.mobile'     => '手机号码格式错误',
        		'email.require'    => 'Email不能为空',
        		'email.email'     => 'Email格式错误'
    		];
    		$validate->message($message);
            //验证部分数据合法性
            if (!$validate->check($post,$rule)) {
                return json(['code'=>2,'msg'=>$validate->getError(),'returnData'=>'']);die;
            }
            $uparr['phone']    = $post['phone'];
            $uparr['email']    = $post['email'];
            $uparr['nickname'] = $post['nickname'];
            $uparr['head_pic'] = $post['head_pic'];
            $uparr['updatetime'] = time();
            $res = Db::name('admin')->where('id',$admininfo['id'])->update($uparr);
            if($res){
            	//更新用户日志
				$log['admin_id']      = $admininfo['id'];
				$log['title']         = "成功修改个人信息";
				$log['content']       = '成功修改个人信息，操作账户：'.$admininfo['username'].'；操作时间：'.date('Y-m-d H:i:s',time()).'；操作IP：'.$this->request->ip();
				$log['url']           = $this->request->root(true);
				$log['addtime']       = time();
				$log['addip']         = $this->request->ip();
				$log['types']         = "changeinfo";
				$log['day']           = date('Ymd');
				$reslog = Db::name('admin_log')->insert($log);
            	return json(['code'=>0,'msg'=>'修改成功','returnData'=>'']);
            }else{
            	return json(['code'=>1,'msg'=>'修改失败','returnData'=>'']);
            }
		}else{
			$one = Db::name('admin')->field(['password'],true)->where(['id'=>$admininfo['id']])->find();
			$this->assign('one',$one);
	    	$list = Db::name('admin_cate')->field(['id','title'])->where(['is_show'=>'y','is_del'=>'n'])->order('addtime asc')->select();
	    	$this->assign('list',$list);
			return $this->fetch();
		}
    }
/******************************************************/
/** 修改密码
/******************************************************/
    public function changepwd(){
    	if($this->request->isPost()){
    		$post = input();
			$validate = new \think\Validate();
			$rule =   [
		        'oldpwd'     => 'require|min:6|max:32',
		        'newpwd'     => 'require|min:6|max:32',
		        'renewpwd'   => 'require'
		    ];
		    $message  =   [
        		'oldpwd.require'   => '旧密码不能为空',
        		'oldpwd.max'       => '旧密码最多不超过32个字符',
        		'oldpwd.min'       => '旧密码最少6个字符',
        		'newpwd.require'   => '新密码不能为空',
        		'newpwd.max'       => '新密码最多不超过32个字符',
        		'newpwd.min'       => '新密码最少6个字符',
        		'renewpwd.require' => '确认密码不能为空'
    		];
    		$validate->message($message);
            //验证部分数据合法性
            if (!$validate->check($post,$rule)) {
                return json(['code'=>2,'msg'=>$validate->getError(),'returnData'=>'']);die;
            }
            if($post['newpwd']!=$post['renewpwd']){
            	return json(['code'=>3,'msg'=>'两次密码不一致','returnData'=>'']);die;
            }
            $admininfo = $this->admininfo;
            $admin = Db::name('admin')->field('id,password')->where(['id'=>$admininfo['id'],'is_del'=>'n','is_show'=>'y'])->find();
            if($admin){
            	if($admin['password']===MD5(MD5($post['oldpwd']))){
            		if($admin['password']===MD5(MD5($post['newpwd']))){
            			return json(['code'=>5,'msg'=>'新密码不能和旧密码一样','returnData'=>'']);die;
            		}
            		$arr['password'] = MD5(MD5($post['newpwd']));
            		$arr['updatetime'] = time();
            		$res = Db::name('admin')->where(['id'=>$admininfo['id']])->update($arr);
            		if($res){
            			//更新用户日志
						$log['admin_id']      = $admininfo['id'];
						$log['title']         = "修改密码成功";
						$log['content']       = '修改密码成功，操作账户：'.$admininfo['username'].'；操作时间：'.date('Y-m-d H:i:s',time()).'；操作IP：'.$this->request->ip();
						$log['url']           = $this->request->root(true);
						$log['addtime']       = time();
						$log['addip']         = $this->request->ip();
						$log['types']         = "changepwd";
						$log['day']           = date('Ymd');
						$reslog = Db::name('admin_log')->insert($log);
            			return json(['code'=>0,'msg'=>'修改成功，下次请用新密码登录','returnData'=>'']);die;
            		}else{
            			return json(['code'=>4,'msg'=>'未知错误，请联系管理员','returnData'=>'']);die;
            		}
            	}else{
            		return json(['code'=>5,'msg'=>'旧密码不正确','returnData'=>'']);die;
            	}
            }else{
            	return json(['code'=>4,'msg'=>'未知错误，请联系管理员','returnData'=>'']);die;
            }
    	}else{
    		return $this->fetch();
    	}
    }
}