<?php
namespace app\api\controller;
use \think\Controller;
use app\api\controller\Base;
use \think\Db;
use \think\Session;
use \think\Cookie;
use \think\Request;
use \think\AES;
class Groupspay extends Base{
	public function __construct(){
		parent::__construct(); //使用父类的构造方法
    }
/*********************************************************************/
/** 获取支付参数
/*********************************************************************/
	public function index(){

	}
	public function checkpay(){
		$data = input();
		$token = $this->CheckToken($data['token']);
   		if($token['code']=='0'){
   			$one = Db::name('groups_order')->field('id,status')->where('ordersn',$data['ordersn'])->find();
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
/*********************************************************************/
/** 微信支付
/*********************************************************************/
	public function WxpayCheck($id){
		$id = $this->request->param('id') ? $this->request->param('id') : $id;
		$ty = $this->request->param('ty') ? $this->request->param('ty') : 'qt';
		$order = Db::name('groups_order')->field('user_id,ordersn')->where('id',$id)->find();
		$res = $this->WxchangeStatus($order['ordersn']);
		return $res;
	}

/*********************************************************************/
/** 修改订单信息
/*********************************************************************/
	public function WxchangeStatus($ordersn,$paydata=array()){
		$order = Db::name('groups_order')->field('id,user_id,ordersn,groups_id')->where('ordersn',$ordersn)->find();
        $id = $order['id'];
        $uparra['pay_status']   = "TRADE_SUCCESS";
        $uparra['pay_transid']  = $order['ordersn'];
        $uparra['pay_time']     = time();
        $uparra['pay_body']     = "免费参团";
    
        $orarr['pay_time']    = $uparra['pay_time'];
        $orarr['pay_day']     = date('Ymd',$uparra['pay_time']);
        $orarr['pay_body']    = $uparra['pay_body'];
        $orarr['pay_type']    = "微信支付";
        $orarr['status']      = 1;
        $orarr['pay_ordersn'] = $order['ordersn'];
        $orarr['pay_transid'] = $order['ordersn'];
        $orarr['pay_status']  = "SUCCESS";
        $orarr['pay_money']   = $orarr['realprice'] = "0.00";
        $res = Db::name('groups_order')->where('id',$id)->update($orarr);
        if($res){
            //付款减库存
            $goods = Db::name('groups_order_goods')->field('id,goods_id,desc,total')->where('order_id',$id)->select();
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
            $title   = '订单已支付';
            $content = "订单支付成功，支付方式：免费；交易流水号：".$orarr['pay_transid'];
            //更新日志
            $this->paylog($id,$title,$content,$order['user_id']);
            //更新当前拼团人数
            $tuan    = Db::name('groups')->field('id,now_num,max_num,tuanzhang_id')->where('id',$order['groups_id'])->find();
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
                    $upm['levelday']  = date('Ymd');
                    $upm['leveltime'] = time();
                    // $upm['oldlevel'] = 0;
            		$upmarr = Db::name('member')->where('id',$order['user_id'])->update($upm);
            	}
            }
            if($now_num==$tuan['max_num']){ //已成团
                //查看是否达到自动提升团长等级的条件
                $this->autoUpmember($tuan['tuanzhang_id']);
                //给团长分佣
                $this->fenyong($tuan['tuanzhang_id']);
            }
            $att = array('code'=>0,'msg'=>'支付成功');
            return $att;
        }else{
            $att = array('code'=>1,'msg'=>'支付失败');
            return $att;
        }
	}
	//检查团长是否能自动升级
	public function autoUpmember($uid){
		$tuanzhang = Db::name('member')->field('id,level')->where('id',$uid)->find();
		$tuancount = Db::name('groups')->where(['tuanzhang_id'=>$uid])->where('status','in',['2','3'])->count();
		$catelist = Db::name('member_cate')->field('id,auto_group')->where(['is_del'=>'n','is_show'=>'y'])->order('auto_group desc')->select();
		$now_level  = $tuanzhang['level'];
		$next_level = $tuanzhang['level'];
		foreach($catelist as $k=>$v){
			if($tuancount>=$v['auto_group']){
				$next_level = $v['id'];
			}
		}
        $uparr['tuan_num'] = $tuancount;
		if($next_level!=$now_level){
            $uparr['level'] = $next_level;
            $uparr['levelday'] = date("Ymd");
            $uparr['leveltime']  = time();
            // $uparr['oldlevel']   = $now_level;
		}
        $uparrres = Db::name('member')->where('id',$uid)->update($uparr);
	}
	//分佣
	public function fenyong($uid){
        $tuanzhang = Db::name('member')->field('user_id,level,pid1,pid2,tz_level')->where('id',$uid)->find();
        if($tuanzhang['tz_level']=="2"){ //总团长开的团

        }else if($tuanzhang['tz_level']=="1"){ //代理团长开的团

        }else if($tuanzhang['tz_level']=="0"){ //普通团长开的团
            $cate = Db::name('member_cate')->field('groups_fenyong')->where('id',$tuanzhang['level'])->find();
            if($cate['groups_fenyong']=="-1"){
                $wconfig = Db::name('groups_configs')->field('pt_jiangli,pt_jiangli_z')->where('id',1)->find();
                if($wconfig['pt_jiangli']){
                    if(strstr($wconfig['pt_jiangli'], '%')){
                        
                    }
                }
            }
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
        $uparr['types']    = 'groups_order';
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