<?php
namespace app\api\controller;
use \think\Controller;
use app\api\controller\Base;
use \think\Db;
use \think\Session;
use \think\Cookie;
use \think\Request;
use \think\AES;
class Payment extends Base{
	public function __construct(){
		parent::__construct(); //使用父类的构造方法
    }
    public function wxrefound(){
		$data = input();
		$pn = isset($data['pn']) ? $data['pn'] : 1;
		$limit = 100;
		$start = ($pn-1)*$limit;
		$list = Db::name('groups_order')->field('id,pay_transid,pay_money,pay_ordersn')->where('pay_money','10')->where('refund_time','0')->limit($start,$limit)->select();
		$pp = 0;
		$env = $this->request->env();
		require $env['ROOT_PATH'].'extend/wxpay/index_refound.php';
		foreach($list as $k=>$one){
			$money = $one['pay_money']*100;
			$refound_no = date('YmdHis').rand(1000,9999);
			$out_trade_no = $one['pay_ordersn'];
			$unifiedOrder->SetOut_trade_no($out_trade_no);
			$unifiedOrder->SetTotal_fee($money);
			$unifiedOrder->SetRefund_fee($money);
			$unifiedOrder->SetOut_refund_no($refound_no);
			$unifiedOrder->SetOp_user_id('1230');
			$result = $WxPayApi->refund($unifiedOrder);
			if(isset($result['result_code']) && ($result['result_code']=="SUCCESS" || $result['result_code']=="TRADE_SUCCESS")){
				$rearr['refund_time']    = time();
				$rearr['refund_day']     = date("Ymd");
				$rearr['refund_status']  = 1;
				$rearr['refund_price']   = $one['pay_money'];
				$rearr['refund_express'] = $refound_no;
				$ups = Db::name('groups_order')->where('id',$one['id'])->update($rearr);
				$pp++;
 			}
		}
		echo "本次退款成功".$pp.'笔';
	}
/*********************************************************************/
/** 获取支付参数
/*********************************************************************/
	public function index(){
		$data = input();
		$id      = isset($data['id']) ? $data['id'] : '0';
		$paytype = isset($data['paytype']) ? $data['paytype'] : 'wxpay';
		$types   = isset($data['types']) ? $data['types'] : 'goods';
		//获取产品信息
		if($types=="goods"){
			$one = Db::name('order')->field('id,price')->where(['ordersn'=>$id])->find();
			$goodslist = Db::name('order_goods')->field('title')->where('order_id',$one['id'])->order('id asc')->limit(1)->find();
			$one['marketprice'] = $one['price'];
			$one['title']       = str_cut1($goodslist['title'],0,100,'utf-8','等商品');
			$goodsid = 0;
			$orderid = $one['id'];
		}else if($types=="groups_goods"){
			$one = Db::name('groups_order')->alias('g')
				->field('g.id,g.price,m.wx_openid,g.createtime,g.status')
				->join('member m','m.id=g.user_id','left')
				->where(['g.ordersn'=>$id])->find();
			$cha = time()-$one['createtime'];
			if($one['status']!="0"){
				return json(['code'=>500001,'message'=>"订单已不能支付",'returnData'=>'']);die;
			}
			if($cha>300){
				return json(['code'=>500001,'message'=>"支付超时",'returnData'=>'']);die;
			}

			$goodslist = Db::name('groups_order_goods')->field('title')->where('order_id',$one['id'])->order('id asc')->limit(1)->find();
			$one['marketprice'] = $one['price'];
			$one['title']       = str_cut1($goodslist['title'],0,100,'utf-8','');
			$goodsid = 0;
			$orderid = $one['id'];
		}else if($types=="goods_items"){
			$one = Db::name('goods_options_item_desc')->alias('a')
					->field('a.marketprice,a.desc_title,ac.title,ac.id')
					->join('goods ac', 'ac.id=a.goodsid', 'left')
					->where(['a.desc'=>$id])->find();
			$one['title'] = $one['title'].$one['desc_title'];
			$goodsid = $one['id'];
			$orderid = 0;
		}else if($types=="stores_goods"){
			$one = Db::name('stores_goods')->field('id,marketprice,title')->where(['id'=>$id])->find();
			$goodsid = $one['id'];
			$orderid = 0;
		}
		//生成支付历史信息
		$paylog['types']        = $types;
		$paylog['pay_ordersn']  = $ordersn = date('YmdHis').rand(100,999);
		$paylog['paytype']      = $paytype;
		$paylog['goodsid']      = $goodsid;
		$paylog['orderid']      = $orderid;
		$paylog['price']        = $one['marketprice'];
		$paylog['body']         = $one['title'];
		$paylog['sendtime']     = time();
		$paylog['sendday']      = date('Ymd');
		$paylog['createtime']   = time();
		$paylog['createday']    = date('Ymd');
		$chekpay = Db::name('order_payment_log')->field('id')->where(['types'=>$types,'paytype'=>$paytype,'orderid'=>$orderid,'goodsid'=>$goodsid])->find();
		if($chekpay && $types=="goods"){
			$reslog = Db::name('order_payment_log')->where('id',$chekpay['id'])->update($paylog);
		}else if($chekpay && $types=="groups_goods"){
			$reslog = Db::name('order_payment_log')->where('id',$chekpay['id'])->update($paylog);
		}else{
			$reslog = Db::name('order_payment_log')->insert($paylog);
		}
		//获取接口信息
		if($paytype=="wxpay"){ //微信app支付
			$res = $this->Wxpay($one,$ordersn);
			return json(['code'=>0,'message'=>"SUCCESS",'returnData'=>$res,'ordersn'=>$ordersn]);
		}else if($paytype=="wxpayMp"){ //微信小程序支付
			$res = $this->WxpayMp($one,$ordersn);
			$res = json_decode($res,1);
			$wechat = Db::name('payment_api')->field('id,title,appid,mch_id,apikey,cert,key_f,root_f,is_show')->where('id',1)->find();
			$res['paySign'] = MD5('appId='.$wechat['appid'].'&nonceStr='.$res['noncestr'].'&package=prepay_id='.$res['prepayid'].'&signType=MD5&timeStamp='.$res['timestamp'].'&key='.$wechat['apikey']);
			return json(['code'=>0,'message'=>"SUCCESS",'returnData'=>$res,'ordersn'=>$ordersn]);
		}else if($paytype=="alipay"){
			$res = $this->Alipay($one,$ordersn);
			return json(['code'=>0,'message'=>"SUCCESS",'returnData'=>$res,'ordersn'=>$ordersn]);
		}else{
			return json(['code'=>500001,'message'=>"Payment doesn't exist",'returnData'=>'']);
		}
	}
	public function checkpay(){
		$data = input();
		$token = $this->CheckToken($data['token']);
   		if($token['code']=='0'){
   			$one = Db::name('order')->field('id,status')->where('ordersn',$data['ordersn'])->find();
			if($one){
				if(in_array($one['status'],['1','2','3','4'])){
					return json(['code'=>0,'msg'=>'订单已支付']);
				}else if($one['status']=='0'){
					if($data['paytype']=='alipay'){
						$res = $this->AlipayCheck($one['id']);
						return json($res);
					}else if($data['paytype']=='wxpay'){
						$res = $this->WxpayCheck($one['id']);
						return json($res);
					}else if($data['paytype']=='wxpayMp'){

					}else{

					}
				}else if($one['status']=="-1"){
					return json(['code'=>12,'msg'=>'订单已取消']);
				}else{
					return json(['code'=>13,'msg'=>'订单已进入维权']);
				}
			}else{
				return json(['code'=>11,'msg'=>'订单错误']);
			}
   		}else{
   			return json($token);
   		}
	}
	public function checkpayg(){
		$data = input();
		$token = $this->CheckToken($data['token']);
   		if($token['code']=='0'){
   			$one = Db::name('groups_order')->field('id,status')->where('ordersn',$data['ordersn'])->find();
			if($one){
				if(in_array($one['status'],['1','2','3','4'])){
					return json(['code'=>0,'msg'=>'订单已支付']);
				}else if($one['status']=='0'){
					return json(['code'=>10,'msg'=>'订单未支付']);
					// if($data['paytype']=='alipay'){
					// 	$res = $this->AlipayCheck($one['id']);
					// 	return json($res);
					// }else if($data['paytype']=='wxpay'){
					// 	$res = $this->WxpayCheck($one['id']);
					// 	return json($res);
					// }else if($data['paytype']=='wxpayMp'){
					// 	$res = $this->WxMppayCheck($one['id']);
					// 	return json($res);
					// }else{

					// }
				}else if($one['status']=="-1"){
					return json(['code'=>12,'msg'=>'订单已取消']);
				}else{
					return json(['code'=>13,'msg'=>'订单已进入维权']);
				}
			}else{
				return json(['code'=>11,'msg'=>'订单错误']);
			}
   		}else{
   			return json($token);
   		}
	}
	//订单退款
	public function refund(){
		$data = input();
		$one = Db::name('order')->field('id,pay_status,pay_ordersn,pay_time,pay_transid,pay_money,payment_type,price')->where('id',$data['orderid'])->find();
		$refund_amount = 0.01;
		if($one['pay_status']=="SUCCESS" || $one['pay_status']=="TRADE_SUCCESS"){
			if($one['pay_type']=="alipay"){
				
				$aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
				$aop->appId = 'your app_id';
				$aop->rsaPrivateKey = '请填写开发者私钥去头去尾去回车，一行字符串';
				$aop->alipayrsaPublicKey='请填写支付宝公钥，一行字符串';
				$aop->apiVersion = '1.0';
				$aop->signType = 'RSA2';
				$aop->postCharset='GBK';
				$aop->format='json';		
				$request->setBizContent("{" .
					"\"out_trade_no\":\"".$one['pay_ordersn']."\"," .
					"\"trade_no\":\"".$one['pay_transid']."\"," .
					"\"refund_amount\":".$refund_amount."," .
					"\"refund_currency\":\"\"," .
					"\"refund_reason\":\"正常退款\"}");
				$result = $aop->execute ( $request); 
				$responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
				$resultCode = $result->$responseNode->code;
				if(!empty($resultCode)&&$resultCode == 10000){
					echo "成功";
				} else {
					echo "失败";
				}
			}else if($one['pay_type']=="wxpay"){

			}
		}else{
			return json(['code'=>1,'msg'=>'未支付不可退']);
		}
	}
	//预约查询付款状态
	public function checkpaya(){
		$data = input();
		$token = $this->CheckToken($data['token']);
   		if($token['code']=='0'){
   			$data['ordersn'] = isset($data['ordersn']) ? $data['ordersn'] : '';
   			$data['paytype'] = isset($data['paytype']) ? $data['paytype'] : 'alipay';
   			$one = Db::name('order_payment_log')->field('id,goodsid,price,pay_ordersn,pay_status,pay_body')->where(['pay_ordersn'=>$data['ordersn'],'paytype'=>$data['paytype'],'types'=>'stores_goods'])->order('createtime desc')->limit(1)->find();
   			if($data['paytype']=="wxpay"){

   			}else if($data['paytype']=="wxpayMp"){

   			}else if($data['paytype']=="alipay"){
   				if($one['pay_status']=="SUCCESS" || $one['pay_status']=="TRADE_SUCCESS"){
   					$ayy = array('code'=>0,'msg'=>'支付成功。');
   					return json($ayy);
   				}else{
   					$env = $this->request->env();
					require $env['ROOT_PATH'].'extend/alipay/indexc.php';
					$payment = Db::name('payment_api')->field('is_show,mch_id,apikey,cert,key_f,root_f',true)->where('id',2)->find();
					$aop->gatewayUrl         = 'https://openapi.alipay.com/gateway.do';
					$aop->appId              = $payment['appid'];
					$aop->rsaPrivateKey      = $payment['private_key'];
					$aop->alipayrsaPublicKey = $payment['public_key'];
					$aop->apiVersion = '1.0';
					$aop->signType = 'RSA2';
					$aop->postCharset='UTF-8';
					$aop->format='json';
					$request->setBizContent("{" .
								"\"out_trade_no\":\"".$one['pay_ordersn']."\"," .
								"\"trade_no\":\"\"," .
								"\"org_pid\":\"\"," .
								"\"query_options\":[" .
								"\"TRADE_SETTE_INFO\"" .
								"]" .
								"}");
					$result = $aop->execute ($request); 
					$responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
					$resultCode = $result->$responseNode->code;
					$resdata = $result->$responseNode;
					$resdata = $this->object2array($resdata);
					if(!empty($resultCode)&&$resultCode==10000){
						$goods = Db::name('stores_goods')->field('is_show,is_del,updatetime,update_admin,addtime,admin_id,orders',true)->where('id',$one['goodsid'])->find();
						$uparra['pay_status']   = $resdata['trade_status'];
			            $uparra['pay_transid']  = $resdata['trade_no'];
			            $uparra['pay_time']     = strtotime($resdata['send_pay_date']);
			            $uparra['pay_body']     = json_encode($resdata,JSON_UNESCAPED_UNICODE);
			            $uparra['status']       = 1;
			            $upres = Db::name('order_payment_log')->where('id',$one['id'])->update($uparra);
			            $orarr['user_id']     = $token['myinfo']['id'];
			            $orarr['stores_id']   = $goods['stores_id'];
			            $orarr['goodsid']     = $one['goodsid'];
			            $orarr['ordersn']     = $one['pay_ordersn'];
			            $orarr['price']       = $one['price'];
			            $orarr['realprice']   = $resdata['total_amount'];
			            $orarr['pay_transid'] = $uparra['pay_transid'];
			            $orarr['pay_status']  = $uparra['pay_status'];
			            $orarr['pay_time']    = $uparra['pay_time'];
			            $orarr['pay_day']     = date("Ymd",$uparra['pay_time']);
			            $orarr['pay_body']    = json_encode($resdata,JSON_UNESCAPED_UNICODE);
			            $orarr['pay_type']    = "alipay";
			            $orarr['createtime']  = time();
			            $orarr['createday']   = date("Ymd");
			            $cor = Db::name('order_stores')->count();
			            $orarr['no']          = 'MTY'.sprintf("%08d",($cor+1));
			            $orarr['goods']       = json_encode($goods,JSON_UNESCAPED_UNICODE);
			            $orres = Db::name('order_stores')->insert($orarr);
			            if($orres){
			            	$ayy = array('code'=>0,'msg'=>'支付成功！');
			            }else{
			            	$ayy = array('code'=>11,'msg'=>'支付成功,请联系管理员');
			            }
						return json($ayy);
					}else{
						$ayy = array('code'=>10001,'msg'=>'支付失败:'.$resdata['sub_msg']);
						return json($ayy);
					}
	   			}
   			}else{

   			}
   		}else{
   			return json($token);
   		}
	}
/*********************************************************************/
/** 微信支付
/*********************************************************************/
	public function Wxpay($detail,$ordersn){
		$total = $detail['marketprice']*100;
		// 商品名称
		$env = $this->request->env();
		require $env['ROOT_PATH'].'extend/wxpay/index.php';
		$subject = $detail['title'];
		// 订单号，示例代码使用时间值作为唯一的订单ID号
		$out_trade_no = $ordersn;
		$unifiedOrder->SetBody($subject);//商品或支付单简要描述
		$unifiedOrder->SetOut_trade_no($out_trade_no);
		$unifiedOrder->SetTotal_fee($total);
		$unifiedOrder->SetTrade_type("APP");
		$result = $WxPayApi->unifiedOrder($unifiedOrder);
		if (is_array($result)) {
		    return json_encode($result);
		}else{
			return false;
		}
	}
	public function WxpayMp($detail,$ordersn){
		$total = $detail['marketprice']*100;
		//$total = 1;
		// 商品名称
		$env = $this->request->env();
		require $env['ROOT_PATH'].'extend/wxpay/index.php';
		$subject = $detail['title'];
		// 订单号，示例代码使用时间值作为唯一的订单ID号
		$out_trade_no = $ordersn;
		$unifiedOrder->SetOpenId($detail['wx_openid']);//商品或支付单简要描述
		$unifiedOrder->SetBody($subject);//商品或支付单简要描述
		$unifiedOrder->SetOut_trade_no($out_trade_no);
		$unifiedOrder->SetTotal_fee($total);
		$unifiedOrder->SetTrade_type("JSAPI");
		$result = $WxPayApi->unifiedOrder($unifiedOrder);
		if (is_array($result)) {
		    return json_encode($result);
		}else{
			return false;
		}
	}
	public function wxnotify(){
		$xml = file_get_contents("php://input");
		$res1 = simplexml_load_string($xml,'SimpleXMLElement',LIBXML_NOCDATA);
        $res = json_decode(json_encode($res1),true);
        if ($res['result_code'] == 'SUCCESS') {
            $out_trade_no = $res['out_trade_no'];
            $one = Db::name('order_payment_log')->field('orderid')->where('pay_ordersn',$out_trade_no)->find();
            $this->WxMppayCheck($one['orderid']);
            echo exit("<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>");
        }
	}
	public function WxpayCheck($id){
		$id = $this->request->param('id') ? $this->request->param('id') : $id;
		$one = Db::name('order_payment_log')->field('id,pay_ordersn,pay_status,pay_body')->where(['orderid'=>$id,'paytype'=>'wxpay'])->order('createtime desc')->limit(1)->find();
		if($one){

		}else{

		}
	}
	public function WxMppayCheck($id){
		$dataa = input();
		$id = isset($dataa['id']) ? $dataa['id'] : $id;
		$ty = isset($dataa['ty']) ? $dataa['ty'] : 'qt';
		$one = Db::name('order_payment_log')->field('id,pay_ordersn,pay_status,pay_body')->where(['orderid'=>$id,'paytype'=>'wxpayMp'])->order('createtime desc')->limit(1)->find();
		if($one){
			$order = Db::name('groups_order')->field('user_id,ordersn')->where('id',$id)->find();
			if($one['pay_status']=="SUCCESS" || $one['pay_status']=="TRADE_SUCCESS"){
				$result = json_decode($one['pay_body'],1);
				$res = $this->WxchangeStatusa($order['ordersn'],$one['id'],$result);
				$ayy = array('code'=>0,'msg'=>'支付成功');
				if($ty=="qt"){
					return $ayy;
				}else{
					return json($ayy);
				}
			}else{
				$env = $this->request->env();
				require $env['ROOT_PATH'].'extend/wxpay/index.php';
				$out_trade_no = $one['pay_ordersn'];
				$unifiedOrder->SetOut_trade_no($out_trade_no);
				$result = $WxPayApi->orderQuery($unifiedOrder);
				if(isset($result['result_code']) && ($result['result_code']=="SUCCESS" || $result['result_code']=="TRADE_SUCCESS")){
					$res = $this->WxchangeStatusa($order['ordersn'],$one['id'],$result);
					// dump($res);
					$ayy = array('code'=>0,'msg'=>'支付成功');
					if($ty=="qt"){
						return $ayy;
					}else{
						return json($ayy);
					}
				}else{
					$ayy = array('code'=>$result['result_code'],'msg'=>$result['trade_state_desc']);
					if($ty=="qt"){
						return $ayy;
					}else{
						return json($ayy);
					}
				}
			}
		}else{
			$ayy = array('code'=>11,'msg'=>'没有发起交易');
			if($ty=="qt"){
				return $ayy;
			}else{
				return json($ayy);
			}
		}
	}
	public function WxchangeStatusa($ordersn,$opid,$paydata=array()){
		$order = Db::name('groups_order')->field('id,user_id,ordersn,groups_id,groups_key,groups_ids')->where('ordersn',$ordersn)->find();
        $id = $order['id'];

        $uparra['pay_status']   = $paydata['trade_state'];
        $uparra['pay_transid']  = $paydata['transaction_id'];
        $uparra['pay_time']     = strtotime($paydata['time_end']);
        $uparra['pay_body']     = json_encode($paydata,JSON_UNESCAPED_UNICODE);
    	$upres = Db::name('order_payment_log')->where('id',$opid)->update($uparra);

        $orarr['pay_time']    = $uparra['pay_time'];
        $orarr['pay_day']     = date('Ymd',$uparra['pay_time']);
        $orarr['pay_body']    = $uparra['pay_body'];
        $orarr['pay_type']    = "微信支付";
        $orarr['status']      = 1;
        $orarr['pay_ordersn'] = $paydata['out_trade_no'];
        $orarr['pay_transid'] = $paydata['transaction_id'];
        $orarr['pay_status']  = "SUCCESS";
        $orarr['pay_money']   = $orarr['realprice'] = $paydata['total_fee']/100;



		$title   = '订单已支付';
	    $content = "订单支付成功，支付方式：微信支付；交易流水号：".$orarr['pay_transid'];
	    //更新日志
	    $this->payloga($id,$title,$content,$order['user_id']);
        $goods = Db::name('groups_order_goods')->field('id,goods_id,desc,total')->where('order_id',$id)->select();
        if($order['groups_ids']=="0"){ //单个团
        	$tuan    = Db::name('groups')->field('id,now_num,max_num,tuanzhang_id')->where('id',$order['groups_id'])->find();
        	Db::startTrans();
	        try {
	            $res = Db::name('groups_order')->where('id',$id)->update($orarr);
	            if($res){
	            	redis()->del($order['groups_key']);
		            //付款减库存
		            
		            foreach($goods as $k=>$v){
		                $oneg = Db::name('groups_goods')->field('id,total,totalcnf,fan_yue,fan_jifen')->where('id',$v['goods_id'])->find();
		                if($oneg['totalcnf']=="1"){ //付款减库存
		                    if($v['desc']==""){
		                        $ntotal = $oneg['total'] - $v['total'];
		                        $resa = Db::name('groups_goods')->where('id',$v['goods_id'])->update(['total'=>$ntotal]);
		                    }else{
		                        $onega = Db::name('groups_goods_options_item_desc')->field('total,desc')->where('desc',$v['desc'])->find();
		                        $ntotal = $onega['total'] - $v['total'];
		                        $resa = Db::name('groups_goods_options_item_desc')->where('desc',$v['desc'])->update(['total'=>$ntotal]);
		                    }
		                }
		            }
		            
		            //更新当前拼团人数
		            
		            $now_num = $tuan['now_num'] + 1;
		            if($now_num==$tuan['max_num']){ //已成团
		            	$upt['status'] = 2;
		                $upt['finishtime'] = time();
		                $upt['finishday']  = date("Ymd");
		            }else{
		            	$upt['status'] = 1;
		            }
		            $upt['now_num'] = $now_num;
		            $uarr = Db::name('groups')->where('id',$order['groups_id'])->update($upt);
		         
		            //如果不是团长本人参团
		            if($tuan['tuanzhang_id']!=$order['user_id']){
		            	$ckmember = Db::name('member')->field('id,user_id,level')->where('id',$order['user_id'])->find();
		            	$upm['user_id'] = $tuan['tuanzhang_id'];
		            	if($ckmember['level']=="0"){  //绑定团长关系，并且成为菜鸟团长
		            		$upm['level'] = 1;
		            		// $upm['oldlevel'] = 0;
		            		$upm['levelday']  = date('Ymd');
		                    $upm['leveltime'] = time();
		            		$upmarr = Db::name('member')->where('id',$order['user_id'])->update($upm);
		            	}
		            }
		            Db::commit();
		            $resaa = '';
		            if($now_num==$tuan['max_num']){ //已成团
		                //查看是否达到自动提升团长等级的条件
		                if($uarr){
		                	$resaa = $this->autoUpmember($tuan['tuanzhang_id']);
		                }
		                // dump($resaa);
		                //给团长分佣
		                $this->fenyong($tuan['tuanzhang_id'],$tuan['id']);
		            }
		            $att = array('code'=>0,'msg'=>'支付成功','mmm'=>$resaa);
		            return $att;
		        }else{
		            $att = array('code'=>1,'msg'=>'支付失败');
		            Db::rollback();
		            return $att;
		        }
	            // 提交事务
	            //Db::commit();
	        }catch (\Exception $e) {
	            // 回滚事务
	            Db::rollback();
	       	}
        }else{
        	$tuans    = Db::name('groups')->field('id,now_num,max_num,tuanzhang_id')->where('id','in',$order['groups_ids'])->limit(1)->find();
        	Db::startTrans();
	        try {
	            $res = Db::name('groups_order')->where('id',$id)->update($orarr);
	            if($res){
	            	foreach($goods as $k=>$v){
		                $oneg = Db::name('groups_goods')->field('id,total,totalcnf,fan_yue,fan_jifen')->where('id',$v['goods_id'])->find();
		                if($oneg['totalcnf']=="1"){ //付款减库存
		                    if($v['desc']==""){
		                        $ntotal = $oneg['total'] - $v['total'];
		                        $resa = Db::name('groups_goods')->where('id',$v['goods_id'])->update(['total'=>$ntotal]);
		                    }else{
		                        $onega = Db::name('groups_goods_options_item_desc')->field('total,desc')->where('desc',$v['desc'])->find();
		                        $ntotal = $onega['total'] - $v['total'];
		                        $resa = Db::name('groups_goods_options_item_desc')->where('desc',$v['desc'])->update(['total'=>$ntotal]);
		                    }
		                }
		            }
		            
		            //更新当前拼团人数
		            $now_num = $tuans['max_num'];
		            if($now_num==$tuans['max_num']){ //已成团
		            	$upt['status'] = 2;
		                $upt['finishtime'] = time();
		                $upt['finishday']  = date("Ymd");
		            }else{
		            	$upt['status'] = 1;
		            }
		            $upt['now_num'] = $now_num;
		            $uarr = Db::name('groups')->where('id','in',$order['groups_ids'])->update($upt);

		            Db::commit();
		            $tuanlist = Db::name('groups')->field('id,now_num,max_num,tuanzhang_id')->where('id','in',$order['groups_ids'])->select();
		            foreach($tuanlist as $key=>$val){
		            	//给团长分佣
		            	$this->fenyong($val['tuanzhang_id'],$val['id']);
		            }
		            //检查是否能自动升级
		            if($uarr){
		            	$resaa = $this->autoUpmember($tuans['tuanzhang_id']);
		            }
		            $att = array('code'=>0,'msg'=>'支付成功','mmm'=>$resaa);
		            return $att;
	            }else{
	            	$att = array('code'=>1,'msg'=>'支付失败');
		            Db::rollback();
		            return $att;
	            }
	        }catch (\Exception $e) {
	            // 回滚事务
	            Db::rollback();
	       	};
        }
	}
/*********************************************************************/
/** 支付宝支付
/*********************************************************************/
	public function Alipay($detail,$ordersn){
		$total = $detail['marketprice'];
		$total = '0.01';
		$payment = Db::name('payment_api')->field('is_show,mch_id,apikey,cert,key_f,root_f',true)->where('id',2)->find();
		if($payment['rsa_type']=="RSA2"){ //新版支付宝支付
			$env = $this->request->env();
			require $env['ROOT_PATH'].'extend/alipay/index.php';
			$aop->gatewayUrl    = "https://openapi.alipay.com/gateway.do";
			$aop->appId         = $payment['appid'];
			$aop->rsaPrivateKey = $payment['private_key']; //应用私钥 
			$aop->format        = "json";
			$aop->charset       = "UTF-8";
			$aop->signType      = "RSA2";
			$aop->alipayrsaPublicKey = $payment['public_key']; //公钥
			// 异步通知地址
			$nourl = $this->hostname.url('api/payment/alinotify');
			$notify_url = urlencode($nourl);
			// 订单标题
			$subject = $detail['title'];
			// 订单详情
			$body = $detail['title'].' x 1'; 
			// 订单号，示例代码使用时间值作为唯一的订单ID号
			$out_trade_no = $ordersn;
			//SDK已经封装掉了公共参数，这里只需要传入业务参数
			$bizcontent = "{\"body\":\"".$body."\","
			                . "\"subject\": \"".$subject."\","
			                . "\"out_trade_no\": \"".$out_trade_no."\","
			                . "\"timeout_express\": \"30m\","
			                . "\"total_amount\": \"".$total."\","
			                . "\"product_code\":\"QUICK_MSECURITY_PAY\""
			                . "}";
			$request->setNotifyUrl($notify_url);
			$request->setBizContent($bizcontent);
			//这里和普通的接口调用不同，使用的是sdkExecute
			$response = $aop->sdkExecute($request);
			// 注意：这里不需要使用htmlspecialchars进行转义，直接返回即可
			return $response;
		}else{ //旧版支付宝支付
			// 支付宝合作者身份ID，以2088开头的16位纯数字
			$partner    = $payment['appid'];
			// 支付宝账号
			$seller_id  = $payment['uname'];
			// 商品网址
			$base_path  = urlencode($this->hostname);
			// 异步通知地址
			$nourl      = $this->hostname.url('api/payment/alinotify');
			$notify_url = urlencode($nourl);
			// 订单标题
			$subject    =  $detail['title'];
			// 订单详情
			$body       =  $detail['title'].' x 1'; 
			// 订单号，示例代码使用时间值作为唯一的订单ID号
			$out_trade_no = date('YmdHis', time());
			$parameter = array(
			    'service'        => 'mobile.securitypay.pay',   // 必填，接口名称，固定值
			    'partner'        => $partner,                   // 必填，合作商户号
			    '_input_charset' => 'UTF-8',                    // 必填，参数编码字符集
			    'out_trade_no'   => $out_trade_no,              // 必填，商户网站唯一订单号
			    'subject'        => $subject,                   // 必填，商品名称
			    'payment_type'   => '1',                        // 必填，支付类型
			    'seller_id'      => $seller_id,                 // 必填，卖家支付宝账号
			    'total_fee'      => $total,                     // 必填，总金额，取值范围为[0.01,100000000.00]
			    'body'           => $body,                      // 必填，商品详情
			    'it_b_pay'       => '1d',                       // 可选，未付款交易的超时时间
			    'notify_url'     => $notify_url,                // 可选，服务器异步通知页面路径
			    'show_url'       => $base_path                  // 可选，商品展示网站
			 );
			//生成需要签名的订单
			$orderInfo = $this->createLinkstring($parameter);
			//签名
			$sign = $this->rsaSign($orderInfo,$payment['private_key']);
			//生成订单
			return $orderInfo.'&sign="'.$sign.'"&sign_type="RSA"';
		}
	}
	public function createLinkstring($para) {
	    $arg  = "";
	    while (list ($key, $val) = each ($para)) {
	        $arg.=$key.'="'.$val.'"&';
	    }
	    //去掉最后一个&字符
	    $arg = substr($arg,0,count($arg)-2);
	    //如果存在转义字符，那么去掉转义
	    if(get_magic_quotes_gpc()){$arg = stripslashes($arg);}
	    return $arg;
	}
	// 签名生成订单信息
	public function rsaSign($data,$private_key) {
    	$priKey = $private_key;
		$res = openssl_get_privatekey($priKey);
		openssl_sign($data, $sign, $res);
		openssl_free_key($res);
		$sign = base64_encode($sign);
		$sign = urlencode($sign);
		return $sign;
	}
	public function alinotify(){
		echo 'success';
	}
	public function AlipayCheck($id){
		$id = $this->request->param('id') ? $this->request->param('id') : $id;
		$ty = $this->request->param('ty') ? $this->request->param('ty') : 'qt';
		$one = Db::name('order_payment_log')->field('id,pay_ordersn,pay_status,pay_body')->where(['orderid'=>$id,'paytype'=>'alipay'])->order('createtime desc')->limit(1)->find();
		if($one){
			$order = Db::name('order')->field('user_id')->where('id',$id)->find();
			if($one['pay_status']=="SUCCESS" || $one['pay_status']=="TRADE_SUCCESS"){
				$ayy = array('code'=>0,'msg'=>'支付成功');
				if($ty=="qt"){
					return $ayy;
				}else{
					return json($ayy);
				}
			}else{
				$env = $this->request->env();
				require $env['ROOT_PATH'].'extend/alipay/indexc.php';
				$payment = Db::name('payment_api')->field('is_show,mch_id,apikey,cert,key_f,root_f',true)->where('id',2)->find();
				$aop->gatewayUrl         = 'https://openapi.alipay.com/gateway.do';
				$aop->appId              = $payment['appid'];
				$aop->rsaPrivateKey      = $payment['private_key'];
				$aop->alipayrsaPublicKey = $payment['public_key'];
				$aop->apiVersion = '1.0';
				$aop->signType = 'RSA2';
				$aop->postCharset='UTF-8';
				$aop->format='json';
				$request->setBizContent("{" .
							"\"out_trade_no\":\"".$one['pay_ordersn']."\"," .
							"\"trade_no\":\"\"," .
							"\"org_pid\":\"\"," .
							"\"query_options\":[" .
							"\"TRADE_SETTE_INFO\"" .
							"]" .
							"}");
				$result = $aop->execute ($request); 
				$responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
				$resultCode = $result->$responseNode->code;
				$resdata = $result->$responseNode;
				$resdata = $this->object2array($resdata);
				if(!empty($resultCode)&&$resultCode==10000){
					$uparra['pay_status']   = $resdata['trade_status'];
		            $uparra['pay_transid']  = $resdata['trade_no'];
		            $uparra['pay_time']     = strtotime($resdata['send_pay_date']);
		            $uparra['pay_body']     = json_encode($resdata,JSON_UNESCAPED_UNICODE);
		            $uparra['status']       = 1;
		            $upres = Db::name('order_payment_log')->where('id',$one['id'])->update($uparra);
		            $orarr['pay_time']    = $uparra['pay_time'];
					$orarr['pay_day']     = date('Ymd',$uparra['pay_time']);
					$orarr['pay_body']    = $uparra['pay_body'];
					$orarr['pay_type']    = "alipay";
					$orarr['status']      = 1;
					$orarr['pay_ordersn'] = $resdata['out_trade_no'];
					$orarr['pay_transid'] = $resdata['trade_no'];
					$orarr['pay_status']  = "SUCCESS";
					$orarr['pay_money']   = $orarr['realprice'] = $resdata['total_amount'];
					$count = Db::name('order')->where('parent_id',$id)->count();
					if($count>0){
						$orarr['is_show'] = 'n';
					}
					$res = Db::name('order')->where('id',$id)->update($orarr);
					if($res){
						//付款减库存
						$goods = Db::name('order_goods')->field('id,goods_id,desc,total')->where('order_id',$id)->select();
						foreach($goods as $k=>$v){
							$oneg = Db::name('goods')->field('id,total,totalcnf,fan_yue,fan_jifen')->where('id',$v['goods_id'])->find();
							if($oneg['totalcnf']=="1"){ //付款减库存
								if($v['desc']==""){
									$ntotal = $oneg['total'] - $v['total'];
									$resa = Db::name('goods')->where('id',$v['goods_id'])->update(['total'=>$ntotal]);
								}else{
									$onega = Db::name('goods_options_item_desc')->field('total,desc')->where('desc',$v['desc'])->find();
									$ntotal = $onega['total'] - $v['total'];
									$resa = Db::name('goods_options_item_desc')->where('desc',$v['desc'])->update(['total'=>$ntotal]);
								}
							}
						}
						$title   = '订单已支付';
						$content = "订单支付成功，支付方式：支付宝支付；交易流水号：".$orarr['pay_transid'];
						//更新日志
						$this->paylog($id,$title,$content,$order['user_id']);
						if($count>0){
							$orarr['is_show'] = 'y';
							$resa = Db::name('order')->where('parent_id',$id)->update($orarr);
							$zidans = Db::name('order')->field('id,user_id')->where('parent_id',$id)->select();
							$yids = [];
							$yids[] = 0;
							foreach($zidans as $k=>$v){
								$this->paylog($v['id'],$title,$content,$v['user_id']);
								$yids[] = $v['id'];
							}
							//更新分佣信息
							$yjarr['pay_time'] = $orarr['pay_time'];
							$yjarr['pay_day']  = $orarr['pay_day'];
							$yjarr['status']   = 1;
							$yj = Db::name('member_distribution')->where('order_id','in',$yids)->update($yjarr);
							//更新余额返现
							$yjarr['status']      = 4;
							$yjarr['shou_time']   = $orarr['pay_time'];
							$yjarr['shou_day']    = $orarr['pay_day'];
							$yjarr['finish_time'] = $orarr['pay_time'];
							$yjarr['finish_day']  = $orarr['pay_day'];
							$yj = Db::name('member_distribution')->where('order_id',$id)->where('types','fanxian')->update($yjarr);
							//更新积分赠送
							$jj = Db::name('member_jifen')->where('orderid',$id)->update(['status'=>0]);
						}else{
							//更新分佣信息
							$yjarr['pay_time'] = $orarr['pay_time'];
							$yjarr['pay_day']  = $orarr['pay_day'];
							$yjarr['status']   = 1;
							$yj = Db::name('member_distribution')->where('order_id',$id)->update($yjarr);
							//更新余额返现
							$yjarr['status']      = 4;
							$yjarr['shou_time']   = $orarr['pay_time'];
							$yjarr['shou_day']    = $orarr['pay_day'];
							$yjarr['finish_time'] = $orarr['pay_time'];
							$yjarr['finish_day']  = $orarr['pay_day'];
							$yj = Db::name('member_distribution')->where('order_id',$id)->where('types','fanxian')->update($yjarr);
							//更新积分赠送
							$jj = Db::name('member_jifen')->where('orderid',$id)->update(['status'=>0]);
						}
		            	$ayy = array('code'=>0,'msg'=>'支付成功');
		            	if($ty=="qt"){
							return $ayy;
						}else{
							return json($ayy);
						}
		            }else{
		            	$ayy = array('code'=>10002,'msg'=>'支付失败');
		            	if($ty=="qt"){
							return $ayy;
						}else{
							return json($ayy);
						}
		            }
				} else {
					$ayy = array('code'=>10001,'msg'=>'支付失败:'.$resdata['sub_msg']);
					if($ty=="qt"){
						return $ayy;
					}else{
						return json($ayy);
					}
				}
			}
		}else{
			$ayy = array('code'=>11,'msg'=>'没有发起交易');
			if($ty=="qt"){
				return $ayy;
			}else{
				return json($ayy);
			}
		}
	}
	public function object2array($object) {
	  	if (is_object($object)) {
	    	foreach ($object as $key => $value) {
	      		$array[$key] = $value;
	    	}
	  	}else {
	    	$array = $object;
	  	}
	  	return $array;
	}

/*********************************************************************/
/** 支付日志
/*********************************************************************/
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
	public function payloga($orderid,$title,$content,$uid){
		//生成订单日志
		$log['orderid'] = $orderid;
		$log['userid']  = $uid;
		$log['title']   = $title;
		$log['content'] = $content;
		$log['createtime'] = time();
		$log['createday']  = date('Ymd');
		$res = Db::name('groups_order_log')->insert($log);
		//发送站内新
		$one = Db::name('groups_order')->field('id,ordersn,number')->where('id',$orderid)->find();
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

	//检查团长是否能自动升级
	public function autoUpmember($uid){
		$tuanzhang = Db::name('member')->field('id,level')->where('id',$uid)->find();
		$tuancount = Db::name('groups')->where(['tuanzhang_id'=>$uid])->where('status','in',['2','3'])->count();
		$now_level  = $tuanzhang['level'];
		$next_level = $this->hhh($now_level,$tuancount);
        $uparr['tuan_num'] = $tuancount;
		if($next_level>$now_level){
            $uparr['level'] = $next_level;
            $uparr['levelday']   = date("Ymd");
            $uparr['leveltime']  = time();
            // $uparr['oldlevel']   = $now_level;
		}
        $uparrres = Db::name('member')->where('id',$uid)->update($uparr);
        return $tuancount.'=='.$next_level.'=='.$now_level;
	}
	public function hhh($now_level,$tuancount){
		$catelist = Db::name('member_cate')->field('id,auto_group')->where(['is_del'=>'n','is_show'=>'y'])->order('auto_group desc')->select();
		foreach($catelist as $k=>$v){
			if($tuancount>=$v['auto_group']){
				return $v['id'];
			}
		}
		return $now_level;
	}
	//分佣
	public function fenyong($uid,$tid){
        $tuanzhang = Db::name('member')->field('user_id,level,pid1,pid2,tz_level')->where('id',$uid)->find();
        $orders = Db::name('groups_order')->field('price')->where(['groups_id'=>$tid])->where('status','in',['1','2','3','4'])->select();
        $price = [];
        foreach($orders as $k=>$v){
            $price[] = $v['price'];
        }
        $pricecount = array_sum($price);
        //自己每开一团，奖励
        $wconfig = Db::name('groups_configs')->field('pt_jiangli,pt_jiangli_z')->where('id',1)->find();
        if($wconfig['pt_jiangli']){
        	$add['user_id']        = $uid;
            $add['groups_id']      = $tid;
            if(strstr($wconfig['pt_jiangli'], '%')){
            	$yjbj = str_replace("%",'',$wconfig['pt_jiangli']);
                $add['money'] = sprintf("%.2f",($pricecount)*($yjbj/100));
            }else{
                $add['money'] = $wconfig['pt_jiangli']; 
            }
            $add['title'] = "开团奖励";
            $add['createtime'] = time();
            $add['createday']  = date("Ymd");
            $add['status']     = 1;
            $add['types']      = "kaituanjiangli";
            $resadd = Db::name('member_distribution')->insert($add);
        }
        //给我的上级奖励
        if($tuanzhang['user_id']>'0'){
        	$tuanzhanga = Db::name('member')->field('user_id,level,pid1,pid2,tz_level')->where('id',$tuanzhang['user_id'])->find();
        	if($wconfig['pt_jiangli_z'] && $tuanzhanga['level']>'0'){
	        	$adda['user_id']        = $tuanzhang['user_id'];
	            $adda['groups_id']     = $tid;
	            if(strstr($wconfig['pt_jiangli_z'], '%')){
	            	$yjbj = str_replace("%",'',$wconfig['pt_jiangli_z']);
	                $adda['money'] = sprintf("%.2f",($pricecount)*($yjbj/100));
	            }else{
	                $adda['money'] = $wconfig['pt_jiangli_z']; 
	            }
	            $adda['title'] = "开团奖励";
	            $adda['createtime'] = time();
	            $adda['createday']  = date("Ymd");
	            $adda['status']     = 1;
	            $adda['types']      = "kaituanjiangli";
	            $resadda = Db::name('member_distribution')->insert($adda);
	        }
        }
        return true;
        if($tuanzhang['tz_level']=="2"){ //总团长开的团

        }else if($tuanzhang['tz_level']=="1"){ //代理团长开的团

        }else if($tuanzhang['tz_level']=="0"){ //普通团长开的团
            $cate = Db::name('member_cate')->field('group_fenhong')->where('id',$tuanzhang['level'])->find();
            if($cate['group_fenhong']=="-1"){
                $wconfig = Db::name('groups_configs')->field('pt_jiangli,pt_jiangli_z')->where('id',1)->find();
                //自己开团奖励
                if($wconfig['pt_jiangli']){
                	$add['user_id']        = $uid;
                	$add['groups_order']   = $tid;
                    if(strstr($wconfig['pt_jiangli'], '%')){
                        $orders = Db::name('groups_order')->field('price')->where(['groups_id'=>$tid])->where('status','in',['1','2','3','4'])->select();
                        $price = [];
                        foreach($orders as $k=>$v){
                        	$price[] = $v['price'];
                        }
                        $pricecount = array_sum($price);
                        $add['money'] = sprintf("%.2f",$pricecount);
                    }else{
                    	$add['money'] = $wconfig['pt_jiangli']; 
                    }
                    $add['title'] = "开团奖励";
                    $add['createtime'] = time();
                    $add['createday']  = date("Ymd");
                    $add['status']     = 1;
                    $add['types']      = "kaituanjiangli";
                    $resadd = Db::name('member_distribution')->insert($add);
                }
                //平级直属团队开团奖励
                if($wconfig['pt_jiangli_z'] && $tuanzhang['user_id']){
                	$shangji = Db::name('member')->field('user_id,level,pid1,pid2,tz_level')->where('id',$tuanzhang['user_id'])->find();
                	if($tuanzhang['tz_level']=="2"){ //上级是总团长

			        }else if($tuanzhang['tz_level']=="1"){ //上级是代理团长

			        }else{
			        	$catea = Db::name('member_cate')->field('group_fenhong')->where('id',$shangji['level'])->find();
			        	if($catea['group_fenhong']=="-1"){
			        		$wconfiga = Db::name('groups_configs')->field('pt_jiangli,pt_jiangli_z')->where('id',1)->find();
			        		if($wconfiga['pt_jiangli']){
			        			$adda['user_id']        = $tuanzhang['user_id'];
	                			$adda['groups_order']   = $tid;
	                			if(strstr($wconfiga['pt_jiangli'], '%')){
			                        $ordersa = Db::name('groups_order')->field('price')->where(['groups_id'=>$tid])->where('status','in',['1','2','3','4'])->select();
			                        $pricea = [];
			                        foreach($ordersa as $k=>$v){
			                        	$pricea[] = $v['price'];
			                        }
			                        $pricecounta = array_sum($pricea);
			                        $adda['money'] = sprintf("%.2f",$pricecounta);
			                    }else{
			                    	$adda['money'] = $wconfiga['pt_jiangli']; 
			                    }
			                    $adda['title'] = "直属团队开团奖励";
			                    $adda['createtime'] = time();
			                    $adda['createday']  = date("Ymd");
			                    $adda['status']     = 1;
			                    $adda['types']      = "kaituanjiangli";
			                    $resadda = Db::name('member_distribution')->insert($adda);
			        		}
			        	}
			        }
                }
            }else{
            	//直属团队开团奖励
                if($wconfig['pt_jiangli_z'] && $tuanzhang['user_id']){
                	$shangji = Db::name('member')->field('user_id,level,pid1,pid2,tz_level')->where('id',$tuanzhang['user_id'])->find();
                	if($tuanzhang['tz_level']=="2"){ //上级是总团长

			        }else if($tuanzhang['tz_level']=="1"){ //上级是代理团长

			        }else{
			        	$catea = Db::name('member_cate')->field('group_fenhong')->where('id',$shangji['level'])->find();
			        	if($catea['group_fenhong']=="-1"){
			        		$wconfiga = Db::name('groups_configs')->field('pt_jiangli,pt_jiangli_z')->where('id',1)->find();
			        		if($wconfiga['pt_jiangli']){
			        			$adda['user_id']        = $tuanzhang['user_id'];
	                			$adda['groups_order']   = $tid;
	                			if(strstr($wconfiga['pt_jiangli'], '%')){
			                        $ordersa = Db::name('groups_order')->field('price')->where(['groups_id'=>$tid])->where('status','in',['1','2','3','4'])->select();
			                        $pricea = [];
			                        foreach($ordersa as $k=>$v){
			                        	$pricea[] = $v['price'];
			                        }
			                        $pricecounta = array_sum($pricea);
			                        $adda['money'] = sprintf("%.2f",$pricecounta);
			                    }else{
			                    	$adda['money'] = $wconfiga['pt_jiangli']; 
			                    }
			                    $adda['title'] = "直属团队开团奖励";
			                    $adda['createtime'] = time();
			                    $adda['createday']  = date("Ymd");
			                    $adda['status']     = 1;
			                    $adda['types']      = "kaituanjiangli";
			                    $resadda = Db::name('member_distribution')->insert($adda);
			        		}
			        	}
			        }
                }
            }
        }
        if($tuanzhang['pid1']>'0'){ //总团长奖励

        }else if($tuanzhang['pid2']>'0'){ //代理团长奖励

        }
	}
}