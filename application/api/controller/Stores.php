<?php
namespace app\api\controller;
use \think\Controller;
use app\api\controller\Base;
use \think\Db;
use \think\Session;
use \think\Cookie;
use \think\Request;
use \think\AES;
class Stores extends Base{
	public function __construct(){
		parent::__construct(); //使用父类的构造方法
    }
/************************************************************************/
/** 获取门店信息
/************************************************************************/
	public function getStores(){
		$data = input();
		$latitude  = isset($post['latitude']) ? $post['latitude'] : '39.071510';
		$longitude = isset($post['longitude']) ? $post['longitude'] : '117.190091';
		$form = $latitude.",".$longitude;
		$where[] = ['is_show','=','y'];
		$where[] = ['is_del','=','n'];
		//分页数据
        $pn = isset($data['pn']) ? $data['pn'] : 1;
        $limit = isset($data['limit']) ? $data['limit'] : 15;
        $start = ($pn-1)*$limit;
        $field = "id,title,logo,tel,lat,lng,diqu,address";
        $orders = isset($data['orders']) ? $data['orders'] : 0;
        if($orders=="0"){ //默认排序
        	$orderby = "orders asc,addtime desc";
        }else{ //默认排序
        	$orderby = "orders asc,addtime desc";
        }
		$list = Db::name('stores')->field($field)->where($where)->order($orderby)->limit($start,$limit)->select();
		$to = [];
		foreach($list as $k=>$v){
			$to[] = $v['lat'].",".$v['lng'];
		}
		$distance = $this->distance($form,$to);
		foreach($list as $k=>$v){
			$list[$k]['logo'] = $this->hostname.getImage($v['logo']);
			if($distance){
				foreach($distance as $ka=>$va){
					if($k===$ka){
						if($va['distance']>='1000'){//起点到终点的距离，单位：米，
							$list[$k]['distance'] = round($va['distance']/1000,2).'Km';
						}else{
							$list[$k]['distance'] = $va['distance'].'m';
						}
						$list[$k]['duration'] = $va['duration'];//表示从起点到终点的结合路况的时间，秒为单位 
					}
				}
			}
		}
		$count = Db::name('stores')->where($where)->count();
		$banner = Db::name('banner')->field('thumb,url')->where('id',8)->find();
		$banner['thumb'] = $this->hostname.getImage($banner['thumb']);
		$one['banner']      = $banner;
        $one['StoresList']  = $list;
        $one['StoresCount'] = $count;
        $one['StoresPage']   = $pn;
        $one['StoresLimit']  = $limit;
        if($list){
        	return json(['code'=>0,'message'=>'SUCCESS','returnData'=>$one]);
        }else{
        	return json(['code'=>400003,'message'=>'没有更多门店了','returnData'=>'']);
        }
	}
	public function getStoresDetail(){
		$data = input();
		$id = isset($data['id']) ? $data['id'] : 0;
		$field = "is_del,content,admin_id,update_admin,orders";
		$one = Db::name('stores')->field($field,true)->where(['id'=>$id,'is_del'=>'n','is_show'=>'y'])->find();
		if($one){
			if($one['is_show']=="y"){
                unset($one['is_show']);
                if($one['pics']){
                	$one['pics'] = explode(",",$one['pics']);
	                $pics = [];
	                foreach($one['pics'] as $k=>$v){
	                    $pics[] = $this->hostname.getImage($v);
	                }
	                $one['pics'] = $pics;
                }else{
                	$one['pics'] = "";
                }
                $list = Db::name('stores_goods')->field('id,thumb,title,marketprice,goodsprice')->where(['stores_id'=>$id,'is_del'=>'n','is_show'=>'y'])->order('orders asc,addtime desc')->select();
                foreach($list as $k=>$v){
                	$list[$k]['thumb'] = $this->hostname.getImage($v['thumb']);
                }
                $one['goodsList'] = $list;
                return json(['code'=>0,'message'=>'SUCCESS','returnData'=>$one]);
            }else{
                unset($one['is_show']);
                return json(['code'=>400002,'message'=>'Stores from the shelves','returnData'=>$one]);
            }
		}else{
			return json(['code'=>400001,'message'=>"Stores doesn't exist",'returnData'=>'']);
		}
	}
	public function distance($from = '',$to = array()){
		$to = implode(";",$to);
		$url = "https://apis.map.qq.com/ws/distance/v1/?mode=driving&from=".$from."&to=".$to."&key=".$this->txMapKey."&get_poi=1";
		$res = $this->get_contents($url);
		$json = json_decode($res,1);
		if($json['status']=="0"){
			return $json['result']['elements'];
		}else{
			return false;
		}
	}

	public function getStoresDetailYuyue(){
		$data = input();
		$id = isset($data['id']) ? $data['id'] : 0;
		$field = "is_del,admin_id,update_admin";
		$one = Db::name('stores_goods')->field($field,true)->where(['id'=>$id,'is_del'=>'n','is_show'=>'y'])->find();
		if($one){
			if($one['times']=="0" || $one['times']==""){
				$one['times'] = "不限时";
			}else if($one['times']<'60' && $one['times']>'0'){
				$one['times'] = $one['times'].'分钟';
			}else{
				$one['times'] = round(($one['times']/60),2).'小时';
			}
			$one['thumb'] = $this->hostname.getImage($one['thumb']);
			$one['content'] = str_replace('src="/ueditor','src="'.$this->hostname.'/ueditor',$one['content']);
			//开启的支付方式
			$one['wxpayapi']  = Db::name('payment_api')->field('id,is_show')->where('id','1')->find();
			$one['alipayapi'] = Db::name('payment_api')->field('id,is_show')->where('id','2')->find();
			$one['yuepayapi'] = Db::name('payment_api')->field('id,is_show')->where('id','3')->find();
			return json(['code'=>0,'message'=>'SUCCESS','returnData'=>$one]);
		}else{
			return json(['code'=>400004,'message'=>"Stores Goods doesn't exist",'returnData'=>'']);
		}
	}

	public function getlist(){
		$data = input();
		$token = $this->CheckToken($data['token']);
   		if($token['code']=='0'){
   			$limit = 10;
   			$pn = isset($data['pn']) ? $data['pn'] : 1;
   			$start = ($pn-1)*$limit;
   			$where[] = ['user_id','=',$token['myinfo']['id']];
   			$where[] = ['is_del','=','n'];
   			$state = isset($data['state']) ? $data['state'] : '0';
   			if($state=="0"){ //全部订单

   			}else if($state=="1"){ //待处理
   				$where[] = ['status','=','0'];
   			}else if($state=="2"){ //已完成
   				$where[] = ['status','=','1'];
   			}else if($state=="3"){ //已退款
   				$where[] = ['status','=','2'];
   			}

   			$list = Db::name('order_stores')->field('id,user_id,ordersn,no,price,realprice,status,createtime,goods')->where($where)->limit($start,$limit)->order('createtime desc')->select();
   			foreach($list as $k=>$v){
   				$totals = [];
   				$goodsList = json_decode($v['goods'],1);
   				$goodsList['thumb'] = $this->hostname.getImage($goodsList['thumb']);
				$list[$k]['createtime']   = date("Y-m-d H:i:s",$v['createtime']);
   				$list[$k]['nums']         = array_sum($totals);
   				$list[$k]['goodsList'][0]  = $goodsList;
   			}
   			if($list){
   				return json(['code'=>'0','msg'=>'获取成功','list'=>$list]);
   			}else{
   				return json(['code'=>'1','msg'=>'已经没有更多了','list'=>$list]);
   			}
   		}else{
   			return json($token);
   		}
	}

	public function orderDetail(){
		$data = input();
		$token = $this->CheckToken($data['token']);
   		if($token['code']=='0'){
   			$one = Db::name('order_stores')->where('ordersn',$data['ordersn'])->find();
   			$goodsList = json_decode($one['goods'],1);
   			$goodsList['thumb'] = $this->hostname.getImage($goodsList['thumb']);
   			$goodsList['times']    = $goodsList['times'].'分钟';
   			$stores = Db::name('stores')->field('id,logo,title')->where('id',$one['stores_id'])->find();
   			$stores['logo'] = $this->hostname.getImage($stores['logo']);
   			$one['goodsList'] = $goodsList;
   			$one['shoplist']  = $stores;
   			$one['createtime'] = date("Y.m.d H:i:s",$one['createtime']);
   			return json(['code'=>0,'msg'=>'获取成功','returnData'=>$one]);
   		}else{
   			return json($token);
   		}
	}
}