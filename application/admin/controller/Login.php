<?php
namespace app\admin\controller;
use \think\Controller;
use \think\Db;
use \think\Session;
use \think\Cookie;
use \think\Request;
use \think\AES;
class Login extends Controller{
	public function index(){
		$webconfig = Db::name('webconfig')->where(['id'=>1])->find();
    	$this->assign('webconfig',$webconfig);
    	$exist = Db::query('show tables like "ygy_admin"');
    	if(empty($exist)){
    		$this->create_table_admin();
    	}
		return $this->fetch();
	}
	public function checkpwd(){
		$data = input();
		if($this->request->isPost()){
			$validate = new \think\Validate();
			$rule =   [
		        'uname'  => 'require|min:3|max:32',
		        'upwd'   => 'require|min:6|max:32',
		        'ucode'  => 'require|captcha',    
		    ];
		    $message  =   [
        		'uname.require' => '用户名不能为空',
        		'uname.max'     => '用户名最多不超过32个字符',
        		'uname.min'     => '用户名最少3个字符',
        		'upwd.require'  => '密码不能为空',
        		'upwd.max'      => '密码最多不超过32个字符',
        		'upwd.min'      => '密码最少6个字符',
        		'ucode.require' => '验证码不能为空',
        		'ucode.captcha' => '验证码不正确'    
    		];
    		$validate->message($message);
            //验证部分数据合法性
            if (!$validate->check($data,$rule)) {
                return json(['code'=>2,'msg'=>$validate->getError(),'returnData'=>'']);die;
            }
            $admin  = Db::name('admin')
            		->field('id,username,password,is_show')
            		->where("username=:username and is_del=:is_del", ['username' => $data['uname'],'is_del'=>'n'])
            		->find();
           	if(empty($admin)){
           		return json(['code'=>3,'msg'=>'用户不存在','returnData'=>'']);die;
           	}else{
           		if($admin['is_show']=="n"){
           			return json(['code'=>4,'msg'=>'用户已被拉黑，请联系管理员','returnData'=>'']);die;
           		}else{
           			if($admin['password']!=MD5(MD5($data['upwd']))){
           				return json(['code'=>5,'msg'=>'账号或密码错误','returnData'=>'']);die;
           			}else{
           				$ip   = $this->request->ip();
						$time = time();
						$tkarr = [];
						$tkarr['uid']      = $admin['id'];
						$tkarr['username'] = $admin['username'];
						//更新登录信息
						$login_arr['last_login'] = $tkarr['last_login'] = $time;
						$login_arr['last_ip']    = $tkarr['last_ip']    = $ip;
						$login_arr['last_round'] = $tkarr['last_round'] = rand(100000,999999);
						$res = Db::name('admin')->where('id',$admin['id'])->update($login_arr);
						//储存缓存Token
						$aes = new AES();
						$token  = urlencode($aes->encrypt(json_encode($tkarr)));
						$session = new Session();
						$session->set('ygy_token',$token);
						//更新用户日志
						$log['admin_id']      = $admin['id'];
						$log['title']         = "登录成功";
						$log['content']       = '账户登录成功，登录账户：'.$admin['username'].'；登录时间：'.date('Y-m-d H:i:s',$time).'；登录IP：'.$ip;
						$log['url']           = $this->request->root(true);
						$log['addtime']       = $time;
						$log['addip']         = $ip;
						$log['types']         = "login";
						$log['day']           = date('Ymd',$time);
						$reslog = Db::name('admin_log')->insert($log);
						return json(['code'=>0,'msg'=>'操作成功','returnData'=>'']);
           			}
           		}
           	}
		}else{
			return json(['code'=>1,'msg'=>'非法请求','returnData'=>'']);
		}
	}
	public function logout(){
		$session = new Session();
		$cookie = new Cookie();
		$session->delete('ygy_token');
		$cookie->delete('ygy_token');
		return json(['code'=>0,'msg'=>'退出成功','returnData'=>'']);
	}
	//校验图片验证码
    public function CheckVerify($code){
    	$captcha = new Captcha();
		if( !$captcha->check($code)){
			return fase;
		}
		return true;
    }

	public function create_table_admin(){
		$exist = Db::query('show tables like "ygy_admin"');
		if(!empty($exist)){
    		return json(['code'=>0,'msg'=>'操作成功','returnData'=>$exist]);
    	}
		$sql = "CREATE TABLE `ygy_admin` (
			  	`id` int(11) NOT NULL AUTO_INCREMENT,
			  	`pid` int(11) NOT NULL DEFAULT '0' COMMENT '角色组id',
			  	`username` varchar(255) NOT NULL COMMENT '用户名',
			  	`password` char(50) NOT NULL COMMENT '密码',
			  	`nickname` varchar(255) DEFAULT '' COMMENT '昵称',
			  	`head_pic` varchar(255) DEFAULT '' COMMENT '头像',
			  	`phone` varchar(20) DEFAULT '' COMMENT '手机',
			  	`email` varchar(255) DEFAULT '' COMMENT '邮箱',
			  	`last_login` int(11) DEFAULT '0' COMMENT '最后登录时间',
			  	`last_ip` varchar(100) DEFAULT '' COMMENT '最后登录ip',
			  	`last_round` char(6) DEFAULT '' COMMENT '最后登录随机数',
			  	`addtime` int(11) DEFAULT '0' COMMENT '注册时间',
			  	`addip` varchar(100) DEFAULT '' COMMENT '注册ip',
			  	`updatetime` int(11) DEFAULT '0' COMMENT '最后更新时间',
			  	`is_show` char(5) DEFAULT 'y' COMMENT '是否拉黑',
			  	`is_del` char(5) DEFAULT 'n' COMMENT '是否删除',
			  	PRIMARY KEY (`id`),
			  	KEY `username` (`username`) USING BTREE,
			  	KEY `phone` (`phone`) USING BTREE,
			  	KEY `pid` (`pid`) USING BTREE,
			  	KEY `is_show` (`is_show`) USING BTREE,
			  	KEY `is_del` (`is_del`) USING BTREE
			) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC COMMENT='管理员表';";
		$aa = Db::execute($sql);
		$add = [];
		$add['pid'] = 0; 
		$add['username']   = 'admin';
		$add['password']   = MD5(MD5('admin123'));
		$add['nickname']   = "超级管理员";
		$add['addtime']    = time();
		$add['addip']      = $this->request->ip();
		$resid = Db::name('admin')->insertGetId($add);

		$sql = "CREATE TABLE `ygy_admin_log` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`admin_id` int(11) NOT NULL DEFAULT '0' COMMENT '管理员id',
				`title` varchar(255) DEFAULT '' COMMENT '标题',
				`content` text COMMENT '内容',
				`url` varchar(255) DEFAULT '' COMMENT '操作的url',
				`addtime` int(11) DEFAULT '0' COMMENT '操作时间',
			  	`addip` varchar(100) DEFAULT '' COMMENT '操作ip',
			  	`day` int(11) DEFAULT '0' COMMENT '操作日期',
			  	`types` char(20) DEFAULT 'login' COMMENT '日志类型',
			  	`is_show` char(5) DEFAULT 'y' COMMENT '是否显示',
			  	`is_del` char(5) DEFAULT 'n' COMMENT '是否删除',
			  	PRIMARY KEY (`id`),
			  	KEY `admin_id` (`admin_id`) USING BTREE,
			  	KEY `day` (`day`) USING BTREE,
			  	KEY `types` (`types`) USING BTREE,
			  	KEY `is_show` (`is_show`) USING BTREE,
			  	KEY `is_del` (`is_del`) USING BTREE
			) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC COMMENT='管理员操作日志表';";
		$aa = Db::execute($sql);

		$log['admin_id']      = $resid;
		$log['title']         = "管理员账号开通注册成功";
		$log['content']       = "管理员账号开通注册成功，超级管理员自动创建";
		$log['url']           = $this->request->root(true);
		$log['addtime']       = time();
		$log['addip']         = $this->request->ip();
		$log['types']         = "register";
		$log['day']           = date('Ymd');
		$reslog = Db::name('admin_log')->insert($log);

		return true;
	}
}