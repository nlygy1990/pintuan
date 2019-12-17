<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 应用公共文件
//会员订单数
function memberorder($uid,$types=0){
    $where[] = ['user_id','=',$uid];
    $where[] = ['is_del','=','n'];
    if($types==0){
        $where[] = ['status','in',['1','2','3','4','5']];
    }
    $count = Db::name('order')->where($where)->count();
    return $count;
}
function memberChengjiao($uid,$types=0){
    $where[] = ['user_id','=',$uid];
    $where[] = ['is_del','=','n'];
    if($types==0){
        $where[] = ['status','in',['1','2','3','4','5']];
    }
    $list = Db::name('order')->field('price')->where($where)->select();
    $zong = [];
    foreach($list as $k=>$v){
        $zong[] = $v['price'];
    }
    $count = sprintf("%.2f",array_sum($zong));
    return $count;
}
function memberZuijin($uid,$types=0){
    $where[] = ['user_id','=',$uid];
    $where[] = ['is_del','=','n'];
    if($types==0){
        $where[] = ['status','in',['1','2','3','4','5']];
    }
    $list = Db::name('order')->field('createtime')->where($where)->order('createtime desc')->limit(1)->find();
    if($list){
        return get_time($list['createtime']);
    }else{
        return "暂无交易";
    }
}
function get_time($time){
    $time = intval($time);
    $nowTime = time();
    $t = $nowTime - $time;// 时间差
    if($t<=10){
        $str = '刚刚';
    }else if($t>10 && $t<=60){
        $str = $t . '秒内';
    }else if($t>60 && $t<=60*60){
        $str = floor($t/60) . '分钟前';
    }else if($t>60*60 && $t<=60*60*24){
        $str = floor($t/(60*60)) . '小时前';
    }else if($t>60*60*24 && $t<=60*60*24*7){
        $str = floor($t/(60*60*24)) . '天前';
    }else if($t>60*60*24*7 && $t<=60*60*24*7*4){
        $str = floor($t/(60*60*24*7)) . '周前';
    }else if($t>60*60*24*7*4 && $t<=60*60*24*365){
        $nowM = date('m',$nowTime);
        $m = date('m',$time);
        if($nowM<$m){
            $str = (12-$m) + $nowM . '个月前';
        }else{
            $str = $nowM - $m . '个月前';
        }
    }else if($t>60*60*24*365){
        $str = date('Y',$nowTime) - date('Y',$time) . '年前';
    }
    return $str;
}
function memberLjYj($uid,$types="leiji"){
    $where[] = ['user_id','=',$uid];
    $list = Db::name('member_distribution')->field('money,status')->where($where)->select();
    $leiji = []; $weidao = []; $weishou = []; $yidao = []; $yiti = [];
    foreach($list as $k=>$v){
        $leiji[] = $v['money'];
        if($v['status']=="0"){ //待付款
            $weidao[]  = $v['money'];
        }else if($v['status']=="1" || $v['status']=="2" || $v['status']=="3"){ //已付款 已发货
            $weishou[] = $v['money'];
        }else if($v['status']=="4"){
            $yidao[]   = $v['money'];
        }else if($v['status']=="5"){
            $yiti[]    = $v['money'];
        }
    }
    if($types=="leiji"){
        $res = sprintf("%.2f",array_sum($leiji));
    }else if($types=="weidaozhang"){
        $res = sprintf("%.2f",array_sum($weidao));
    }else if($types=="weishou"){
        $res = sprintf("%.2f",array_sum($weishou));
    }else if($types=="yidao"){
        $res = sprintf("%.2f",array_sum($yidao));
    }else if($types=="yiti"){
        $res = sprintf("%.2f",array_sum($yiti));
    }
    return $res;
}
function memberTixian($uid,$types='yitx'){
    $tixian = Db::name('member_tixian')->field('money,status')->where(['uid'=>$uid])->select();
    $shentx = []; $daitx = []; $yitx = []; $shitx = [];
    foreach($tixian as $k=>$v){
        if($v['status']=="0"){
            $shentx[] = $v['money'];
        }else if($v['status']=="1"){
            $daitx[]  = $v['money'];
        }else if($v['status']=="2"){
            $yitx[]   = $v['money'];
        }else{
            $shitx[]  = $v['money'];
        }
    }
    $shentxc = array_sum($shentx);$daitxc = array_sum($daitx);$yitxc = array_sum($yitx);$shitxc = array_sum($shitx);
    $one['shentx']     = sprintf("%.2f",$shentxc);
    $one['daitx']     = sprintf("%.2f",$daitxc);
    $one['yitx']     = sprintf("%.2f",$yitxc);
    $one['shitx']     = sprintf("%.2f",$shitxc);
    return $one[$types];
}
//还库存
function huankuncun($id){
    $one = Db::name('order')->field('id,ordersn,parent_id,status')->where('id',$id)->find();
    if($one['parent_id']=="0"){
        $goods = Db::name('order_goods')->field('id,total,goods_id,desc,huan_total')->where('order_id',$id)->select();
        foreach($goods as $k=>$v){
            $ngoods = Db::name('goods')->field('id,total,totalcnf')->where('id',$v['goods_id'])->find();
            if($v['desc']==""){
                $ntotal = $ngoods['total']+$v['total'];
                if($v['huan_total']=="0" && $ngoods['totalcnf']=="0"){
                    $res = Db::name('goods')->where('id',$v['goods_id'])->update(['total'=>$ntotal]);
                    if($res){
                        $resa = Db::name('order_goods')->where('id',$v['id'])->update(['huan_total'=>$v['total']]);
                    }
                }
            }else{
                $ngoodsa = Db::name('goods_options_item_desc')->field('desc,total')->where('desc',$v['desc'])->find();
                $ntotal = $ngoodsa['total']+$v['total'];
                if($v['huan_total']=="0" && $ngoods['totalcnf']=="0"){
                    $res = Db::name('goods_options_item_desc')->where('desc',$v['desc'])->update(['total'=>$ntotal]);
                    if($res){
                        $resa = Db::name('order_goods')->where('id',$v['id'])->update(['huan_total'=>$v['total']]);
                    }
                }
            }
        }
    }else{
        $goods = Db::name('order_goods')->field('id,total,goods_id,desc,huan_total')->where('order_id',$id)->select();
        foreach($goods as $k=>$v){
            $ngoods = Db::name('goods')->field('id,total,totalcnf')->where('id',$v['goods_id'])->find();
            if($v['desc']==""){
                $ntotal = $ngoods['total']+$v['total'];
                if($v['huan_total']=="0" && $ngoods['totalcnf']=="0"){
                    $res = Db::name('goods')->where('id',$v['goods_id'])->update(['total'=>$ntotal]);
                    if($res){
                        $resa = Db::name('order_goods')->where('id',$v['id'])->update(['huan_total'=>$v['total']]);
                        $resb = Db::name('order_goods')->where('order_id',$v['parent_id'])->where('goods_id',$v['goods_id'])->update(['huan_total'=>$v['total']]);
                    }
                }
            }else{
                $ngoodsa = Db::name('goods_options_item_desc')->field('desc,total')->where('desc',$v['desc'])->find();
                $ntotal = $ngoodsa['total']+$v['total'];
                if($v['huan_total']=="0" && $ngoods['totalcnf']=="0"){
                    $res = Db::name('goods_options_item_desc')->where('desc',$v['desc'])->update(['total'=>$ntotal]);
                    if($res){
                        $resa = Db::name('order_goods')->where('id',$v['id'])->update(['huan_total'=>$v['total']]);
                        $resb = Db::name('order_goods')->where('order_id',$v['parent_id'])->where('desc',$v['desc'])->update(['huan_total'=>$v['total']]);
                    }
                }
            }
        }
    }
}
//还拼团商品库存
function groupshuankuncun($id){
    $one = Db::name('groups_order')->field('id,ordersn,groups_id,parent_id,status,groups_key')->where('id',$id)->find();
    $tuan = Db::name('groups')->field('id,max_num,goods_id,guige,status')->where('id',$one['groups_id'])->find();
    if($tuan['status']=="0"){ //开团中
        if($tuan['guige']){
            //增加库存缓存
            redis()->incrBy('groups_goods_'.$tuan['guige'],$tuan['max_num']);
        }else{
            //增加库存缓存
            redis()->incrBy('groups_'.$tuan['goods_id'],$tuan['max_num']);
        }
    }else if($tuan['status']=="1"){ //已开团
        redis()->del($one['groups_key']);
        //增加库存缓存
        redis()->incrBy('tuan_'.$tuan['id'],1);
    }

    if($one['parent_id']=="0"){
        $goods = Db::name('groups_order_goods')->field('id,total,goods_id,desc,huan_total')->where('order_id',$id)->select();
        foreach($goods as $k=>$v){
            $ngoods = Db::name('groups_goods')->field('id,total,totalcnf')->where('id',$v['goods_id'])->find();
            if($v['desc']==""){
                $ntotal = $ngoods['total']+$v['total'];
                if($v['huan_total']=="0" && $ngoods['totalcnf']=="0"){
                    $res = Db::name('groups_goods')->where('id',$v['goods_id'])->update(['total'=>$ntotal]);
                    if($res){
                        $resa = Db::name('groups_order_goods')->where('id',$v['id'])->update(['huan_total'=>$v['total']]);
                    }
                }
            }else{
                $ngoodsa = Db::name('groups_goods_options_item_desc')->field('desc,total')->where('desc',$v['desc'])->find();
                $ntotal = $ngoodsa['total']+$v['total'];
                if($v['huan_total']=="0" && $ngoods['totalcnf']=="0"){
                    $res = Db::name('groups_goods_options_item_desc')->where('desc',$v['desc'])->update(['total'=>$ntotal]);
                    if($res){
                        $resa = Db::name('groups_order_goods')->where('id',$v['id'])->update(['huan_total'=>$v['total']]);
                    }
                }
            }
        }
    }else{
        $goods = Db::name('groups_order_goods')->field('id,total,goods_id,desc,huan_total')->where('order_id',$id)->select();
        foreach($goods as $k=>$v){
            $ngoods = Db::name('groups_goods')->field('id,total,totalcnf')->where('id',$v['goods_id'])->find();
            if($v['desc']==""){
                $ntotal = $ngoods['total']+$v['total'];
                if($v['huan_total']=="0" && $ngoods['totalcnf']=="0"){
                    $res = Db::name('groups_goods')->where('id',$v['goods_id'])->update(['total'=>$ntotal]);
                    if($res){
                        $resa = Db::name('groups_order_goods')->where('id',$v['id'])->update(['huan_total'=>$v['total']]);
                        $resb = Db::name('groups_order_goods')->where('order_id',$v['parent_id'])->where('goods_id',$v['goods_id'])->update(['huan_total'=>$v['total']]);
                    }
                }
            }else{
                $ngoodsa = Db::name('groups_goods_options_item_desc')->field('desc,total')->where('desc',$v['desc'])->find();
                $ntotal = $ngoodsa['total']+$v['total'];
                if($v['huan_total']=="0" && $ngoods['totalcnf']=="0"){
                    $res = Db::name('groups_goods_options_item_desc')->where('desc',$v['desc'])->update(['total'=>$ntotal]);
                    if($res){
                        $resa = Db::name('groups_order_goods')->where('id',$v['id'])->update(['huan_total'=>$v['total']]);
                        $resb = Db::name('groups_order_goods')->where('order_id',$v['parent_id'])->where('desc',$v['desc'])->update(['huan_total'=>$v['total']]);
                    }
                }
            }
        }
    }
}
function  GroupsGoogsXiaoliang($id){
    $list = Db::name('groups_order_goods')->field('total')->where('goods_id',$id)->select();
    $counta = [];
    foreach($list as $k=>$v){
        $counta[] = $v['total'];
    }
    $count = array_sum($counta);
    $uparr['sales'] = $count;
    $resa = Db::name('goods')->where('id',$id)->update($uparr);
    if($count>10000000){
        $a = ceil($count/10000000)-1;
        $count = $a."000万+";
    }else if($count>1000000 && $count<=1000000){
        $a = ceil($count/1000000)-1;
        $count = $a."00万+";
    }else if($count>100000 && $count<=100000){
        $a = ceil($count/100000)-1;
        $count = $a."0万+";
    }else if($count>10000 && $count<=10000){
        $a = ceil($count/10000)-1;
        $count = $a."万+";
    }
    return $count;
}

function  GoogsXiaoliang($id){
    $list = Db::name('order_goods')->field('total')->where('goods_id',$id)->select();
    $counta = [];
    foreach($list as $k=>$v){
        $counta[] = $v['total'];
    }
    $count = array_sum($counta);
    $uparr['sales'] = $count;
    $resa = Db::name('goods')->where('id',$id)->update($uparr);
    if($count>10000000){
        $a = ceil($count/10000000)-1;
        $count = $a."000万+";
    }else if($count>1000000 && $count<=1000000){
        $a = ceil($count/1000000)-1;
        $count = $a."00万+";
    }else if($count>100000 && $count<=100000){
        $a = ceil($count/100000)-1;
        $count = $a."0万+";
    }else if($count>10000 && $count<=10000){
        $a = ceil($count/10000)-1;
        $count = $a."万+";
    }
    return $count;
}
function orderZidan($id){
    $count = Db::name('order')->where('parent_id',$id)->count();
    if($count>0){
        $configs = Db::name('webconfig')->field('quxiaotime,shouhuotime,wanchengtime')->where('id','1')->find();
        $list = Db::name('order')->alias('or')
                ->field('or.id,or.user_id,or.ordersn,or.consignee_name,or.consignee_mobile,or.consignee_address,price,or.status,or.postageprice,or.createtime,or.discount,or.pay_type,or.express_company,or.send_time,or.shou_time')
                ->where("parent_id",$id)
                ->order('or.createtime desc')
                ->select();
        foreach($list as $k=>$v){
            //超时自动取消
            $createcha = time()-$v['createtime'];
            $quxiaotime = isset($configs['quxiaotime']) ? $configs['quxiaotime'] : 60;
            if($v['status']=="0" && $createcha>=(60*$quxiaotime)){
                $uparr['cancel_time'] = $v['createtime']+(60*$quxiaotime);
                $uparr['cancel_day']  = date("Ymd",($v['createtime']+(60*$quxiaotime)));
                $uparr['status']      = "-1";
                $res = Db::name('order')->where('id',$v['id'])->update($uparr);
                paylog($v['id'],'订单已取消','订单超时自动取消',$v['user_id']);
                $quxiaoid[] = $v['id'];
                $v['status'] = "-1";
                //更新分佣信息
                $yjarr['cancel_time'] = $uparr['cancel_time'];
                $yjarr['cancel_day']  = $uparr['cancel_day'];
                $yjarr['status']    = '-1';
                $yj = Db::name('member_distribution')->where('order_id',$v['id'])->update($yjarr);
            }
            //超时自动收货
            $sendcha = time()-$v['send_time'];
            $shouhuotime = isset($configs['shouhuotime']) ? $configs['shouhuotime'] : 7;
            if($v['status']=="2" && $sendcha>=(60*60*24*$shouhuotime)){
                $shouhuoid[] = $v['id'];
                $uparr['shou_time'] = $v['send_time']+(60*60*24*$shouhuotime);
                $uparr['shou_day']  = date("Ymd",($v['send_time']+(60*60*24*$shouhuotime)));
                $uparr['status']      = "3";
                $res = Db::name('order')->where('id',$v['id'])->update($uparr);
                paylog($v['id'],'订单已收货','订单已成功收货',$v['user_id']);
                $v['status'] = "3";
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
                $wanchengid[] = $v['id'];
                $uparr['finish_time'] = $v['shou_time']+(60*60*24*$wanchengtime);
                $uparr['finish_day']  = date("Ymd",($v['shou_time']+(60*60*24*$wanchengtime)));
                $uparr['status']      = "4";
                $res = Db::name('order')->where('id',$v['id'])->update($uparr);
                paylog($v['id'],'订单已完成','订单已完成，感谢您的支持',$v['user_id']);
                $v['status'] = "4";
                //更新分佣信息
                $yjarr['finish_time'] = $uparr['finish_time'];
                $yjarr['finish_day']  = $uparr['finish_day'];
                $yjarr['status']    = '4';
                $yj = Db::name('member_distribution')->where('order_id',$v['id'])->update($yjarr);
            }
        }
    }
    return $count;
}
function paylog($orderid,$title,$content,$uid){
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

function paylogg($orderid,$title,$content,$uid){
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


function getImage($id,$type=""){
    if($id=="0"){
        return "";die;
    }
    $Request = new \think\Request();
    // $hostname = $Request->root();
    $hostname = "https://wanyi.tanghan.cn";
	$one = \think\Db::name('attachment')->field(['filepath','filepath_oss','filepath_240x160','filepath_480x320'])->where('id',$id)->find();
	if($one){
		if($one['filepath_oss']=="aliyuncs"){
			if($type==''){
				return $one['filepath'];
			}else{
				return $one['filepath'].'?x-oss-process=image/resize,w_240';
			}
		}else{
			return $hostname.ltrim($one['filepath'.$type],'.'); 
		}
	}else{
		return $hostname.'/static/admin/images/beiyong.png';
	}
}
function getAdmin($id){
    $one = Db::name('admin')->field('id,nickname,username')->where(['id'=>$id])->find();
    if($one){
        return $one['nickname'] ? $one['nickname'] : $one['username'];
    }else{
        return '未知账号';
    }
}
function getStores($id){
    $one = Db::name('stores')->field('id,title')->where(['id'=>$id])->find();
    if($one){
        return $one['title'];
    }else{
        return false;
    }
}
//学校管理员
function getSchoolAdmin($school_id){
    $one = Db::name('admin')->field('id,nickname,username')->where(['school_id'=>$school_id,'school_type'=>'1'])->find();
    if($one){
        return $one['nickname'] ? $one['nickname'] : $one['username'];
    }else{
        return false;
    }
}
function getClassCount($school_id){
    return Db::name('school_class')->where(['school_id'=>$school_id,'is_del'=>'n'])->count();
}
function getClassAdminCount($school_id){
    return Db::name('admin')->where(['school_id'=>$school_id,'is_del'=>'n'])->count();
}
function getClassStudentCount($school_id){
    return Db::name('school_student')->where(['school_id'=>$school_id,'is_del'=>'n'])->count();;
}
//班级管理员
function getSchoolClassAdmin($class_id){
    $one = Db::name('admin')->field('id,nickname,username')->where(['class_id'=>$class_id,'class_type'=>'1'])->find();
    if($one){
        return $one['nickname'] ? $one['nickname'] : $one['username'];
    }else{
        return false;
    }
}
function getStudenCount($class_id){
    return Db::name('school_student')->where(['class_id'=>$class_id,'is_del'=>'n'])->count();
}


function statics($type='css'){
	switch ($type) {
		case 'css':
			echo '/static/admin/css/';
		break;
		case 'img':
			echo '/static/admin/images/';
		break;
		case 'js':
			echo '/static/admin/js/';
		break;
		case 'public':
			echo '/static/admin/';
		break;
		case 'layui':
			echo '/static/layui/';
		break;
		default:
			echo '/static/admin/css/';
		break;
	}
}

function getMember($id){
    $one = Db::name('member')->field('id,nickname,username')->where(['id'=>$id])->find();
    if($one){
        return $one['nickname'] ? $one['nickname'] : $one['username'];
    }else{
        return '未知账号';
    }
}
function getMembers($id){
    $one = Db::name('member')->field('id,nickname,username,phone')->where(['id'=>$id])->find();
    if($one){
        $aa = $one['nickname'] ? $one['nickname'] : $one['username'];
        if($one['phone']){
            $bb = substr_replace($one['phone'],'****',3,4);
        }else{
            $bb = $one['phone'] ? $one['phone'] : '未绑定';
        }
        return $aa.'('.$bb.')';
    }else{
        return '';
    }
}

function str_cut1(&$string, $start, $length, $charset = "utf-8", $dot = '...') {
    if(function_exists('mb_substr')) {
        if(mb_strlen($string, $charset) > $length) {
            return mb_substr ($string, $start, $length, $charset) . $dot;
        }
        return mb_substr ($string, $start, $length, $charset);
        
    }else if(function_exists('iconv_substr')) {
        if(iconv_strlen($string, $charset) > $length) {
            return iconv_substr($string, $start, $length, $charset) . $dot;
        }
        return iconv_substr($string, $start, $length, $charset);
    }

    $charset = strtolower($charset);
    switch ($charset) {
        case "utf-8" :
            preg_match_all("/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|\xe0[\xa0-\xbf][\x80-\xbf]|[\xe1-\xef][\x80-\xbf][\x80-\xbf]|\xf0[\x90-\xbf][\x80-\xbf][\x80-\xbf]|[\xf1-\xf7][\x80-\xbf][\x80-\xbf][\x80-\xbf]/", $string, $ar);
            if(func_num_args() >= 3) {
                if (count($ar[0]) > $length) {
                    return join("", array_slice($ar[0], $start, $length)) . $dot;
                }
                return join("", array_slice($ar[0], $start, $length));
            } else {
                return join("", array_slice($ar[0], $start));
            }
            break;
        default:
            $start = $start * 2;
            $length   = $length * 2;
            $strlen = strlen($string);
            for ( $i = 0; $i < $strlen; $i++ ) {
                if ( $i >= $start && $i < ( $start + $length ) ) {
                    if ( ord(substr($string, $i, 1)) > 129 ) $tmpstr .= substr($string, $i, 2);
                    else $tmpstr .= substr($string, $i, 1);
                }
                if ( ord(substr($string, $i, 1)) > 129 ) $i++;
            }
            if ( strlen($tmpstr) < $strlen ) $tmpstr .= $dot;
            
            return $tmpstr;
    }
}

    function images_s($name,$pics=array(),$upurl,$w="100",$h="100",$ws="0.3"){
        $htmls = '<style>
        .progress{position:relative;padding: 1px; border-radius:3px; margin:30px 0 0 0;}
        .bar{background-color: green; display:block; width:0%; height:20px; border-radius:3px;}
        .percent{position:absolute; height:20px; display:inline-block;top:3px; left:2%; color:#fff}
        .progress{height: 100px;padding: 30px 0 0;width:100px;border-radius: 0;}
        .btn{-webkit-border-radius:3px; -moz-border-radius:3px; -ms-border-radius:3px; -o-border-radius:3px; border-radius:3px;
            background-color:#009688; color:#fff; display:inline-block; height:38px; line-height:38px; text-align:center; padding:0 12px; 
            transition:background-color .2s linear 0s; border:0; cursor:pointer;text-decoration: none}
            .btn:hover{}
            .photos_area .item {float: left;margin: 0 10px 10px 0;position: relative;}
            .photos_area .item{position: relative;float:left;margin:0 10px 10px 0;}
            .photos_area .item img{border: 1px solid #cdcdcd;}
            .photos_area .operate{background: rgba(33, 33, 33, 0.7) none repeat scroll 0 0; bottom: 0; padding:5px 0; left: 0; position: absolute; width: 102px;height:20px; z-index: 5; line-height: 21px; text-align: center;position:absolute;top:0px;left:0;}
            .photos_area .operate i{cursor: pointer; display: inline-block; font-size: 0; height: 12px; line-height: 0; margin: 0 5px; overflow: hidden; width: 12px; background: url("/static/admin/upimgs/images/icon_sucaihuo.png") no-repeat scroll 0 0;}
            .photos_area .operate .toright{background-position: -13px -13px; position: relative;top:1px;}
            .photos_area .operate .toleft{background-position: 0 -13px; position: relative;top:1px;}
            .photos_area .operate .del{background-position: -13px 0; position: relative;top:0px;}
            .photos_area .preview{background-color: #fff; font-family: arial; line-height: 90px; text-align: center; z-index: 4; left: 0; position: absolute; top: 0; height: 90px; overflow: hidden; width: 90px;}</style>';
            $htmls .= '<div>
            <button type="button" class="layui-btn" id="logo_upload_btn">上传图片</button>
            <button type="button" class="layui-btn" id="logo_upload_btn_select" style="display:none;">选择图片</button>
            &nbsp;&nbsp;(<span style="color:green;">图片尺寸：'.$w.'px,'.$h.'px</span>)支持多图上传以及左右按钮排序图片
        </div>
        <p style="height:15px;"></p>
        <div id="logo_upload_area" style="width:80%;height:'.($h*$ws).'px">
            <div id="photos_area" class="photos_area clearfix">
                ';
                if ($pics) {
                    $atyle = 'style="bottom:40px;"';
                    foreach ($pics as $k=>$v) {
                        $htmls .= '
                    <div class="item">
                        <input type="hidden" name="'.$name.'" value="'.$v.'"/>
                        <img src="'.getImage($v).'"  width="'.($w*$ws).'px" height="'.($h*$ws).'px"/>
                        <div class="operate" '.$atyle.'><i class="toleft">左移</i><i class="toright">右移</i><i class="del">删除</i></div>';
                    $htmls .= '</div>';
                }
            }
            $htmls .= '</div></div>';
            $htmls .= '<script type="text/javascript" src="/static/admin/upimgs/plupload/plupload.full.min.js"></script>';
            $htmls .= '<script type="text/javascript">
            var uploader = new plupload.Uploader({
                runtimes: "gears,html5,html4,silverlight,flash",
                browse_button: "logo_upload_btn",
                url: "'.$upurl.'?use=product",
                type:"post",
                flash_swf_url: "/static/admin/upimgs/plupload/Moxie.swf",
                silverlight_xap_url: "/static/admin/upimgs/plupload/Moxie.xap",
                filters: {
                    max_file_size: "25mb",
                    mime_types: [
                    {title: "files", extensions: "jpg,png,gif,jpeg"}
                    ]
                },
                multi_selection: true,
                init: {
                    FilesAdded: function(up, files) {
                        $("#btn_submit").attr("disabled", "disabled").addClass("disabled").val("正在上传...");
                        var item = "";
                        plupload.each(files, function(file) { //遍历文件
                            item += "<div class=\"item\" id=\""+ file["id"]+"\"><div class=\"progress\"><span class=\"bar\"></span><span class=\"percent\">0%</span></div></div>";
                        });
                        $("#photos_area").append(item);
                        uploader.start();
                    },
                    UploadProgress: function(up, file) { //上传中，显示进度条 
                        var percent = file.percent;
                        $("#" + file.id).find(".bar").css({"width": percent + "%"});
                        $("#" + file.id).find(".percent").text(percent + "%");
                    },
                    FileUploaded: function(up, file, info) {
                        var data = eval("(" + info.response + ")");
                        $("#" + file.id).html("<input type=\"hidden\" name=\"'.$name.'\" value=\""+data.returnData.id+"\"><img src=\""+data.returnData.url+"\" alt=\""+data.name+"\" width=\"'.($w*$ws).'px\" height=\"'.($h*$ws).'px\">\n\
                        <div class=\"operate\"><i class=\"toleft\">左移</i><i class=\"toright\">右移</i><i class=\"del\">删除</i></div>");
                        $("#btn_submit").removeAttr("disabled").removeClass("disabled").val("提 交");
                        if (data.code == 0) {
                            layer.msg(res.msg);
                        }   
                    },
                    Error: function(up, err) {
                        if (err.code == -601) {
                            layer.msg("请上传jpg,png,gif,jpeg,zip或rar！");
                            $("#btn_submit").removeAttr("disabled").removeClass("disabled").val("提 交");
                        }
                    }
                }
            });
            uploader.init();
                //左右切换和删除图片
            $(function() {
                $(document).on("click",".toleft", function() {
                    var item = $(this).parent().parent(".item");
                    var item_left = item.prev(".item");
                    if ($("#photos_area").children(".item").length >= 2) {
                        if (item_left.length == 0) {
                            item.insertAfter($("#photos_area").children(".item:last"));
                        } else {
                            item.insertBefore(item_left);
                        }
                    }

                })
                $(document).on("click",".toright",function() {
                    var item = $(this).parent().parent(".item");
                    var item_right = item.next(".item");
                    if ($("#photos_area").children(".item").length >= 2) {
                        if (item_right.length == 0) {
                            item.insertBefore($("#photos_area").children(".item:first"));
                        } else {
                            item.insertAfter(item_right);
                        }
                    }
                })
                $(document).on("click",".del",function() {
                    $(this).parent().parent(".item").remove();
                });
            })
        </script>';
        return $htmls;
    }


/*
Author:GaZeon
Date:2016-6-20
Function:getArrSet
Param:$arrs 二维数组
getArrSet(array(array(),...))
数组不重复排列集合
*/
function getArrSet($arrs,$_current_index=-1)
{
    //总数组
    static $_total_arr;
    //总数组下标计数
    static $_total_arr_index;
    //输入的数组长度
    static $_total_count;
    //临时拼凑数组
    static $_temp_arr;
    
    //进入输入数组的第一层，清空静态数组，并初始化输入数组长度
    if($_current_index<0){
        $_total_arr=array();
        $_total_arr_index=0;
        $_temp_arr=array();
        $_total_count=count($arrs)-1;
        getArrSet($arrs,0);
    }else{
        //循环第$_current_index层数组
        foreach($arrs[$_current_index] as $v){
            //如果当前的循环的数组少于输入数组长度
            if($_current_index<$_total_count){
                //将当前数组循环出的值放入临时数组
                $_temp_arr[$_current_index]=$v;
                //继续循环下一个数组
                getArrSet($arrs,$_current_index+1);
                
            }
            //如果当前的循环的数组等于输入数组长度(这个数组就是最后的数组)
            else if($_current_index==$_total_count){
                //将当前数组循环出的值放入临时数组
                $_temp_arr[$_current_index]=$v;
                //将临时数组加入总数组
                $_total_arr[$_total_arr_index]=$_temp_arr;
                //总数组下标计数+1
                $_total_arr_index++;
            }

        }
    }
    
    return $_total_arr;
}

// /*************TEST**************/
// $arr=array(
//     array('a','b','c'),
//     array('A','B','C'),
//     array('1','2','3'),
//     array('I','II','III')
// );

// var_dump(getArrSet($arr));

function huifucount($id){
    return Db::name('zixun_huifu')->where(['pid'=>$id,'is_show'=>'y','is_del'=>'n','types'=>'huifu'])->count();
}
function zhuiwencount($id){
    return Db::name('zixun_huifu')->where(['pid'=>$id,'is_show'=>'y','is_del'=>'n'])->where('types','neq','huifu')->count();
}
function Tii($time){
    $day = strtotime(date('Y-m-d',time()));
    $pday = strtotime(date('Y-m-d',strtotime('-1 day')));
    $nowtime = time();
    $tc = $nowtime-$time;
    if($time<$pday){
        $str = date('Y-m-d',$time);
    }elseif($time<$day && $time>$pday){
        $str = "昨天".date('H:i:s',$time);
    }elseif($tc>60*60){
        $str = floor($tc/(60*60))."小时前";
    }elseif($tc>60){
        $str = floor($tc/60)."分钟前";
    }else{
        $str = "刚刚";
    }
    return $str;
}
function Tiia($time){
    $day = strtotime(date('Y-m-d',time()));
    $pday = strtotime(date('Y-m-d',strtotime('-1 day')));
    $nowtime = time();
    $tc = $nowtime-$time;
    if($time<$pday){
        $str = date('Y-m-d H:i:s',$time);
    }elseif($time<$day && $time>$pday){
        $str = "昨天".date('H:i:s',$time);
    }elseif($tc>60*60){
        $str = floor($tc/(60*60))."小时前";
    }elseif($tc>60){
        $str = floor($tc/60)."分钟前";
    }else{
        $str = "刚刚";
    }
    return $str;
}
function birthday($birthday){ 
     $age = strtotime($birthday); 
     if($age === false){ 
      return false; 
     } 
     list($y1,$m1,$d1) = explode("-",date("Y-m-d",$age)); 
     $now = strtotime("now"); 
     list($y2,$m2,$d2) = explode("-",date("Y-m-d",$now)); 
     $age = $y2 - $y1; 
     if((int)($m2.$d2) < (int)($m1.$d1)) 
      $age -= 1; 
     return $age; 
}
function getContinueDay($day_list){
    //昨天开始时间戳
    $beginYesterday=mktime(0,0,0,date('m'),date('d')-1,date('Y')); 
    if($beginYesterday>$day_list[0]) $days = 0;
    else $days = 1;
    $count = count($day_list);
    for($i=0;$i<$count;$i++){
        if($i<$count-1){
            $res = compareDay($day_list[$i],$day_list[$i+1]);
            if($res) $days++;
            else break;
        }
    }
        
    return $days+1;
}
 
function compareDay($curDay,$nextDay){
    $lastBegin = mktime(0,0,0,date('m',$curDay),date('d',$curDay)-1,date('Y',$curDay));
    $lastEnd   = mktime(0,0,0,date('m',$curDay),date('d',$curDay),date('Y',$curDay))-1; 
    if($nextDay>=$lastBegin && $nextDay<=$lastEnd){
        return true;
    }else{
        return false;
    }
        
}