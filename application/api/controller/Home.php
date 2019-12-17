<?php
namespace app\api\controller;
use \think\Controller;
use app\api\controller\Base;
use \think\Db;
use \think\Session;
use \think\Cookie;
use \think\Request;
use \think\AES;
class Home extends Base{
	public function __construct(){
		parent::__construct(); //使用父类的构造方法
    }
    public function checkBanben(){
    	$one = Db::name('webconfig')->field(['id','banben'])->where('id','1')->find();
		return json(['code'=>0,'banben'=>$one['banben']]);
    }
    public function updateapp(){
    	$path = "./apps/updateapp.wgt";
    	$file = fopen($path,"r");
		header("Content-Type: application/octet-stream");
		header("Accept-Ranges: bytes");
		header("Accept-Length: ".filesize($path));
		header("Content-Disposition: attachment; filename=updateapp.wgt");
		echo fread($file,filesize($path));
		fclose($file);
    }
    public function downloadapp(){
    	$path = "./apps/shili.apk";
    	$file = fopen($path,"r");
		header("Content-Type: application/octet-stream");
		header("Accept-Ranges: bytes");
		header("Accept-Length: ".filesize($path));
		header("Content-Disposition: attachment; filename=shili.apk");
		echo fread($file,filesize($path));
		fclose($file);
    }
/********************************************************************************/
/** 首页信息
/********************************************************************************/
    public function index(){
    	$banner = Db::name('banner')->field(['id','title','thumb','url'])->where(['pid'=>'1','is_del'=>'n','is_show'=>'y'])->order('orders asc,updatetime desc')->select();
		foreach($banner as $k=>$v){
			$banner[$k]['thumb'] = $this->hostname.getImage($v['thumb']);
		}
		$slcp = DB::name('banner')->field(['id','title','thumb'])->where(['id'=>'4','is_del'=>'n','is_show'=>'y'])->find();
		if($slcp){
			$slcp['thumb'] = $this->hostname.getImage($slcp['thumb']);
		}
		$xiaqiyugao = DB::name('banner')->field(['id','title','thumb'])->where(['id'=>'3','is_del'=>'n','is_show'=>'y'])->find();
		if($xiaqiyugao){
			$xiaqiyugao['thumb'] = $this->hostname.getImage($xiaqiyugao['thumb']);
		}
		$yanghu = DB::name('banner')->field(['id','title','thumb'])->where(['id'=>'5','is_del'=>'n','is_show'=>'y'])->find();
		if($yanghu){
			$yanghu['thumb'] = $this->hostname.getImage($yanghu['thumb']);
		}

		$kuaixun = Db::name('news')->field(['id','title'])->where(['is_show'=>'y','is_del'=>'n'])->order('updatetime desc,addtime desc')->limit(0,8)->select();
		$news = Db::name('news')->field(['id','title','description','thumb'])->where(['is_show'=>'y','is_del'=>'n'])->order('updatetime desc,addtime desc')->limit(0,4)->select();
		foreach ($news as $k => $v) {
			$news[$k]['thumb'] = $this->hostname.getImage($v['thumb']);
			if(!$v['description']){
				$news[$k]['description'] = "";
			}
		}
		$jiangzuo = Db::name('shili_video')->field(['id','title','img'])->where(['is_del'=>'n','is_show'=>'y'])->limit(0,8)->order('addtime desc')->select();
		foreach($jiangzuo as $k=>$v){
			$jiangzuo[$k]['img'] = $this->hostname.getImage($v['img']);
		}
		$shequ = Db::name('shequ')->field(['is_show'],true)->where(['is_show'=>'y','pic'=>['neq',""]])->order('looknums desc,addtime desc')->limit(0,4)->select();
		foreach($shequ as $k=>$v){
			if($v['uid']=="0"){
				$shequ[$k]['author']  = getAdmin($v['aid']);
			}else{
				$shequ[$k]['author']  = getMember($v['uid']);
			}
			$shequ[$k]['pinglunums'] = Db::name('shequ_pinglun')->field('id')->where(['pid'=>$v['id'],'pl_id'=>'0','is_del'=>'n'])->count();
			$shequ[$k]['addtime'] = Tiia($v['addtime']);
			if($v['pic']){
				$piclist = explode(",",$v['pic']);
				foreach($piclist as $key=>$val){
					$piclist[$key] = $this->hostname.$val;
				}
				$shequ[$k]['pic']     = $piclist[0];
			}else{
				$shequ[$k]['pic']     = "";
			}
		}
		$one['banner']     = $banner;
		$one['xiaqiyugao'] = $xiaqiyugao;
		$one['slcp']       = $slcp;
		$one['yanghu']     = $yanghu;
		$one['news']       = $news;
		$one['kuaixun']    = $kuaixun;
		$one['jiangzuo']   = $jiangzuo;
		$one['shequ']      = $shequ;
		$one['zz']         = date("Y-m-d H:i:s W",time());
		return json(['code'=>0,'message'=>"SUCCESS",'returnData'=>$one]);
    }
    public function getLocation(){
    	$post = input();
		$latitude  = $post['latitude'];
		$longitude = $post['longitude'];
		$url = 'https://apis.map.qq.com/ws/geocoder/v1/?location='.$latitude.','.$longitude.'&key=KLQBZ-MRPCX-3LX4C-7ZCSV-XQD55-ZKF44&get_poi=1';
		$res = $this->get_contents($url);
		$json = json_decode($res,1);
		if($json['status']=="0"){
			return json($json['result']);
		}
    }
/********************************************************************************/
/** 养护操信息
/********************************************************************************/
    public function getHuyanJifen(){
   		$data = input();
   		$token = $this->CheckToken($data['token']);

   		$guanggao = Db::name('banner')->where(['id'=>'7'])->find();
	    if($guanggao){
	      	$guanggao['thumb'] = $this->hostname.getImage($guanggao['thumb']);
	    }
	    $w = date('w');
	    $shiping = DB::name('shili_yanghucao')->field(['id','title','video','img'])->where(['xq'=>$w])->find();
	    if($shiping){
	      	$shiping['img'] =  $this->hostname.getImage($shiping['img']);
	    }

   		if($token['code']=='0'){
   			$list = Db::name('member_jifen')->field(['id','jifen'])->where(['pid'=>'2','uid'=>$token['myinfo']['id'],'types'=>'1'])->select();
   			$jifen = [];
   			foreach($list as $k=>$v){
   				$jifen[] = $v['jifen'];
   			}
   			$one['jifen'] = array_sum($jifen);
   			$one['cishu'] = count($list);
   			return json(['code'=>0,'one'=>$one,'guanggao'=>$guanggao,'shiping'=>$shiping]);
   		}else{
   			$token['one']     = $one;
   			$token['shiping'] = $shiping;
   			return json($token);
   		}
    }
    public function getYancao(){
    	$data = input();
    	$shiping = DB::name('shili_yanghucao')->field(['id','title','video','img'])->where(['id'=>$data['id']])->find();
	    if($shiping){
	      	$shiping['img']   =  $this->hostname.getImage($shiping['img']);
	      	$shiping['video'] =  str_replace("/uploads",$this->hostname."/uploads",$shiping['video']);
	    }
	    return json(['code'=>0,'shiping'=>$shiping]);
    }
    //看眼保健操积分
    public function AddMyYancao(){
    	$data = input();
    	$token = $this->CheckToken($data['token']);
    	if($token['code']=='0'){
    		$jf_add['uid']     = $token['myinfo']['id'];
    		$jf_add['addtime'] = time();
    		$jf_add['types']   = '1';
    		$jf_add['jifen']   = $data['jifen'];
    		$jf_add['title']   = "每日护眼操积分";
    		$jf_add['description'] = date("Y年m月d日",time())."每日护眼操获取积分 + ".$data['jifen'];
    		$jf_add['day']     = date('Ymd',time());
    		$jf_add['pid']     = "2";
    		$cks = Db::name('member_jifen')->field('id')->where(['uid'=>$token['myinfo']['id'],'pid'=>'2','types'=>'1','day'=>$jf_add['day']])->find();
    		if(!$cks){
    			$raa = Db::name('member_jifen')->insert($jf_add);
    			if($raa){
    				return json(['code'=>0,'msg'=>'您已获得'.$jf_add['jifen'].'积分']);
    			}else{
    				return json(['code'=>12,'msg'=>'操作失败']);
    			}
    		}else{
    			return json(['code'=>12,'msg'=>'操作失败']);
    		}
    	}else{
    		return json($token);
    	}
    }
/********************************************************************************/
/** 讲座信息
/********************************************************************************/
    public function getJiangzuo(){
      	$limit = 10;
      	$list  = Db::name('shili_video')->field(['id','title','img'])->where(['is_del'=>'n','is_show'=>'y'])->limit(0,$limit)->order('addtime desc')->select();
      	foreach($list as $k=>$v){
         	$list[$k]['img'] = $this->hostname.getImage($v['img']);
      	}
      	$yugao = Db::name('shili_video')->field(['id','title','zhujiang','img','starttime'])->where(['id'=>'1'])->find();
      	if($yugao){
      		$yugao['img'] = $this->hostname.getImage($yugao['img']);
      		$yugao['starttime'] = date("Y-m-d H:i:s",$yugao['starttime']);
      	}
      	$guanggao = Db::name('banner')->where(['id'=>'6'])->find();
      	if($guanggao){
      		$guanggao['thumb'] = $this->hostname.getImage($guanggao['thumb']);
      	}
      	return json(['code'=>0,'list'=>$list,'yugao'=>$yugao,'guanggao'=>$guanggao]);
   	}
   	public function getJiangzuoDetail(){
   		$data = input();
   		$one = Db::name('shili_video')->where(['id'=>$data['sl_id']])->find();
   		$one['video'] = str_replace("/uploads",$this->hostname."/uploads", $one['video']);
      	$limit = 10;
      	$list  = Db::name('shili_video')->field(['id','title','img'])->where('id','neq',$data['sl_id'])->where(['is_del'=>'n','is_show'=>'y'])->limit(0,$limit)->order('addtime desc')->select();
      	foreach($list as $k=>$v){
         	$list[$k]['img'] = $this->hostname.getImage($v['img']);
      	}
      	$token = isset($data['token']) ? $data['token'] : '';
      	$is_shoucang = 0;
      	if($token!=""){
      		$token = $this->CheckToken($data['token']);
      		if($token['code']=='0'){
      			$checkshouchang = Db::name('member_collection')->where(['uid'=>$token['myinfo']['id'],'jz_id'=>$data['sl_id'],'is_del'=>'n'])->find();
      			if($checkshouchang){
      				$is_shoucang = 1;
      			}
      		}
      	}
   		return json(['code'=>0,'one'=>$one,'list'=>$list,'is_shoucang'=>$is_shoucang]);
   	}
   	public function getJiangzuoDetailPwd(){
   		$data = input();
   		$one = Db::name('webconfig')->field('videopwd')->where('id',1)->find();
   		if($one['videopwd']==$data['pwd']){
   			return json(['code'=>0,'msg'=>'密码正确']);
   		}else{
   			return json(['code'=>1,'msg'=>'密码错误']);
   		}
   	}
/********************************************************************************/
/** 社区信息
/********************************************************************************/ 
	public function getShequ(){
		$hotlist = Db::name('shequ')->field(['is_show'],true)->where(['is_show'=>'y','pic'=>['neq',""]])->order('looknums desc,addtime desc')->limit(0,4)->select();
		foreach($hotlist as $k=>$v){
			if($v['uid']=="0"){
				$hotlist[$k]['author']  = getAdmin($v['aid']);
			}else{
				$hotlist[$k]['author']  = getMember($v['uid']);
			}
			$hotlist[$k]['pinglunums'] = Db::name('shequ_pinglun')->field('id')->where(['pid'=>$v['id'],'pl_id'=>'0','is_del'=>'n'])->count();;
			$hotlist[$k]['addtime'] = Tiia($v['addtime']);
			if($v['pic']){
				$piclist = explode(",",$v['pic']);
				foreach($piclist as $key=>$val){
					$piclist[$key] = $this->hostname.$val;
				}
				$hotlist[$k]['pic']     = $piclist[0];
			}else{
				$hotlist[$k]['pic']     = "";
			}
		}

		$newlist = Db::name('shequ')->field(['is_show'],true)->where(['is_show'=>'y','pic'=>['neq',""]])->order('addtime desc')->limit(0,4)->select();
		foreach($newlist as $k=>$v){
			if($v['uid']=="0"){
				$newlist[$k]['author']  = getAdmin($v['aid']);
			}else{
				$newlist[$k]['author']  = getMember($v['uid']);
			}
			$newlist[$k]['pinglunums'] = Db::name('shequ_pinglun')->field('id')->where(['pid'=>$v['id'],'pl_id'=>'0','is_del'=>'n'])->count();
			$newlist[$k]['addtime'] = Tiia($v['addtime']);
			if($v['pic']){
				$piclist = explode(",",$v['pic']);
				foreach($piclist as $key=>$val){
					$piclist[$key] = $this->hostname.$val;
				}
				$newlist[$k]['pic']     = $piclist[0];
			}else{
				$newlist[$k]['pic']     = "";
			}
		}

		$typelist = Db::name('shequ_cate')->field(['id','cate_title'])->where(['is_del'=>'n','is_show'=>'y'])->order('aorder asc,updatetime desc')->select();
		foreach($typelist  as $k=>$v){
			$child = Db::name('shequ')->field(['is_show'],true)->where(['is_show'=>'y','pid'=>$v['id']])->order('addtime desc')->limit(0,8)->select();
			foreach($child as $ke=>$va){
				if($va['uid']=="0"){
					$child[$ke]['author']  = getAdmin($va['aid']);
				}else{
					$child[$ke]['author']  = getMember($va['uid']);
				}
				$child[$ke]['pinglunums'] = Db::name('shequ_pinglun')->field('id')->where(['pid'=>$va['id'],'pl_id'=>'0','is_del'=>'n'])->count();;
				$child[$ke]['addtime'] = Tiia($va['addtime']);
				if($va['pic']){
					$piclist = explode(",",$va['pic']);
					foreach($piclist as $key=>$val){
						$piclist[$key] = $this->hostname.$val;
					}
					$child[$ke]['pic']     = $piclist[0];
				}else{
					$child[$ke]['pic']     = "";
				}
			}
			$typelist[$k]['child'] = $child;
		}

		$list = Db::name('shequ')->field(['is_show'],true)->where(['is_show'=>'y'])->order('addtime desc')->limit(0,8)->select();
		foreach($list as $k=>$v){
			if($v['uid']=="0"){
				$list[$k]['author']  = getAdmin($v['aid']);
			}else{
				$list[$k]['author']  = getMember($v['uid']);
			}
			$list[$k]['pinglunums'] = Db::name('shequ_pinglun')->field('id')->where(['pid'=>$v['id'],'pl_id'=>'0','is_del'=>'n'])->count();
			$list[$k]['addtime'] = Tiia($v['addtime']);
			if($v['pic']){
				$piclist = explode(",",$v['pic']);
				foreach($piclist as $key=>$val){
					$cc = str_replace("./","/",$val);
					$cc = str_replace("\\","/",$cc);
					$piclist[$key] = $this->hostname.$cc;
				}
				$list[$k]['pic']     = $piclist[0];
			}else{
				$list[$k]['pic']     = "";
			}
		}
		return json(['code'=>'0','hotlist'=>$hotlist,'newlist'=>$newlist,'typelist'=>$typelist,'list'=>$list]);
	}
	public function getShequdetails(){
		$data = input();
		$id = isset($data['sl_id']) ? $data['sl_id'] : '0';
		$token = isset($data['token']) ? $data['token'] : '';
		$one = Db::name('shequ')->field(['is_show','is_del'],true)->where(['id'=>$id,'is_del'=>'n','is_show'=>'y'])->find();
		if($one){
			if($one['uid']=="0"){
            	$one['author']  = getAdmin($one['aid']);
         	}else{
            	$one['author']  = getMember($one['uid']);
         	}
			$piclist = explode(",",$one['pic']);
			foreach($piclist as $k=>$v){
				$piclist[$k] = $this->hostname.$v;
			}
			$one['img']       = $piclist;
			$one['author_qz'] = '发表于';
			$one['addtime'] = Tii($one['addtime']);
			$where['pid']    = $id;
			$where['is_del'] = 'n';
			$limit = 10;
			$pllist = Db::name('shequ_pinglun')->where($where)->limit($limit)->order('addtime desc')->select();
			foreach($pllist as $k=>$v){
				if($v['fabu_uid']=="0"){
					$pllist[$k]['author'] = Db::name('admin')->field(['nickname','username','head_pic'])->where(['id'=>$v['fabu_aid']])->find();
					if($pllist[$k]['author']['head_pic']){
						$pllist[$k]['author']['head_pic'] = $this->hostname.$pllist[$k]['author']['head_pic'];
					}else{
						$pllist[$k]['author']['head_pic'] = "";
					}
				}else{
					$pllist[$k]['author'] = Db::name('member')->field(['nickname','username','head_pic','avatar'])->where(['id'=>$v['fabu_uid']])->find();
					if($pllist[$k]['author']['head_pic']){
						$pllist[$k]['author']['head_pic'] = $this->hostname.$pllist[$k]['author']['head_pic'];
					}else{
						$pllist[$k]['author']['head_pic'] = "";
					}
					if(isset($pllist[$k]['author']['avatar'])){
						$pllist[$k]['author']['avatar'] = $pllist[$k]['author']['avatar'];
					}else{
						$pllist[$k]['author']['avatar'] = "";
					}
				}
				$pllist[$k]['addtime'] = Tiia($v['addtime']);
			}
			$one['pinglun'] = $pllist;
			$one['plcount'] = Db::name('shequ_pinglun')->field('id')->where(['pid'=>$id,'is_del'=>'n'])->count();
			$is_shoucang = 0;
			$is_zan      = 0;
			if($token!=""){
				$token = $this->CheckToken($data['token']);
				if($token['code']=='0'){
					$checkshouchang = Db::name('member_collection')->where(['uid'=>$token['myinfo']['id'],'sq_id'=>$id,'is_del'=>'n'])->find();
					if($checkshouchang){
						$is_shoucang = 1;
					}
					$checkzan = Db::name('member_zan')->where(['uid'=>$token['myinfo']['id'],'sq_id'=>$id,'is_del'=>'n'])->find();
					if($checkzan){
						$is_zan = 1;
					}
				}
			}
			$up_arr['looknums'] = $one['looknums']+1;
			$res = Db::name('shequ')->where(['id'=>$id])->update($up_arr);
			return json(['code'=>'0','data'=>$one,'id'=>$id,'is_shoucang'=>$is_shoucang,'is_zan'=>$is_zan]);
		}else{
			return json(['code'=>'1','data'=>'没有相关资讯','id'=>$id]);
		}
	}
	public function getPinglunList(){
		$data = input();
		$page = isset($data['page']) ? $data['page'] : '1';
		$limit = 10;
		$start = $page*$limit;
		$where['pid']    = $data['id'];
		$where['is_del'] = 'n';
		$pllist  = Db::name('shequ_pinglun')->where($where)->limit($start,$limit)->order('addtime desc')->select();
		foreach($pllist as $k=>$v){
			if($v['fabu_uid']=="0"){
				$pllist[$k]['author'] = Db::name('admin')->field(['nickname','username','head_pic'])->where(['id'=>$v['fabu_aid']])->find();
				if($pllist[$k]['author']['head_pic']){
					$pllist[$k]['author']['head_pic'] = $this->hostname.$pllist[$k]['author']['head_pic'];
				}else{
					$pllist[$k]['author']['head_pic'] = "";
				}
			}else{
				$pllist[$k]['author'] = Db::name('member')->field(['nickname','username','head_pic','avatar'])->where(['id'=>$v['fabu_uid']])->find();
				if($pllist[$k]['author']['head_pic']){
					$pllist[$k]['author']['head_pic'] = $this->hostname.$pllist[$k]['author']['head_pic'];
				}else{
					$pllist[$k]['author']['head_pic'] = "";
				}
				if($pllist[$k]['author']['avatar']){
					$pllist[$k]['author']['avatar'] = $pllist[$k]['author']['avatar'];
				}else{
					$pllist[$k]['author']['avatar'] = "";
				}
			}
			$pllist[$k]['addtime'] = Tiia($v['addtime']);
		}
		if($pllist){
			return json(['code'=>0,'msg'=>'操作成功','list'=>$pllist,'page'=>($page+1)]);
		}else{
			return json(['code'=>0,'msg'=>'没有更多相关评论了']);
		}
	}
	//发表社区评论
	public function addShequPinglun(){
		$data = input();
		$token = $this->CheckToken($data['token']);
		if($token['code']=='0'){
			$one = Db::name('shequ')->field(['id','uid','aid'])->where(['id'=>$data['id']])->find();
			$add['fabu_uid']     = $token['myinfo']['id']; //发表评论的uid
			$add['huifu_uid']    = $one['uid']; //发表社区话题的uid
			$add['pid']          = $data['id']; //社区id
			$add['pl_id']        = '0';//评论id
			$add['content']      = $data['pinglun']; //评论内容
			$add['addtime']      = time();
			$res = Db::name('shequ_pinglun')->insert($add);
			if($res){
				return json(['code'=>0,'msg'=>'评论成功']);
			}else{
				return json(['code'=>1,'msg'=>'评论失败']);
			}
		}else{
			return json($token);
		}
	}
	public function getShequType(){
		$list = Db::name('shequ_cate')->field(['id','cate_title'])->where(['is_del'=>'n','is_show'=>'y'])->order('aorder asc,updatetime desc')->select();
		return json(['code'=>'0','list'=>$list]);
	}
	public function uploadfabu($size=1024*1024*10,$ext='jpg,png,gif,jpeg',$save_dir='./uploads',$rule='date'){
		if($this->request->file('file')){
			$file = $this->request->file('file');
			$info = $file->validate(['size'=>$size,'ext'=>$ext])->rule($rule)->move($save_dir);
			if($info){
	           	$url = $info->getSaveName();
		    	$filePath = str_replace("./","/",$save_dir.'/'.$url);
	            return $filePath;
        	}else{
	        	return "";
        	}
	    }else{
	    	$res['code'] = 1;
	    	$res['msg']  = '没有上传图片';
	    	return json($res);die;
	    }
	}
	public function addFabu(){
		$data = input();
		$token = $this->CheckToken($data['token']);
		if($token['code']=='0'){
			if($data['title']==""){
				return json(['code'=>'12','msg'=>'请填写标题']);
			}
			if($data['content']==""){
				return json(['code'=>'12','msg'=>'请填写内容']);
			}
			$add['uid']     = $token['myinfo']['id'];
			$add['title']   = $data['title'];
			$add['content'] = $data['content'];
			$add['pic']     = $data['pic'];
			$add['addtime'] = time();
			$res = Db::name('shequ')->insert($add);
			if($res){
				return json(['code'=>'0','msg'=>'发布成功']);
			}else{
				return json(['code'=>'11','msg'=>'发布失败']);
			}
		}else{
			return json($token);
		}
	}

	public function getShequsType(){
		$limit = 10;
		$list = Db::name('shequ_cate')->field(['id','cate_title'])->where(['is_del'=>'n','is_show'=>'y'])->order('aorder asc,addtime desc')->select();
		foreach($list as $k=>$v){
			$list[$k]['id']   = 'a'.$v['id'];
			$list[$k]['pn']   = 1;
			$child = Db::name('shequ')->field(['is_del','is_show'],true)->where(['pid'=>$v['id'],'is_del'=>'n','is_show'=>'y'])->order('id asc')->limit(0,$limit)->select();
			$child_count = Db::name('shequ')->field('id')->where(['pid'=>$v['id'],'is_del'=>'n','is_show'=>'y'])->count();
			$list[$k]['pageNum'] = ceil($child_count/$limit);
			$list[$k]['count']   = $child_count;
			foreach($child as $key=>$val){
				$child[$key]['pinglun']     =  Db::name('shequ_pinglun')->field('id')->where(['pid'=>$val['id'],'pl_id'=>'0','is_del'=>'n'])->count();
				if($val['uid']=="0"){
					$child[$key]['author']  = getAdmin($val['aid']);
				}else{
					$child[$key]['author']  = getMember($val['uid']);
				}
				if($val['pic']){
					$piclist = explode(",",$val['pic']);
					foreach($piclist as $keya=>$vala){
						$piclist[$keya] = $this->hostname.$vala;
					}
					$child[$key]['pic']     = $piclist[0];
				}else{
					$child[$key]['pic']     = "";
				}
				$child[$key]['addtime'] = Tii($val['addtime']);
				$child[$key]['show']    = false;
				$child[$key]['loaded']  = false;
			}
			$list[$k]['list'] = $child;
		}
		return json(['code'=>'0','data'=>$list]);
	}
	public function getShequsList(){
		$data = input();
		$limit = 10;
		$pn = isset($data['sl_pn']) ? $data['sl_pn'] : '1';
		$start = $pn*$limit;
		$where['is_del']  = 'n';
		$where['is_show'] = 'y';
		$pid = isset($data['sl_pid']) ? $data['sl_pid'] : 'all';
		if($pid!='all'){
			$where['pid'] = str_replace('a','',$pid);
		}
		$keywords = isset($data['sl_keywords']) ? $data['al_keywords'] : '';
		if($keywords!=""){
			$where['title'] = ['like','%'.$keywords.'%'];
		}
		$list = Db::name('shequ')->field(['is_del','is_show'],true)->where($where)->order('id asc')->limit($start,$limit)->select();
		$count = Db::name('shequ')->field(['id'])->where($where)->count();
		$pages = ceil($count/$limit);
		foreach($list as $k=>$v){
			if($v['uid']=="0"){
				$list[$k]['author']  = getAdmin($v['aid']);
			}else{
				$list[$k]['author']  = getMember($v['uid']);
			}
			$list[$k]['pinglun'] =  Db::name('shequ_pinglun')->field('id')->where(['pid'=>$v['id'],'pl_id'=>'0','is_del'=>'n'])->count();
			if($v['pic']){
				$piclist = explode(",",$v['pic']);
				foreach($piclist as $key=>$val){
					$piclist[$key] = $this->hostname.$val;
				}
				$list[$k]['pic']     = $piclist[0];
			}else{
				$list[$k]['pic']     = "";
			}
			$list[$k]['addtime'] = Tii($v['addtime']);
			$list[$k]['show']    = false;
			$list[$k]['loaded']  = false;
		}
		if($list){
			return json(['code'=>'0','data'=>$list,'pn'=>$pn+1]);
		}else{
			return json(['code'=>'1','data'=>'没有更多相关数据了','pn'=>$pn]);
		}
	}

	public function banklist(){
		$list = Db::name('bank')->field('id,name,logo,bgcolor')->where(['is_show'=>'y','is_del'=>'n'])->order('aorder asc,addtime desc')->select();
		foreach ($list as $key => $value) {
			$list[$key]['logo'] = $this->hostname.$value['logo'];
		}
		if($list){
			return json(['code'=>'0','data'=>$list]);
		}else{
			return json(['code'=>'1','data'=>'暂时没有相关银行']);
		}
	}
}