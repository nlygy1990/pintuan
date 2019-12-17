<?php
namespace app\api\controller;
use \think\Controller;
use app\api\controller\Base;
use \think\Db;
use \think\Session;
use \think\Cookie;
use \think\Request;
use \think\AES;
class Shop extends Base{
	public function __construct(){
		parent::__construct(); //使用父类的构造方法
    }
/************************************************************************/
/** 获取店铺信息
/************************************************************************/
	public function lists(){
		//幻灯片
		$banner = Db::name('banner')->field(['id','title','thumb','url'])->where(['pid'=>'3','is_del'=>'n','is_show'=>'y'])->order('orders asc,updatetime desc')->select();
		foreach($banner as $k=>$v){
			$banner[$k]['thumb'] = $this->hostname.getImage($v['thumb']);
		}
		$one['banner'] = $banner;

		$data = input();
		$limit = 10;
		$pn = isset($data['pn']) ? $data['pn'] : '1';
		$start = ($pn-1)*$limit;
		$list = Db::name('shop')->field('id,logo,title')->where(['is_del'=>'n','is_show'=>'y'])->limit($start,$limit)->select();
		foreach($list as $k=>$v){
			$list[$k]['logo'] = $this->hostname.getImage($v['logo']);
		}
		$one['shops'] = $list;
		//推荐
		$tjlist = Db::name('shop')->field('id,logo,title')->where(['is_del'=>'n','is_show'=>'y'])->where('orders','>','0')->order('orders asc')->limit(16)->select();
		foreach($tjlist as $k=>$v){
			$tjlist[$k]['logo'] = $this->hostname.getImage($v['logo']);
		}
		$one['tjlist'] = $tjlist;
		return json(['code'=>0,'message'=>"SUCCESS",'returnData'=>$one]);
	}
}