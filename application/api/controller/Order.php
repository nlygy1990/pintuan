<?php
namespace app\api\controller;
use \think\Controller;
use app\api\controller\Base;
use \think\Db;
use \think\Session;
use \think\Cookie;
use \think\Request;
use \think\AES;
class Order extends Base{
	public function __construct(){
		parent::__construct(); //使用父类的构造方法
    }
/************************************************************************/
/** 生成产品订单
/************************************************************************/
	public function GoodsOrder(){
		$data = input();
		$token = $this->CheckToken($data['token']);
   		if($token['code']=='0'){
   			$shop = [];
   			if(isset($data['shop'])){
   				$shop = json_decode($data['shop'],1);
   			}
   			if(!isset($data['goods'])){
				return json(['code'=>400001,'msg'=>'产品信息不能为空','returnData'=>'']);die;
			}
			$goods = json_decode($data['goods'],1);
			if(!isset($data['address'])){
				return json(['code'=>400002,'msg'=>'收货地址信息不能为空','returnData'=>'']);die;
			}
			$address = json_decode($data['address'],1);
			if(!isset($data['price'])){
				return json(['code'=>400002,'msg'=>'订单金额信息不能为空','returnData'=>'']);die;
			}
			$price = json_decode($data['price'],1);
			$quan = isset($data['quan']) ? $data['quan'] : '0';
			if($quan == "0"){
				$quanid = 0;
				$quanprice = 0;
			}else{
				$quan = json_decode($quan,1);
				if($quan){
					$quanid = $quan['id'];
					$quanprice = 0;
				}else{
					$quanid = 0;
					$quanprice = 0;
				}
			}
			/*****************************************************/
			//生成订单相关数据
			/*****************************************************/
			if(count($shop)==1){
				$orarr['shop_id'] = isset($shop[0]['shopid']) ? $shop[0]['shopid'] : 0;
				$orarr['is_show'] = "y";
			}
			$count = Db::name('order')->where('parent_id','0')->count();
			$ordersn = 'MT'.sprintf("%08d",($count+1));
			$orarr['user_id']    = $token['myinfo']['id'];
			$orarr['ordersn']    = $ordersn;
			$orarr['number']     = date('YmdHis').rand(1000,9999);
			$orarr['createtime'] = time();
			$orarr['createday']  = date('Ymd');
			$orarr['remark']     = isset($data['remark']) ? $data['remark'] : '';
			//收货人信息
			$orarr['consignee_name']     = $address['name'];
			$orarr['consignee_mobile']   = $address['phone'];
			$orarr['consignee_address']  = $address['diqu'].$address['address'].$address['menpai'];
			$orarr['consignee_province'] = $address['province'];
			$orarr['consignee_city']     = $address['city'];
			$orarr['consignee_area']     = $address['qu'];
			//优惠券信息
			$orarr['coupons_id']    = $quanid;
			$orarr['coupons_price'] = $quanprice;
			//价格信息
			$orarr['goodsprice']    = $price['totalprice']; //商品金额
			$orarr['postageprice']  = $price['yunfei'];     //邮费
			$orarr['discount']      = $price['youhui'];     //优惠金额
			$orarr['price']         = $price['shifuprice']; //应付金额
			$orarr['realprice']     = 0; //实付金额
			$orarr['balance']       = 0; //余额抵扣
			$orarr['points']        = 0; //积分抵扣
			$orderid = Db::name('order')->insertGetId($orarr);
			if($token['myinfo']['user_id']=="0"){
				$yiji['id']  = '0';
				$erji['id']  = '0';
				$sanji['id'] = '0';
			}else{
				$yiji = Db::name('member')->field('id,user_id')->where('id',$token['myinfo']['user_id'])->find();
				if($yiji['user_id']=='0'){
					$erji['id']  = '0';
					$sanji['id'] = '0';
				}else{
					$erji = Db::name('member')->field('id,user_id')->where('id',$yiji['user_id'])->find();
					if($erji['user_id']=="0"){
						$sanji['id'] = '0';
					}else{
						$sanji = Db::name('member')->field('id,user_id')->where('id',$erji['user_id'])->find();
					}
				}
			}
			$yijiarr  = [];
			$erjiarr  = [];
			$sanjiarr = [];
			if($orderid){
				$one['orderid'] = $orderid;
				$one['ordersn'] = $ordersn;
				//生成订单商品信息
				$goarr = [];
				foreach($goods as $k=>$v){
					$bl = round($v['price']/$price['totalprice'],6);
					$gone =  Db::name('goods')->field('id,title,total,isdistribution,distribution,distribution_2,distribution_3,fan_yue,fan_jifen')->where('id',$v['id'])->find();
					//判断减库存方式
					if($v['totalcnf']=="0"){ //下单立减库存
						if($v['guige']==''){
							$kuns = $gone;
							$upadd['total'] = $kuns['total']-$v['number'];
							$reip = Db::name('goods')->where('id',$v['id'])->update($upadd);
						}else{
							$kuns = Db::name('goods_options_item_desc')->field('total')->where('desc',$v['guige'])->find();
							$upadd['total'] = $kuns['total']-$v['number'];
							$reip = Db::name('goods_options_item_desc')->where('desc',$v['guige'])->update($upadd);
						}
					}else if($v['totalcnf']=="1"){ //付款立减库存

					}else{ //永不减库存

					}
					//平均优惠
					if($price['youhui']>'0'){
						$pjyouhui = round($bl*$price['youhui'],4);
					}else{
						$pjyouhui = 0;
					}
					//平均邮费
					if($price['yunfei']>'0'){
						$pjyunfei = round($bl*$price['yunfei'],4);
					}else{
						$pjyunfei = 0;
					}
					$goarr[$k]['user_id']          = $orarr['user_id'];
					$goarr[$k]['order_id']         = $orderid;
					$goarr[$k]['goods_id']         = $v['id'];
					$goarr[$k]['desc']             = isset($v['guige']) ? $v['guige'] : '';
					$goarr[$k]['goodssn']          = $v['goodssn'];
					$goarr[$k]['productsn']        = $v['productsn'];
					$goarr[$k]['title']            = $v['title'];
					$goarr[$k]['desc_title']       = isset($v['attr_val']) ? $v['attr_val'] : '';
					$goarr[$k]['image']            = str_replace($this->hostname,"",$v['image']);
					$goarr[$k]['marketprice']      = $v['price'];       // 商品单价
					$goarr[$k]['goodsprice']       = $v['goodsprice'];  //商品原价
					$goarr[$k]['realprice']        = $v['price']-$pjyouhui; //实付金额
					$goarr[$k]['discount']         = $pjyouhui;        //分配到的优惠金额
					$goarr[$k]['shopid']           = $v['shopid'];
					$goarr[$k]['shopname']         = $v['shopname'];
					$goarr[$k]['shoplogo']         = str_replace($this->hostname,"",$v['shoplogo']);
					$goarr[$k]['total']            = $v['number'];
					$goarr[$k]['postageprice']     = $v['postageprice'];
					$goarr[$k]['realpostageprice'] = $pjyunfei;
					//佣金计算
					if($gone['isdistribution']=="1"){ //参与分销
						$goarr[$k]['isdistribution'] = $gone['isdistribution'];
						if($yiji['id'] == "0"){
							$goarr[$k]['distribution']   = $yijiarr[]  = 0;
							$goarr[$k]['distribution_2'] = $erjiarr[]  = 0;
							$goarr[$k]['distribution_3'] = $sanjiarr[] = 0;
						}else{
							//一级佣金
							if(strstr($gone['distribution'], '%')){
								$yjbj = str_replace("%",'',$gone['distribution']);
								$goarr[$k]['distribution'] = $goarr[$k]['realprice']*($yjbj/100);
							}else{
								$goarr[$k]['distribution'] = $gone['distribution'];
							}
							$yijiarr[] = $goarr[$k]['distribution']*$v['number'];

							if($erji['id']=="0"){
								$goarr[$k]['distribution_2'] = $erjiarr[]  = 0;
								$goarr[$k]['distribution_3'] = $sanjiarr[] = 0;
							}else{
								//二级级佣金
								if(strstr($gone['distribution_2'], '%')){
									$yjbj = str_replace("%",'',$gone['distribution_2']);
									$goarr[$k]['distribution_2'] = $goarr[$k]['realprice']*($yjbj/100);
								}else{
									$goarr[$k]['distribution_2'] = $gone['distribution_2'];
								}
								$erjiarr[] = $goarr[$k]['distribution_2']*$v['number'];

								if($sanji['id']=="0"){
									$goarr[$k]['distribution_3'] = $sanjiarr[] = 0;
								}else{
									//三级佣金
									if(strstr($gone['distribution_3'], '%')){
										$yjbj = str_replace("%",'',$gone['distribution_3']);
										$goarr[$k]['distribution_3'] = $goarr[$k]['realprice']*($yjbj/100);
									}else{
										$goarr[$k]['distribution_3'] = $gone['distribution_3'];
									}
									$sanjiarr[] = $goarr[$k]['distribution_3']*$v['number'];
								}
							}
						}
					}
					//满返
					if($gone['fan_yue']>'0'){ //余额返现
						if(strstr($gone['fan_yue'],'%')){
							$fxbl = str_replace("%",'',$gone['fan_yue']);
							$goarr[$k]['fan_yue'] = $goarr[$k]['realprice']*($fxbl/100);
						}else{
							$goarr[$k]['fan_yue'] = $gone['fan_yue'];
						}
						$fxje = $goarr[$k]['fan_yue']*$v['number'];
						$fxarr['user_id']  = $token['myinfo']['user_id'];
	   					$fxarr['order_id'] = $orderid;
	   					$fxarr['order_sn'] = $ordersn;
	   					$fxarr['title']    = "商品：【".$gone['title'].'】余额返现';
	   					$fxarr['money']    = $fxje;
	   					$fxarr['createtime'] = time();
	   					$fxarr['createday']  = date("Ymd");
	   					$fxarr['types']      = "fanxian";
	   					$fxres = Db::name('member_distribution')->insert($fxarr);
					}
					if($gone['fan_jifen']>'0'){ //积分赠送
						if(strstr($gone['fan_jifen'],'%')){
							$jfbl = str_replace("%",'',$gone['fan_jifen']);
							$goarr[$k]['fan_jifen'] = $goarr[$k]['realprice']*($jfbl/100);
						}else{
							$goarr[$k]['fan_jifen'] = $gone['fan_jifen'];
						}
						$jfje = $goarr[$k]['fan_jifen']*$v['number'];
						$jfarr['uid'] = $token['myinfo']['user_id'];
						$jfarr['addtime']    = time();
						$jfarr['types']      = 1;
						$jfarr['jifen']      = $jfje;
						$jfarr['title']      = "商品：【".$gone['title'].'】积分赠送';
						$jfarr['description']= "商品：【".$gone['title'].'】积分赠送 +'.$jfje;
						$jfarr['day']        = date("Ymd");
						$jfarr['orderid']    = $orderid;
						$jfarr['status']     = "-1";
						$jfres = Db::name('member_jifen')->insert($jfarr);
					}
				}
				$resgoods = Db::name('order_goods')->insertAll($goarr);
				//生成日志
   				$this->paylog($orderid,'订单已生成','订单已生成，等待付款',$orarr['user_id']);
   				if(count($shop)==1){
	   				//生成分佣信息
	   				if($yiji['id']>"0"){ //一级分佣
	   					$yiarr['user_id']  = $yiji['id'];
	   					$yiarr['order_id'] = $orderid;
	   					$yiarr['order_sn'] = $ordersn;
	   					$yiarr['title']    = "一级分销奖励";
	   					$yiarr['money']    = round(array_sum($yijiarr),4);
	   					$yiarr['createtime'] = time();
	   					$yiarr['createday']  = date("Ymd");
	   					if($yiarr['money']>'0'){
	   						$yires = Db::name('member_distribution')->insert($yiarr);
	   					}
	   				}
	   				if($erji['id']>"0"){ //二级分佣
	   					$erarr['user_id']  = $erji['id'];
	   					$erarr['order_id'] = $orderid;
	   					$erarr['order_sn'] = $ordersn;
	   					$erarr['title']    = "二级分销奖励";
	   					$erarr['money']    = round(array_sum($erjiarr),4);
	   					$erarr['createtime'] = time();
	   					$erarr['createday']  = date("Ymd");
	   					if($erarr['money']>'0'){
	   						$erres = Db::name('member_distribution')->insert($erarr);
	   					}
	   				}
	   				if($sanji['id']>"0"){ //三级分佣
	   					$sanarr['user_id']  = $sanji['id'];
	   					$sanarr['order_id'] = $orderid;
	   					$sanarr['order_sn'] = $ordersn;
	   					$sanarr['title']    = "三级分销奖励";
	   					$sanarr['money']    = round(array_sum($sanjiarr),4);
	   					$sanarr['createtime'] = time();
	   					$sanarr['createday']  = date("Ymd");
	   					if($sanarr['money']>'0'){
	   						$sanres = Db::name('member_distribution')->insert($sanarr);
	   					}
	   				}
	   			}else{ //不同店铺实行分单
					foreach($shop as $k=>$v){
						$orarra['shop_id']    = isset($v['shopid']) ? $v['shopid'] : 0;
						$orarra['parent_id']  = $orderid;
						$orarra['user_id']    = $token['myinfo']['id'];
						$orarra['ordersn']    = $ordersn."-".sprintf("%02d",($k+1));
						$orarra['number']     = date('YmdHis').rand(1000,9999);
						$orarra['createtime'] = time();
						$orarra['createday']  = date('Ymd');
						$orarra['remark']     = isset($data['remark']) ? $data['remark'] : '';
						$orarra['is_show']    = "n";
						//收货人信息
						$orarra['consignee_name']     = $address['name'];
						$orarra['consignee_mobile']   = $address['phone'];
						$orarra['consignee_address']  = $address['diqu'].$address['address'].$address['menpai'];
						$orarra['consignee_province'] = $address['province'];
						$orarra['consignee_city']     = $address['city'];
						$orarra['consignee_area']     = $address['qu'];
						//优惠券信息
						$orarra['coupons_id']    = $quanid;
						$orarra['coupons_price'] = $quanprice;
						$orderida = Db::name('order')->insertGetId($orarra);
						if($orderida){
							$newgoods = Db::name('order_goods')->where('order_id',$orderid)->select();
							$newarr = [];
							$spjine = [];
							$yfjine = [];
							$yhjine = [];
							$yijine = [];
							$yijiarra = []; $erjiarra = []; $sanjiarra = [];
							foreach($newgoods as $ka=>$va){
								if($va['shopid']==$v['shopid']){
									unset($va['id']);
									$va['order_id'] = $orderida;
									$newarr[] = $va;
									$spjine[] = $va['marketprice']*$va['total'];
									$yfjine[] = $va['realpostageprice']*$va['total'];
									$yhjine[] = $va['discount']*$va['total'];
									$yijine[] = $va['realprice']*$va['total'];
									$yijiarra[]  = $va['distribution']*$va['total'];
									$erjiarra[]  = $va['distribution_2']*$va['total'];
									$sanjiarra[] = $va['distribution_3']*$va['total'];
								}
							}
							$resaaa = Db::name('order_goods')->insertAll($newarr);
							$norarr['goodsprice']   = array_sum($spjine);
							$norarr['postageprice'] = array_sum($yfjine);
							$norarr['discount']     = array_sum($yhjine);
							$norarr['price']        = array_sum($yijine);
							$upnres = Db::name('order')->where('id',$orderida)->update($norarr);
							$this->paylog($orderida,'订单已生成','订单已生成，等待付款',$orarr['user_id']);

							//生成分佣信息
							$yijifenyong = round(array_sum($yijiarra),4);
			   				if($yiji['id']>"0"){ //一级分佣
			   					$yiarr['user_id']  = $yiji['id'];
			   					$yiarr['order_id'] = $orderida;
			   					$yiarr['order_sn'] = $orarra['ordersn'];
			   					$yiarr['title']    = "一级分销奖励";
			   					$yiarr['money']    = $yijifenyong;
			   					$yiarr['createtime'] = time();
			   					$yiarr['createday']  = date("Ymd");
			   					if($yiarr['money']>'0'){
			   						$yires = Db::name('member_distribution')->insert($yiarr);
			   					}
			   				}
			   				$erjifenyong = round(array_sum($erjiarra),4);
			   				if($erji['id']>"0"){ //二级分佣
			   					$erarr['user_id']  = $erji['id'];
			   					$erarr['order_id'] = $orderida;
			   					$erarr['order_sn'] = $orarra['ordersn'];
			   					$erarr['title']    = "二级分销奖励";
			   					$erarr['money']    = $erjifenyong;
			   					$erarr['createtime'] = time();
			   					$erarr['createday']  = date("Ymd");
			   					if($erarr['money']>'0'){
			   						$erres = Db::name('member_distribution')->insert($erarr);
			   					}
			   				}
			   				$sanjifenyong = round(array_sum($sanjiarra),4);
			   				if($sanji['id']>"0"){ //三级分佣
			   					$sanarr['user_id']  = $sanji['id'];
			   					$sanarr['order_id'] = $orderida;
			   					$sanarr['order_sn'] = $orarra['ordersn'];
			   					$sanarr['title']    = "三级分销奖励";
			   					$sanarr['money']    = $sanjifenyong;
			   					$sanarr['createtime'] = time();
			   					$sanarr['createday']  = date("Ymd");
			   					if($sanarr['money']>'0'){
			   						$sanres = Db::name('member_distribution')->insert($sanarr);
			   					}
			   				}

						}
					}
				}
				return json(['code'=>0,'msg'=>'订单已生成','returnData'=>$one]);die;
			}else{
				return json(['code'=>400003,'msg'=>'生成失败','returnData'=>'']);die;
			}
   		}else{
   			return json($token);
   		}
	}
/************************************************************************/
/** 产品订单详情
/************************************************************************/
	public function getlist(){
		$data = input();
		$token = $this->CheckToken($data['token']);
   		if($token['code']=='0'){
   			$configs = Db::name('webconfig')->field('quxiaotime,shouhuotime,wanchengtime')->where('id','1')->find();
   			$limit = 10;
   			$pn = isset($data['pn']) ? $data['pn'] : 1;
   			$start = ($pn-1)*$limit;
   			$where[] = ['user_id','=',$token['myinfo']['id']];
   			$where[] = ['is_del','=','n'];
   			$where[] = ['is_show','=','y'];
   			$state = isset($data['state']) ? $data['state'] : '0';
   			if($state=="0"){ //全部订单

   			}else if($state=="1"){ //待付款
   				$where[] = ['status','=','0'];
   			}else if($state=="2"){ //待发货
   				$where[] = ['status','=','1'];
   			}else if($state=="3"){ //待收货
   				$where[] = ['status','=','2'];
   			}else if($state=="4"){ //评价
   				$where[] = ['status','in',[3,4]];
   			}else if($state=="5"){ //售后
   				$where[] = ['status','in',['-2','-3','-3','-4','-5']];
   			}

   			$list = Db::name('order')->field('id,user_id,ordersn,price,realprice,status,createtime,send_time,shou_time')->where($where)->limit($start,$limit)->order('createtime desc')->select();
   			foreach($list as $k=>$v){
   				orderZidan($v['id']);
   				$totals = [];
   				$goodsList = Db::name('order_goods')->field('id,title,desc_title,image,marketprice,goodsprice,realprice,total')->where('order_id',$v['id'])->order('id asc')->select();
   				foreach($goodsList as $key=>$val){
   					$goodsList[$key]['image'] = $this->hostname.$val['image'];
   					$totals[] = $val['total'];
   				}
   				//超时自动取消
				$createcha = time() - $v['createtime'];
				$quxiaotime = isset($configs['quxiaotime']) ? $configs['quxiaotime'] : 60;
				if($v['status']=="0" && $createcha>=(60*$quxiaotime)){
					$uparr['cancel_time'] = $v['createtime']+(60*$quxiaotime);
					$uparr['cancel_day']  = date("Ymd",($v['createtime']+(60*$quxiaotime)));
					$uparr['status']      = "-1";
					$res = Db::name('order')->where('id',$v['id'])->update($uparr);
					$this->paylog($v['id'],'订单已取消','订单超时自动取消',$v['user_id']);
					$list[$k]['status'] = $v['status'] = "-1";
					//更新分佣信息
					$yjarr['cancel_time'] = $uparr['cancel_time'];
					$yjarr['cancel_day']  = $uparr['cancel_day'];
					$yjarr['status']    = '-1';
					$yj = Db::name('member_distribution')->where('order_id',$v['id'])->update($yjarr);
					huankuncun($v['id']);
				}
				//超时自动收货
				$sendcha = time()-$v['send_time'];
				$shouhuotime = isset($configs['shouhuotime']) ? $configs['shouhuotime'] : 7;
				if($v['status']=="2" && $sendcha>=(60*60*24*$shouhuotime)){
					$uparr['shou_time'] = $v['send_time']+(60*60*24*$shouhuotime);
					$uparr['shou_day']  = date("Ymd",($v['send_time']+(60*60*24*$shouhuotime)));
					$uparr['status']      = "3";
					$res = Db::name('order')->where('id',$v['id'])->update($uparr);
					$this->paylog($v['id'],'订单已收货','订单已成功收货',$v['user_id']);
					$list[$k]['status'] = $v['status'] = "3";
					//更新分佣信息
					$yjarr['shou_time'] = $uparr['shou_time'];
					$yjarr['shou_day']  = $uparr['shou_day'];
					$yjarr['status']    = '3';
					$yj = Db::name('member_distribution')->where('order_id',$v['id'])->update($yjarr);
				}
				//超时自动完成
				$shoucha = time()-$v['shou_time'];
				$wanchengtime = isset($configs['wanchengtime']) ? $configs['wanchengtime'] : 7;
				if($v['status']=="3" && $shoucha>=(60*60*24*$wanchengtime)){
					$uparr['finish_time'] = $v['shou_time']+(60*60*24*$wanchengtime);
					$uparr['finish_day']  = date("Ymd",($v['shou_time']+(60*60*24*$wanchengtime)));
					$uparr['status']      = "4";
					$res = Db::name('order')->where('id',$v['id'])->update($uparr);
					$this->paylog($v['id'],'订单已完成','订单已完成，感谢您的支持',$v['user_id']);
					$list[$k]['status'] = "4";
					//更新分佣信息
					$yjarr['finish_time'] = $uparr['finish_time'];
					$yjarr['finish_day']  = $uparr['finish_day'];
					$yjarr['status']    = '4';
					$yj = Db::name('member_distribution')->where('order_id',$v['id'])->update($yjarr);
				}
				$list[$k]['createtime'] = date("Y-m-d H:i:s",$v['createtime']);
   				$list[$k]['nums']       = array_sum($totals);
   				$list[$k]['goodsList']  = $goodsList;
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
   			$one = Db::name('order')->field('id,user_id,ordersn,goodsprice,price,discount,postageprice,createtime,send_time,shou_time,status,pay_time,pay_transid,express_sn,consignee_name,consignee_mobile,consignee_address,createtime')->where('ordersn',$data['ordersn'])->find();
			$cha = time()-$one['createtime'];
			$waittime = 60*60;
			$chaa = $waittime-$cha;
      		if($chaa>0){
      			$one['cha'] = $chaa;
      		}else{
                $one['cha'] = 0;
            }
            orderZidan($one['id']);
            $configs = Db::name('webconfig')->field('quxiaotime,shouhuotime,wanchengtime')->where('id','1')->find();
			//超时自动取消
			$createcha = time() - $one['createtime'];
			$quxiaotime = isset($configs['quxiaotime']) ? $configs['quxiaotime'] : 60;
			if($one['status']=="0" && $createcha>=(60*$quxiaotime)){
				$uparr['cancel_time'] = $one['createtime']+(60*$quxiaotime);
				$uparr['cancel_day']  = date("Ymd",($one['createtime']+(60*$quxiaotime)));
				$uparr['status']      = "-1";
				$res = Db::name('order')->where('id',$one['id'])->update($uparr);
				$this->paylog($one['id'],'订单已取消','订单超时自动取消',$one['user_id']);
				$one['status'] = "-1";
				//更新分佣信息
				$yjarr['cancel_time'] = $uparr['cancel_time'];
				$yjarr['cancel_day']  = $uparr['cancel_day'];
				$yjarr['status']    = '-1';
				$yj = Db::name('member_distribution')->where('order_id',$one['id'])->update($yjarr);
				huankuncun($one['id']);
			}
			$one['createtime'] = date("Y-m-d H:i:s",$one['createtime']);
			//超时自动收货
			$sendcha = time() - $one['send_time'];
			$shouhuotime = isset($configs['shouhuotime']) ? $configs['shouhuotime'] : 7;
			if($one['status']=="2" && $sendcha>=(60*60*24*$shouhuotime)){
				$one['status'] = "3";
				$uparr['shou_time'] = $one['send_time']+(60*60*24*$shouhuotime);
				$uparr['shou_day']  = date("Ymd",($one['send_time']+(60*60*24*$shouhuotime)));
				$uparr['status']      = "3";
				$res = Db::name('order')->where('id',$one['id'])->update($uparr);
				$this->paylog($one['id'],'订单已收货','订单已成功收货',$one['user_id']);
				//更新分佣信息
				$yjarr['shou_time'] = $uparr['shou_time'];
				$yjarr['shou_day']  = $uparr['shou_day'];
				$yjarr['status']    = '3';
				$yj = Db::name('member_distribution')->where('order_id',$one['id'])->update($yjarr);
			}
			//超时自动完成
			$shoucha = time()-$one['shou_time'];
			$wanchengtime = isset($configs['wanchengtime']) ? $configs['wanchengtime'] : 7;
			if($one['status']=="3" && $shoucha>=(60*60*24*$wanchengtime)){
				$one['status'] = '4';
				$uparr['finish_time'] = $one['shou_time']+(60*60*24*$wanchengtime);
				$uparr['finish_day']  = date("Ymd",($one['shou_time']+(60*60*24*$wanchengtime)));
				$uparr['status']      = "4";
				$res = Db::name('order')->where('id',$one['id'])->update($uparr);
				$this->paylog($one['id'],'订单已完成','订单已完成，感谢您的支持',$one['user_id']);
				//更新分佣信息
				$yjarr['finish_time'] = $uparr['finish_time'];
				$yjarr['finish_day']  = $uparr['finish_day'];
				$yjarr['status']    = '4';
				$yj = Db::name('member_distribution')->where('order_id',$one['id'])->update($yjarr);
			}
			$wllist = $this->wuliu($one['id']);
			$goods = Db::name('order_goods')->field('id,shopid,shopname,shoplogo,total,goods_id,desc,title,desc_title,image,goodssn,productsn,marketprice,realprice')->where('order_id',$one['id'])->order('id asc')->select();
			$shoplist = [];$zaicimai = [];
			foreach($goods as $k=>$v){
				$shoplist[] = $v['shopname'];
				$goods[$k]['image'] = $this->hostname.$v['image'];
				$zaicimai[$k]['id']       = $v['goods_id'];
				$zaicimai[$k]['guige']    = $v['desc'];
				$zaicimai[$k]['nums']     = $v['total'];
				$zaicimai[$k]['utoken']   = "";
				$zaicimai[$k]['shopname'] = "";
			}
			$shoplist = array_unique($shoplist);
			$nshoplist = [];
	    	foreach($shoplist as $k=>$v){
	    		$nshoplist[$k]['key']       = $k;
	    		$nshoplist[$k]['shopname']  = $v;
                $nshoplist[$k]['shopid']    = 0;
	    		foreach($goods as $key=>$val){
	    			if($v==$val['shopname']){
                        $nshoplist[$k]['shopid'] = $val['shopid'];
	    				$nshoplist[$k]['shoplogo'] = $this->hostname.getImage($val['shoplogo']);
	    				$zaicimai[$key]['shopname'] = $v;
	    			}
	    		}
	    	}
	    	$one['goodsList'] = $goods;
	    	$one['shopslist'] = $nshoplist;
	    	$one['zaicimai']  = $zaicimai;
	    	if(!$one['discount']){
	    		$one['discount'] = "0.00";
	    	}
            return json(['code'=>0,'msg'=>'获取成功','returnData'=>$one,'wllist'=>$wllist]);
   		}else{
   			return json($token);
   		}
	}
	public function lookwuliu(){
		$data = input();
		$one = Db::name('order')->field('id,express_company,express_sn')->where('ordersn',$data['ordersn'])->find();
		$one['wuliu'] = $this->wuliu($one['id']);
		return json(['code'=>0,'msg'=>'获取成功','returnData'=>$one]);
	}
	public function wuliu($id){
		$one = Db::name('order')->field(['id','ordersn','express_sn','express'])->where(['id'=>$id])->find();
		$lista = Db::name('order_log')->field('id,title,content,createtime')->where('orderid',$id)->where('is_del','n')->select();
		$list = [];
		foreach($lista as $k=>$v){
			$v['createtime'] = date("Y.m.d H:i:s",$v['createtime']);
			$v['desc']       = $v['content'];
			$list[] = $v;
		}
		if($one['express_sn']){
			$wuliu = $this->kuai100($one['express'],$one['express_sn']);
	        if(!empty($wuliu) && !empty($wuliu['data'])){
	            foreach($wuliu['data'] as $k=>$v){
	                $a['title']       = $v['context'];
	                $a['content']     = $v['context'];
	                $a['createtime']  = str_replace("-",".",$v['ftime']);
	                $list[] = $a;
	            }
	        }
		}
        $list = $this->array_sort($list,'createtime','desc');
        return $list;
	}
	public function array_sort($array,$row,$type){
        $array_temp = array();
        foreach($array as $v){
            $array_temp[$v[$row]] = $v;
        }
        if($type == 'asc'){
            ksort($array_temp);
        }elseif($type='desc'){
            krsort($array_temp);
        }else{
        }
        $arr = [];
        foreach($array_temp as $k=>$v){
            $arr[] = $v;
        }
        return $arr;
    }
    public function kuai100($com,$num){
    	$api = Db::name('api_config')->field('id,customer,key')->where('id',1)->find();
        //参数设置
        $post_data = array();
        $post_data["customer"] = '25A394CC5DE059E467DEBD5EBB1AC213';
        $key= 'MDPDJELR8134' ;
        $post_data["param"] = '{"com":"'.$com.'","num":"'.$num.'"}';
        $url='http://poll.kuaidi100.com/poll/query.do';
        $post_data["sign"] = md5($post_data["param"].$key.$post_data["customer"]);
        $post_data["sign"] = strtoupper($post_data["sign"]);
        $o = "";
        foreach ($post_data as $k=>$v){
            $o.= "$k=".urlencode($v)."&";      //默认UTF-8编码格式
        }
        $post_data = substr($o,0,-1);
        $data = $this->http_posta($url,$post_data);
        $data = json_decode($data,1);
        return $data;
    }
	public function paywait(){
		$data = input();
		$token = $this->CheckToken($data['token']);
   		if($token['code']=='0'){
			$one = Db::name('order')->field('id,ordersn,goodsprice,price,discount,postageprice,createtime,status')->where('ordersn',$data['ordersn'])->find();
			$cha = time()-$one['createtime'];
			$waittime = 60*60;
			$chaa = $waittime-$cha;
      		if($chaa>0){
      			$one['cha'] = $chaa;
      		}else{
                $one['cha'] = 0;
            }
			//开启的支付方式
			$one['wxpayapi']  = Db::name('payment_api')->field('id,is_show')->where('id','1')->find();
			$one['alipayapi'] = Db::name('payment_api')->field('id,is_show')->where('id','2')->find();
			$one['yuepayapi'] = Db::name('payment_api')->field('id,is_show')->where('id','3')->find();
			$one['jifenpayapi'] = Db::name('payment_api')->field('id,is_show,man,bili,startman,endman')->where('id','4')->find();
			$one['user_yue']  = "0.00";
			$max_jifen = 0;
			$jifen = $this->getJifen($token['myinfo']['id']);
			if($one['jifenpayapi']['man']=="0"){ //不受订单限制
				if($one['jifenpayapi']['startman']=="0"){
					if($one['jifenpayapi']['endman']=="0"){
						$one['jifen_use'] = "all"; //订单满不限，积分开始不限，积分上限不限 可用
						$max_jifen = $jifen;
					}else if($jifen>=$one['jifenpayapi']['endman']){
						$one['jifen_use'] = "jifen-max"; //订单满不限 积分开始不限 积分上限有上限满足 可用
						$max_jifen = $one['jifenpayapi']['endman'];
					}else{
						$one['jifen_use'] = "all"; //可用 无上限
						$max_jifen = $jifen;
					}
				}else if($jifen>=$one['jifenpayapi']['startman']){ //如果用户积分大于开始可用积分
					if($one['jifenpayapi']['endman']=="0"){
						$one['jifen_use'] = "all"; //可用
						$max_jifen = $jifen;
					}else if($jifen>=$one['jifenpayapi']['endman']){
						$one['jifen_use'] = "jifen-max"; //可用 超过积分上限 只能用积分上限
						$max_jifen = $one['jifenpayapi']['endman'];
					}else{
						$one['jifen_use'] = "all"; //可用 无上限
						$max_jifen = $jifen;
					}
				}else{
					$one['jifen_use'] = "jifen-n"; //未达到最低使用积分不可用
					$max_jifen = 0;
				}
			}else if($one['price'] >= $one['jifenpayapi']['man']){
				if($one['jifenpayapi']['startman']=="0"){
					if($one['jifenpayapi']['endman']=="0"){
						$one['jifen_use'] = "all"; //可用
						$max_jifen = $jifen;
					}else if($jifen>=$one['jifenpayapi']['endman']){
						$one['jifen_use'] = "jinfen-max"; //可用 有上限
						$max_jifen = $one['jifenpayapi']['endman'];
					}else{
						$one['jifen_use'] = "all"; //可用 无上限
						$max_jifen = $jifen;
					}
				}else if($jifen>=$one['jifenpayapi']['startman']){ //如果用户积分大于开始可用积分
					if($one['jifenpayapi']['endman']=="0"){
						$one['jifen_use'] = "all"; //可用
						$max_jifen = $jifen;
					}else if($jifen>=$one['jifenpayapi']['endman']){
						$one['jifen_use'] = "jifen-max"; //可用 有上限
						$max_jifen = $one['jifenpayapi']['endman'];
					}else{
						$one['jifen_use'] = "all"; //可用 无上限
						$max_jifen = $jifen;
					}
				}else{
					$one['jifen_use'] = "jifen-n"; //不可用
					$max_jifen = 0;
				}
			}else{
				$one['jifen_use'] = "price-n"; //不可用
				$max_jifen = 0;
			}
			$one['user_jifen']  = $jifen;
			$one['jifen_max']   = $max_jifen;
			if($one['status']=="0"){
				return json(['code'=>0,'msg'=>'获取成功','returnData'=>$one]);
			}else if(in_array($one['status'],['1','2','3','4','5'])){
				return json(['code'=>1,'msg'=>'订单已支付','returnData'=>$one]);
			}else{
				return json(['code'=>2,'msg'=>'订单已关闭不能支付','returnData'=>$one]);
			}
		}else{
			return json($token);
		}
	}
	public function getJifen($uid){
	   	$jifenlist = Db::name('member_jifen')->field(['types','jifen'])->where(['uid'=>$uid])->order('addtime asc')->select();
	   	$addarr = [];
	   	$delarr = [];
	   	foreach($jifenlist as $k=>$v){
	   		if($v['types']=="1"){
	   			$addarr[] = $v['jifen'];
	   		}else{
	   			$delarr[] = $v['jifen'];
	   		}
	   	}
	   	return array_sum($addarr)-array_sum($delarr);
   	}

	public function deleteOrder(){
		$data = input();
		$token = $this->CheckToken($data['token']);
   		if($token['code']=='0'){
   			$uparr['is_del'] = 'y';
   			$res = Db::name('order')->where(['id'=>$data['id'],'status'=>'-1'])->update($uparr);
   			if($res){
   				//生成日志
   				$this->paylog($data['id'],'订单已删除','订单删除成功（备注：客户操作删除）',$token['myinfo']['id']);
   				return json(['code'=>0,'msg'=>'删除成功']);
   			}else{
   				return json(['code'=>1,'msg'=>'订单已不能删除']);
   			}
   		}else{
   			return json($token);
   		}
	}

	//取消订单
	public function cancel(){
		$data = input();
		$tui  = isset($data['tui']) ? $data['tui'] : '0';
		$token = $this->CheckToken($data['token']);
   		if($token['code']=='0'){
   			$uparr['status'] = "-1";
   			$uparr['cancel_time'] = time();
   			$uparr['cancel_day']  = date('Ymd');
   			$res = Db::name('order')->where(['ordersn'=>$data['ordersn'],'status'=>'0'])->update($uparr);
   			if($res){
   				huankuncun($data['orderid']);
   				//生成日志
   				$this->paylog($data['orderid'],'订单已取消','订单取消成功，备注：客户手动取消',$token['myinfo']['id']);
   				$count = Db::name('order')->where('parent_id',$data['orderid'])->count();
   				if($count>0){
   					$zidans = Db::name('order')->field('id,user_id')->where('parent_id',$data['orderid'])->select();
   					$yids = [];
					$yids[] = 0;
					foreach($zidans as $k=>$v){
						$this->paylog($v['id'],'订单已取消','订单取消成功，备注：客户手动取消',$v['user_id']);
						$yids[] = $v['id'];
					}
					//更新分佣信息
					$yjarr['cancel_time'] = $uparr['cancel_time'];
					$yjarr['cancel_day']  = $uparr['cancel_day'];
					$yjarr['status']    = '-1';
					$yj = Db::name('member_distribution')->where('order_id','in',$yids)->update($yjarr);
   				}else{
   					//更新分佣信息
					$yjarr['cancel_time'] = $uparr['cancel_time'];
					$yjarr['cancel_day']  = $uparr['cancel_day'];
					$yjarr['status']    = '-1';
					$yj = Db::name('member_distribution')->where(['order_sn'=>$data['ordersn']])->update($yjarr);
   				}
   				//退款
   				if($tui>'0'){
   					$url = $this->hostname.url('api/payment/refund',['orderid'=>$data['id']]);
   					// $res = $this->get_contents($url);
   				}
   				return json(['code'=>0,'msg'=>'取消成功']);
   			}else{
   				return json(['code'=>1,'msg'=>'订单已不能取消','returnData'=>$one]);
   			}
   		}else{
   			return json($token);
   		}
	}

	//确认收货
	public function shouhuo(){
		$data = input();
		$token = $this->CheckToken($data['token']);
   		if($token['code']=='0'){
   			$uparr['status'] = "3";
   			$uparr['shou_time'] = time();
   			$uparr['shou_day']  = date('Ymd');
   			$res = Db::name('order')->where(['ordersn'=>$data['ordersn'],'status'=>'2'])->update($uparr);
   			if($res){
   				//生成日志
   				$this->paylog($data['orderid'],"订单已收货","订单成功收货(备注：客户确认收货)",$token['myinfo']['id']);
   				//更新分佣信息
				$yjarr['shou_time'] = $uparr['shou_time'];
				$yjarr['shou_day']  = $uparr['shou_day'];
				$yjarr['status']    = '3';
				$yj = Db::name('member_distribution')->where(['order_sn'=>$data['ordersn']])->update($yjarr);
   				return json(['code'=>0,'msg'=>'操作成功']);
   			}else{
   				return json(['code'=>1,'msg'=>'订单已不能收货','returnData'=>$one]);
   			}
   		}else{
   			return json($token);
   		}
	}
	//确认完成
	public function wancheng(){
		$data = input();
		$token = $this->CheckToken($data['token']);
   		if($token['code']=='0'){
   			$uparr['status'] = "4";
   			$uparr['finish_time'] = time();
   			$uparr['finish_day']  = date('Ymd');
   			$res = Db::name('order')->where(['ordersn'=>$data['ordersn'],'status'=>'3'])->update($uparr);
   			if($res){
   				//生成日志
   				$this->paylog($data['orderid'],"订单已完成","订单已完成，感谢您的支持（备注：客户确认完成）",$token['myinfo']['id']);
   				//更新分佣信息
				$yjarr['finish_time'] = $uparr['finish_time'];
				$yjarr['finish_day']  = $uparr['finish_day'];
				$yjarr['status']    = '4';
				$yj = Db::name('member_distribution')->where(['order_sn'=>$data['ordersn']])->update($yjarr);

   				return json(['code'=>0,'msg'=>'操作成功']);
   			}else{
   				return json(['code'=>1,'msg'=>'订单已不能收货','returnData'=>$one]);
   			}
   		}else{
   			return json($token);
   		}
	}
/***********************************************************************************************/
/** 评价相关
/***********************************************************************************************/
	public function addPingjia(){
		$data = input();
		$token = $this->CheckToken($data['token']);
   		if($token['code']=='0'){
   			$add['user_id']  = $token['myinfo']['id'];
   			$add['ordersn']  = $data['ordersn'];
   			$order = Db::name('order')->field('id')->where('ordersn',$data['ordersn'])->find();
   			$add['order_id'] = $order['id'];
   			$add['score']    = $data['score'];
   			$add['content']  = $data['textareaValue'];
   			$add['createtime'] = time();
   			$add['createday']  = date("Ymd");
   			$add['pics']       = isset($data['pic']) ? $data['pic'] : '';
   			$goods = Db::name('order_goods')->field('goods_id,title,desc_title')->where('order_id',$order['id'])->select();
   			$addarr = [];
   			foreach($goods as $k=>$v){
   				$addarr[$k] = $add;
   				$addarr[$k]['goods_id'] = $v['goods_id'];
   				$addarr[$k]['goods']    = json_encode($v,JSON_UNESCAPED_UNICODE);
   			}
   			$res = Db::name('order_pingjia')->insertAll($addarr);
   			if($res){
   				$uparr['status'] = "4";
	   			$uparr['finish_time'] = time();
	   			$uparr['finish_day']  = date('Ymd');
	   			$resa = Db::name('order')->where(['ordersn'=>$data['ordersn'],'status'=>'3'])->update($uparr);
	   			if($resa){
	   				//生成日志
	   				$this->paylog($order['id'],"订单已完成","订单已完成，感谢您的支持（备注：客户已评价）",$token['myinfo']['id']);
	   				//更新分佣信息
					$yjarr['finish_time'] = $uparr['finish_time'];
					$yjarr['finish_day']  = $uparr['finish_day'];
					$yjarr['status']    = '4';
					$yj = Db::name('member_distribution')->where(['order_sn'=>$data['ordersn']])->update($yjarr);
	   			}
   				return json(['code'=>0,'msg'=>'操作成功']);
   			}else{
   				return json(['code'=>1,'msg'=>'操作失败']);
   			}
   		}else{
   			return json($token);
   		}
	}
	public function pingjia(){
		$data = input();
		$pn = isset($data['pn']) ? $data['pn'] :1;
		$limit = 10;
		$start = ($pn-1)*$limit;
		$list = Db::name('order_pingjia')->where(['is_del'=>'n','is_show'=>'y','goods_id'=>$data['id']])->order('createtime desc')->limit($start,$limit)->select();
		$pingjiacount = Db::name('order_pingjia')->where(['is_del'=>'n','is_show'=>'y','goods_id'=>$data['id']])->count();
		$haoping      = Db::name('order_pingjia')->where(['is_del'=>'n','is_show'=>'y','goods_id'=>$data['id']])->where('score','>=',4)->count();
		$plist = Db::name('order_pingjia')->field('id,score')->where(['is_del'=>'n','is_show'=>'y','goods_id'=>$data['id']])->select();
		$fen = [];
		foreach($plist as $k=>$v){
			$fen[] = $v['score'];
		}
		$zongfen = array_sum($fen);
		if($pingjiacount=="0"){
			$pjun    = 0.0;
		}else{
			$pjun    = round($zongfen/$pingjiacount,1);
		}
		if($list){
			$nlist = [];
			foreach($list as $k=>$v){
				$user = Db::name('member')->field('id,username,nickname,head_pic,avatar')->where('id',$v['user_id'])->find();
                if($user['head_pic']){
                    $user['head_pic'] = $this->hostname.getImage($user['head_pic']);
                }else{
                    if($user['avatar']){
                        $user['head_pic'] = $user['avatar'];
                    }else{
                        $user['head_pic'] = '';
                    }                           
                }
                if($v['pics']!=''){
                	$pics = explode(",",$v['pics']);
                	foreach($pics as $key=>$val){
                		$pics[$key] = $this->hostname.str_replace("\\","/",$val);
                	}
                }else{
                	$pics = '';
                }
				$nlist[$k]['content']     = $v['content'];
				$nlist[$k]['create_time'] = date('Y-m-d H:i:s',$v['createtime']);
				$nlist[$k]['header_img']  = $user['head_pic']?$user['head_pic']:'../../static/user_head.png';
				$nlist[$k]['user_name']   = $user['nickname'];
				$nlist[$k]['rate']        = $v['score'];
				$nlist[$k]['imgs']        = $pics;
			}
			return json(['code'=>0,'list'=>$nlist,'msg'=>'获取成功','pingjiacount'=>$pingjiacount,'pingjun'=>$pjun]);
		}else{
			return json(['code'=>1,'list'=>$list,'msg'=>'已经没有更多了','pingjiacount'=>$pingjiacount,'pingjun'=>$pjun]);
		}
	}
	public function paylog($orderid,$title,$content,$uid){
		//生成订单日志
		$log['orderid'] = $orderid;
		$log['userid']  = $uid;
		$log['title']   = $title;
		$log['content'] = $content;
		$log['createtime'] = time();
		$log['createday']  = date('Ymd');
		$res = Db::name('order_log')->insert($log);
		//发送站内新
		$one = Db::name('order')->field('id,ordersn,number')->where('id',$orderid)->find();
		$uparr['orderid'] = $orderid;
		$uparr['userid']  = $uid;
		$uparr['title']   = $title."，订单号：".$one['ordersn'];
		$uparr['desc']    = $content;
		$uparr['content'] = $content;
		$uparr['sendtime'] = time();
		$uparr['sendday']  = date('Ymd');
		$uparr['types']    = 'order';
		$uparr['createtime'] = time();
		$uparr['createday']  = date('Ymd');
		$resa = Db::name('member_message')->insert($uparr);
		if($res){
			return true;
		}else{
			return false;
		}
	}
}