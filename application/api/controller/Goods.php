<?php
namespace app\api\controller;
use \think\Controller;
use app\api\controller\Base;
use \think\Db;
use \think\Session;
use \think\Cookie;
use \think\Request;
use \think\AES;
class Goods extends Base{
	public function __construct(){
		parent::__construct(); //使用父类的构造方法
    }
/************************************************************************/
/** 产品首页
/************************************************************************/
	public function index(){
		//幻灯片
		$banner = Db::name('banner')->field(['id','title','thumb','url'])->where(['pid'=>'2','is_del'=>'n','is_show'=>'y'])->order('orders asc,updatetime desc')->select();
		foreach($banner as $k=>$v){
			$banner[$k]['thumb'] = $this->hostname.getImage($v['thumb']);
		}
		$one['banner'] = $banner;
		//新品热卖
		$where[] = ['is_del','=','n'];
		$where[] = ['is_show','=','y'];
		$where[] = ['tags',['like','%新品%'],['like','%热卖%'],'or'];
		$xprmGoods = Db::name('goods')->field('id,title,short_title,marketprice,goodsprice,thumb,total')
					->where($where)
					->order('addtime desc')
					->limit(16)->select();
		foreach($xprmGoods as $k=>$v){
			$xprmGoods[$k]['thumb'] = $this->hostname.getImage($v['thumb']);
			$xprmGoods[$k]['sale']  = GoogsXiaoliang($v['id']);
		}
		$one['xinpin'] = $xprmGoods;
		//全部产品
		$wherea[] = ['is_del','=','n'];
		$wherea[] = ['is_show','=','y'];
		$goods = Db::name('goods')->field('id,title,short_title,marketprice,goodsprice,thumb,total')
					->where($wherea)
					->order('addtime desc')
					->limit(16)->select();
		foreach($goods as $k=>$v){
			$goods[$k]['thumb'] = $this->hostname.getImage($v['thumb']);
			$goods[$k]['sale']  = GoogsXiaoliang($v['id']);
		}
		$one['goods'] = $goods;
		return json(['code'=>0,'message'=>"SUCCESS",'returnData'=>$one]);
	}
/************************************************************************/
/** 获取商品信息
/************************************************************************/
    //商品类型
    public function getGoodsCate(){
        $data = input();
        $pid       = isset($data['pid']) ? $data['pid'] : 0;
        $shop_id   = isset($data['shop_id']) ? $data['shop_id'] : 0;
        $stores_id = isset($data['stores_id']) ? $data['stores_id'] : 0;

        $where[] = ['is_show','=','y'];
        $where[] = ['is_del','=','n'];
        $where[] = ['pid','=',$pid];
        $where[] = ['shop_id','=',$shop_id];
        $where[] = ['stores_id','=',$stores_id];
        $list = Db::name('goods_cate')->field('id,pid,title,thumb')->where($where)->order('orders asc,addtime desc')->select();
        foreach($list as $k=>$v){
            if($v['thumb']){
                $list[$k]['thumb'] = $this->hostname.getImage($v['thumb']);
            }else{
                $list[$k]['thumb'] = '';
            }
            $childa = Db::name('goods_cate')->field('id,pid,title,thumb')->where(['is_del'=>'n','is_show'=>'y','pid'=>$v['id']])->order('orders asc,addtime desc')->select();
            if($childa){
                foreach($childa as $ka=>$va){
                    if($va['thumb']){
                        $childa[$ka]['thumb'] = $this->hostname.getImage($va['thumb']);
                    }else{
                        $childa[$ka]['thumb'] = '';
                    }
                    $childb = Db::name('goods_cate')->field('id,pid,title,thumb')->where(['is_del'=>'n','is_show'=>'y','pid'=>$va['id']])->order('orders asc,addtime desc')->select();
                    if($childb){
                        foreach($childb as $kb=>$vb){
                            if($vb['thumb']){
                                $childb[$kb]['thumb'] = $this->hostname.getImage($vb['thumb']);
                            }else{
                                $childb[$kb]['thumb'] = '';
                            }
                        }
                    }
                    $childa[$ka]['childrenData'] = $childb;
                }
            }
            $list[$k]['childrenData'] = $childa;
        }
        $count = Db::name('goods_cate')->where($where)->count();
        $one['goodsCateList']  = $list;
        $one['goodsCateCount'] = $count;
        return json(['code'=>0,'message'=>'SUCCESS','returnData'=>$one]);
    }
    //商品列表
    public function getGoodsList(){
        $data = input();
        //分页数据
        $pn = isset($data['pn']) ? $data['pn'] : 1;
        $limit = isset($data['limit']) ? $data['limit'] : 15;
        $start = ($pn-1)*$limit;

        $filterIndex = isset($data['filterIndex']) ? $data['filterIndex'] : '0';
        if($filterIndex=='0'){ //默认排序方式
            $orderby = "addtime desc";
        }else if($filterIndex=="1"){ //销量
            $orderby = "sales desc";
        }else if($filterIndex=="2"){ //价格
            $orderby = "addtime desc";
            $priceOrder = isset($data['priceOrder']) ? $data['priceOrder'] : '1';
            if($priceOrder=="2"){
                $orderby = "marketprice desc";
            }else{
                $orderby = "marketprice asc";
            }
        }else{ //人气
            $orderby = "renqi desc";
        }
        //查询字段
        $field = "id,short_title,seo_title,title,description,thumb,goodsprice,marketprice";

        $where[] = ['is_show','=','y'];
        $where[] = ['is_del','=','n'];
        //条件筛选
        $keys = isset($data['keys']) ? $data['keys'] : '';
        if($keys!=""){
            $where[] = ['title|short_title|keywords|tags|description','like','%'.$keys.'%'];
        }

        $pid = isset($data['pid']) ? $data['pid'] : 0;
        if($pid>0){ //产品类型
            //查看有无下级
            $one = Db::name('goods_cate')->field('id')->where('pid',$pid)->select();
            $pids = [];
            foreach($one as $k=>$v){
                $pids[] = $v['id'];
                $two = Db::name('goods_cate')->field('id')->where('pid',$v['id'])->select();
                foreach($two as $ka=>$va){
                    $pids[] = $va['id'];
                }
            }
            if($pids){
                $where[] = ['pid','in',$pids];
            }else{
                $where[] = ['pid','=',$pid];
            }
        }

        //店铺相关筛选
        $shop_id  = isset($data['shop_id']) ? $data['shop_id'] : 0;
        $shop_pid = isset($data['shop_pid']) ? $data['shop_pid'] : 0;
        $shopDetail = [];
        if($shop_id!=0){//店铺
            $where[] = ['storeid','=',$shop_id];
            if($shop_id>0){
                //查看有无下级
                $one = Db::name('goods_cate')->field('id')->where('pid',$shop_pid)->where('shop_id',$shop_id)->select();
                $shop_pids = [];
                foreach($one as $k=>$v){
                    $shop_pids[] = $v['id'];
                    $two = Db::name('goods_cate')->field('id')->where('pid',$v['id'])->where('shop_id',$shop_id)->select();
                    foreach($two as $ka=>$va){
                        $shop_pids[] = $va['id'];
                    }
                }
                if($shop_pids){
                    $where[] = ['shop_pid','in',$shop_pids];
                }else{
                    $where[] = ['shop_pid','=',0];
                }
            }
            $shopDetail = Db::name('shop')->where('id',$shop_id)->find();
            $shopDetail['logo'] = $this->hostname.getImage($shopDetail['logo']);
            $pics = explode(",",$shopDetail['pics']);
            $piclist = [];
            foreach($pics as $k=>$v){
                $piclist[] = $this->hostname.getImage($v);
            }
            $shopDetail['pics'] = $piclist;
        }

        $list = Db::name('goods')->field($field)->where($where)->order($orderby)->limit($start,$limit)->select();
        foreach($list as $k=>$v){
            if($v['thumb']>0){
                $list[$k]['thumb'] = $this->hostname.getImage($v['thumb']);
            }else{
                $list[$k]['thumb'] = '';
            }
        }
        $count = Db::name('goods')->where($where)->count();
        $one['goodsList']  = $list;
        $one['goodsCount'] = $count;
        $one['goodsPage']  = $pn;
        $one['goodsLimit'] = $limit;
        $one['shopDetail'] = $shopDetail;
        if($list){
            return json(['code'=>0,'message'=>'SUCCESS','returnData'=>$one]);
        }else{
            return json(['code'=>1,'message'=>'SUCCESS','returnData'=>$one]);
        }
    }
    public function addShoucang(){
    	$data = input();
    	$token = $this->CheckToken($data['token']);
   		if($token['code']=='0'){
   			$types = isset($data['types'])?$data['types']:'cp_id';
			$add['uid']   = $token['myinfo']['id'];
			$add[$types]  = $data['id'];
			$ck = Db::name('member_collection')->where($add)->find();
			if($ck){
				$res = Db::name('member_collection')->where($add)->delete();
				if($res){
					return json(['code'=>'0','msg'=>'已取消']);
				}else{
					return json(['code'=>'1','msg'=>'操作失败']);
				}
			}else{
				$add['addtime'] = time();
				$add['is_del']  = 'n';
				$res = Db::name('member_collection')->insert($add);
				if($res){
					return json(['code'=>'0','msg'=>'已收藏']);
				}else{
					return json(['code'=>'1','msg'=>'操作失败']);
				}
			}
   		}else{
   			return json($token);
   		}
    }
    //商品详情
    public function getGoodsDetails(){
        $data = input();
        $field = 'pid,cates,costprice,is_del,admin_id,update_admin';
        $one = Db::name('goods')->field($field,true)->where('id',$data['id'])->where('is_del','n')->find();
        if($one){
        	//购买限制
        	if($one['minbuy']=='0'){
        		$one['minbuy'] = 1;
        	}
        	if($one['maxbuy']=="0"){ //无限制单次最大购买量，取库存为单次最大购买量
        		$one['maxbuy'] = $one['total'];
        	}else{
        		if($one['maxbuy']>=$one['total']){ //单次最大购买量大于库存时，以库存为最大的单次购买量
        			$one['maxbuy'] = $one['total'];
        		}
        	}
        	//运费
        	if($one['postagetype']=="0"){
        		$one['yunfei'] = '20.00';
        	}else{
        		$one['yunfei'] = $one['postageprice'];
        	}
            $one['addtime'] = date("Y-m-d H:i:s",$one['addtime']);
            $one['updatetime'] = date("Y-m-d H:i:s",$one['updatetime']);
            if($one['thumb']>0){
                $one['thumb']   = $this->hostname.getImage($one['thumb']);
            }else{
                $one['thumb']   = "";
            }
            if($one['store_logo']>0){
                $one['store_logo']   = $this->hostname.getImage($one['store_logo']);
            }else{
                $one['store_logo']   = "";
            }
            if($one['pics']){
                $one['pics'] = explode(",",$one['pics']);
                $pics = [];
                foreach($one['pics'] as $k=>$v){
                    $pics[] = $this->hostname.getImage($v);
                }
                $one['pics'] = $pics;
                $one['content'] = str_replace("/ueditor/",$this->hostname.'/ueditor/',$one['content']);
            }
            $spec = Db::name('goods_options')->field('id,title')->where(['goodsid'=>$data['id'],'is_del'=>'n','is_show'=>'y'])->order('orders asc,id desc')->select();
            if($spec){
                foreach($spec as $k=>$v){
                    $childrenData = Db::name('goods_options_item')->field('id,title,thumb,optionid')->where(['goodsid'=>$data['id'],'optionid'=>$v['id'],'is_del'=>'n','is_show'=>'y'])->order('orders asc,id desc')->select();
                    foreach($childrenData as $ka=>$va){
                        if($va['thumb']){
                            $childrenData[$ka]['thumb'] = $this->hostname.getImage($va['thumb']);
                        }
                    }
                    $spec[$k]['childrenData'] = $childrenData;
                }
            }
            $one['specData'] = $spec;
            $one['zhekou']   = round($one['marketprice']/$one['goodsprice']*10,1);
            $guige = Db::name('goods_options_item_desc')->field('marketprice,goodsprice,total,productsn,goodssn,desc')->where('goodsid',$data['id'])->select();
            foreach($guige as $k=>$v){
            	$guige[$k]['zhekou'] = round($v['marketprice']/$v['goodsprice']*10,1);
            }
            $one['guige'] = $guige;
            $one['sales'] = GoogsXiaoliang($one['id']);

            $pingjia = Db::name('order_pingjia')->where(['is_show'=>'y','is_del'=>'n','goods_id'=>$one['id']])->order('createtime desc')->limit(1)->find();
            if($pingjia){
                $user = Db::name('member')->field('id,username,nickname,head_pic,avatar')->where('id',$pingjia['user_id'])->find();
                if($user['head_pic']){
                    $user['head_pic'] = $this->hostname.getImage($user['head_pic']);
                }else{
                    if($user['avatar']){
                        $user['head_pic'] = $user['avatar'];
                    }else{
                        $user['head_pic'] = '';
                    }                           
                }
                $pingjia['user']        = $user;
                $pingjia['goods']       = json_decode($pingjia['goods'],1);
                $pingjia['createtime']  = date("Y-m-d H:i:s",$pingjia['createtime']);
                $one['pingjia'] = $pingjia;
            }else{
                $one['pingjia'] = '';
            }
            $one['pingjiacount'] = Db::name('order_pingjia')->where(['is_show'=>'y','is_del'=>'n','goods_id'=>$one['id']])->count();
            $one['pingjiacountgood'] = Db::name('order_pingjia')->where(['is_show'=>'y','is_del'=>'n','goods_id'=>$one['id']])->where('score','>=',4)->count();
            if($one['pingjiacount']=="0"){
                $one['haopinglv'] = '0%';
            }else{
                $one['haopinglv'] = (round($one['pingjiacountgood']/$one['pingjiacount'],2)*100).'%';
            }
            if($one['is_show']=="y"){
                unset($one['is_show']);
                return json(['code'=>0,'message'=>'SUCCESS','returnData'=>$one]);
            }else{
                unset($one['is_show']);
                return json(['code'=>400002,'message'=>'Goods from the shelves','returnData'=>$one]);
            }
        }else{
            return json(['code'=>400001,'message'=>"Goods doesn't exist",'returnData'=>'']);
        }
    }
    //商品规格的价钱
    public function getGoodsGuige(){
    	$data = input();
		$one = Db::name('goods_options_item_desc')->field('marketprice,goodsprice,total,productsn,goodssn')->where(['desc'=>$data['ids']])->find();
		$one['productsn'] = $one['goodssn'] ? $one['goodssn'] : $one['goodssn'];
		return json(['code'=>0,'msg'=>'成功','one'=>$one]);
    }
    public function cartGoods(){
    	$data = input();
    	$cartarr = json_decode($data['cartarr'],1);
    	$shoplist = [];
    	$goodslist = [];
    	foreach($cartarr as $k=>$v){
    		$shoplist[] = isset($v['shopname']) ? $v['shopname'] : '';
    		$goods =  Db::name('goods')->field('id,title,short_title,thumb,marketprice,goodsprice,storeid,store_name,total')->where('id',$v['id'])->find();
    		$goodslist[$k]['id']         = $v['id'];
    		$goodslist[$k]['image']      = $this->hostname.getImage($goods['thumb']);
    		$goodslist[$k]['stock']      = $goods['total'];
    		$goodslist[$k]['title']      = $goods['title'];
    		$goodslist[$k]['price']      = $goods['marketprice'];
    		$goodslist[$k]['goodsprice'] = $goods['goodsprice'];
    		if($v['guige']!=""){
    			$guige = Db::name('goods_options_item_desc')->where('desc',$v['guige'])->find();
    			$goodslist[$k]['attr_val']   = $guige['desc_title'];
    			$goodslist[$k]['stock']      = $guige['total'];
    			$goodslist[$k]['price']      = $guige['marketprice'];
    			$goodslist[$k]['goodsprice'] = $guige['goodsprice'];
    		}
    		$goodslist[$k]['number']       = $v['nums'];
    		$goodslist[$k]['shopname']     = $v['shopname'];
    		if($goodslist[$k]['stock']>'0'){
    			$goodslist[$k]['ischecked'] = 1;
    		}else{
    			$goodslist[$k]['ischecked'] = 0;
    		}
    		$goodslist[$k]['guige']         = $v['guige'];
    		$goodslist[$k]['utoken']        = $v['utoken'];
    	}
    	$shoplist = array_unique($shoplist);
    	$nshoplist = [];
    	foreach($shoplist as $k=>$v){
    		$nshoplist[$k]['key']       = $k;
    		$nshoplist[$k]['shopname']  = $v;
    		$nshoplist[$k]['ischecked'] = 1;
    	}
    	return json(['code'=>0,'message'=>'SUCCESS','goodslist'=>$goodslist,'shoplist'=>$nshoplist]);
    }
    //
    public function getGoodsOrder(){
    	$data = input();
    	$token = $this->CheckToken($data['token']);
   		if($token['code']=='0'){
   			$datas = json_decode($data['datas'],1);
   			$shoplist = [];
	    	$goodslist = [];
	    	//默认收货地址
	    	$addressid = isset($data['addressid']) ? $data['addressid'] : '0';
	    	if($addressid=='0'){
	    		$address = Db::name('member_address')->field('is_del,updatetime,aorder,addtime,diquid',true)->where(['uid'=>$token['myinfo']['id'],'is_del'=>'n'])->order('aorder desc,addtime desc')->limit(1)->find();
	    	}else{
	    		$address = Db::name('member_address')->field('is_del,updatetime,aorder,addtime,diquid',true)->where(['id'=>$addressid])->order('aorder desc,addtime desc')->limit(1)->find();
	    		$addressid = $address['id'];
	    	}
	    	foreach($datas as $k=>$v){
	    		$shoplist[] = $v['shopname'];
	    		$goods =  Db::name('goods')->field('id,title,short_title,thumb,marketprice,goodsprice,storeid,store_name,total,store_logo,ednum,edmoney,postagetype,postageid,postageprice,ispostage,edareas,goodssn,productsn,totalcnf')->where('id',$v['id'])->find();
	    		$goodslist[$k]['id']         = $v['id'];
	    		$goodslist[$k]['image']      = $this->hostname.getImage($goods['thumb']);
	    		$goodslist[$k]['stock']      = $goods['total'];
	    		$goodslist[$k]['title']      = $goods['title'];
	    		$goodslist[$k]['price']      = $goods['marketprice'];
	    		$goodslist[$k]['goodsprice'] = $goods['goodsprice'];
	    		$goodslist[$k]['goodssn']    = $goods['goodssn'];
	    		$goodslist[$k]['productsn']  = $goods['productsn'];
                $goodslist[$k]['totalcnf']   = $goods['totalcnf'];
	    		if($v['guige']!=""){
	    			$guige = Db::name('goods_options_item_desc')->field('desc_title,total,marketprice,goodsprice,j_weight,m_weight,goodssn,productsn')->where('desc',$v['guige'])->find();
	    			$goodslist[$k]['attr_val']   = $guige['desc_title'];
	    			$goodslist[$k]['stock']      = $guige['total'];
	    			$goodslist[$k]['price']      = $guige['marketprice'];
	    			$goodslist[$k]['goodsprice'] = $guige['goodsprice'];
	    			$goodslist[$k]['goodssn']    = $guige['goodssn'];
	    			$goodslist[$k]['productsn']  = $guige['productsn'];
	    		}
	    		$goodslist[$k]['number']        = $v['nums'];
	    		$goodslist[$k]['shopname']      = $goods['store_name'];
	    		$goodslist[$k]['shoplogo']      = $goods['store_logo'];
	    		$goodslist[$k]['shopid']        = $goods['storeid'];
	    		$goodslist[$k]['guige']         = $v['guige'];
	    		$goodslist[$k]['utoken']        = $v['utoken'];
	    		if($goods['ispostage']=="1"){ //单品包邮
	    			if($goods['edareas']>'0'){ //不包邮地区
	    				$edareas = explode(",",$goods['edareas']);
	    				if(in_array($addressid,$edareas)){ //在不包邮地区
	    					if($goods['postagetype']=="0"){ //运费模板
			    				$goodslist[$k]['postageprice'] = "6.00";
			    			}else{
			    				$goodslist[$k]['postageprice'] = $goods['postageprice'];
			    			}
	    				}else{
	    					$goodslist[$k]['postageprice'] = "0.00";
	    				}
	    			}else{
	    				$goodslist[$k]['postageprice'] = "0.00";
	    			}
	    		}else{
	    			if($goods['postagetype']=="0"){ //运费模板
	    				$goodslist[$k]['postageprice'] = "6.00";
	    			}else{
	    				$goodslist[$k]['postageprice'] = $goods['postageprice'];
	    			}
	    			if($goods['ednum']>'0'){ //单品满件包邮
	    				if($v['nums']>=$goods['ednum']){
	    					$edareas = explode(",",$goods['edareas']);
		    				if(in_array($addressid,$edareas)){ //在不包邮地区
		    					if($goods['postagetype']=="0"){ //运费模板
				    				$goodslist[$k]['postageprice'] = "6.00";
				    			}else{
				    				$goodslist[$k]['postageprice'] = $goods['postageprice'];
				    			}
		    				}else{
		    					$goodslist[$k]['postageprice'] = "0.00";
		    				}
	    				}
		    		}
		    		if($goods['edmoney']>'0'){ //单品满额包邮
		    			if(($v['nums']*$goodslist[$k]['price'])>=$goods['edmoney']){
	    					$edareas = explode(",",$goods['edareas']);
		    				if(in_array($addressid,$edareas)){ //在不包邮地区
		    					if($goods['postagetype']=="0"){ //运费模板
				    				$goodslist[$k]['postageprice'] = "6.00";
				    			}else{
				    				$goodslist[$k]['postageprice'] = $goods['postageprice'];
				    			}
		    				}else{
		    					$goodslist[$k]['postageprice'] = "0.00";
		    				}
	    				}
		    		}
	    		}
	    	}
	    	$shoplist = array_unique($shoplist);
	    	$nshoplist = [];
	    	foreach($shoplist as $k=>$v){
	    		$nshoplist[$k]['key']       = $k;
	    		$nshoplist[$k]['shopname']  = $v;
                $nshoplist[$k]['shopid']    = 0;
	    		foreach($goodslist as $key=>$val){
	    			if($v==$val['shopname']){
                        $nshoplist[$k]['shopid'] = $val['shopid'];
	    				$nshoplist[$k]['shoplogo'] = $this->hostname.getImage($val['shoplogo']);
	    			}
	    		}
	    	}
	    	//计算金额，满减，邮费
	    	$price  = []; 
	    	$youfei = [];
	    	foreach($goodslist as $k=>$v){
	    		$price[]  = $v['price']*$v['number'];
	    		$youfei[] = $v['postageprice'];
	    	}
	    	$totalprice = sprintf('%.2f',round(array_sum($price),2));
	    	$totalyufei = sprintf('%.2f',round(array_sum($youfei),2));
	    	//检查是否满额包邮
	    	$baoyou = Db::name('sale')->field('id,man,edgoods,edareas')->where('id',1)->find();
	    	if($baoyou['edgoods']>'0'){ //不包邮商品
	    		$edgoods = explode(",",$baoyou['edgoods']);
	    		$by = true;
	    		foreach($goodslist as $k=>$v){
	    			if(in_array($v['id'],$edgoods)){
	    				$by = false;
	    			}
	    		}
	    		if($by==true){
	    			$totalyufei = '0.00';
	    		}
	    	}else{
	    		if($totalprice>=$baoyou['man']){
	    			$totalyufei = '0.00';
	    		}
	    	}
	    	if($baoyou['edareas']>'0'){ //不包邮地区
	    		$edareas = explode(",",$baoyou['edareas']);
	    		$bya = true;
	    		if(in_array($addressid,$edareas)){
	    			$bya = false;
	    		}
	    		if($bya==true){
	    			$totalyufei = '0.00';
	    		}
	    	}else{
	    		if($totalprice>=$baoyou['man']){
	    			$totalyufei = '0.00';
	    		}
	    	}
	    	//满额立减
	    	$quanid = isset($data['quanid']) ? $data['quanid'] : '0';
	    	if($quanid=="0"){
	    		$youhui = $this->getManjian($totalprice);
	    	}else{
	    		$youhui = '10.00';
	    	}

	    	//满额立减信息
	    	$manjian = $this->getManjiana($totalprice);
	    	$one['totalprice'] = $totalprice;
	    	$one['yunfei']     = $totalyufei;
	    	$one['youhui']     = $youhui;
	    	$one['shifuprice'] = sprintf('%.2f',$totalprice+$totalyufei-$youhui);
	    	$one['manjian']    = $manjian;
	    	return json(['code'=>0,'message'=>'SUCCESS','goodslist'=>$goodslist,'shoplist'=>$nshoplist,'address'=>$address,'one'=>$one]);
   		}else{
   			return json($token);
   		}
    }
    public function getManjian($totalprice=0){
    	$manjian = Db::name('sale')->field('id,man,jian')->where(['types'=>'manjian','is_show'=>'y'])->order('man desc')->select();
    	if($manjian){
    		foreach($manjian as $k=>$v){
    			if($totalprice>=$v['man']){
    				return $v['jian'];
    			}
    		}
    	}else{
    		return '0.00';
    	}
    }
    public function getManjiana($totalprice=0){
    	$manjian = Db::name('sale')->field('id,man,jian')->where(['types'=>'manjian','is_show'=>'y'])->order('man desc')->select();
    	if($manjian){
    		$aa = true;
    		foreach($manjian as $k=>$v){
    			if($totalprice>=$v['man']){
    				return array('man'=>$v['man'],'jian'=>$v['jian']);
    			}
    		}
    		return array('man'=>'0.00','jian'=>'0.00');
    	}else{
    		return array('man'=>'0.00','jian'=>'0.00');
    	}
    }
}