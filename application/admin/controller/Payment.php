<?php
namespace app\admin\controller;
use \think\Controller;
use app\admin\controller\Base;
use \think\Db;
use \think\Cookie;
use \think\Session;
use \think\Request;
class Payment extends Base{
	public function __construct(){
		parent::__construct(); //使用父类的构造方法
	}

	public function index(){
		if($this->request->isPost()){
			$post = $this->request->post();
			$wechat = $post['wechat'];
			if(isset($wechat['is_show']) && $wechat['is_show']=="y"){
				$validate = new \think\Validate();
	            $rule =   [
	            	'appid'  => 'require',
	                'mch_id'  => 'require',
	                'apikey'  => 'require'
	            ];
	            $message  =   [
	            	'appid.require'   => '微信appid不能为空',
	                'mch_id.require' => '微信支付商户号不能为空',
	                'mch_id.require' => '微信支付密钥不能为空'
	            ];
	            $validate->message($message);
	            //验证部分数据合法性
	            if (!$validate->check($wechat,$rule)) {
	                return json(['code'=>2,'msg'=>$validate->getError(),'returnData'=>'']);die;
	            }
	            $res = Db::name('payment_api')->where('id',1)->update($wechat);
			}else{
				$res = Db::name('payment_api')->where('id',1)->update(['is_show'=>'n']);
			}
			$alipay = $post['alipay'];
			if(isset($alipay['is_show']) && $alipay['is_show']=="y"){
				$validatea = new \think\Validate();
	            $rulea =   [
	            	'appid'  => 'require',
	                'private_key'  => 'require',
	                'public_key'  => 'require'
	            ];
	            $messagea  =   [
	            	'appid.require'   => '支付宝appid不能为空',
	                'private_key.require' => '支付宝公钥不能为空',
	                'public_key.require' => '支付宝应用私钥不能为空'
	            ];
	            $validatea->message($messagea);
	            //验证部分数据合法性
	            if (!$validatea->check($alipay,$rulea)) {
	                return json(['code'=>2,'msg'=>$validatea->getError(),'returnData'=>'']);die;
	            }
	            $res = Db::name('payment_api')->where('id',2)->update($alipay);
			}else{
				$res = Db::name('payment_api')->where('id',2)->update(['is_show'=>'n']);
			}
			if(isset($post['yue'])){
				$res = Db::name('payment_api')->where('id',3)->update(['is_show'=>'y']);
			}else{
				$res = Db::name('payment_api')->where('id',3)->update(['is_show'=>'n']);
			}
			$jifen = $post['jifen'];
			if(isset($jifen['is_show']) && $jifen['is_show']=="y"){
				$res = Db::name('payment_api')->where('id',4)->update($jifen);
			}else{
				$res = Db::name('payment_api')->where('id',4)->update(['is_show'=>'n']);
			}
			return json(['code'=>0,'msg'=>'保存成功','returnData'=>'']);die;
		}else{
			$wechat = Db::name('payment_api')->field('id,title,appid,mch_id,apikey,cert,key_f,root_f,is_show')->where('id',1)->find();
			$this->assign('wechat',$wechat);
			$alipay = Db::name('payment_api')->field('id,title,uname,appid,rsa_type,public_key,private_key,is_show')->where('id',2)->find();
			$this->assign('alipay',$alipay);
			$yue = Db::name('payment_api')->field('id,title,is_show')->where('id',3)->find();
			$this->assign('yue',$yue);
			$jifen = Db::name('payment_api')->field('id,title,bili,man,startman,endman,is_show')->where('id',4)->find();
			$this->assign('jifen',$jifen);
			return $this->fetch();
		}
	}
	public function publish(){
		if($this->request->isPost()){
			$post = $this->request->post();
			$wechat = $post['wechat'];
			if(isset($wechat['is_show']) && $wechat['is_show']=="y"){
				$validate = new \think\Validate();
	            $rule =   [
	            	'appid'  => 'require',
	                'mch_id'  => 'require',
	                'apikey'  => 'require'
	            ];
	            $message  =   [
	            	'appid.require'   => '微信appid不能为空',
	                'mch_id.require' => '微信支付商户号不能为空',
	                'mch_id.require' => '微信支付密钥不能为空'
	            ];
	            $validate->message($message);
	            //验证部分数据合法性
	            if (!$validate->check($wechat,$rule)) {
	                return json(['code'=>2,'msg'=>$validate->getError(),'returnData'=>'']);die;
	            }
	            $res = Db::name('payment_api')->where('id',1)->update($wechat);
	            if($res){
	            	$env = $this->request->env();
	            	$SSLCERT_PATH = $env['ROOT_PATH'].'public/'.$wechat['cert'];
	            	$SSLKEY_PATH  = $env['ROOT_PATH'].'public/'.$wechat['key_f'];
	            	$wj = $env['ROOT_PATH'].'extend/wxpay/WxPay.Config.php';
	            	$myfile = fopen($wj, "w");
	            	//修改微信支付配置文件
	            	$txt = '<?php '."\r\n".'class WxPayConfig{ '."\r\n".'	//=======【基本信息设置】====================================='."\r\n".'	//'."\r\n".'	/**'."\r\n".'	* TODO: 修改这里配置为您自己申请的商户信息'."\r\n".'	* 微信公众号信息配置'."\r\n".'	* '."\r\n".'	* APPID：应用APPID（必须配置，开户邮件中可查看）'."\r\n".'	* '."\r\n".'	* MCHID：微信支付商户号（必须配置，开户邮件中可查看）'."\r\n".'	* '."\r\n".'	* KEY：API密钥，参考开户邮件设置（必须配置，登录商户平台自行设置）'."\r\n".'	* 设置地址：https://pay.weixin.qq.com/index.php/account/api_cert'."\r\n".'	* '."\r\n".'	*/'."\r\n".'	const APPID      = "'.$wechat['appid'].'";'."\r\n".'	const MCHID      = "'.$wechat['mch_id'].'";'."\r\n".'	const KEY        = "'.$wechat['apikey'].'";'."\r\n".'	const NOTIFY_URL = "'.$this->hostname.url('api/payment/Wxnotify').'";'."\r\n".'	//=======【证书路径设置】====================================='."\r\n".'	/**'."\r\n".'	* TODO：设置商户证书路径'."\r\n".'	* 证书路径,注意应该填写绝对路径（仅退款、撤销订单时需要，可登录商户平台下载，'."\r\n".'	* API证书下载地址：https://pay.weixin.qq.com/index.php/account/api_cert，下载之前需要安装商户操作证书）'."\r\n".'	* @var path'."\r\n".'	*/'."\r\n".'	const SSLCERT_PATH = "'.$SSLCERT_PATH.'";'."\r\n".'	const SSLKEY_PATH  = "'.$SSLKEY_PATH.'";'."\r\n".'	//=======【curl代理设置】==================================='."\r\n".'	/**'."\r\n".'	* TODO：这里设置代理机器，只有需要代理的时候才设置，不需要代理，请设置为0.0.0.0和0'."\r\n".'	* 本例程通过curl使用HTTP POST方法，此处可修改代理服务器，'."\r\n".'	* 默认CURL_PROXY_HOST=0.0.0.0和CURL_PROXY_PORT=0，此时不开启代理（如有需要才设置）'."\r\n".'	* @var unknown_type'."\r\n".'	*/'."\r\n".'	const CURL_PROXY_HOST = "0.0.0.0";//"10.152.18.220"'."\r\n".'	const CURL_PROXY_PORT = 0;//8080;'."\r\n".'	//=======【上报信息配置】==================================='."\r\n".'	/**'."\r\n".'	* TODO：接口调用上报等级，默认紧错误上报（注意：上报超时间为【1s】，上报无论成败【永不抛出异常】，'."\r\n".'	* 不会影响接口调用流程），开启上报之后，方便微信监控请求调用的质量，建议至少'."\r\n".'	* 开启错误上报。'."\r\n".'	* 上报等级，0.关闭上报; 1.仅错误出错上报; 2.全量上报'."\r\n".'	* @var int'."\r\n".'	*/'."\r\n".'	const REPORT_LEVENL = 1;'."\r\n".'}';
    				fwrite($myfile, $txt);
            		fclose($myfile);
	            }
			}else{
				$res = Db::name('payment_api')->where('id',1)->update(['is_show'=>'n']);
			}
			$alipay = $post['alipay'];
			if(isset($alipay['is_show']) && $alipay['is_show']=="y"){
				$validatea = new \think\Validate();
	            $rulea =   [
	            	'appid'  => 'require',
	                'private_key'  => 'require',
	                'public_key'  => 'require'
	            ];
	            $messagea  =   [
	            	'appid.require'   => '支付宝appid不能为空',
	                'private_key.require' => '支付宝公钥不能为空',
	                'public_key.require' => '支付宝应用私钥不能为空'
	            ];
	            $validatea->message($messagea);
	            //验证部分数据合法性
	            if (!$validatea->check($alipay,$rulea)) {
	                return json(['code'=>2,'msg'=>$validatea->getError(),'returnData'=>'']);die;
	            }
	            $res = Db::name('payment_api')->where('id',2)->update($alipay);
			}else{
				$res = Db::name('payment_api')->where('id',2)->update(['is_show'=>'n']);
			}
			if(isset($post['yue'])){
				$res = Db::name('payment_api')->where('id',3)->update(['is_show'=>'y']);
			}else{
				$res = Db::name('payment_api')->where('id',3)->update(['is_show'=>'n']);
			}
			$jifen = $post['jifen'];
			if(isset($jifen['is_show']) && $jifen['is_show']=="y"){
				$res = Db::name('payment_api')->where('id',4)->update($jifen);
			}else{
				$res = Db::name('payment_api')->where('id',4)->update(['is_show'=>'n']);
			}
			return json(['code'=>0,'msg'=>'保存成功','returnData'=>'']);die;
		}
	}
	public function uploada($name='image',$size=1024*1024*10,$ext='cert,key,pem,txt,zip',$save_dir='./',$rule='date',$module='admin',$use='admin'){
		$data = input();
		$name = isset($data['name']) ? $data['name'] : $name; //提交的文件name
		$size = isset($data['size']) ? $data['size'] : $size; //限制上传的文件大小
		$ext  = isset($data['ext'])  ? $data['ext']  : $ext;  //文件格式
		$save_dir = isset($data['save_dir']) ? $data['save_dir'] : $save_dir; //保存路径
		$rule = isset($data['rule']) ? $data['rule'] : $rule; //生成的文件命名方式，默认支持：date根据日期和微秒数生成，md5对文件使用md5_file散列生成,sha1对文件使用sha1_file散列生成
		$module = isset($data['module']) ? $data['module'] : $module;
		$use = isset($data['use']) ? $data['use'] : $use;
	    if($this->request->file('file')){
	    	$file = $this->request->file('file');
		    $info = $file->validate(['size'=>$size,'ext'=>$ext])->rule($rule)->move($save_dir);
		    if($info){
		    	$url = $info->getSaveName();
		    	$arr['url'] = $save_dir.''.$url;
	            $arr['url']  = ltrim($arr['url'],'.');
	            $arr['url']  = str_replace("\\","/",$arr['url']);
		        return json(['code'=>0,'msg'=>'上传成功','returnData'=>$arr]);
		    }else{
		        return json(['code'=>1,'msg'=>'上传失败','returnData'=>$file->getError()]);
	    	}
	    }else{
	    	return json(['code'=>2,'msg'=>'请选择上传的图片','returnData'=>'']);
	    }
	}
}