<?php
namespace app\home\controller;
use \think\Controller;
use \think\Db;
use \think\Session;
use \think\Cookie;
use \think\Request;
use \think\AES;
class Index extends Controller{
	public function __construct(){
		parent::__construct(); //使用父类的构造方法
		//处理跨域问题
        header('Access-Control-Allow-Origin:*'); 
        header('Access-Control-Max-Age:86400'); // 允许访问的有效期
        header('Access-Control-Allow-Headers:*'); 
        header('Access-Control-Allow-Methods:OPTIONS,GET,POST,DELETE');
        //域名
       	$hostname = $this->request->root(true);
        $this->hostname = $hostname;
        // 
        $this->appid = "wx8f2005531d7a9dfa";
        $this->key   = "f4a2aeb48ec8bfba46eeb40e76f9774a";
    }
    public function index(){
    	$appid = $this->appid;
	   	$secret = $this->key;
	    $code = $this->request->param('code') ? $this->request->param('code') : '';
	    if($code==""){
    		$redirect = "https://wanyi.tanghan.cn";
    		$state = time();
	    	Session('state',$state);
	    	$url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=".$appid."&redirect_uri=".urlencode($redirect)."&response_type=code&scope=snsapi_userinfo&state=".$state."#wechat_redirect";
	    	header("location:".$url);die;
	    }else{
	    	$state = Session('state');
	    	$z_domain='https://wyh5.tanghan.cn';
	    	//$this->request->root(true)
	    	header("location:".$z_domain.'/#/pages/h5/index?code='.$code);
	    }
    }
    public function yanzheng(){
    	$data = input();
    	$code = $data['code'];
    	$urla = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid='.$this->appid.'&secret='.$this->key.'&code='.$code.'&grant_type=authorization_code';
    	$resa = $this->get_contents($urla);
    	$resa = json_decode($resa,1);
    	if(isset($resa['unionid'])){
	    	$ckeck = Db::name('member')->field('id,username,last_login,last_ip,level')->where('username',$resa['unionid'])->find();
	    	if(!$ckeck){
	    		return json(['code'=>1,'msg'=>'账号不存在']);die;
	    	}else{
	    		if($ckeck['level']=="0"){
	    			return json(['code'=>1,'msg'=>'权限不足']);die;
	    		}
	    		$aes = new AES();
				$tk['id']         = $ckeck['id'];
				$tk['username']   = $ckeck['username'];
				$tk['last_login'] = $ckeck['last_login'];
				$tk['last_ip']    = $ckeck['last_ip'];
				$tka = urlencode($aes->encrypt(json_encode($tk)));
				return json(['code'=>0,'msg'=>'登录成功','token'=>$tka]);die;
	    	}
	    }else{
	    	return json(['code'=>1,'msg'=>'参数错误']);die;
	    }
    }
    public function get_contents($url){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_URL, $url);
        $response =  curl_exec($ch);
        curl_close($ch);
        //-------请求为空
        if(empty($response)){
            exit("50001");
        }
        // var_dump($response);die;
        return $response;
    }
    public function hello($name = 'ThinkPHP5')
    {
        return 'hello,' . $name;
    }
}
