<?php
namespace app\api\controller;
use \think\Controller;
use app\api\controller\Base;
use \think\Db;
use \think\Session;
use \think\Cookie;
use \think\Request;
use \think\AES;
class News extends Base{
	public function __construct(){
		parent::__construct(); //使用父类的构造方法
    }
    public function index(){
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
		$list = Db::name('news')->field(['id','title','thumb','author','description','updatetime'])->where($where)->order('id asc')->limit($start,$limit)->select();
		$count = Db::name('news')->field(['id'])->where($where)->count();
		$pages = ceil($count/$limit);
		foreach($list as $k=>$v){
			if(!$v['description']){
				$list[$k]['description'] = "";
			}
			$checkzan = Db::name('member_zan')->where(['wz_id'=>$v['id'],'is_del'=>'n'])->count();
			$list[$k]['zan']        = $checkzan;
			$list[$k]['thumb']      = $this->hostname.getImage($v['thumb']);
			$list[$k]['updatetime'] = Tii($v['updatetime']);
			$list[$k]['show']       = false;
			$list[$k]['loaded']     = false;
		}
		if($list){
			return json(['code'=>'0','message'=>'SUCCESS','returnData'=>$list,'pn'=>$pn+1]);
		}else{
			return json(['code'=>'1','message'=>'没有更多相关资讯了','returnData'=>'没有更多相关资讯了','pn'=>$pn]);
		}
    }
    public function cates(){
    	$limit = 10;
		$list = Db::name('news_cate')->field(['id','title'])->where(['pid'=>1,'is_del'=>'n','is_show'=>'y'])->order('orders asc,addtime desc')->select();
		foreach($list as $k=>$v){
			$list[$k]['id']   = 'a'.$v['id'];
			$list[$k]['pn']   = 1;
			$child = Db::name('news')->field(['id','title','thumb','author','description','updatetime'])->where(['pid'=>$v['id'],'is_del'=>'n','is_show'=>'y'])->order('id asc')->limit(0,$limit)->select();
			$child_count = Db::name('news')->field('id')->where(['pid'=>$v['id'],'is_del'=>'n','is_show'=>'y'])->count();
			$list[$k]['pageNum'] = ceil($child_count/$limit);
			$list[$k]['count']   = $child_count;
			foreach($child as $key=>$val){
				if(!$val['description']){
					$child[$key]['description'] = "";
				}
				$checkzan = Db::name('member_zan')->where(['wz_id'=>$val['id'],'is_del'=>'n'])->count();
				$child[$key]['zan']     = $checkzan;
				$child[$key]['thumb']     = $this->hostname.getImage($val['thumb']);
				$child[$key]['updatetime'] = Tii($val['updatetime']);
				$child[$key]['show']    = false;
				$child[$key]['loaded']  = false;
			}
			$list[$k]['list'] = $child;
		}
		return json(['code'=>'0','message'=>'SUCCESS','returnData'=>$list]);
    }
    public function details(){
    	$data = input();
		$id = isset($data['sl_id']) ? $data['sl_id'] : '0';
		$token = isset($data['token']) ? $data['token'] : '';
		$one = Db::name('news')->field(['id','title','thumb','author','description','updatetime','content'])->where(['id'=>$id,'is_del'=>'n','is_show'=>'y'])->find();
		if($one){
			if(!$one['description']){
				$one['description'] = "";
			}
			$one['thumb']       = $this->hostname.getImage($one['thumb']);
			$one['author_qz'] = '发表于';
			$one['updatetime'] = Tii($one['updatetime']);
			$a = 'img style="max-width:100%;" src="'.$this->hostname.'/';
			$one['content'] = htmlspecialchars_decode(stripslashes(str_replace('img src="/',$a,$one['content'])));

			$where['pid']    = $id;
			$where['is_del'] = 'n';
			$limit = 10;
			$pllist = Db::name('news_pinglun')->where($where)->limit($limit)->order('addtime desc')->select();
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
						$pllist[$k]['author']['avatar'] = $this->hostname.$pllist[$k]['author']['avatar'];
					}else{
						$pllist[$k]['author']['avatar'] = "";
					}
				}
				$pllist[$k]['addtime'] = Tiia($v['addtime']);
			}
			$one['pinglun'] = $pllist;
			$one['plcount'] = Db::name('news_pinglun')->field('id')->where(['pid'=>$id,'is_del'=>'n'])->count();
			$is_shoucang = 0;
			$is_zan      = 0;
			if($token!=""){
				$token = $this->CheckToken($data['token']);
				if($token['code']=='0'){
					$checkshouchang = Db::name('member_collection')->where(['uid'=>$token['myinfo']['id'],'wz_id'=>$id,'is_del'=>'n'])->find();
					if($checkshouchang){
						$is_shoucang = 1;
					}
					$checkzan = Db::name('member_zan')->where(['uid'=>$token['myinfo']['id'],'wz_id'=>$id,'is_del'=>'n'])->find();
					if($checkzan){
						$is_zan = 1;
					}
				}
			}
			return json(['code'=>'0','data'=>$one,'id'=>$id,'is_shoucang'=>$is_shoucang,'is_zan'=>$is_zan]);
		}else{
			return json(['code'=>'1','data'=>'没有相关资讯','id'=>$id]);
		}
    }
    public function addpinglun(){
    	$data = input();
		$token = $this->CheckToken($data['token']);
		if($token['code']=='0'){
			$add['fabu_uid']     = $token['myinfo']['id']; //发表评论的uid
			$add['huifu_uid']    = 0; //发表社区话题的uid
			$add['pid']          = $data['id']; //社区id
			$add['pl_id']        = '0';//评论id
			$add['content']      = $data['pinglun']; //评论内容
			$add['addtime']      = time();
			$res = Db::name('news_pinglun')->insert($add);
			if($res){
				return json(['code'=>0,'msg'=>'评论成功']);
			}else{
				return json(['code'=>1,'msg'=>'评论失败']);
			}
		}else{
			return json($token);
		}
    }
    public function addZan(){
		$data = input();
		$token = $this->CheckToken($data['token']);
		if($token['code']=='0'){
			$types = isset($data['types'])?$data['types']:'wz_id';
			$add['uid']   = $token['myinfo']['id'];
			$add[$types] = $data['id'];
			$ck = Db::name('member_zan')->where($add)->find();
			if($ck){
				$res = Db::name('member_zan')->where($add)->delete();
			}else{
				$add['addtime'] = time();
				$add['is_del']  = 'n';
				$res = Db::name('member_zan')->insert($add);
			}
			if($res){
				return json(['code'=>'0','msg'=>'操作成功']);
			}else{
				return json(['code'=>'1','msg'=>'操作失败']);
			}
		}else{
			return json($token);
		}
	}
	public function addShoucang(){
		$data = input();
		$token = $this->CheckToken($data['token']);
		if($token['code']=='0'){
			$types = isset($data['types'])?$data['types']:'wz_id';
			$add['uid']   = $token['myinfo']['id'];
			$add[$types] = $data['id'];
			$ck = Db::name('member_collection')->where($add)->find();
			if($ck){
				$res = Db::name('member_collection')->where($add)->delete();
			}else{
				$add['addtime'] = time();
				$add['is_del']  = 'n';
				$res = Db::name('member_collection')->insert($add);
			}
			if($res){
				return json(['code'=>'0','msg'=>'操作成功']);
			}else{
				return json(['code'=>'1','msg'=>'操作失败']);
			}
		}else{
			return json($token);
		}
	}
}