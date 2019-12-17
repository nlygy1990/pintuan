<?php
namespace app\api\controller;
use \think\Controller;
use app\api\controller\Base;
use \think\Db;
use \think\Session;
use \think\Cookie;
use \think\Request;
use \think\AES;
class Login extends Base{
	public function __construct(){
		parent::__construct(); //使用父类的构造方法
		$this->wechatMp_appid = "wx4e21922e720f7ee7";
		$this->wechatMp_keys  = "7efa59235a7b8f2e81e08d33e2c7ce20";
    }
/************************************************************************************/
/** 账号密码登录
/***********************************************************************************/
    public function login(){
		$data = input();
		$validate = new \think\Validate();
		$rule =   [
            'sl_uname'  => 'require',
            'sl_upwd'   => 'require'
        ];
        $message  =   [
            'sl_uname.require' => '请填写手机号码',
            'sl_upwd.require' => '请填写密码'
        ];
        $validate->message($message);
        //验证部分数据合法性
        if (!$validate->check($data,$rule)) {
            return json(['code'=>2,'msg'=>$validate->getError()]);die;
        }

		$checkmember = Db::name('member')->field(['wx_openid','qq_openid','sn_openid','is_del','is_show','addip','addtime','sjmobile','sjsystem','sjplatform'],true)->where(['username'=>$data['sl_uname'],'is_show'=>'y','is_del'=>'n'])->find();
		if($checkmember){
			if(MD5(MD5($data['sl_upwd'])) == $checkmember['password']){
				$aes = new AES();
				$time = time();
				$ip = $this->request->ip();
				$tk['id']         = $checkmember['id'];
				$tk['username']   = $checkmember['username'];
				$tk['last_login'] = $time;
				$tk['last_ip']    = $ip;
				$tka = urlencode($aes->encrypt(json_encode($tk)));
				//操作日志
				$add_log['uid']     = $checkmember['id'];
				$add_log['title']   = "手机登录成功";
				$add_log['content'] = "手机号码：".$checkmember['username']."登录成功！操作信息：IP（".$ip."）；时间（".date('Y.m.d H:i:s')."）；";
				$add_log['addtime'] = time();
				$resa = Db::name('member_log')->insert($add_log);
				//更新登录信息
	            $uparr['last_login'] = $time;
	            $uparr['last_ip']   = $ip;
	            $uparr['sjmobile']  = $data['Sjmobile'];
	            $uparr['sjsystem']  = $data['Sjsystem'];
	            $uparr['sjplatform'] = $data['Sjplatform'];
	            $uparr['cid']        = $data['cid'];
	            $uparr['ctoken']     = $data['ctoken'];
				$resb = DB::name('member')->where(['id'=>$checkmember['id']])->update($uparr);
				//信息状态
				if(!$checkmember['nickname'] || !$checkmember['address']){
					$xinxi = 0;
				}else{
					$xinxi = 1;
				}
				return json(['code'=>0,'msg'=>'登录成功！','token'=>$tka,'xinxi'=>$xinxi]);
			}else{
				return json(['code'=>'2','msg'=>'用户名或者密码错误']);
			}
		}else{
			return json(['code'=>'1','msg'=>'用户不存在或者已被拉黑']);
		}
	}
/************************************************************************************/
/** 退出登录
/***********************************************************************************/
	public function logout(){
		$data = input();
   		$token = $this->CheckToken($data['token']);
   		if($token['code']=='0'){
   			$ip = $this->request->ip();
   			$datas['last_login'] = time();
   			$datas['last_ip']   = $this->request->ip();
   			$ups = Db::name('member')->where(['id'=>$token['myinfo']['id']])->update($datas);
   			if($ups){
   				$checkmember = $token['myinfo'];
   				//操作日志
				$add_log['uid']     = $checkmember['id'];
				$add_log['title']   = "账号退出成功";
				$add_log['content'] = "账号退出成功！操作信息：IP（".$ip."）；时间（".date('Y.m.d H:i:s')."）；";
				$add_log['addtime'] = time();
				$resa = Db::name('member_log')->insert($add_log);
   				return json(['code'=>0,'msg'=>'退出成功']);
   			}else{
   				return json(['code'=>1,'msg'=>'退出失败']);
   			}
   		}else{
   			return json($token);
   		}
	}
/************************************************************************************/
/** 微信登录
/***********************************************************************************/
	public function wxLogin(){
		$data = input();
		$checkmember = Db::name('member')->field(['wx_openid','is_del','is_show','addip','addtime','sjmobile','sjsystem','sjplatform'],true)->where(['wx_openid'=>$data['openId'],'is_show'=>'y','is_del'=>'n','oauth'=>'wechat'])->find();
		if($checkmember){
			$aes = new AES();
			$time = time();
			$ip = $this->request->ip();
			$tk['id']        = $checkmember['id'];
			$tk['username']  = $checkmember['username'];
			$tk['last_login'] = $time;
			$tk['last_ip']   = $ip;
			$tka = urlencode($aes->encrypt(json_encode($tk)));
			//操作日志
			$add_log['uid']     = $checkmember['id'];
			$add_log['title']   = "微信登录成功";
			$add_log['content'] = "微信：".$checkmember['nickname']."登录成功！操作信息：IP（".$ip."）；时间（".date('Y.m.d H:i:s')."）；";
			$add_log['addtime'] = time();
			$resa = Db::name('member_log')->insert($add_log);
			//更新登录信息
         	$uparr['last_login'] = $time;
         	$uparr['last_ip']   = $ip;
         	$uparr['sjmobile']  = $data['Sjmobile'];
         	$uparr['sjsystem']  = $data['Sjsystem'];
         	$uparr['sjplatform'] = $data['Sjplatform'];
         	$uparr['cid']        = $data['cid'];
         	$uparr['ctoken']     = $data['ctoken'];
			$resb = DB::name('member')->where(['id'=>$checkmember['id']])->update($uparr);
			//信息状态
			if(!$checkmember['nickname'] || !$checkmember['address']){
				$xinxi = 0;
			}else{
				$xinxi = 1;
			}
			return json(['code'=>0,'msg'=>'登录成功！','token'=>$tka,'xinxi'=>$xinxi]);
		}else{
			$add['user_id']   = isset($data['pid']) ? $data['pid'] : 0;
			$add['wx_openid'] = $data['openId'];
			$add['username']  = $data['unionId'];
			$add['nickname']  = $data['nickName'];
			$add['password']  = MD5(MD5(88888888));
			$add['addip']     = $add['last_ip'] = $this->request->ip();
			$add['addtime']   = $add['last_login'] = time();
			$add['avatar']    = $data['avatarUrl'];
			$add['country']   = $data['country'];
			$add['province']  = $data['province'];
			$add['city']      = $data['city'];
			$add['oauth']     = "wechat";
			//注册的手机信息
			$add['sjmobile']  = $data['Sjmobile'];
			$add['sjsystem']  = $data['Sjsystem'];
			$add['sjplatform'] = $data['Sjplatform'];
	        $add['cid']       = $data['cid'];
	        $add['ctoken']    = $data['ctoken'];
			$res = Db::name('member')->insertGetId($add);
			if($res){
				$aes = new AES();
				$tk['id']        = $res;
				$tk['username']  = $add['username'];
				$tk['last_login'] = $add['last_login'];
				$tk['last_ip']   = $add['last_ip'];
				$tka = urlencode($aes->encrypt(json_encode($tk)));
				//操作日志
				$add_log['uid']     = $res;
				$add_log['title']   = "微信注册/登录成功";
				$add_log['content'] = "微信：".$add['nickname']."注册/登录成功！操作信息：IP（".$ip."）；时间（".date('Y.m.d H:i:s')."）；";
				$add_log['addtime'] = time();
				$resa = Db::name('member_log')->insert($add_log);
				return json(['code'=>0,'msg'=>'登录成功！','token'=>$tka,'xinxi'=>0]);
			}else{
				return json(['code'=>1,'msg'=>'登录失败！']);
			}
		}
	}
	public function filterEmoji($str){
  		$str = preg_replace_callback(
    	'/./u',
    	function (array $match) {
      		return strlen($match[0]) >= 4 ? '' : $match[0];
    	},
    	$str);

 	 return $str;
	}
	public function wechatMp(){
		$aes = new AES();
		$data = input();
		$url = "https://api.weixin.qq.com/sns/jscode2session?appid=".$this->wechatMp_appid."&secret=".$this->wechatMp_keys."&js_code=".$data['code']."&grant_type=authorization_code";
        $res = $this->get_contents($url);
        $result = json_decode($res,1);
        if(isset($result['openid'])){
            $appid         = $this->wechatMp_appid;
            $sessionKey    = $result['session_key'];
            $encryptedData = $data['encryptedData'];
            $iv            = $data['iv'];
            include_once "./class/wechataes/index.php";
            $errCode = $pc->decryptData($encryptedData,$iv,$dataa);
            $userinfo = json_decode($dataa,1);
            if(isset($userinfo['openId'])){
            	if(isset($userinfo['unionId']) && $userinfo['unionId']!=''){
            		$checkmember = Db::name('member')->field(['wx_openid','is_del','is_show','addip','addtime','sjmobile','sjsystem','sjplatform'],true)->where(['username'=>$userinfo['unionId'],'is_show'=>'y','is_del'=>'n','oauth'=>'wechat_mp'])->find();
            	}else{
            		$checkmember = Db::name('member')->field(['wx_openid','is_del','is_show','addip','addtime','sjmobile','sjsystem','sjplatform'],true)->where(['wx_openid'=>$userinfo['openId'],'is_show'=>'y','is_del'=>'n','oauth'=>'wechat_mp'])->find();
            	}
            	if($checkmember){
					$time = time();
					$ip = $this->request->ip();
					$tk['id']         = $checkmember['id'];
					$tk['username']   = $checkmember['username'];
					$tk['last_login'] = $time;
					$tk['last_ip']    = $ip;
					$tka = urlencode($aes->encrypt(json_encode($tk)));
					//操作日志
					$add_log['uid']     = $checkmember['id'];
					$add_log['title']   = "微信登录成功";
					$add_log['content'] = "微信：".$checkmember['nickname']."登录成功！操作信息：IP（".$ip."）；时间（".date('Y.m.d H:i:s')."）；";
					$add_log['addtime'] = time();
					$resa = Db::name('member_log')->insert($add_log);
					//更新登录信息
		         	$uparr['last_login'] = $time;
		         	$uparr['last_ip']    = $ip;
		         	$uparr['wx_openid']  = $userinfo['openId'];
					$resb = DB::name('member')->where(['id'=>$checkmember['id']])->update($uparr);
					//信息状态
					if(!$checkmember['nickname'] || !$checkmember['address']){
						$xinxi = 0;
					}else{
						$xinxi = 1;
					}
					return json(['code'=>0,'msg'=>'登录成功！','token'=>$tka,'xinxi'=>$xinxi]);
				}else{
					$time = time();
					$ip = $this->request->ip();
					$add['user_id']   = isset($data['pid']) ? $data['pid'] : 0;
					if($add['user_id']>'0'){
						$ckone = Db::name('member')->field('id,pid1,pid2,tz_level')->where('id',$add['user_id'])->find();
						if($ckone['tz_level']=="2"){ //如果是总团长
							$add['pid1'] = $ckone['id'];
							$add['pid2'] = "0";
						}else if($ckone['tz_level']=="1"){ //代理团长
							$add['pid1'] = $ckone['pid1'];
							$add['pid2'] = $ckone['id'];
						}else{ //普通团长
							$add['pid1'] = $ckone['pid1'];
							$add['pid2'] = $ckone['pid2'];
						}
					}
					$add['wx_openid'] = $userinfo['openId'];
					$add['username']  = isset($userinfo['unionId']) ? $userinfo['unionId'] : '';
					$add['nickname']  = $this->filterEmoji($userinfo['nickName']);
					$add['password']  = MD5(MD5(88888888));
					$add['addip']     = $add['last_ip'] = $this->request->ip();
					$add['addtime']   = $add['last_login'] = time();
					$add['avatar']    = $userinfo['avatarUrl'];
					$add['country']   = $userinfo['country'];
					$add['province']  = $userinfo['province'];
					$add['city']      = $userinfo['city'];
					$add['oauth']     = "wechat_mp";
					$res = Db::name('member')->insertGetId($add);
					if($res){
						$aes = new AES();
						$tk['id']         = $res;
						$tk['username']   = $add['username'];
						$tk['last_login'] = $add['last_login'];
						$tk['last_ip']    = $add['last_ip'];
						$tka = urlencode($aes->encrypt(json_encode($tk)));
						//操作日志
						$add_log['uid']     = $res;
						$add_log['title']   = "微信注册/登录成功";
						$add_log['content'] = "微信：".$add['nickname']."注册/登录成功！操作信息：IP（".$ip."）；时间（".date('Y.m.d H:i:s')."）；";
						$add_log['addtime'] = time();
						$resa = Db::name('member_log')->insert($add_log);
						return json(['code'=>0,'msg'=>'登录成功！','token'=>$tka,'xinxi'=>0]);
					}else{
						return json(['code'=>1,'msg'=>'登录失败！']);
					}
				}
            }else{
            	return json(['code'=>2,'msg'=>'微信回调错误！错误码：'.$result['errcode']]);
            }
        }else{
        	return json(['code'=>2,'msg'=>'微信回调错误！错误码：'.$result['errcode']]);
        }
	}
/************************************************************************************/
/** QQ登录
/***********************************************************************************/
	public function qqLogin(){
		$data = input();
		$checkmember = Db::name('member')->field(['qq_openid','is_del','is_show','addip','addtime','sjmobile','sjsystem','sjplatform'],true)->where(['qq_openid'=>$data['openId'],'is_show'=>'y','is_del'=>'n','oauth'=>'qq'])->find();
		if($checkmember){
			$aes = new AES();
			$time = time();
			$ip = $this->request->ip();
			$tk['id']        = $checkmember['id'];
			$tk['username']  = $checkmember['username'];
			$tk['last_login'] = $time;
			$tk['last_ip']   = $ip;
			$tka = urlencode($aes->encrypt(json_encode($tk)));
			//操作日志
			$add_log['uid']     = $checkmember['id'];
			$add_log['title']   = "QQ登录成功";
			$add_log['content'] = "QQ：".$checkmember['nickname']."登录成功！操作信息：IP（".$ip."）；时间（".date('Y.m.d H:i:s')."）；";
			$add_log['addtime'] = time();
			$resa = Db::name('member_log')->insert($add_log);
			//更新登录信息
			$uparr['last_login'] = $time;
         	$uparr['last_ip']   = $ip;
         	$uparr['sjmobile']  = $data['Sjmobile'];
         	$uparr['sjsystem']  = $data['Sjsystem'];
         	$uparr['sjplatform'] = $data['Sjplatform'];
         	$uparr['cid']        = $data['cid'];
         	$uparr['ctoken']     = $data['ctoken'];
         	$resb = DB::name('member')->where(['id'=>$checkmember['id']])->update($uparr);
			//信息状态
			if(!$checkmember['nickname'] || !$checkmember['address']){
				$xinxi = 0;
			}else{
				$xinxi = 1;
			}
			return json(['code'=>0,'msg'=>'登录成功！','token'=>$tka,'xinxi'=>$xinxi]);
		}else{
			$add['qq_openid']  = $data['openid'];
			$add['username']   = $data['openid'];
			$add['nickname']   = $data['nickname'];
			$add['password']   = MD5(MD5(88888888));
			$add['addip']      = $add['last_ip'] = $this->request->ip();
			$add['addtime']    = $add['last_login'] = time();
			$add['avatar']   = $data['headimgurl'];
			$add['country']  = "中国";
			$add['province'] = $data['province'];
			$add['city']     = $data['city'];
			$add['oauth']    = 'qq';
			//注册的手机信息
			$add['sjmobile'] = $data['Sjmobile'];
			$add['sjsystem'] = $data['Sjsystem'];
			$add['sjplatform'] = $data['Sjplatform'];
         	$add['cid']      = $data['cid'];
         	$add['ctoken']   = $data['ctoken'];
			$res = Db::name('member')->insertGetId($add);
			if($res){
				$aes = new AES();
				$tk['id']        = $res;
				$tk['username']  = $add['username'];
				$tk['last_login'] = $add['last_login'];
				$tk['last_ip']   = $add['last_ip'];
				$tka = urlencode($aes->encrypt(json_encode($tk)));
				//操作日志
				$add_log['uid']     = $res;
				$add_log['title']   = "QQ注册/登录成功";
				$add_log['content'] = "QQ：".$add['nickname']."注册/登录成功！操作信息：IP（".$add['last_ip']."）；时间（".date('Y.m.d H:i:s')."）；";
				$add_log['addtime'] = time();
				$resa = Db::name('member_log')->insert($add_log);
				return json(['code'=>0,'msg'=>'登录成功！','token'=>$tka,'xinxi'=>0]);
			}else{
				return json(['code'=>1,'msg'=>'登录失败！']);
			}
		}
	}
/************************************************************************************/
/** 新浪登录
/***********************************************************************************/
	public function sinaLogin(){
		$data = input();
		$checkmember = Db::name('member')->field(['sn_openid','is_del','is_show','addip','addtime','sjmobile','sjsystem','sjplatform'],true)->where(['sn_openid'=>$data['id'],'is_show'=>'y','is_del'=>'n','oauth'=>'sina'])->find();
		if($checkmember){
			$aes = new AES();
			$time = time();
			$ip = $this->request->ip();
			$tk['id']        = $checkmember['id'];
			$tk['username']  = $checkmember['username'];
			$tk['last_login'] = $time;
			$tk['last_ip']   = $ip;
			$tka = urlencode($aes->encrypt(json_encode($tk)));
			//操作日志
			$add_log['uid']     = $checkmember['id'];
			$add_log['title']   = "新浪微博登录成功";
			$add_log['content'] = "新浪微博：".$checkmember['nickname']."登录成功！操作信息：IP（".$ip."）；时间（".date('Y.m.d H:i:s')."）；";
			$add_log['addtime'] = time();
			$resa = Db::name('member_log')->insert($add_log);
			//更新登录信息
	        $uparr['last_login'] = $time;
	        $uparr['last_ip']   = $ip;
	        $uparr['sjmobile']  = $data['Sjmobile'];
	        $uparr['sjsystem']  = $data['Sjsystem'];
	        $uparr['sjplatform'] = $data['Sjplatform'];
	        $uparr['cid']        = $data['cid'];
	        $uparr['ctoken']     = $data['ctoken'];
	        $resb = DB::name('member')->where(['id'=>$checkmember['id']])->update($uparr);
			if(!$checkmember['nickname'] || !$checkmember['address']){
				$xinxi = 0;
			}else{
				$xinxi = 1;
			}
			return json(['code'=>0,'msg'=>'登录成功！','token'=>$tka,'xinxi'=>$xinxi]);
		}else{
			$add['sn_openid']  = $data['id'];
			$add['username'] = $data['idstr'];
			$add['nickname'] = $data['nickName'];
			$add['password'] = MD5(MD5(88888888));
			$add['addip']   = $add['last_ip'] = $this->request->ip();
			$add['addtime'] = $add['last_login'] = time();
			$add['avatar']   = $data['avatar_hd'];
			$add['country']  = "中国";
			$add['province'] = "";
			$add['city']     = "";
			$add['oauth']    = 'sina';
			//注册的手机信息
			$add['sjmobile'] = $data['Sjmobile'];
			$add['sjsystem'] = $data['Sjsystem'];
			$add['sjplatform'] = $data['Sjplatform'];
         	$add['cid']      = $data['cid'];
         	$add['ctoken']   = $data['ctoken'];
			$res = Db::name('member')->insertGetId($add);
			if($res){
				$aes = new AES();
				$tk['id']        = $res;
				$tk['username']  = $add['username'];
				$tk['last_login'] = $add['last_login'];
				$tk['last_ip']   = $add['last_ip'];
				$tka = urlencode($aes->encrypt(json_encode($tk)));
				//操作日志
				$add_log['uid']     = $res;
				$add_log['title']   = "新浪微博注册/登录成功";
				$add_log['content'] = "新浪微博：".$add['nickname']."注册/登录成功！操作信息：IP（".$add['last_ip']."）；时间（".date('Y.m.d H:i:s')."）；";
				$add_log['addtime'] = time();
				$resa = Db::name('member_log')->insert($add_log);
				return json(['code'=>0,'msg'=>'登录成功！','token'=>$tka,'xinxi'=>0]);
			}else{
				return json(['code'=>1,'msg'=>'登录失败！']);
			}
		}
	}
/************************************************************************************/
/** 账号密码注册
/***********************************************************************************/
   	public function register(){
    	$data = input();
		$validate = new \think\Validate();
		$rule =   [
            'sl_uname'  => 'require|mobile',
            'sl_ucode'  => 'require',
            'sl_upwd'  	=> 'require',
            'token'     => 'require'
        ];
        $message  =   [
            'sl_uname.require' => '请填写手机号码',
            'sl_uname.mobile'  => '请填写正确的手机号码',
            'sl_ucode.require' => '请填写手机验证码',
            'sl_upwd.require'  => '请填写密码',
            'token.require'    => '参数错误'
        ];
        $validate->message($message);
        //验证部分数据合法性
        if (!$validate->check($data,$rule)) {
            return json(['code'=>2,'msg'=>$validate->getError()]);die;
        }
		$aes = new AES();
		$token = $aes->decrypt(urldecode($data['token']));
		if(empty($token)){
			return json(['code'=>1,'msg'=>'参数错误']);
		}else{
			$tkarr = json_decode($token,1);
			if($tkarr['phone']==$data['sl_uname'] && $tkarr['Sjmobile'] == $data['Sjmobile'] && $tkarr['Sjsystem'] == $data['Sjsystem'] && $tkarr['Sjplatform'] == $data['Sjplatform'] && $tkarr['dotype'] == "register"){
				if($tkarr['code']==$data['sl_ucode']){
					$add['username'] = $add['phone'] = $data['sl_uname'];
					$add['nickname'] = '用户'.substr($data['sl_uname'],-4);
					$add['password'] = MD5(MD5($data['sl_upwd']));
					$add['addip']   = $add['last_ip'] = $this->request->ip();
					$add['addtime'] = $add['last_login'] = time();
					//注册的手机信息
					$add['sjmobile'] = $data['Sjmobile'];
					$add['sjsystem'] = $data['Sjsystem'];
					$add['sjplatform'] = $data['Sjplatform'];
               		$add['cid']      = $data['cid'];
               		$add['ctoken']   = $data['ctoken'];
					$check = Db::name('member')->field('id')->where(['username'=>$data['sl_uname']])->find();
					if($check){
						return json(['code'=>1,'msg'=>'该手机号码已被注册，请直接登录或者换一个号码']);
					}else{
						$res = Db::name('member')->insertGetId($add);
						if($res){
							$tk['id']        = $res;
							$tk['username']  = $add['username'];
							$tk['last_ip']   = $add['last_ip'];
							$tk['last_login'] = $add['last_login'];
							$tka = urlencode($aes->encrypt(json_encode($tk)));
							//操作日志
							$add_log['uid']     = $res;
							$add_log['title']   = "手机号码注册/登录成功";
							$add_log['content'] = "手机号码：".$add['username']."注册并登录成功！操作信息：IP（".$add['last_ip']."）；时间（".date('Y.m.d H:i:s')."）；";
							$add_log['addtime'] = time();
							$resa = Db::name('member_log')->insert($add_log);
							return json(['code'=>0,'msg'=>'注册成功！','token'=>$tka,"xinxi"=>'0']);
						}else{
							return json(['code'=>1,'msg'=>'数据错误']);
						}
					}
				}else{
					return json(['code'=>1,'msg'=>'手机验证码错误']);
				}
			}else{
				return json(['code'=>1,'msg'=>'手机号码已被篡改']);
			}
		}
    }
/************************************************************************************/
/** 获取手机验证码
/***********************************************************************************/
    public function getPhoneCode(){
    	$data = input();
		$validate = new \think\Validate();
		$rule =   [
            'phone'  => 'require|mobile'
        ];
        $message  =   [
            'phone.require' => '请填写手机号码',
            'phone.mobile'  => '请填写正确的手机号码'
        ];
        $validate->message($message);
        //验证部分数据合法性
        if (!$validate->check($data,$rule)) {
            return json(['code'=>2,'msg'=>$validate->getError()]);die;
        }
		$aes = new AES();
		$data['code'] = rand(100000,999999);
		$token  = urlencode($aes->encrypt(json_encode($data)));
		return json(['code'=>'0','msg'=>'！'.$data['code'],'token'=>$token]);
    }
}