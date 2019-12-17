<?php
namespace app\admin\controller;
use \think\Controller;
use \think\Db;
use \think\Session;
use \think\Cookie;
use \think\Request;
use think\Console;
use \think\AES;
use think\captcha\Captcha;
class Base extends Controller{
	public function __construct(){
		parent::__construct(); //使用父类的构造方法
        //域名
       	$hostname = $this->request->root(true);
        $this->hostname = $hostname;
        $this->assign('hostname',$hostname);

        $admininfo = $this->CheckLogin();
		$this->admininfo = $admininfo;
		$this->assign('admininfo',$admininfo);
        //网站配置
        $webconfig = Db::name('webconfig')->where('id',1)->find();
        $this->assign('webconfig',$webconfig);
        //检查权限
        $this->CheckQuanxian();
    }
/******************************************************/
/** 检查权限
/******************************************************/
	public function CheckQuanxian(){
		$root = $this->request;
		$module     = $root->module();
		$controller = $root->controller();
		$action     = $root->action();
		$menu = Db::name('admin_menus')->field('id')->where(['mm'=>$module,'cc'=>$controller,'aa'=>$action])->find();
		if($menu){
			$admininfo = $this->admininfo;
			if($admininfo['id']=='1'){ //顶级超级管理员
	            
	        }else if($admininfo['pid']=='1'){ //超级管理员
	            
	        }else if($admininfo['pid']>'1'){ //平台管理员
	        	$admincate = Db::name('admin_cate')->field('quanxian')->where('id',$admininfo['pid'])->find();
	        	$quanxian = explode(',',$admincate['quanxian']);
	        	if(!in_array($menu['id'], $quanxian)){
	        		return json(['code'=>9,'msg'=>'对不起，您没有权限进行该操作','returnData'=>'']);die;
	        	}
	        }else if($admininfo['pid']=='0' && $admininfo['shop_id']>0){ //店铺管理员 40店铺
	            
	        }else if($admininfo['pid']=='0' && $admininfo['stores_id']>0){ //门店店员及店长 30门店
	            
	        }else if($admininfo['pid']=='0' && $admininfo['school_id']>0){ //学校管理员 66学校
	            
	        }else if($admininfo['pid']=='0' && $admininfo['hospital_id']>0){ //医院管理员 50医院
	            
	        }
		}
	}
	public function xiazai(){
		$path = $this->request->param('file');
		$path = ".".$path;
    	$file = fopen($path,"r");
		header("Content-Type: application/octet-stream");
		header("Accept-Ranges: bytes");
		header("Accept-Length: ".filesize($path));
		header("Content-Disposition: attachment; filename=".$path);
		echo fread($file,filesize($path));
		fclose($file);
	}
/******************************************************/
/** 检查登录状态
/******************************************************/
	public function CheckLogin(){
		$request = $this->request;
		$array = ['verify'];
		if($request->module() == 'admin' && $request->controller() == 'Base' && in_array($request->action(),$array)){
			return true;
		}
		$session = new Session();
		$cookie = new Cookie();
		$token = $session->get('ygy_token');
		if(empty($token)){
			$token = $cookie->get('ygy_token');
			if(empty($token)){
				$url = url('admin/login/index');
				header("location:".$url);die;
			}
		}
		$aes = new AES();
		$tk = urldecode($token);
		$tka = $aes->decrypt($tk);
		if(empty($tka)){
			$session->delete('ygy_token');
			$cookie->delete('ygy_token');
			$url = url('admin/login/index');
			header("location:".$url);die;
		}else{
			$token = json_decode($tka,1);
			$admininfo = Db::name('admin')->field(['id','pid','pid_type','shop_id','school_id','school_type','hospital_id','hospital_type','shop_type','stores_id','stores_type','username','nickname','head_pic','last_login','last_ip','last_round','class_id','schools'])->where(['id'=>$token['uid'],'is_del'=>'n','is_show'=>'y'])->find();
			if(empty($admininfo)){
				$session->delete('ygy_token');
				$cookie->delete('ygy_token');
				$url = url('admin/login/index');
				header("location:".$url);die;
			}else{
				if($admininfo['last_login'] == $token['last_login'] && $admininfo['last_ip'] == $token['last_ip'] && $admininfo['last_round'] == $token['last_round']){
					return $admininfo;
				}else{
					$session->delete('ygy_token');
					$cookie->delete('ygy_token');
					$url = url('admin/login/index');
					header("location:".$url);die;
				}
			}
		}
	}

/******************************************************/
/** 文件上传
/******************************************************/
	//单个上传
	public function upload($name='image',$size=1024*1024*10,$ext='jpg,png,gif,jpeg',$save_dir='./uploads',$rule='date',$module='admin',$use='admin'){
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
		    	$arr['url'] = $save_dir.'/'.$url;
		    	$admininfo = $this->admininfo;
	       		$data = [];
	            $uparr['module']      = $module;
	            $uparr['title']       = $info->getInfo('name');
	            $uparr['filename']    = $info->getFilename();//文件名
	            $uparr['filepath']    = ltrim($arr['url'],'.');//文件路径
	            $uparr['fileext']     = $info->getExtension();//文件后缀
	            $uparr['filesize']    = $info->getSize();//文件大小
	            $uparr['create_time'] = time();//时间
	            $uparr['uploadip']    = $this->request->ip();//IP
	            $uparr['u_id']        = $admininfo['id'];

	            $uparr['use']     = $this->request->param('use') ? $this->request->param('use') : $use;//用处
	            $uparr['u_title'] = $this->request->param('utitle') ? $this->request->param('utitle') : '未知';
	            if(in_array($uparr['fileext'],['jpg','png','gif','jpeg'])){
		            $nimg = str_replace("\\","/",$arr['url']);
		            $imgas = explode('.',$nimg);
		            $image = \think\Image::open($nimg);
					// 按照原图的比例生成一个最大为150*150的缩略图并保存为thumb.png
					$image->thumb(240,160)->save('.'.$imgas[1].'_240x160.'.$imgas[2]);
					$image2 = \think\Image::open($nimg);
					$image2->thumb(480,320)->save('.'.$imgas[1].'_480x320.'.$imgas[2]);
					$uparr['filepath']         = $nimg;
					$uparr['filepath_240x160'] = $imgas[1].'_240x160.'.$imgas[2];
					$uparr['filepath_480x320'] = $imgas[1].'_480x320.'.$imgas[2];
				}
	            $arr['id']   = Db::name('attachment')->insertGetId($uparr);
	            $arr['url']  = ltrim($arr['url'],'.');

		        return json(['code'=>0,'msg'=>'上传成功','returnData'=>$arr]);
		    }else{
		        return json(['code'=>1,'msg'=>'上传失败','returnData'=>$file->getError()]);
	    	}
	    }else{
	    	return json(['code'=>2,'msg'=>'请选择上传的图片','returnData'=>'']);
	    }
	}
	//多个上传
	public function uploads($name='image',$size=1024*10,$ext='jpg,png,gif,jpeg',$save_dir='../uploads',$rule='date'){
		$data = input();
		$name = isset($data['name']) ? $data['name'] : $name; //提交的文件name
		$size = isset($data['size']) ? $data['size'] : $size; //限制上传的文件大小
		$ext  = isset($data['ext'])  ? $data['ext']  : $ext;  //文件格式
		$save_dir = isset($data['save_dir']) ? $data['save_dir'] : $save_dir; //保存路径
		$rule = isset($data['rule']) ? $data['rule'] : $rule; //生成的文件命名方式，默认支持：date根据日期和微秒数生成，md5对文件使用md5_file散列生成,sha1对文件使用sha1_file散列生成
	    $files = $this->request()->file($name);
	    $returnData = [];
	    foreach($files as $k=>$file){
		    $info = $file->validate(['size'=>$size,'ext'=>$ext])->rule($rule)->move($save_dir);
		    if($info){
		        $returnData[] = array('code'=>0,'msg'=>'上传成功','returnData'=>$info);
		    }else{
		        $returnData[] = array('code'=>1,'msg'=>'上传失败','returnData'=>$file->getError());
		    }
		}
	}

/******************************************************/
/** 验证码
/******************************************************/
	//生成图片验证码
	public function verify(){
        $captcha = new Captcha();
        $captcha->codeSet  = '0123456789ABCDEFGHJKLMNPQRSTUVWXYZ';  //设置验证码字符
        $captcha->fontSize = 30; //	验证码字体大小(px)
		$captcha->length   = 4;  //验证码位数
		$captcha->useCurve = false; //	是否画混淆曲线
		$captcha->useNoise = true; //是否添加杂点
		$captcha->imageW   = 0; //验证码图片宽度，设置为0为自动计算
		$captcha->imageH   = 0; //验证码图片高度，设置为0为自动计算
		// $captcha->bg       = [0,0,0,0.5]; //背景颜色
        return $captcha->entry();    
    }
    //校验图片验证码
    public function CheckVerify($code){
    	$captcha = new Captcha();
		if( !$captcha->check($code)){
			return fase;
		}
		return true;
    }
/******************************************************/
/** 获取城市
/******************************************************/
    public function region(){
    	if($this->request->isPost()){
    		$post = $this->request->post();
    		$id = isset($post['id']) ? $post['id'] : '1';
    		$list = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>$id])->order('region_order asc,region_id asc')->select();
    		if($list){
    			return json(['code'=>0,'returnData'=>$list]);
    		}else{
    			return json(['code'=>1,'returnData'=>'']);
    		}
    	}
    }
/******************************************************/
/** 获取班级
/******************************************************/
    public function Ckclass(){
    	if($this->request->isPost()){
    		$post = $this->request->post();
    		$id = isset($post['id']) ? $post['id'] : '1';
    		$list = Db::name('school_class')->field('id,title')->where(['school_id'=>$id])->order('orders asc,addtime desc')->select();
    		if($list){
    			return json(['code'=>0,'returnData'=>$list]);
    		}else{
    			return json(['code'=>1,'returnData'=>'']);
    		}
    	}
    }
/******************************************************/
/** 获取店铺
/******************************************************/
    public function ckShop(){
    	if($this->request->isPost()){
    		$post = $this->request->post();
    		$id = isset($post['id']) ? $post['id'] : '1';
    		$list = Db::name('shop')->field('id,title,logo,description')->where(['id'=>$id])->order('orders asc,addtime desc')->find();
    		if($list){
    			$list['img'] = getImage($list['logo']);
    			return json(['code'=>0,'returnData'=>$list]);
    		}else{
    			return json(['code'=>1,'returnData'=>'']);
    		}
    	}
    }
/******************************************************/
/** 选择城市
/******************************************************/
    public function selectregion(){
    	$sheng = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>1])->order('region_order asc,region_id asc')->select();
    	foreach($sheng as $k=>$v){
    		$sheng[$k]['shi'] = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>$v['region_id']])->order('region_order asc,region_id asc')->select();
    	}
    	$datas = [];
    	foreach($sheng as $k=>$v){
    		$datasa = [];
    		foreach($v['shi'] as $ka=>$va){
    			$datasa[$ka]['name'] = $va['region_name'];
    			$datasa[$ka]['id']   = $va['region_id'];
    		}
    		$datas[$k]['children'] = $datasa;
    		$datas[$k]['name']     = $v['region_name'];
    		$datas[$k]['id']       = $v['region_id'];
    	}
    	$this->assign('datas',$datas);
    	return $this->fetch();
    }

    public function http_posta($api,$data){
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $api);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }
    public function http_request($url, $data = null){
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        if (!empty($data)){
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }
}