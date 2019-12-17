<?php
namespace app\api\controller;
use \think\Controller;
use app\api\controller\Base;
use \think\Db;
use \think\Session;
use \think\Cookie;
use \think\Request;
use \think\AES;
class Groups extends Base{
	public function __construct(){
		parent::__construct(); //使用父类的构造方法
    }
/************************************************************************/
/** 拼团
/************************************************************************/
    public function home(){
    	$banner = Db::name('banner')->field(['id','title','thumb','url'])->where(['pid'=>'4','is_del'=>'n','is_show'=>'y'])->order('orders asc,updatetime desc')->select();
    	foreach($banner as $k=>$v){
			$banner[$k]['thumb'] = getImage($v['thumb']);
		}
		$goods = Db::name('groups_goods')->field('id,title,description,groupsprice,thumb')->where(['is_show'=>'y','is_del'=>'n'])->where('total','>','0')->select();
		foreach($goods as $k=>$v){
			$goods[$k]['thumb'] = getImage($v['thumb']);
			$goods[$k]['groupsprice'] = explode(".",$v['groupsprice']);
		}
		return json(['code'=>0,'msg'=>'获取成功','banner'=>$banner,'goods'=>$goods]);
    }
    public function goodsDetails(){
    	$data = input();
    	$one = Db::name('groups_goods')->field('addtime,autocancle,autoreceive,is_del,admin_id,updatetime,update_admin,tuanyuan_jiangli,tuanzhang_jiangli',true)->where(['id'=>$data['id'],'is_del'=>'n'])->find();
    	if($one){
    		$pics = explode(",",$one['pics']);
    		$banner = [];
    		$banner[] = getImage($one['thumb']);
    		foreach($pics as $k=>$v){
    			$banner[] = getImage($v);
    		}
    		$one['banner'] = $banner;
    		if($one['ispostage']=="1"){ //设置包邮
    			$one['postageprice'] = "0.00";
    		}else{
    			if($one['postagetype']=="0"){ //运费模板

    			}
    		}
            $strs = "------";
            $zhengfu     = substr(str_shuffle($strs),mt_rand(0,strlen($strs)-2),1);
            $buchang     = rand(0,(int)($one['costprice'])*100);
            if($zhengfu=='+'){
                $one['groupspricea'] = sprintf('%.2f',$one['groupsprice']+($buchang/100));
            }else{
                $one['groupspricea'] = sprintf('%.2f',$one['groupsprice']-($buchang/100));
            }
            $aes = new AES();
            $one['shijiprice']  = $aes->encrypt($one['groupspricea']);
    		$one['groupsprice'] = explode(".",$one['groupsprice']);
    		$one['content'] = str_replace('src="/ueditor','style="max-width:100% !important;" src="'.$this->hostname.'/ueditor', $one['content']);
            $one['sales'] = Db::name('groups_order_goods')->where('goods_id',$one['id'])->count();
            $shengyu = 0;
            $pylist = [];
    		if(isset($data['tid'])){
    			$haoyou = Db::name('groups_order')
                    ->alias('aa')
                    ->field('aa.id as goid,bb.id,bb.nickname,bb.avatar,aa.status,aa.createtime,aa.send_time,aa.shou_time,aa.user_id,aa.groups_key')
                    ->join('member bb', 'bb.id=aa.user_id', 'left')
                    ->where(['aa.groups_id'=>$data['tid']])
                    ->where('aa.status','in','0,1,2,3,4')
                    ->order('aa.createtime asc')
                    ->select();
                $configs = Db::name('webconfig')->field('quxiaotime,shouhuotime,wanchengtime')->where('id','1')->find();
                $yio = [];
                $yio[] = 0;
                foreach($haoyou as $k=>$v){
                	if($v['status']>='0'){
            			$yio[] = $v['groups_key'];
            		}
            		$pylist[] = $v;
                    //超时自动取消
                    $createcha = time() - $v['createtime'];
                    $quxiaotime = isset($configs['quxiaotime']) ? $configs['quxiaotime'] : 60;
                    if($v['status']=="0" && $createcha>=(60*$quxiaotime)){
                        $uparr['cancel_time'] = $v['createtime']+(60*$quxiaotime);
                        $uparr['cancel_day']  = date("Ymd",($v['createtime']+(60*$quxiaotime)));
                        $uparr['status']      = "-1";
                        $res = Db::name('groups_order')->where('id',$v['goid'])->update($uparr);
                        $this->paylog($v['goid'],'订单已取消','订单超时自动取消',$v['user_id']);
                        $haoyou[$k]['status'] = $v['status'] = "-1";
                        groupshuankuncun($v['goid']);
                    }
                    //超时自动收货
                    $sendcha = time()-$v['send_time'];
                    $shouhuotime = isset($configs['shouhuotime']) ? $configs['shouhuotime'] : 7;
                    if($v['status']=="2" && $sendcha>=(60*60*24*$shouhuotime)){
                        $uparr['shou_time'] = $v['send_time']+(60*60*24*$shouhuotime);
                        $uparr['shou_day']  = date("Ymd",($v['send_time']+(60*60*24*$shouhuotime)));
                        $uparr['status']      = "3";
                        $res = Db::name('groups_order')->where('id',$v['goid'])->update($uparr);
                        $this->paylog($v['goid'],'订单已收货','订单已成功收货',$v['user_id']);
                        $haoyou[$k]['status'] = $v['status'] = "3";
                    }
                    //超时自动完成
                    $shoucha = time()-$v['shou_time'];
                    $wanchengtime = isset($configs['wanchengtime']) ? $configs['wanchengtime'] : 7;
                    if($v['status']=="3" && $shoucha>=(60*60*24*$wanchengtime)){
                        $uparr['finish_time'] = $v['shou_time']+(60*60*24*$wanchengtime);
                        $uparr['finish_day']  = date("Ymd",($v['shou_time']+(60*60*24*$wanchengtime)));
                        $uparr['status']      = "4";
                        $res = Db::name('groups_order')->where('id',$v['goid'])->update($uparr);
                        $this->paylog($v['goid'],'订单已完成','订单已完成，感谢您的支持',$v['user_id']);
                        $haoyou[$k]['status'] = "4";
                    }
                }
                $tuan = Db::name('groups')->field('id,max_num,tuanzhang_id')->where('id',$data['tid'])->find();

                $shengyu = $this->tuanCount($tuan['id']);
                if(isset($shengyu['list'])){
	            	foreach($shengyu['list'] as $k=>$v){
	            		$mem = Db::name('member')->field('avatar,nickname,id')->where('id',$v['uid'])->find();
	            		$mem['status'] = 10;
	            		$pylist[] = $mem;
	            	}
	            }
                $tuanzhang = Db::name('member')->field('id,nickname,avatar')->where('id',$tuan['tuanzhang_id'])->find();
    		}else{
    			$haoyou = [];
    			$tuanzhang = [];
    		}
    		return json(['code'=>0,'msg'=>'获取成功','returnData'=>$one,'haoyou'=>$pylist,'tuanzhang'=>$tuanzhang,'shengyu'=>$shengyu]);
    	}else{
    		return json(['code'=>1,'msg'=>'获取失败','returnData'=>'']);
    	}
    }
    public function  tuanCount($tid){
    	$list = redis()->lRange('tuan_cache'.$tid,0,-1); //拿到队列内容
    	redis()->del('tuan_cache'.$tid); //清楚队列
    	foreach($list as $k=>$v){
    		$dl = json_decode($v,1);
    		$chekc = Db::name('groups_order')->field('id')->where(['groups_id'=>$tid,'groups_key'=>$dl['hid']])->where("status",'in',['0','1','2','3','4','5'])->find();
    		if($chekc){ //如果已生成有效订单，剔出队列

    		}else{
    			$gg = redis()->get($dl['hid']);
    			//如果还在有效的
    			if($gg){
    				if($dl['endtime']<=time()){ //如果已失效，出队列

		    		}else{ //未失效，重新入列
		    			redis()->lPush('tuan_cache'.$tid,json_encode($dl,255));
		    		}
    			}
    		}
    	}
    	$tuan = Db::name('groups')->field('max_num')->where('id',$tid)->find();
    	$max_num = $tuan['max_num'];
    	//已生成订单的数量
    	$llist = Db::name('groups_order')->field('id')->where(['groups_id'=>$tid])->where("status",'in',['0','1','2','3','4','5'])->count();
    	//在锁住的数量
    	$nlist    = redis()->lRange('tuan_cache'.$tid,0,-1);
    	foreach($nlist as $k=>$v){
    		$nlist[$k] = json_decode($v,1);
    	}

    	$shengyu = $max_num-$llist-count($nlist);
    	redis()->set('tuan_'.$tid,$shengyu);
    	return array('shengyu'=>$shengyu,'list'=>$nlist);
    	return $shengyu;
    }
/************************************************************************/
/** 一键开团
/************************************************************************/
    public function kaituans(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $tid = isset($data['tid']) ? $data['tid'] : '0';
            $guige = isset($data['guige']) ? $data['guige'] : '';
            $aes = new AES();
            $shijiprice = $aes->decrypt($data['shijiprice']);
            if($tid=="0"){
                $shuliang = isset($data['nums']) ? $data['nums'] : '1'; //团数量
                if($shuliang>'8'){
                    return json(['code'=>1003,'msg'=>'购买团数不能超过8团']);die;
                }else if($shuliang<'1'){
                    return json(['code'=>1004,'msg'=>'购买团数不能低于1团']);die;
                }
                $goods = Db::name('groups_goods')->field('groupsnum')->where('id',$data['id'])->find();
                $suonums = $shuliang*$goods['groupsnum'];
                if($guige==""){
                    //锁住当前的产品30s 防止并发时过载
                    $status = $this->lock('groups_key'.$data['id']);
                    if(!$status){
                        $this->unlock('groups_key'.$data['id']);
                        return json(['code'=>1002,'msg'=>'当前抢购火爆，请稍后再试']);die;
                    }
                    $num = redis()->get('groups_'.$data['id']);
                    if($num<$suonums){
                        $this->unlock('groups_key'.$data['id']);
                        return json(['code'=>1001,'msg'=>'库存不足']);die;
                    }
                }else{
                    //锁住当前的产品30s 防止并发时过载
                    $status = $this->lock('groups_goods_key'.$guige);
                    if(!$status){
                        $this->unlock('groups_goods_key'.$guige);
                        return json(['code'=>1002,'msg'=>'当前抢购火爆，请稍后再试']);die;
                    }
                    $num = redis()->get('groups_goods_'.$guige);
                    if($num<$suonums){
                        $this->unlock('groups_goods_key'.$guige);
                        return json(['code'=>1003,'msg'=>'库存不足']);die;
                    }
                }
                $gids = [];
                for($i=0;$i<$shuliang;$i++){
                    $addg['uid']     = $token['myinfo']['id'];
                    $addg['pid1']    = $token['myinfo']['pid1'];
                    $addg['pid2']    = $token['myinfo']['pid2'];
                    $addg['tz_level']= $token['myinfo']['tz_level'];
                    $addg['ordersn'] = date("YmdHis").rand(1000,9999);
                    $addg['addtime'] = time();
                    $addg['addday']  = date("Ymd");
                    $addg['max_num'] = $goods['groupsnum'];
                    $addg['now_num'] = 0;
                    $addg['tuanzhang_id'] = $token['myinfo']['id'];
                    $addg['goods_id'] = $data['id'];
                    $addg['guige']    = $guige;
                    $res = Db::name('groups')->insertGetId($addg);
                    $gids[] = $res;
                }
                if(count($gids)==$shuliang){
                    //完成清除锁
                    if($guige==""){
                        //减少库存 释放锁
                        redis()->decrBy('groups_'.$data['id'],$suonums);
                        $this->unlock('groups_key'.$data['id']);
                    }else{
                        //减少库存 释放锁
                        redis()->decrBy('groups_goods_'.$guige,$suonums);
                        $this->unlock('groups_goods_key'.$guige);
                    }
                    $gids = implode(",",$gids);
                    return json(['code'=>0,'msg'=>'开团成功','id'=>$gids,'shijiprice'=>$data['shijiprice'],'num'=>$num,'hid'=>0]);die;
                }else{
                    return json(['code'=>1002,'msg'=>'开团失败']);die;
                }
            }
        }else{
            return json($token);
        }
    }
    public function querenOrders(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $aes = new AES();
            $shijiprice = $aes->decrypt($data['shijiprice']);
            $oshijiprice = $shijiprice;
            //默认收货地址
            $addressid = isset($data['addressid']) ? $data['addressid'] : '0';
            if($addressid=='0'){
                $address = Db::name('member_address')->field('is_del,updatetime,aorder,addtime,diquid',true)->where(['uid'=>$token['myinfo']['id'],'is_del'=>'n'])->order('aorder desc,addtime desc')->limit(1)->find();
            }else{
                $address = Db::name('member_address')->field('is_del,updatetime,aorder,addtime,diquid',true)->where(['id'=>$addressid])->order('aorder desc,addtime desc')->limit(1)->find();
                $addressid = $address['id'];
            }
            $tuan = Db::name('groups')->where('id','in',$data['tid'])->limit(1)->find();
            $goods =  Db::name('groups_goods')->field('id,title,short_title,thumb,description,groupsprice,marketprice,goodsprice,storeid,store_name,total,store_logo,ednum,edmoney,postagetype,postageid,postageprice,ispostage,edareas,goodssn,productsn,totalcnf')->where('id',$tuan['goods_id'])->find();

            $k = 0;
            $goodslist[$k]['id']         = $goods['id'];
            $goodslist[$k]['image']      = getImage($goods['thumb']);
            $goodslist[$k]['stock']      = $goods['total'];
            $goodslist[$k]['title']      = $goods['title'];
            $goodslist[$k]['description']= $goods['description'];
            $goodslist[$k]['price']      = $shijiprice;
            $goodslist[$k]['marketprice']= $goods['groupsprice'];
            $goodslist[$k]['goodsprice'] = $goods['goodsprice'];
            $goodslist[$k]['goodssn']    = $goods['goodssn'];
            $goodslist[$k]['productsn']  = $goods['productsn'];
            $goodslist[$k]['totalcnf']   = $goods['totalcnf'];
            if($tuan['guige']){
                $guige = Db::name('groups_goods_options_item_desc')->field('desc_title,total,marketprice,goodsprice,j_weight,m_weight,goodssn,productsn')->where('desc',$tuan['guige'])->find();
                $goodslist[$k]['attr_val']   = $guige['desc_title'];
                $goodslist[$k]['stock']      = $guige['total'];
                $goodslist[$k]['price']      = $shijiprice;
                $goodslist[$k]['marketprice']= $oshijiprice;
                $goodslist[$k]['goodsprice'] = $guige['goodsprice'];
                $goodslist[$k]['goodssn']    = $guige['goodssn'];
                $goodslist[$k]['productsn']  = $guige['productsn'];
            }
            $goodslist[$k]['number']        = 4*$data['nums'];
            $goodslist[$k]['shopname']      = $goods['store_name'];
            $goodslist[$k]['shoplogo']      = $goods['store_logo'];
            $goodslist[$k]['shopid']        = $goods['storeid'];
            $goodslist[$k]['guige']         = $tuan['guige'];
            $goodslist[$k]['utoken']        = '';
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
            $price = []; $yunfei = [];
            foreach($goodslist as $k=>$v){
                $price[]  = $v['marketprice']*$v['number'];
                $yunfei[] = $v['postageprice']*$v['number'];
            }
            $totalprice  = sprintf('%.2f',round(array_sum($price),2));
            $totalyunfei = sprintf('%.2f',round(array_sum($yunfei),2));
            $youhui = sprintf('%.2f',0);
            $manjian = 0;
            $one['totalprice'] = $totalprice;
            $one['yunfei']     = $totalyunfei;
            $one['youhui']     = $youhui;
            $one['shifuprice'] = sprintf('%.2f',$totalprice+$totalyunfei-$youhui);
            $one['suijijian']  = sprintf('%.2f',$goods['groupsprice']-$shijiprice);
            $one['manjian']    = $manjian;
            return json(['code'=>0,'message'=>'SUCCESS','goodslist'=>$goodslist,'address'=>$address,'one'=>$one]);
        }else{
            return json($token);
        }
    }
    public function CreateOrders(){
        $data = input();
        $hid = isset($data['hid']) ? $data['hid'] : 0;
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
            if(!isset($data['tid'])){
                return json(['code'=>400002,'msg'=>'拼团信息不能为空','returnData'=>'']);die;
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
            $count = Db::name('groups_order')->where('parent_id','0')->count();
            $ordersn = 'MT'.sprintf("%08d",($count+1)).rand(100,999);
            $tuan = Db::name('groups')->field('tuanzhang_id,now_num,max_num')->where('id','in',$data['tid'])->limit(1)->find();
            if($tuan['now_num']=="0"){
                $orarr['tid']        = $tuan['tuanzhang_id'];
            }
            $orarr['groups_ids']  = $data['tid'];
            $orarr['groups_key']  = $hid;
            $orarr['user_id']     = $token['myinfo']['id'];
            $orarr['ordersn']     = $ordersn;
            $orarr['number']      = date('YmdHis').rand(1000,9999);
            $orarr['createtime']  = time();
            $orarr['createday']   = date('Ymd');
            $orarr['remark']      = isset($data['remark']) ? $data['remark'] : '';
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
            if($price['shifuprice']=="0"){
                $orarr['price']         = $price['shifuprice']; //应付金额
            }else{
                $orarr['price']         = $price['shifuprice']-$price['suijijian']; //应付金额
                if($orarr['price']<0){
                    $orarr['price'] = 0;
                }
            }
            $orarr['suijijian']     = $price['suijijian'];  //随机立减金额
            $orarr['oldprice']      = $price['shifuprice'];
            $orarr['realprice']     = 0; //实付金额
            $orarr['balance']       = 0; //余额抵扣
            $orarr['points']        = 0; //积分抵扣
            Db::startTrans();
            try {
                $orderid = Db::name('groups_order')->insertGetId($orarr);
                // 提交事务
                Db::commit();
            }catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
            };
            if($orderid){
                $one['orderid'] = $orderid;
                $one['ordersn'] = $ordersn;
                //生成订单商品信息
                $goarr = [];
                foreach($goods as $k=>$v){
                    if($price['totalprice']>'0'){
                        $bl = round($v['price']/$price['totalprice'],6);
                    }else{
                        $bl = 0;
                    }
                    $gone =  Db::name('groups_goods')->field('id,title,description,total,isdistribution,distribution,distribution_2,distribution_3,fan_yue,fan_jifen')->where('id',$v['id'])->find();
                    //判断减库存方式
                    if($v['totalcnf']=="0"){ //下单立减库存
                        if($v['guige']==''){
                            $kuns = $gone;
                            $upadd['total'] = $kuns['total']-$v['number'];
                            Db::startTrans();
                            try {
                                $reip = Db::name('groups_goods')->where('id',$v['id'])->update($upadd);
                                // 提交事务
                                Db::commit();
                            }catch (\Exception $e) {
                                // 回滚事务
                                Db::rollback();
                            };
                        }else{
                            $kuns = Db::name('groups_goods_options_item_desc')->field('total')->where('desc',$v['guige'])->find();
                            $upadd['total'] = $kuns['total']-$v['number'];
                            Db::startTrans();
                            try {
                                $reip = Db::name('groups_goods_options_item_desc')->where('desc',$v['guige'])->update($upadd);
                                // 提交事务
                                Db::commit();
                            }catch (\Exception $e) {
                                // 回滚事务
                                Db::rollback();
                            };
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
                    $goarr[$k]['description']      = $v['description'];
                    $goarr[$k]['desc_title']       = isset($v['attr_val']) ? $v['attr_val'] : '';
                    $goarr[$k]['image']            = str_replace($this->hostname,"",$v['image']);
                    $goarr[$k]['oldprice']         = $v['price'];       // 商品单价
                    $goarr[$k]['marketprice']      = $v['marketprice']; // 商品单价
                    $goarr[$k]['goodsprice']       = $v['goodsprice'];  //商品原价
                    $goarr[$k]['realprice']        = $v['price']-$pjyouhui; //实付金额
                    $goarr[$k]['discount']         = $pjyouhui;        //分配到的优惠金额
                    $goarr[$k]['shopid']           = $v['shopid'];
                    $goarr[$k]['shopname']         = $v['shopname'];
                    $goarr[$k]['shoplogo']         = str_replace($this->hostname,"",$v['shoplogo']);
                    $goarr[$k]['total']            = $v['number'];
                    $goarr[$k]['postageprice']     = $v['postageprice'];
                    $goarr[$k]['realpostageprice'] = $pjyunfei;
                }
                Db::startTrans();
                try {
                    $resgoods = Db::name('groups_order_goods')->insertAll($goarr);
                    // 提交事务
                    Db::commit();
                }catch (\Exception $e) {
                    // 回滚事务
                    Db::rollback();
                }
                //生成日志
                $this->paylog($orderid,'订单已生成','订单已生成，等待付款',$orarr['user_id']);
                return json(['code'=>0,'msg'=>'订单已生成','returnData'=>$one]);die;
            }else{
                return json(['code'=>400003,'msg'=>'生成失败','returnData'=>'']);die;
            }
        }else{
            return json($token);
        }
    }
/************************************************************************/
/** 开团/参团
/************************************************************************/
    public function kaituan(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $tid = isset($data['tid']) ? $data['tid'] : '0';
            $guige = isset($data['guige']) ? $data['guige'] : '';
            $aes = new AES();
            $shijiprice = $aes->decrypt($data['shijiprice']);
            if($tid=='0'){
                $goods = Db::name('groups_goods')->field('groupsnum')->where('id',$data['id'])->find();
                if($guige==""){
                    //锁住当前的产品30s 防止并发时过载
                    $status = $this->lock('groups_key'.$data['id']);
                    if(!$status){
                        $this->unlock('groups_key'.$data['id']);
                        return json(['code'=>1002,'msg'=>'当前抢购火爆，请稍后再试']);die;
                    }
                    $num = redis()->get('groups_'.$data['id']);
                    if($num<$goods['groupsnum']){
                        $this->unlock('groups_key'.$data['id']);
                        return json(['code'=>1001,'msg'=>'库存不足']);die;
                    }
                }else{
                    //锁住当前的产品30s 防止并发时过载
                    $status = $this->lock('groups_goods_key'.$guige);
                    if(!$status){
                        $this->unlock('groups_goods_key'.$guige);
                        return json(['code'=>1002,'msg'=>'当前抢购火爆，请稍后再试']);die;
                    }
                    $num = redis()->get('groups_goods_'.$guige);
                    if($num<$goods['groupsnum']){
                        $this->unlock('groups_goods_key'.$guige);
                        return json(['code'=>1003,'msg'=>'库存不足']);die;
                    }
                }
                $addg['uid']     = $token['myinfo']['id'];
                $addg['pid1']    = $token['myinfo']['pid1'];
                $addg['pid2']    = $token['myinfo']['pid2'];
                $addg['tz_level']= $token['myinfo']['tz_level'];
                $addg['ordersn'] = date("YmdHis").rand(1000,9999);
                $addg['addtime'] = time();
                $addg['addday']  = date("Ymd");
                $addg['max_num'] = $goods['groupsnum'];
                $addg['now_num'] = 0;
                $addg['tuanzhang_id'] = $token['myinfo']['id'];
                $addg['goods_id'] = $data['id'];
                $addg['guige']    = $guige;
                $res = Db::name('groups')->insertGetId($addg);
                if($res){
                    //将团的容纳人数写入缓存
                    redis()->set('tuan_'.$res,$addg['max_num']);
                    redis()->decrBy('tuan_'.$res,1);
                    //完成清除锁
                    if($guige==""){
                        //减少库存 释放锁
                        redis()->decrBy('groups_'.$data['id'],$goods['groupsnum']);
                        $this->unlock('groups_key'.$data['id']);
                    }else{
                        //减少库存 释放锁
                        redis()->decrBy('groups_goods_'.$guige,$goods['groupsnum']);
                        $this->unlock('groups_goods_key'.$guige);
                    }
                    return json(['code'=>0,'msg'=>'开团成功','id'=>$res,'shijiprice'=>$data['shijiprice'],'num'=>$num,'hid'=>0]);die;
                }else{
                    return json(['code'=>1002,'msg'=>'开团失败']);die;
                }
            }else{
                $status = $this->lock('tuan_key'.$tid);
                if($status){
                    $num = redis()->get('tuan_'.$tid);
                    if($num<="0"){
                        $this->unlock('tuan_key'.$tid);
                        return json(['code'=>'1001','msg'=>'该团已满','num'=>$num]);die;
                    }else{
                        //减少库存 释放锁
                        redis()->decrBy('tuan_'.$tid,1);
                        
                        //生成一个5分钟的号
                        //$configs = Db::name('webconfig')->field('quxiaotime,shouhuotime,wanchengtime')->where('id','1')->find();
    					//$quxiaotime  = isset($configs['quxiaotime']) ? $configs['quxiaotime'] : 5;
    					$quxiaotime = 10;
    					$qxtime = $quxiaotime*60;
                        $rhid = 'tid'.$tid.'_num'.$num.'_t'.time().rand(100,999);
                        redis()->set($rhid,'1',$qxtime);
                        // 
                        $dl['id']       = 'tid'.$tid.'_num'.$num.'_uid'.$token['myinfo']['id'];
                        $dl['uid']      = $token['myinfo']['id'];
                        $dl['hid']      = $rhid;
                        $dl['addtime']  = time();
                        $dl['endtime']  = time()+$qxtime;
                        $dl['tuan_id']  = $tid;
                        $dl['tuan_key'] = 'tid'.$tid.'_num'.$num;
                        $dl['status']   = 0; //0占号中 1占号成功 2 占号失败
                        redis()->lPush('tuan_cache'.$tid,json_encode($dl,255));
                        $this->unlock('tuan_key'.$tid);
                        return json(['code'=>0,'msg'=>'参团成功','id'=>$tid,'shijiprice'=>$data['shijiprice'],'num'=>$num,'hid'=>$rhid]);die;
                    }
                }else{
                    return json(['code'=>1002,'msg'=>'当前抢购火爆，请稍后再试']);die;
                }
            }
        }else{
            return json($token);
        }
    }
    public function shifangTuan(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $one = Db::name('groups')->field('id,max_num,goods_id,guige,status')->where('id',$data['tid'])->find();
            if($one['status']=="0"){ //开团中
                if($one['guige']){
                    //增加库存缓存
                    redis()->incrBy('groups_goods_'.$one['guige'],$one['max_num']);
                    redis()->incrBy('tuan_'.$one['id'],1);
                }else{
                    //增加库存缓存
                    redis()->incrBy('groups_'.$one['goods_id'],$one['max_num']);
                    $hid = isset($data['hid']) ? $data['hid'] : '0';
                	redis()->del($hid);
                }
            }else if($one['status']=="1"){ //已开团
                //增加库存缓存
                $hid = isset($data['hid']) ? $data['hid'] : '0';
                redis()->del($hid);
                //增加库存缓存
        		redis()->incrBy('tuan_'.$data['tid'],1);
            }else if($one['status']=="2"){ //已成团

            }
            return json(['code'=>0,'msg'=>'SUCCESS']);
        }else{
            return json($token);
        }
    }
/************************************************************************/
/** 确认订单
/************************************************************************/
    public function querenOrder(){  //token,shijiprice,tid,addressid,
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $aes = new AES();
            $shijiprice = $aes->decrypt($data['shijiprice']);
            $oshijiprice = $shijiprice;
            //默认收货地址
            $addressid = isset($data['addressid']) ? $data['addressid'] : '0';
            if($addressid=='0'){
                $address = Db::name('member_address')->field('is_del,updatetime,aorder,addtime,diquid',true)->where(['uid'=>$token['myinfo']['id'],'is_del'=>'n'])->order('aorder desc,addtime desc')->limit(1)->find();
            }else{
                $address = Db::name('member_address')->field('is_del,updatetime,aorder,addtime,diquid',true)->where(['id'=>$addressid])->order('aorder desc,addtime desc')->limit(1)->find();
                $addressid = $address['id'];
            }

            $tuanzhang = false;
            $tjy = 0;
            $tuan = Db::name('groups')->where('id',$data['tid'])->find();
            $goods =  Db::name('groups_goods')->field('id,title,short_title,thumb,description,groupsprice,marketprice,goodsprice,storeid,store_name,total,store_logo,ednum,edmoney,postagetype,postageid,postageprice,ispostage,edareas,goodssn,productsn,totalcnf')->where('id',$tuan['goods_id'])->find();
            //如果是团长并且是刚开团 判断团长开团是否要付钱
            if($tuan['tuanzhang_id']==$token['myinfo']['id'] && $tuan['now_num']=="0"){
                $tuanzhang = true;
                $configspt = Db::name('groups_configs')->field('tuanzhang_youhui')->where('id','1')->find();
                if($configspt['tuanzhang_youhui']>'0'){
                    if(strstr($configspt['tuanzhang_youhui'],'%')){
                        $fxbl = str_replace("%",'',$configspt['tuanzhang_youhui']);
                        $fxbl = sprintf("%.2f",$fxbl);
                        $jian = $shijiprice*($fxbl/100);
                    }else{
                        $jian = $configspt['tuanzhang_youhui'];
                    }
                    $shijiprice = $goods['groupsprice']-$jian;
                    $tjy = $jian;
                    if($shijiprice<'0'){
                        $shijiprice = 0;
                        $tjy = $oshijiprice;
                    }
                }else{
                    $shijiprice = 0;
                    $tjy = $goods['groupsprice'];
                }
            }
            $k = 0;
            $goodslist[$k]['id']         = $goods['id'];
            $goodslist[$k]['image']      = getImage($goods['thumb']);
            $goodslist[$k]['stock']      = $goods['total'];
            $goodslist[$k]['title']      = $goods['title'];
            $goodslist[$k]['description']= $goods['description'];
            $goodslist[$k]['price']      = $shijiprice;
            $goodslist[$k]['marketprice']= $goods['groupsprice'];
            $goodslist[$k]['goodsprice'] = $goods['goodsprice'];
            $goodslist[$k]['goodssn']    = $goods['goodssn'];
            $goodslist[$k]['productsn']  = $goods['productsn'];
            $goodslist[$k]['totalcnf']   = $goods['totalcnf'];
            if($tuan['guige']){
                $guige = Db::name('groups_goods_options_item_desc')->field('desc_title,total,marketprice,goodsprice,j_weight,m_weight,goodssn,productsn')->where('desc',$tuan['guige'])->find();
                $goodslist[$k]['attr_val']   = $guige['desc_title'];
                $goodslist[$k]['stock']      = $guige['total'];
                $goodslist[$k]['price']      = $shijiprice;
                $goodslist[$k]['marketprice']= $oshijiprice;
                $goodslist[$k]['goodsprice'] = $guige['goodsprice'];
                $goodslist[$k]['goodssn']    = $guige['goodssn'];
                $goodslist[$k]['productsn']  = $guige['productsn'];
            }
            $goodslist[$k]['number']        = 1;
            $goodslist[$k]['shopname']      = $goods['store_name'];
            $goodslist[$k]['shoplogo']      = $goods['store_logo'];
            $goodslist[$k]['shopid']        = $goods['storeid'];
            $goodslist[$k]['guige']         = $tuan['guige'];
            $goodslist[$k]['utoken']        = '';
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
            $price = []; $yunfei = [];
            foreach($goodslist as $k=>$v){
                $price[]  = $v['marketprice'];
                $yunfei[] = $v['postageprice'];
            }
            $totalprice  = sprintf('%.2f',round(array_sum($price),2));
            $totalyunfei = sprintf('%.2f',round(array_sum($yunfei),2));
            // $youhui  = sprintf('%.2f',$goods['groupsprice']-$shijiprice);
            $youhui = 0;
            if($tuanzhang){
                $youhui = sprintf('%.2f',$tjy);
            }else{

            }
            $manjian = 0;
            $one['totalprice'] = $totalprice;
            $one['yunfei']     = $totalyunfei;
            $one['youhui']     = $youhui;
            $one['shifuprice'] = sprintf('%.2f',$totalprice+$totalyunfei-$youhui);
            $one['suijijian']  = sprintf('%.2f',$goods['groupsprice']-$shijiprice);
            $one['manjian']    = $manjian;
            return json(['code'=>0,'message'=>'SUCCESS','goodslist'=>$goodslist,'address'=>$address,'one'=>$one]);
        }else{
            return json($token);
        }
    }
/************************************************************************/
/** 生成订单
/************************************************************************/
    public function CreateOrder(){
        $data = input();
        $hid = isset($data['hid']) ? $data['hid'] : 0;
        $haoid = redis()->get($hid);
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
            if(!isset($data['tid'])){
                return json(['code'=>400002,'msg'=>'拼团信息不能为空','returnData'=>'']);die;
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
            $count = Db::name('groups_order')->where('parent_id','0')->count();
            $ordersn = 'MT'.sprintf("%08d",($count+1)).rand(100,999);
            $tuan = Db::name('groups')->field('tuanzhang_id,now_num,max_num')->where('id',$data['tid'])->find();
            if($tuan['now_num']=="0"){
                $orarr['tid']        = $tuan['tuanzhang_id'];
            }else{
            	if(!$haoid){
        			return json(['code'=>1,'msg'=>'订单已失效，请重新参团','returnData'=>'']);die;
        		}
            }
            $orarr['groups_id']  = $data['tid'];
            $orarr['groups_key'] = $hid;
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
            if($price['shifuprice']=="0"){
                $orarr['price']         = $price['shifuprice']; //应付金额
            }else{
                $orarr['price']         = $price['shifuprice']-$price['suijijian']; //应付金额
                if($orarr['price']<0){
                    $orarr['price'] = 0;
                }
            }
            $orarr['suijijian']     = $price['suijijian'];  //随机立减金额
            $orarr['oldprice']      = $price['shifuprice'];
            $orarr['realprice']     = 0; //实付金额
            $orarr['balance']       = 0; //余额抵扣
            $orarr['points']        = 0; //积分抵扣
            Db::startTrans();
            try {
                $orderid = Db::name('groups_order')->insertGetId($orarr);
                // 提交事务
                Db::commit();
            }catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
            };
            if($orderid){
                $one['orderid'] = $orderid;
                $one['ordersn'] = $ordersn;
                //生成订单商品信息
                $goarr = [];
                foreach($goods as $k=>$v){
                    if($price['totalprice']>'0'){
                        $bl = round($v['price']/$price['totalprice'],6);
                    }else{
                        $bl = 0;
                    }
                    $gone =  Db::name('groups_goods')->field('id,title,description,total,isdistribution,distribution,distribution_2,distribution_3,fan_yue,fan_jifen')->where('id',$v['id'])->find();
                    //判断减库存方式
                    if($v['totalcnf']=="0"){ //下单立减库存
                        if($v['guige']==''){
                            $kuns = $gone;
                            $upadd['total'] = $kuns['total']-$v['number'];
                            Db::startTrans();
                            try {
                                $reip = Db::name('groups_goods')->where('id',$v['id'])->update($upadd);
                                // 提交事务
                                Db::commit();
                            }catch (\Exception $e) {
                                // 回滚事务
                                Db::rollback();
                            };
                        }else{
                            $kuns = Db::name('groups_goods_options_item_desc')->field('total')->where('desc',$v['guige'])->find();
                            $upadd['total'] = $kuns['total']-$v['number'];
                            Db::startTrans();
                            try {
                                $reip = Db::name('groups_goods_options_item_desc')->where('desc',$v['guige'])->update($upadd);
                                // 提交事务
                                Db::commit();
                            }catch (\Exception $e) {
                                // 回滚事务
                                Db::rollback();
                            };
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
                    $goarr[$k]['description']      = $v['description'];
                    $goarr[$k]['desc_title']       = isset($v['attr_val']) ? $v['attr_val'] : '';
                    $goarr[$k]['image']            = str_replace($this->hostname,"",$v['image']);
                    $goarr[$k]['oldprice']         = $v['price'];       // 商品单价
                    $goarr[$k]['marketprice']      = $v['marketprice']; // 商品单价
                    $goarr[$k]['goodsprice']       = $v['goodsprice'];  //商品原价
                    $goarr[$k]['realprice']        = $v['price']-$pjyouhui; //实付金额
                    $goarr[$k]['discount']         = $pjyouhui;        //分配到的优惠金额
                    $goarr[$k]['shopid']           = $v['shopid'];
                    $goarr[$k]['shopname']         = $v['shopname'];
                    $goarr[$k]['shoplogo']         = str_replace($this->hostname,"",$v['shoplogo']);
                    $goarr[$k]['total']            = $v['number'];
                    $goarr[$k]['postageprice']     = $v['postageprice'];
                    $goarr[$k]['realpostageprice'] = $pjyunfei;
                    //佣金/奖励
                }
                Db::startTrans();
                try {
                    $resgoods = Db::name('groups_order_goods')->insertAll($goarr);
                    // 提交事务
                    Db::commit();
                }catch (\Exception $e) {
                    // 回滚事务
                    Db::rollback();
                }
                //生成日志
                $this->paylog($orderid,'订单已生成','订单已生成，等待付款',$orarr['user_id']);
                return json(['code'=>0,'msg'=>'订单已生成','returnData'=>$one]);die;
            }else{
                return json(['code'=>400003,'msg'=>'生成失败','returnData'=>'']);die;
            }
        }else{
            return json($token);
        }
    }
    //0元付款
    public function tuanzhangFukuan(){
        $ordersn = $this->request->param('ordersn') ? $this->request->param('ordersn') : "";
        $order = Db::name('groups_order')->field('id,user_id,ordersn,groups_id')->where('ordersn',$ordersn)->find();
        $id = $order['id'];
        $uparra['pay_status']   = "TRADE_SUCCESS";
        $uparra['pay_transid']  = $order['ordersn'];
        $uparra['pay_time']     = time();
        $uparra['pay_body']     = "免费参团";
    
        $orarr['pay_time']    = $uparra['pay_time'];
        $orarr['pay_day']     = date('Ymd',$uparra['pay_time']);
        $orarr['pay_body']    = $uparra['pay_body'];
        $orarr['pay_type']    = "mianfei";
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
            $tuan    = Db::name('groups')->field('id,now_num')->where('id',$order['groups_id'])->find();
            $now_num = $tuan['now_num'] + 1;
            $uarr    = Db::name('groups')->where('id',$order['groups_id'])->update(['now_num'=>$now_num,'status'=>1]);
            $ayy = array('code'=>0,'msg'=>'支付成功');
            return json($ayy);
        }else{
            $ayy = array('code'=>1,'msg'=>'支付失败');
            return json($ayy);
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
     /**
     * 获取锁
     * @param  String  $key    锁标识
     * @param  Int     $expire 锁过期时间
     * @return Boolean
     */
    public function lock($key, $expire=10){
        $is_lock = redis()->setnx($key, time()+$expire);
        // 不能获取锁
        if(!$is_lock){
            // 判断锁是否过期
            $lock_time = redis()->get($key);

            // 锁已过期，删除锁，重新获取
            if(time()>$lock_time){
                $this->unlock($key);
                $is_lock = redis()->setnx($key, time()+$expire);
            }
        }
        return $is_lock? true : false;
    }
     /**
     * 释放锁
     * @param  String  $key 锁标识
     * @return Boolean
     */
    public function unlock($key){
        return redis()->del($key);
    }
/************************************************************************/
/** 拼团详情
/************************************************************************/
	public function tuanLists(){
		$data = input();
        $pn    = isset($data['pn']) ? $data['pn'] : 1;
        $limit = isset($data['limit']) ? $data['limit'] : 10;
        $start = ($pn-1)*$limit;
        $nowtuan = Db::name('groups')->field('tuanzhang_id,id,goods_id,max_num,uid')->where('id',$data['tid'])->where('status','in',['1','2','3'])->find();
        if(isset($data['token']) && $data['token']){
            $token = $this->CheckToken($data['token']);
            if($token['code']=="0"){
                if($token['myinfo']['level']>'0'){
                    if($token['myinfo']['id']!=$nowtuan['tuanzhang_id']){
                        $nowtuan = Db::name('groups')->field('tuanzhang_id,id,goods_id,max_num,uid')->where('tuanzhang_id',$token['myinfo']['id'])->where('status','in',['1','2','3'])->order('addtime desc')->limit(1)->find();
                    }
                }
            }
        }
        if(!$nowtuan){
            return json(['code'=>2,'msg'=>'团已关闭']);die;
        }
        $list = Db::name('groups')->alias('g')
        		->field('g.id,g.max_num,g.ordersn,g.goods_id,m.id as uid,m.nickname,m.username,m.avatar')
        		->where(['g.is_del'=>'n'])->where('g.status','in',['1','2','3'])->where('g.id','neq',$nowtuan['id'])
        		// ->where('uid','neq',$nowtuan['uid'])
        		->join('member m','m.id=g.tuanzhang_id','left')
        		->order('g.addtime desc')
        		->limit($start,$limit)->select();
       	foreach($list as $k => $v) {
       		$count = Db::name('groups_order')->where(['groups_id'=>$v['id']])->where('status','in',['0','1','2','3','4'])->count();
       		$list[$k]['cha_num'] = $v['max_num']-$count;
            if($list[$k]['cha_num']<'0'){
                $list[$k]['cha_num'] = 0;
            } 
       	}
       	$count = Db::name('groups')->alias('g')
        		->field('g.id,g.max_num,g.ordersn,g.goods_id,m.id as uid,m.nickname,m.username,m.avatar')
        		->where(['g.is_del'=>'n'])->where('g.status','in',['1','2','3'])->where('g.id','neq',$nowtuan['id'])
        		// ->where('uid','neq',$nowtuan['uid'])
        		->join('member m','m.id=g.tuanzhang_id','left')
        		->count();

 		$nowtuan['goods'] = Db::name('groups_goods')->field('id,title,thumb,description,costprice,groupsprice')->where('id',$nowtuan['goods_id'])->find();
 		$configs = Db::name('webconfig')->field('quxiaotime,shouhuotime,wanchengtime')->where('id','1')->find();
 		$orderlist = Db::name('groups_order')->field('status,id,user_id,createtime')->where('groups_id',$nowtuan['id'])->where('status','0')->where('tid','0')->select();
 		foreach($orderlist as $k=>$v){
 			$createcha = time() - $v['createtime'];
            $quxiaotime = isset($configs['quxiaotime']) ? $configs['quxiaotime'] : 60;
            if($v['status']=="0" && $createcha>=(60*$quxiaotime)){
            	$uparr['cancel_time'] = $v['createtime']+(60*$quxiaotime);
                $uparr['cancel_day']  = date("Ymd",($v['createtime']+(60*$quxiaotime)));
                $uparr['status']      = "-1";
                $res = Db::name('groups_order')->where('id',$v['id'])->update($uparr);
                $this->paylog($v['id'],'订单已取消','订单超时自动取消',$v['user_id']);
                groupshuankuncun($v['id']);
            }
 		}

 		$nowtuan['goods']['thumb'] = getImage($nowtuan['goods']['thumb']);
 		$strs = "------";
        $zhengfu     = substr(str_shuffle($strs),mt_rand(0,strlen($strs)-2),1);
        $buchang     = rand(0,(int)($nowtuan['goods']['costprice'])*100);
        if($zhengfu=='+'){
            $nowtuan['goods']['groupspricea'] = sprintf('%.2f',$nowtuan['goods']['groupsprice']+($buchang/100));
        }else{
            $nowtuan['goods']['groupspricea'] = sprintf('%.2f',$nowtuan['goods']['groupsprice']-($buchang/100));
        }
        $nowtuan['tuanzhang'] = Db::name('member')->field('id,nickname,username,avatar')->where('id',$nowtuan['tuanzhang_id'])->find();
        $ccount = Db::name('groups_order')->where(['groups_id'=>$nowtuan['id']])->where('status','in',['0','1','2','3','4'])->count();
        $nowtuan['tuanzhang']['cha_num'] = $nowtuan['max_num']-$ccount;
        if($list){
        	return json(['code'=>0,'tuan'=>$list,'nowtuan'=>$nowtuan,'count'=>$count]);
        }else{
        	return json(['code'=>1,'tuan'=>$list,'nowtuan'=>$nowtuan,'count'=>$count]);
        }
	}
    public function tuanlist(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $limit = 10;
            $pn = isset($data['pn']) ? $data['pn'] : 1;
            $start = ($pn-1)*$limit;
            $where[] = ['a.is_del','=','n'];
            $wd = isset($data['wd']) ? $data['wd'] : '0';
            $state = isset($data['state']) ? $data['state'] : '0';
            if($state=="0"){
            	$where[] = ['a.status','>','0'];
            }else if($state=="1"){
            	$where[] = ['a.status','=','1'];
            }else{
            	$where[] = ['a.status','in',['2','3']];
            }
            if($wd=='0'){ //我开的团
                $where[] = ['a.tuanzhang_id','=',$token['myinfo']['id']];
            }else{ //我参的团
            	$orders = Db::name('groups_order')->field('id,groups_id,tid')
            			->where(['user_id'=>$token['myinfo']['id'],'tid'=>'0','is_del'=>'n','is_show'=>'y'])->select();
                $gids = [];
                $gids[] = 0;
                foreach($orders as $k=>$v){
                	$gids[] = $v['groups_id'];
                }
                $where[] = ['a.id','in',$gids];
            }
            $list = Db::name('groups')->alias('a')
            		->field('a.id,a.ordersn as gsn,a.status as gs,a.tuanzhang_id,a.addtime')
            		->where($where)
            		->order('a.addtime desc')
                    ->limit($start,$limit)
            		->select();
            foreach($list as $k=>$v){
            	$order = Db::name('groups_order')->field('id,ordersn,status')->where(['groups_id'=>$v['id'],'tid'=>$v['tuanzhang_id']])->find();
                $totals = [];
                $goodsList = Db::name('groups_order_goods')->field('id,title,desc_title,image,marketprice,goodsprice,realprice,total')->where('order_id',$order['id'])->order('id asc')->select();
                foreach($goodsList as $key=>$val){
                    $goodsList[$key]['image'] = $this->hostname.$val['image'];
                    $totals[] = $val['total'];
                }
                $list[$k]['ordersn']  = $order['ordersn'];
                $list[$k]['status']   = $order['status'];
                $list[$k]['addtime'] = date("Y-m-d H:i:s",$v['addtime']);
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
    public function tuanDetail(){
        $data = input();
        $ordero = Db::name('groups_order')->field('id,groups_id,number,ordersn')->where(['ordersn'=>$data['ordersn']])->find();
        $one = Db::name('groups')->field('id,addtime,finishtime,id,tuanzhang_id,max_num,now_num,status')->where('id',$ordero['groups_id'])->find();
        if($one){
            $one['addtime'] = date("Y-m-d H:i:s",$one['addtime']);
            if($one['finishtime']){
                $one['finishtime'] = date("Y-m-d H:i:s",$one['finishtime']);
            }
            $one['cha_num'] = $one['max_num'] - $one['now_num'];
            $tuanzhang = Db::name('member')->field('id,nickname,avatar')->where('id',$one['tuanzhang_id'])->find();
            $goods = DB::name('groups_order_goods')
                    ->alias('a')
                    ->field('a.id,a.title,a.image,a.description,a.desc,b.groupsprice,b.costprice,b.id as gid,b.thumbhb')
                    ->join('groups_goods b', 'b.id=a.goods_id', 'left')
                    ->where('a.order_id',$ordero['id'])
                    ->find();

            $strs = "------";
            $zhengfu     = substr(str_shuffle($strs),mt_rand(0,strlen($strs)-2),1);
            $buchang     = rand(0,(int)($goods['costprice'])*100);
            if($zhengfu=='+'){
                $goods['groupspricea'] = sprintf('%.2f',$goods['groupsprice']+($buchang/100));
            }else{
                $goods['groupspricea'] = sprintf('%.2f',$goods['groupsprice']-($buchang/100));
            }
            $goods['thumbhb'] = getImage($goods['thumbhb']);
            $aes = new AES();
            $goods['shijiprice']  = $aes->encrypt($goods['groupspricea']);
            $goods['image'] = $this->hostname.$goods['image'];
            $haoyou = Db::name('groups_order')
                    ->alias('aa')
                    ->field('aa.id as goid,bb.id,bb.nickname,bb.avatar,aa.status,aa.createtime,aa.send_time,aa.shou_time,aa.user_id,aa.groups_key')
                    ->join('member bb', 'bb.id=aa.user_id', 'left')
                    ->where(['aa.groups_id'=>$one['id']])
                    ->where('aa.status','in',['0','1','2','3','4'])
                    ->order('aa.createtime asc')
                    ->select();
            $configs = Db::name('webconfig')->field('quxiaotime,shouhuotime,wanchengtime')->where('id','1')->find();
            $yio = [];
            $yio[] = '0';
            $pylist = [];
            foreach($haoyou as $k=>$v){
            	$pylist[] = $v;
                //超时自动取消
                $createcha = time() - $v['createtime'];
                $quxiaotime = isset($configs['quxiaotime']) ? $configs['quxiaotime'] : 60;
                if($v['status']=="0" && $createcha>=(60*$quxiaotime)){
                    $uparr['cancel_time'] = $v['createtime']+(60*$quxiaotime);
                    $uparr['cancel_day']  = date("Ymd",($v['createtime']+(60*$quxiaotime)));
                    $uparr['status']      = "-1";
                    $res = Db::name('groups_order')->where('id',$v['goid'])->update($uparr);
                    $this->paylog($v['goid'],'订单已取消','订单超时自动取消',$v['user_id']);
                    $haoyou[$k]['status'] = $v['status'] = "-1";
                    groupshuankuncun($v['goid']);
                }
                    //超时自动收货
                    $sendcha = time()-$v['send_time'];
                    $shouhuotime = isset($configs['shouhuotime']) ? $configs['shouhuotime'] : 7;
                    if($v['status']=="2" && $sendcha>=(60*60*24*$shouhuotime)){
                        $uparr['shou_time'] = $v['send_time']+(60*60*24*$shouhuotime);
                        $uparr['shou_day']  = date("Ymd",($v['send_time']+(60*60*24*$shouhuotime)));
                        $uparr['status']      = "3";
                        $res = Db::name('groups_order')->where('id',$v['goid'])->update($uparr);
                        $this->paylog($v['goid'],'订单已收货','订单已成功收货',$v['user_id']);
                        $haoyou[$k]['status'] = $v['status'] = "3";
                }
                //超时自动完成
                $shoucha = time()-$v['shou_time'];
                $wanchengtime = isset($configs['wanchengtime']) ? $configs['wanchengtime'] : 7;
                if($v['status']=="3" && $shoucha>=(60*60*24*$wanchengtime)){
                    $uparr['finish_time'] = $v['shou_time']+(60*60*24*$wanchengtime);
                    $uparr['finish_day']  = date("Ymd",($v['shou_time']+(60*60*24*$wanchengtime)));
                    $uparr['status']      = "4";
                    $res = Db::name('groups_order')->where('id',$v['goid'])->update($uparr);
                    $this->paylog($v['goid'],'订单已完成','订单已完成，感谢您的支持',$v['user_id']);
                    $haoyou[$k]['status'] = "4";
                }
                if($v['status']>='0'){
            		$yio[] = $v['groups_key'];
            	}
            }
            $haoyoucount = count($haoyou);
            $one['cha_num'] = $one['max_num']-$haoyoucount;
            $token = $token = $this->CheckToken($data['token']);
            $one['yican'] = 0;
            if($token['code']=='0'){
            	foreach($haoyou as $k=>$v){
            		if($token['myinfo']['id']==$v['id']){
            			$one['yican'] = 1;
            		}
            	}
            }
            $one['qrcode'] = $this->qrcode($one['id']);
            $shengyu = $this->tuanCount($one['id']);
            if(isset($shengyu['list'])){
            	foreach($shengyu['list'] as $k=>$v){
            		$mem = Db::name('member')->field('avatar,nickname,id')->where('id',$v['uid'])->find();
            		$mem['status'] = 10;
            		$pylist[] = $mem;
            	}
            }
            return json(['code'=>0,'msg'=>'获取成功','tuan'=>$one,'order'=>$ordero,'tuanzhang'=>$tuanzhang,'goods'=>$goods,'haoyou'=>$pylist,'shengyu'=>$shengyu]);
        }else{
            return json(['code'=>1,'msg'=>'团不存在']);
        }
    }
    //生成带参数的小程序二维码
    public function qrcode($tid){
        $APPID     = "wx4e21922e720f7ee7"; 
        $APPSECRET =  "7efa59235a7b8f2e81e08d33e2c7ce20";
        $access_token = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$APPID."&secret=".$APPSECRET;
        $access = Db::name('webconfig')->field('access_token,access_token_time')->where('id','1')->find();
        $token = '';
        if($access['access_token']){
            $cha = time()-$access['access_token_time'];
            if($cha<((60*60*2)-30)){
                $token = $access['access_token'];
            }
        }
        if($token==""){
            $json = $this->httpRequest($access_token);
            $json = json_decode($json,1);
            $uparr['access_token']      =  $token = $json['access_token'];
            $uparr['access_token_time'] = time();
            $upres = Db::name('webconfig')->where('id','1')->update($uparr);
        }
        $qcode  ="https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=".$token;
        $param  = json_encode(array("scene"=>$tid,"page"=>"pages/index/pintuanList","width"=>750));

        $result = $this->httpRequest($qcode,$param,"POST");
        file_put_contents("./qrcode/qrcode".$tid.".png", $result);
        $base64_image ="data:image/jpeg;base64,".base64_encode($result);
        $img = $this->hostname."/qrcode/qrcode".$tid.".png";
        return $img;
    }
    public function httpRequest($url, $data='', $method='GET'){
        $curl = curl_init();  
        curl_setopt($curl, CURLOPT_URL, $url);  
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);  
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);  
        curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);  
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);  
        curl_setopt($curl, CURLOPT_AUTOREFERER, 1);  
        if($method=='POST'){
            curl_setopt($curl, CURLOPT_POST, 1); 
            if ($data != '')
            {
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);  
            }
        }
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);  
        curl_setopt($curl, CURLOPT_HEADER, 0);  
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);  
        $result = curl_exec($curl);  
        curl_close($curl);  
        return $result;
    } 
/************************************************************************/
/** 数据排名
/************************************************************************/
    public function paiming(){
    	$data = input();
    	$types = isset($data['types']) ? $data['types'] : '0';
    	$pn    = isset($data['pn']) ? $data['pn'] : '1';
    	$limit = isset($data['limit']) ? $data['limit'] : '15';
    	$start = ($pn-1)*$limit;
        $banner['thumb'] = $this->hostname."/uploads/images/pmbanner.png";
        $whereg[] = ['g.status','in',['2','3']];
        $day = date("Ym",mktime(0, 0 , 0,date("m")-1,1,date("Y")));
        if($types=="0"){ //今日排行
            $whereg[] = ['g.finishday','=',date('Ymd')];
        }else if($types=="1"){ //月排名
            $whereg[] = ['g.finishday','like',date('Ym').'%'];
        }else if($types=="2"){ //总排名

        }else if($types=="3"){ //上月排名
            $whereg[] = ['g.finishday','like',$day."%"];
        }
    	$lista = Db::name('member')->alias('m')
                ->field('m.id,m.avatar,m.nickname,m.username,IF(g.tuanzhang_id,count(*),count(*)-1) as count')
                ->where(['m.is_del'=>'n','is_show'=>'y'])
                ->join("groups g",'g.tuanzhang_id=m.id','left')
                ->group('g.tuanzhang_id,m.id')
                ->where($whereg)
                ->order('count desc,m.addtime asc')
                ->limit($start,$limit)
    			->select();
        $countlist = Db::name('member')->alias('m')
                ->field('m.id')
                ->where(['m.is_del'=>'n','is_show'=>'y'])
                ->join("groups g",'g.tuanzhang_id=m.id','left')
                ->group('g.tuanzhang_id,m.id')
                ->where($whereg)
                ->select();
        $uids = [];
        $uids[] = 0;
        foreach($countlist as $k=>$v){
            $uids[] = $v['id'];
        }
        $counta = count($lista);
        $countb = count($countlist);

        $limita = $limit-$counta;
        if($countb==0){
            $pna = $pn;
        }else{
            $pna = $pn-(ceil($countb/$limit)-1);
        }
        if($pna<='0' || $limita<='0'){
            $listb = [];
        }else{
            if($limita<='0'){
                $limita = $limit;
            }
            if($pna<='0'){
                $pna = 1;
            }
            $starta = ($pna-1)*$limita;
            $listb = Db::name('member')->field('id,nickname,username,avatar')->where('is_del','n')->where('is_show','y')->where('id','not in',$uids)->order('addtime asc')->limit($starta,$limita)->select();
        }
        $count = Db::name('member')->where('is_del','n')->where('is_show','y')->count();
        $list = [];
        foreach($lista as $k=>$v){
            $list[] = $v;
        }
        foreach($listb as $k=>$v){
            $v['count'] = 0;
            $list[] = $v;
        }
        if($list){
            $code = 0;
        }else{
            $code = 1;
        }
    	return json(['code'=>$code,'list'=>$list,'banner'=>$banner,'count'=>$count]);
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

            $list = Db::name('groups_order')->field('id,user_id,ordersn,price,oldprice,realprice,status,createtime,send_time,shou_time')->where($where)->limit($start,$limit)->order('createtime desc')->select();
            foreach($list as $k=>$v){
                // orderZidan($v['id']);
                $totals = [];
                $goodsList = Db::name('groups_order_goods')->field('id,title,desc_title,image,marketprice,goodsprice,realprice,total')->where('order_id',$v['id'])->order('id asc')->select();
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
                    $res = Db::name('groups_order')->where('id',$v['id'])->update($uparr);
                    $this->paylog($v['id'],'订单已取消','订单超时自动取消',$v['user_id']);
                    $list[$k]['status'] = $v['status'] = "-1";
                    groupshuankuncun($v['id']);
                }
                //超时自动收货
                $sendcha = time()-$v['send_time'];
                $shouhuotime = isset($configs['shouhuotime']) ? $configs['shouhuotime'] : 7;
                if($v['status']=="2" && $sendcha>=(60*60*24*$shouhuotime)){
                    $uparr['shou_time'] = $v['send_time']+(60*60*24*$shouhuotime);
                    $uparr['shou_day']  = date("Ymd",($v['send_time']+(60*60*24*$shouhuotime)));
                    $uparr['status']      = "3";
                    $res = Db::name('groups_order')->where('id',$v['id'])->update($uparr);
                    $this->paylog($v['id'],'订单已收货','订单已成功收货',$v['user_id']);
                    $list[$k]['status'] = $v['status'] = "3";
                }
                //超时自动完成
                $shoucha = time()-$v['shou_time'];
                $wanchengtime = isset($configs['wanchengtime']) ? $configs['wanchengtime'] : 7;
                if($v['status']=="3" && $shoucha>=(60*60*24*$wanchengtime)){
                    $uparr['finish_time'] = $v['shou_time']+(60*60*24*$wanchengtime);
                    $uparr['finish_day']  = date("Ymd",($v['shou_time']+(60*60*24*$wanchengtime)));
                    $uparr['status']      = "4";
                    $res = Db::name('groups_order')->where('id',$v['id'])->update($uparr);
                    $this->paylog($v['id'],'订单已完成','订单已完成，感谢您的支持',$v['user_id']);
                    $list[$k]['status'] = "4";
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
            $one = Db::name('groups_order')->field('id,user_id,ordersn,goodsprice,price,discount,postageprice,createtime,send_time,shou_time,status,pay_time,pay_type,pay_transid,express_sn,express_company,consignee_name,consignee_mobile,consignee_address,createtime,remark')->where('ordersn',$data['ordersn'])->find();
            $cha = time()-$one['createtime'];
            $waittime = 60*60;
            $chaa = $waittime-$cha;
            if($chaa>0){
                $one['cha'] = $chaa;
            }else{
                $one['cha'] = 0;
            }
            $configs = Db::name('webconfig')->field('quxiaotime,shouhuotime,wanchengtime')->where('id','1')->find();
            //超时自动取消
            $createcha = time() - $one['createtime'];
            $quxiaotime = isset($configs['quxiaotime']) ? $configs['quxiaotime'] : 60;
            if($one['status']=="0" && $createcha>=(60*$quxiaotime)){
                $uparr['cancel_time'] = $one['createtime']+(60*$quxiaotime);
                $uparr['cancel_day']  = date("Ymd",($one['createtime']+(60*$quxiaotime)));
                $uparr['status']      = "-1";
                $res = Db::name('groups_order')->where('id',$one['id'])->update($uparr);
                $this->paylog($one['id'],'订单已取消','订单超时自动取消',$one['user_id']);
                $one['status'] = "-1";
                groupshuankuncun($one['id']);
            }
            $one['createtime'] = date("Y-m-d H:i:s",$one['createtime']);
            $one['paytime'] = date("Y-m-d H:i:s",$one['pay_time']);
            if($one['pay_type']=="wxpay"){
            	$one['paytype'] = "微信支付";
            }else if($one['pay_type']=="alipay"){
            	$one['paytype'] = "支付宝支付";
            }else{
            	$one['paytype'] = "其他支付";
            }
            //超时自动收货
            $sendcha = time() - $one['send_time'];
            $shouhuotime = isset($configs['shouhuotime']) ? $configs['shouhuotime'] : 7;
            if($one['status']=="2" && $sendcha>=(60*60*24*$shouhuotime)){
                $one['status'] = "3";
                $uparr['shou_time'] = $one['send_time']+(60*60*24*$shouhuotime);
                $uparr['shou_day']  = date("Ymd",($one['send_time']+(60*60*24*$shouhuotime)));
                $uparr['status']      = "3";
                $res = Db::name('groups_order')->where('id',$one['id'])->update($uparr);
                $this->paylog($one['id'],'订单已收货','订单已成功收货',$one['user_id']);
            }
            //超时自动完成
            $shoucha = time()-$one['shou_time'];
            $wanchengtime = isset($configs['wanchengtime']) ? $configs['wanchengtime'] : 7;
            if($one['status']=="3" && $shoucha>=(60*60*24*$wanchengtime)){
                $one['status'] = '4';
                $uparr['finish_time'] = $one['shou_time']+(60*60*24*$wanchengtime);
                $uparr['finish_day']  = date("Ymd",($one['shou_time']+(60*60*24*$wanchengtime)));
                $uparr['status']      = "4";
                $res = Db::name('groups_order')->where('id',$one['id'])->update($uparr);
                $this->paylog($one['id'],'订单已完成','订单已完成，感谢您的支持',$one['user_id']);
            }
            $wllist = $this->wuliu($one['id']);
            $goods = Db::name('groups_order_goods')->field('id,shopid,shopname,shoplogo,total,goods_id,desc,title,desc_title,description,image,goodssn,productsn,marketprice,realprice')->where('order_id',$one['id'])->order('id asc')->select();
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
                        $nshoplist[$k]['shoplogo'] = getImage($val['shoplogo']);
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
        $one = Db::name('groups_order')->field('id,express_company,express_sn')->where('ordersn',$data['ordersn'])->find();
        $one['wuliu'] = $this->wuliu($one['id']);
        return json(['code'=>0,'msg'=>'获取成功','returnData'=>$one]);
    }
    public function wuliu($id){
        $one = Db::name('groups_order')->field(['id','ordersn','express_sn','express'])->where(['id'=>$id])->find();
        $lista = Db::name('groups_order_log')->field('id,title,content,createtime')->where('orderid',$id)->where('is_del','n')->select();
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
            $one = Db::name('groups_order')->field('id,ordersn,goodsprice,price,discount,postageprice,createtime,status')->where('ordersn',$data['ordersn'])->find();
            $cha = time()-$one['createtime'];
            $configs = Db::name('webconfig')->field('quxiaotime,shouhuotime,wanchengtime')->where('id','1')->find();
            $quxiaotime  = isset($configs['quxiaotime']) ? $configs['quxiaotime'] : 5;
            $waittime = ($quxiaotime*60)/2;
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
            $one['price']       = $one['goodsprice'];
            $goods = DB::name('groups_order_goods')->field('id,goods_id')->where('order_id',$one['id'])->find();
            $one['goods_id'] = $goods['goods_id'];
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
            $res = Db::name('groups_order')->where(['id'=>$data['id'],'status'=>'-1'])->update($uparr);
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
            $res = Db::name('groups_order')->where(['ordersn'=>$data['ordersn'],'status'=>'0'])->update($uparr);
            if($res){
                groupshuankuncun($data['orderid']);
                //生成日志
                $this->paylog($data['orderid'],'订单已取消','订单取消成功，备注：客户手动取消',$token['myinfo']['id']);
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
            $res = Db::name('groups_order')->where(['ordersn'=>$data['ordersn'],'status'=>'2'])->update($uparr);
            if($res){
                //生成日志
                $this->paylog($data['orderid'],"订单已收货","订单成功收货(备注：客户确认收货)",$token['myinfo']['id']);
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
            $res = Db::name('groups_order')->where(['ordersn'=>$data['ordersn'],'status'=>'3'])->update($uparr);
            if($res){
                //生成日志
                $this->paylog($data['orderid'],"订单已完成","订单已完成，感谢您的支持（备注：客户确认完成）",$token['myinfo']['id']);
                return json(['code'=>0,'msg'=>'操作成功']);
            }else{
                return json(['code'=>1,'msg'=>'订单已不能收货','returnData'=>$one]);
            }
        }else{
            return json($token);
        }
    }
}