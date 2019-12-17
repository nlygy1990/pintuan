<?php
namespace app\admin\controller;
use \think\Controller;
use app\admin\controller\Base;
use \think\Db;
use \think\Cookie;
use \think\Session;
use \think\Request;
use PHPExcel_IOFactory;
use PHPExcel;
use PHPExcel_Style_Alignment;
use PHPExcel_Style_Border;
class Orders extends Base{
	public function __construct(){
		parent::__construct(); //使用父类的构造方法
	}
/************************************************************************/
/** 订单管理
/************************************************************************/
	public function lists(){
		$admininfo = $this->admininfo;
		$ids = [];
		$zid = $this->request->param('zid') ? $this->request->param('zid') : '0';
		$this->assign('zid',$zid);
		if($admininfo['pid']=='0' && $admininfo['shop_id']>0){ //店铺管理员 40店铺
			$where[] = ['or.shop_id','=',$admininfo['shop_id']];
			// $where[] = ['or.is_show','=','y'];
        }else{
        	if($zid=="0"){
        		$where[] = ['or.parent_id','=','0'];
        	}else{
        		$where[] = ['or.parent_id','=',$zid];
        	}
        }

		$where[] = ['or.is_del','=','n'];
		//状态
		$status = $this->request->param('status') ? $this->request->param('status') : 'all';
		if($status!="all"){
			if($status=="1"){
				$where[] = ['or.status','=','0'];
			}else if($status=="2"){
				$where[] = ['or.status','=','1'];
			}else if($status=="3"){
				$where[] = ['or.status','=','2'];
			}else if($status=="4"){
				$where[] = ['or.status','=','3'];
			}else if($status=="5"){
				$where[] = ['or.status','=','4'];
			}else{
				$where[] = ['or.status','=',$status];
			}
		}
		$this->assign('status',$status);
		//按支付方式
		$pay_type = $this->request->param('pay_type') ? $this->request->param('pay_type') : 'all';
		if($pay_type!="all"){
			$where[] = ['or.pay_type','=',$pay_type];
		}
		$this->assign('pay_type',$pay_type);
		//时间线筛选
		$time_type = $this->request->param('time_type') ? $this->request->param('time_type') : 'all';
		$times     = $this->request->param('times') ? $this->request->param('times') : '';
		if($times==""){
			$start_time = date("Y-m-d",time()-60*60*24*30);
			$end_time   = date("Y-m-d");
		}else{
			$times = trim($times);
			$times = explode("~",$times);
			$start_time = $times[0];
			$end_time   = $times[1];
		}
		if($time_type!="all"){
			$starttimes = strtotime($start_time.' 00:00:00');
			$endtimes   = strtotime($end_time.' 23:59:59');;
			$where[] = ['or.'.$time_type,'>=',$starttimes];
			$where[] = ['or.'.$time_type,'<=',$endtimes];
		}
		$this->assign('time_type',$time_type);
		$this->assign('start_time',trim($start_time));
		$this->assign('end_time',trim($end_time));
		//搜索关键字
		$keys_key = $this->request->param('keys_key') ? $this->request->param('keys_key') : 'order_sn';
		$this->assign('keys_key',$keys_key);
		$keys = $this->request->param('keys') ? $this->request->param('keys') : '';
		if($keys!=''){
			if($keys_key=="ordersn"){ //订单号
				$where[] = ['or.ordersn','like','%'.$keys.'%'];
			}else if($keys_key=="buyer"){ //买家
				$users = Db::name('member')->field('id')->where('username|nickname|phone','like','%'.$keys.'%')->select();
				$uids = [];
				foreach($users as $k=>$v){
					$uids[] = $v['id'];
				}
				if(!empty($uids)){
					$where[] = ['or.user_id','in',$uids];
				}else{
					$where[] = ['or.user_id','=',0];
				}
			}else if($keys_key=="consignee"){ //收货人信息
				$where[] = ['or.consignee_name|or.consignee_mobile','like','%'.$keys.'%'];
			}else if($keys_key=="address"){ //地址
				$where[] = ['or.consignee_address','like','%'.$keys.'%'];
			}else if($keys_key=="delivery"){ //快递单号
				$where[] = ['or.express_sn','like','%'.$keys.'%'];
			}else if($keys_key=="goods_detail"){ //商品信息
				$users = Db::name('order_goods')->field('order_id')->where('title|desc_title','like','%'.$keys.'%')->select();
				$gids = [];
				foreach($users as $k=>$v){
					$gids[] = $v['order_id'];
				}
				if(!empty($gids)){
					$where[] = ['or.id','in',$gids];
				}else{
					$where[] = ['or.id','=',0];
				}
			}else if($keys_key=="shop_detail"){ //店铺信息
				$users = Db::name('order_goods')->field('order_id')->where('shopname','like','%'.$keys.'%')->select();
				$gidsa = [];
				foreach($users as $k=>$v){
					$gidsa[] = $v['order_id'];
				}
				if(!empty($gidsa)){
					$where[] = ['or.id','in',$gidsa];
				}else{
					$where[] = ['or.id','=',0];
				}
			}
		}
		$this->assign('keys',$keys);
		
		$list = Db::name('order')->alias('or')
				->field('or.id,or.user_id,or.ordersn,or.consignee_name,or.consignee_mobile,or.consignee_address,price,or.status,or.postageprice,or.createtime,or.discount,or.pay_type,or.express_company,or.send_time,or.shou_time')
				->where($where)
				->order('or.createtime desc')
				->paginate(10);
		//获取自动配置
		$configs = Db::name('webconfig')->field('quxiaotime,shouhuotime,wanchengtime')->where('id','1')->find();
		$quxiaoid = [];
		$quxiaoid[] = 0;
		$shouhuoid = [];
		$shouhuoid[] = 0;
		$wanchengid = [];
		$wanchengid[] = 0;
		foreach($list as $k=>$v){
			$ids[] = $v['id'];
			//超时自动取消
			$createcha = time()-$v['createtime'];
			$quxiaotime = isset($configs['quxiaotime']) ? $configs['quxiaotime'] : 60;
			if($v['status']=="0" && $createcha>=(60*$quxiaotime)){
				$uparr['cancel_time'] = $v['createtime']+(60*$quxiaotime);
				$uparr['cancel_day']  = date("Ymd",($v['createtime']+(60*$quxiaotime)));
				$uparr['status']      = "-1";
				$res = Db::name('order')->where('id',$v['id'])->update($uparr);
				$this->paylog($v['id'],'订单已取消','订单超时自动取消',$v['user_id']);
				$quxiaoid[] = $v['id'];
				$v['status'] = "-1";
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
				$shouhuoid[] = $v['id'];
				$uparr['shou_time'] = $v['send_time']+(60*60*24*$shouhuotime);
				$uparr['shou_day']  = date("Ymd",($v['send_time']+(60*60*24*$shouhuotime)));
				$uparr['status']      = "3";
				$res = Db::name('order')->where('id',$v['id'])->update($uparr);
				$this->paylog($v['id'],'订单已收货','订单已成功收货',$v['user_id']);
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
				$this->paylog($v['id'],'订单已完成','订单已完成，感谢您的支持',$v['user_id']);
				$v['status'] = "4";
				//更新分佣信息
				$yjarr['finish_time'] = $uparr['finish_time'];
				$yjarr['finish_day']  = $uparr['finish_day'];
				$yjarr['status']    = '4';
				$yj = Db::name('member_distribution')->where('order_id',$v['id'])->update($yjarr);
			}
		}
		$count = Db::name('order')->alias('or')->where($where)->count();
		if(!empty($ids)){
			$goods = Db::name('order_goods')->field('id,order_id,title,desc_title,image,marketprice,goodsprice,realprice,total')->where('order_id','in',$ids)->order('id asc')->select();
		}else{
			$goods = [];
		}
		$this->assign('quxiaoid',$quxiaoid);
		$this->assign('shouhuoid',$shouhuoid);
		$this->assign('wanchengid',$wanchengid);
		$this->assign('list',$list);
		$this->assign('count',$count);
		$this->assign('goods',$goods);
		return $this->fetch();
	}
	public function daochu(){
		$admininfo = $this->admininfo;
		$ids = [];
		$zid = $this->request->param('zid') ? $this->request->param('zid') : '0';
		$this->assign('zid',$zid);
		if($admininfo['pid']=='0' && $admininfo['shop_id']>0){ //店铺管理员 40店铺
			$where[] = ['or.shop_id','=',$admininfo['shop_id']];
        }else{
        	if($zid=="0"){
        		$where[] = ['or.parent_id','=','0'];
        	}else{
        		$where[] = ['or.parent_id','=',$zid];
        	}
        }

		$where[] = ['or.is_del','=','n'];
		//状态
		$status = $this->request->param('status') ? $this->request->param('status') : 'all';
		if($status!="all"){
			if($status=="1"){
				$where[] = ['or.status','=','0'];
			}else if($status=="2"){
				$where[] = ['or.status','=','1'];
			}else if($status=="3"){
				$where[] = ['or.status','=','2'];
			}else if($status=="4"){
				$where[] = ['or.status','=','3'];
			}else if($status=="5"){
				$where[] = ['or.status','=','4'];
			}else{
				$where[] = ['or.status','=',$status];
			}
		}
		$this->assign('status',$status);
		//按支付方式
		$pay_type = $this->request->param('pay_type') ? $this->request->param('pay_type') : 'all';
		if($pay_type!="all"){
			$where[] = ['or.pay_type','=',$pay_type];
		}
		$this->assign('pay_type',$pay_type);
		//时间线筛选
		$time_type = $this->request->param('time_type') ? $this->request->param('time_type') : 'all';
		$times     = $this->request->param('times') ? $this->request->param('times') : '';
		if($times==""){
			$start_time = date("Y-m-d",time()-60*60*24*30);
			$end_time   = date("Y-m-d");
		}else{
			$times = trim($times);
			$times = explode("~",$times);
			$start_time = $times[0];
			$end_time   = $times[1];
		}
		if($time_type!="all"){
			$starttimes = strtotime($start_time.' 00:00:00');
			$endtimes   = strtotime($end_time.' 23:59:59');;
			$where[] = ['or.'.$time_type,'>=',$starttimes];
			$where[] = ['or.'.$time_type,'<=',$endtimes];
		}
		$this->assign('time_type',$time_type);
		$this->assign('start_time',trim($start_time));
		$this->assign('end_time',trim($end_time));
		//搜索关键字
		$keys_key = $this->request->param('keys_key') ? $this->request->param('keys_key') : 'order_sn';
		$this->assign('keys_key',$keys_key);
		$keys = $this->request->param('keys') ? $this->request->param('keys') : '';
		if($keys!=''){
			if($keys_key=="ordersn"){ //订单号
				$where[] = ['or.ordersn','like','%'.$keys.'%'];
			}else if($keys_key=="buyer"){ //买家
				$users = Db::name('member')->field('id')->where('username|nickname|phone','like','%'.$keys.'%')->select();
				$uids = [];
				foreach($users as $k=>$v){
					$uids[] = $v['id'];
				}
				if(!empty($uids)){
					$where[] = ['or.user_id','in',$uids];
				}else{
					$where[] = ['or.user_id','=',0];
				}
			}else if($keys_key=="consignee"){ //收货人信息
				$where[] = ['or.consignee_name|or.consignee_mobile','like','%'.$keys.'%'];
			}else if($keys_key=="address"){ //地址
				$where[] = ['or.consignee_address','like','%'.$keys.'%'];
			}else if($keys_key=="delivery"){ //快递单号
				$where[] = ['or.express_sn','like','%'.$keys.'%'];
			}else if($keys_key=="goods_detail"){ //商品信息
				$users = Db::name('order_goods')->field('order_id')->where('title|desc_title','like','%'.$keys.'%')->select();
				$gids = [];
				foreach($users as $k=>$v){
					$gids[] = $v['order_id'];
				}
				if(!empty($gids)){
					$where[] = ['or.id','in',$gids];
				}else{
					$where[] = ['or.id','=',0];
				}
			}else if($keys_key=="shop_detail"){ //店铺信息
				$users = Db::name('order_goods')->field('order_id')->where('shopname','like','%'.$keys.'%')->select();
				$gidsa = [];
				foreach($users as $k=>$v){
					$gidsa[] = $v['order_id'];
				}
				if(!empty($gidsa)){
					$where[] = ['or.id','in',$gidsa];
				}else{
					$where[] = ['or.id','=',0];
				}
			}
		}
		$this->assign('keys',$keys);
		
		$list = Db::name('order')->alias('or')
				->field('or.id,or.user_id,or.shop_id,or.ordersn,or.consignee_name,or.consignee_mobile,or.consignee_address,price,or.status,or.postageprice,or.createtime,or.discount,or.pay_type,or.express_company,or.send_time,or.shou_time,or.goodsprice,or.pay_transid,or.express_sn')
				->where($where)
				->order('or.createtime desc')
				->select();
		foreach($list as $k=>$v){
			$ids[] = $v['id'];
		}
		$count = Db::name('order')->alias('or')->where($where)->count();
		if(!empty($ids)){
			$goods = Db::name('order_goods')->field('id,order_id,title,desc_title,image,marketprice,goodsprice,realprice,total')->where('order_id','in',$ids)->order('id asc')->select();
		}else{
			$goods = [];
		}
		$name = '订单校对表（'.$start_time.' ~ '.$end_time.'）';
		$objPHPExcel = new \PHPExcel();
		$objPHPExcel->getProperties()->setCreator("无崖子")->setLastModifiedBy("无崖子")->setTitle("数据EXCEL导出")->setSubject("数据EXCEL导出")->setDescription("数据EXCEL导出")->setKeywords("excel")->setCategory("result file");
        $objPHPExcel->getActiveSheet()->setCellValue('A1', '订单核对');
        $objPHPExcel->getActiveSheet()->setCellValue('A2', '导出日期：'.date('Y-m-d',time()));
        //表头
        $objPHPExcel->getActiveSheet()->setCellValue('A3','订单号');
	    $objPHPExcel->getActiveSheet()->setCellValue('B3','商品信息');
	    $objPHPExcel->getActiveSheet()->setCellValue('C3','状态');
	    $objPHPExcel->getActiveSheet()->setCellValue('D3','商品金额');
	    $objPHPExcel->getActiveSheet()->setCellValue('E3','运费');
	    $objPHPExcel->getActiveSheet()->setCellValue('F3','优惠金额');
	    $objPHPExcel->getActiveSheet()->setCellValue('G3','实付金额');
	    $objPHPExcel->getActiveSheet()->setCellValue('H3','支付方式');
	    $objPHPExcel->getActiveSheet()->setCellValue('I3','支付流水号');
	    $objPHPExcel->getActiveSheet()->setCellValue('J3','物流公司');
	    $objPHPExcel->getActiveSheet()->setCellValue('K3','物流单号');
	    $objPHPExcel->getActiveSheet()->setCellValue('L3','收货人');
	    $objPHPExcel->getActiveSheet()->setCellValue('M3','收货人电话');
	    $objPHPExcel->getActiveSheet()->setCellValue('N3','收货地址');
	    $objPHPExcel->getActiveSheet()->setCellValue('O3','店铺');
	    $goodsprice = [];
	    $price = [];
	    $postageprice = [];
	    $discount   = [];
	    foreach($list as $k=>$v){
	    	$num = $k+4;
	    	$status = '';
	    	if($v['status']=="-1"){
	    		$status = "已取消";
	    	}else if($v['status']=="0"){
	    		$status = "待付款";
	    	}else if($v['status']=="1"){
	    		$status = "待发货";
	    	}else if($v['status']=="2"){
	    		$status = "待收货";
	    	}else if($v['status']=="3"){
	    		$status = "待评论";
	    	}else if($v['status']=="4"){
	    		$status = "已完成";
	    	}else if($v['status']=="-2"){
	    		$status = "维权中";
	    	}else if($v['status']=="-3"){
	    		$status = "退款中";
	    	}else if($v['status']=="-4"){
	    		$status = "退货中";
	    	}else if($v['status']=="-5"){
	    		$status = "维权完成";
	    	}
	    	$paytype = '';
	    	if($v['pay_type']=="alipay"){
	    		$paytype = "支付宝支付";
	    	}else if($v['pay_type']=="wxpay"){
	    		$paytype = "微信支付";
	    	}else if($v['pay_type']=="yuepay"){
	    		$paytype = "余额支付";
	    	}else if($v['pay_type']=="htpay"){
	    		$paytype = "后台支付";
	    	}
	    	$goods_detail = '';
	    	foreach($goods as $key=>$val){
	    		if($val['order_id']==$v['id']){
	    			$goods_detail .= $val['title'].",".$val['desc_title'].' X '.$val['total'] ."件；\r\n";
	    		}
	    	}
	    	$shop = Db::name('shop')->field('title')->where('id',$v['shop_id'])->find();
	    	$objPHPExcel->getActiveSheet()->setCellValue('A'.$num,$v['ordersn']);
	    	$objPHPExcel->getActiveSheet()->setCellValue('B'.$num,$goods_detail);
	    	$objPHPExcel->getActiveSheet()->setCellValue('C'.$num,$status);
	    	$objPHPExcel->getActiveSheet()->setCellValue('D'.$num,'￥'.$v['goodsprice']);
	    	$objPHPExcel->getActiveSheet()->setCellValue('E'.$num,'￥'.$v['postageprice']);
	    	$objPHPExcel->getActiveSheet()->setCellValue('F'.$num,'￥'.($v['discount']?$v['discount']:'0.00'));
	    	$objPHPExcel->getActiveSheet()->setCellValue('G'.$num,'￥'.$v['price']);
	    	$objPHPExcel->getActiveSheet()->setCellValue('H'.$num,$paytype);
	    	$objPHPExcel->getActiveSheet()->setCellValue('I'.$num,'`'.$v['pay_transid']);
	    	$objPHPExcel->getActiveSheet()->setCellValue('J'.$num,$v['express_company']);
	    	$objPHPExcel->getActiveSheet()->setCellValue('K'.$num,$v['express_sn']);
	    	$objPHPExcel->getActiveSheet()->setCellValue('L'.$num,$v['consignee_name']);
	    	$objPHPExcel->getActiveSheet()->setCellValue('M'.$num,$v['consignee_mobile']);
	    	$objPHPExcel->getActiveSheet()->setCellValue('N'.$num,$v['consignee_address']);
	    	if($shop){
	    		$objPHPExcel->getActiveSheet()->setCellValue('O'.$num,$shop['title']);
	    	}else{
	    		$objPHPExcel->getActiveSheet()->setCellValue('O'.$num,'平台总部');
	    	}
	    	$goodsprice[]   = $v['goodsprice'];
	    	$postageprice[] = $v['postageprice'];
	    	$price[]        = $v['price'];
	    	$discount[]     = $v['discount'];
	    }
	    $numca = count($list)+5;
	    $ngoodsprice = sprintf("%.2f",array_sum($goodsprice));
	    $npostageprice = sprintf("%.2f",array_sum($postageprice));
	    $ndiscount = sprintf("%.2f",array_sum($discount));
	    $nprice = sprintf("%.2f",array_sum($price));
	    $objPHPExcel->getActiveSheet()->setCellValue('A'.$numca,'');
	    $objPHPExcel->getActiveSheet()->setCellValue('B'.$numca,'');
	    $objPHPExcel->getActiveSheet()->setCellValue('C'.$numca,'总计');
	    $objPHPExcel->getActiveSheet()->setCellValue('D'.$numca,'￥'.$ngoodsprice);
	    $objPHPExcel->getActiveSheet()->setCellValue('E'.$numca,'￥'.$npostageprice);
	    $objPHPExcel->getActiveSheet()->setCellValue('F'.$numca,'￥'.$ndiscount);
	    $objPHPExcel->getActiveSheet()->setCellValue('G'.$numca,'￥'.$nprice);
	    $numc = count($list)+5;
	    $ends = 'O';
	    //设置单元格属性------------
        //合并单元格
        $objPHPExcel->getActiveSheet()->mergeCells('A1:'.$ends.'1');
        $objPHPExcel->getActiveSheet()->mergeCells('A2:'.$ends.'2');
        //设置单元格字体
        $objPHPExcel->getActiveSheet()->getStyle('A1')->getFont()->setName('黑体');
        $objPHPExcel->getActiveSheet()->getStyle('A1')->getFont()->setSize(20);
        $objPHPExcel->getActiveSheet()->getStyle('A1')->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet()->getStyle('A'.$numc.':'.($ends.$numc))->getFont()->setBold(true);
        //设置宽度
        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(35);
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('H')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('I')->setWidth(30);
        $objPHPExcel->getActiveSheet()->getColumnDimension('J')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('K')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('L')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('M')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('N')->setWidth(30);
        $objPHPExcel->getActiveSheet()->getColumnDimension('O')->setWidth(25);
        //设置表头行高
        $objPHPExcel->getActiveSheet()->getRowDimension(1)->setRowHeight(35);
        $objPHPExcel->getActiveSheet()->getRowDimension(2)->setRowHeight(22);
        
        //设置自动换行
        $objPHPExcel->getActiveSheet()->getStyle('A3:'.($ends.$numc))->getAlignment()->setWrapText(true);


        $objPHPExcel->getActiveSheet()->setTitle('订单校对表');
        //设置水平居中
	    $objPHPExcel->getActiveSheet()->getStyle('A1:'.$ends.($numc))->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
	    $objPHPExcel->getActiveSheet()->getStyle('A2:'.$ends.'2')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
	            
	    //所有垂直居中
	    $objPHPExcel->getActiveSheet()->getStyle('A1:'.$ends.($numc))->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
	    //设置单元格边框
	    $objPHPExcel->getActiveSheet()->getStyle('A3:'.$ends.($numc))->getBorders()->getAllBorders()->setBorderStyle(PHPExcel_Style_Border::BORDER_THIN);

	    header('pragma:public');
    	header('Content-type:application/vnd.ms-excel;charset=utf-8;name="' . $name . '.xls"');
   	 	header("Content-Disposition:attachment;filename=$name.xls");//attachment新窗口打印inline本窗口打印
    	$objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
    	$objWriter->save('php://output');
	}
	public function detail(){
		$id = $this->request->param('id') ? $this->request->param('id') : '0';
		$one = Db::name('order')->where('id',$id)->find();
		$goods = Db::name('order_goods')->where('order_id',$id)->order('id asc')->select();
		$shuliang = []; $jiangli = [];
		foreach($goods as $k=>$v){
			$shuliang[] = $v['total'];
			$jiangli[]  = $v['distribution'];
			$jiangli[]  = $v['distribution_2'];
			$jiangli[]  = $v['distribution_3'];
 		}
		$one['shuliang'] = array_sum($shuliang);
		$one['jiangli']  = array_sum($jiangli);

		$configs = Db::name('webconfig')->field('quxiaotime,shouhuotime,wanchengtime')->where('id','1')->find();
		//超时自动取消
		$createcha = time()-$one['createtime'];
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
		//超时自动收货
		$sendcha = time()-$one['send_time'];
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

		$this->assign('one',$one);
		$this->assign('goods',$goods);
		return $this->fetch();
	}
	public function beizhu(){
		if($this->request->isPost()){
			$post = $this->request->post();
			if($post['newremark']==""){
				return json(['code'=>1,'msg'=>'请填写备注']);
			}
			$res = Db::name('order')->where('id',$post['id'])->update(['remark'=>$post['remark']]);
			if($res){
				return json(['code'=>0,'msg'=>'添加成功']);
			}else{
				return json(['code'=>1,'msg'=>'添加失败']);
			}
		}else{
			$id = $this->request->param('id') ? $this->request->param('id') : '0';
			$one = Db::name('order')->field('id,remark')->where('id',$id)->find();
			$this->assign('one',$one);
			return $this->fetch();
		}
	}
	//修改收货信息
	public function address_publish(){
		$id = $this->request->param('id') ? $this->request->param('id') : '0';
		if($this->request->isAjax()){
			$post = $this->request->post();
			$validate = new \think\Validate();
            $rule =   [
                'consignee_name'  => 'require',
                'consignee_mobile' => 'require|mobile',
                'consignee_address' => 'require',
                'consignee_province' => 'require',
                'consignee_city' => 'require',
                'consignee_area' => 'require'
            ];
            $message  =   [
                'consignee_name.require' => '收货人不能为空',
                'consignee_mobile.require' => '手机不能为空',
                'consignee_mobile.mobile' => '手机格式不正确',
                'consignee_address.require' => '收货地址不能为空',
                'consignee_province.require' => '请选择所在地省',
                'consignee_city.require' => '请选择所在地市',
                'consignee_area.require' => '请选择所在地区县'
            ];
            $validate->message($message);
            //验证部分数据合法性
            if (!$validate->check($post,$rule)) {
                return json(['code'=>2,'msg'=>$validate->getError(),'returnData'=>'']);die;
            }
            $res = Db::name('order')->where('id',$post['id'])->update($post);
            if($res){
            	$this->paylog($post['id'],'修改收货信息成功','后台管理员将收货地址修改为：'.$post['consignee_address'],$post['user_id']);
            	return json(['code'=>0,'msg'=>'保存成功','returnData'=>'']);die;
            }else{
            	return json(['code'=>3,'msg'=>'保存失败','returnData'=>'']);die;
            }
		}else{
			$one = Db::name('order')->field('id,user_id,consignee_name,consignee_mobile,consignee_address,consignee_province,consignee_city,consignee_area')->where('id',$id)->find();
			$sheng = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>1])->order('region_order asc,region_id asc')->select();
	    	$this->assign('sheng',$sheng);
	    	if($id>0){
	    		if($one['consignee_province']>0){
	    			$shi = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>$one['consignee_province']])->order('region_order asc,region_id asc')->select();
	    			$this->assign('shi',$shi);
	    		}
	    		if($one['consignee_city']>0){
	    			$qu = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>$one['consignee_city']])->order('region_order asc,region_id asc')->select();
	    			$this->assign('qu',$qu);
	    		}
	    	}
			$this->assign('one',$one);
			return $this->fetch();
		}
	}
	//确认付款
	public function fukuan_publish(){
		$id = $this->request->param('id') ? $this->request->param('id') : '0';
		if($this->request->isAjax()){
			$post = $this->request->post();
			$validate = new \think\Validate();
            $rule =   [
                'pay_money'  => 'require'
            ];
            $message  =   [
                'pay_money.require' => '实付金额不能为空'
            ];
            $validate->message($message);
            //验证部分数据合法性
            if (!$validate->check($post,$rule)) {
                return json(['code'=>2,'msg'=>$validate->getError(),'returnData'=>'']);die;
            }
			if($post['pay_type']=="wxpay"){
				$url = $this->hostname.url('api/payment/WxpayCheck',['id'=>$post['id'],'ty'=>'ht']);
				$res = $this->http_request($url);
				$res = json_decode($res,1);
				return json($res);die;
			}else if($post['pay_type']=="alipay"){
				$url = $this->hostname.url('api/payment/AlipayCheck',['id'=>$post['id'],'ty'=>'ht']);
				$res = $this->http_request($url);
				$res = json_decode($res,1);
				return json($res);die;
			}else if($post['pay_type']=="yuepay"){
				$uparr['pay_time'] = time();
				$uparr['pay_day']  = date('Ymd',time());
				$uparr['pay_body'] = "余额支付";
				$title   = "订单已支付";
				$content = "订单支付成功，支付方式：余额支付";
			}else{
				$uparr['pay_time'] = time();
				$uparr['pay_day']  = date('Ymd',time());
				$uparr['pay_body'] = "后台支付";
				$title   = "订单已支付";
				$content = "订单支付成功，支付方式：后台支付";
			}
			$count = Db::name('order')->where('parent_id',$post['id'])->count();
			if($count>0){
				$uparr['is_show'] = 'n';
			}
			$uparr['pay_type'] = $post['pay_type'];
			$uparr['status']   = 1;
			$uparr['pay_ordersn'] = $post['pay_ordersn'];
			$uparr['pay_transid'] = $post['pay_transid'];
			$uparr['pay_status']  = "SUCCESS";
			$uparr['pay_money']   = $post['pay_money'];
			$res = Db::name('order')->where('id',$post['id'])->update($uparr);
			if($res){
				//付款减库存
				$goods = Db::name('order_goods')->field('id,goods_id,desc,total')->where('order_id',$post['id'])->select();
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
				//更新日志
				$this->paylog($post['id'],$title,$content,$post['user_id']);
				if($count>0){
					$uparr['is_show'] = 'y';
					$resa = Db::name('order')->where('parent_id',$post['id'])->update($uparr);
					$zidans = Db::name('order')->field('id,user_id')->where('parent_id',$post['id'])->select();
					$yids = [];
					$yids[] = 0;
					foreach($zidans as $k=>$v){
						$this->paylog($v['id'],$title,$content,$v['user_id']);
						$yids[] = $v['id'];
					}
					//更新分佣信息
					$yjarr['pay_time'] = time();
					$yjarr['pay_day']  = date("Ymd");
					$yjarr['status']   = 1;
					$yj = Db::name('member_distribution')->where('order_id','in',$yids)->update($yjarr);
					//更新余额返现
					$yjarr['status']   = 4;
					$yjarr['shou_time'] = time();
					$yjarr['shou_day']  = date("Ymd");
					$yjarr['finish_time'] = time();
					$yjarr['finish_day']  = date("Ymd");
					$yj = Db::name('member_distribution')->where('order_id',$post['id'])->where('types','fanxian')->update($yjarr);
					//更新积分赠送
					$jj = Db::name('member_jifen')->where('orderid',$post['id'])->update(['status'=>0]);
				}else{
					//更新分佣信息
					$yjarr['pay_time'] = time();
					$yjarr['pay_day']  = date("Ymd");
					$yjarr['status']   = 1;
					$yj = Db::name('member_distribution')->where('order_id',$post['id'])->update($yjarr);
					//更新余额返现
					$yjarr['status']   = 4;
					$yjarr['shou_time'] = time();
					$yjarr['shou_day']  = date("Ymd");
					$yjarr['finish_time'] = time();
					$yjarr['finish_day']  = date("Ymd");
					$yj = Db::name('member_distribution')->where('order_id',$post['id'])->where('types','fanxian')->update($yjarr);
					//更新积分赠送
					$jj = Db::name('member_jifen')->where('orderid',$post['id'])->update(['status'=>0]);
				}
            	return json(['code'=>0,'msg'=>'保存成功','returnData'=>'']);die;
            }else{
            	return json(['code'=>3,'msg'=>'保存失败','returnData'=>'']);die;
            }
		}else{
			$onelog = Db::name('order_payment_log')->field('id,pay_ordersn,pay_status,pay_body')->where(['orderid'=>$id,'paytype'=>'wxpay'])->order('createtime desc')->limit(1)->find();
			$oneloa = Db::name('order_payment_log')->field('id,pay_ordersn,pay_status,pay_body')->where(['orderid'=>$id,'paytype'=>'alipay'])->order('createtime desc')->limit(1)->find();
			$one = Db::name('order')->field('id,pay_transid,pay_ordersn,price,user_id')->where('id',$id)->find();
			$this->assign('one',$one);
			$this->assign('onelog',$onelog);
			$this->assign('oneloa',$oneloa);
			return $this->fetch();
		}
	}
	public function check_fukuan_publish(){
		$id = $this->request->param('id') ? $this->request->param('id') : '0';
		if($this->request->isAjax()){
			$post = $this->request->post();
			if($post['pay_type']=="wxpay"){
				$url = $this->hostname.url('api/payment/WxpayCheck',['id'=>$post['id'],'ty'=>'ht']);
				$res = $this->http_request($url);
				$res = json_decode($res,1);
				return json($res);die;
			}else if($post['pay_type']=="alipay"){
				$url = $this->hostname.url('api/payment/AlipayCheck',['id'=>$post['id'],'ty'=>'ht']);
				$res = $this->http_request($url);
				$res = json_decode($res,1);
				return json($res);die;
			}
		}else{
			$onelog = Db::name('order_payment_log')->field('id,pay_ordersn,pay_status,pay_body')->where(['orderid'=>$id,'paytype'=>'wxpay'])->order('createtime desc')->limit(1)->find();
			$oneloa = Db::name('order_payment_log')->field('id,pay_ordersn,pay_status,pay_body')->where(['orderid'=>$id,'paytype'=>'alipay'])->order('createtime desc')->limit(1)->find();
			$one = Db::name('order')->field('id,pay_transid,pay_ordersn,price,user_id')->where('id',$id)->find();
			$this->assign('one',$one);
			$this->assign('onelog',$onelog);
			$this->assign('oneloa',$oneloa);
			return $this->fetch();
		}
	}
	public function fahuo_publish(){
		$id = $this->request->param('id') ? $this->request->param('id') : '0';
		if($this->request->isAjax()){
			$post = $this->request->post();
			$validate = new \think\Validate();
            $rule =   [
                'express'  => 'require',
                'express_sn'  => 'require'
            ];
            $message  =   [
                'express.require' => '请选择物流公司',
                'express_sn.require' => '物流单号不能为空'
            ];
            $validate->message($message);
            //验证部分数据合法性
            if (!$validate->check($post,$rule)) {
                return json(['code'=>2,'msg'=>$validate->getError(),'returnData'=>'']);die;
            }
            $one = Db::name('express')->where('id',$post['express'])->find();
            $uparr['status'] = 2;
            $uparr['send_time'] = time();
            $uparr['send_day']  = date('Ymd');
            $uparr['express_company'] = $one['name'];
            $uparr['express_sn']      = $post['express_sn'];
            $uparr['express']         = $one['express'];
            $res = Db::name('order')->where('id',$post['id'])->update($uparr);
			if($res){
				$this->paylog($post['id'],"订单已发货","订单已发货，已交付".$one['name'].'，运单号：'.$post['express_sn'],$post['user_id']);
            	return json(['code'=>0,'msg'=>'保存成功','returnData'=>'']);die;
            }else{
            	return json(['code'=>3,'msg'=>'保存失败','returnData'=>'']);die;
            }
		}else{
			$express = Db::name('express')->field('id,name,express')->where('status','1')->order('displayorder asc')->select();
			$this->assign('express',$express);
			$one = Db::name('order')->field('id,express_company,express_sn,express,user_id')->where('id',$id)->find();
			$this->assign('one',$one);
			return $this->fetch();
		}
	}
	public function fahuo_cancel(){
		if($this->request->isAjax()){
			$post = $this->request->post();
			$one = Db::name('order')->field('id,user_id')->where('id',$post['id'])->find();
			$uparr['status']    = 1;
	        $uparr['send_time'] = 0;
	        $uparr['send_day']  = 0;
	        $uparr['express_company'] = '';
	        $uparr['express_sn']      = '';
	        $uparr['express']         = '';
	        $res = Db::name('order')->where('id',$post['id'])->update($uparr);
			if($res){
				$this->paylog($post['id'],"订单已取消发货","订单取消发货成功",$one['user_id']);
            	return json(['code'=>0,'msg'=>'保存成功','returnData'=>'']);die;
            }else{
            	return json(['code'=>3,'msg'=>'保存失败','returnData'=>'']);die;
            }
	    }
	}
	public function shouhuo(){
		if($this->request->isAjax()){
			$post = $this->request->post();
			$one = Db::name('order')->field('id,user_id')->where('id',$post['id'])->find();
			$uparr['status'] = 3;
			$uparr['shou_time'] = time();
			$uparr['shou_day']  = date("Ymd");
	        $res = Db::name('order')->where('id',$post['id'])->update($uparr);
			if($res){
				$this->paylog($post['id'],"订单已收货","订单成功收货",$one['user_id']);
				//更新分佣信息
				$yjarr['shou_time'] = time();
				$yjarr['shou_day']  = date("Ymd");
				$yjarr['status']    = 3;
				$yj = Db::name('member_distribution')->where('order_id',$post['id'])->update($yjarr);
            	return json(['code'=>0,'msg'=>'保存成功','returnData'=>'']);die;
            }else{
            	return json(['code'=>3,'msg'=>'保存失败','returnData'=>'']);die;
            }
	    }
	}
	public function wancheng(){
		if($this->request->isAjax()){
			$post = $this->request->post();
			$one = Db::name('order')->field('id,user_id')->where('id',$post['id'])->find();
			$uparr['status'] = 4;
			$uparr['finish_time'] = time();
			$uparr['finish_day']  = date("Ymd");
	        $res = Db::name('order')->where('id',$post['id'])->update($uparr);
			if($res){
				$this->paylog($post['id'],"订单已完成","订单已完成，感谢您的支持",$one['user_id']);
				//更新分佣信息
				$yjarr['finish_time'] = time();
				$yjarr['finish_day']  = date("Ymd");
				$yjarr['status']      = 4;
				$yj = Db::name('member_distribution')->where('order_id',$post['id'])->update($yjarr);
            	return json(['code'=>0,'msg'=>'保存成功','returnData'=>'']);die;
            }else{
            	return json(['code'=>3,'msg'=>'保存失败','returnData'=>'']);die;
            }
	    }
	}
	public function wuliu(){
		$id = $this->request->param('id');
		$one = Db::name('order')->field(['id','ordersn','express_sn','express'])->where(['id'=>$id])->find();
		$lista = Db::name('order_log')->field('id,title,content,createtime')->where('orderid',$id)->where('is_del','n')->select();
		$list = [];
		foreach($lista as $k=>$v){
			$v['createtime'] = date("Y.m.d H:i:s",$v['createtime']);
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
        $this->assign('list',$list);
        return $this->fetch();
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
/************************************************************************/
/** 维权管理
/************************************************************************/
	public function weiquan(){
		$admininfo = $this->admininfo;
		$where[] = ['or.is_del','=','n'];
		if($admininfo['shop_id']>0){
			$where[] = ['or.shop_id','=',$admininfo['shop_id']];
		}
		//状态
		$status = $this->request->param('status') ? $this->request->param('status') : 'all';
		if($status!="all"){
			
		}
		$this->assign('status',$status);
		//按支付方式
		$pay_type = $this->request->param('pay_type') ? $this->request->param('pay_type') : 'all';
		if($pay_type!="all"){
			$where[] = ['or.pay_type','=',$pay_type];
		}
		$this->assign('pay_type',$pay_type);
		//时间线筛选
		$time_type = $this->request->param('time_type') ? $this->request->param('time_type') : 'all';
		$times     = $this->request->param('times') ? $this->request->param('times') : '';
		if($times==""){
			$start_time = date("Y-m-d",time()-60*60*24*30);
			$end_time   = date("Y-m-d");
		}else{
			$times = trim($times);
			$times = explode("~",$times);
			$start_time = $times[0];
			$end_time   = $times[1];
		}
		if($time_type!="all"){
			$starttimes = strtotime($start_time.' 00:00:00');
			$endtimes   = strtotime($end_time.' 23:59:59');;
			$where[] = ['or.'.$time_type,'>=',$starttimes];
			$where[] = ['or.'.$time_type,'<=',$endtimes];
		}
		$this->assign('time_type',$time_type);
		$this->assign('start_time',trim($start_time));
		$this->assign('end_time',trim($end_time));
		//搜索关键字
		$keys_key = $this->request->param('keys_key') ? $this->request->param('keys_key') : 'order_sn';
		$this->assign('keys_key',$keys_key);
		$keys = $this->request->param('keys') ? $this->request->param('keys') : '';
		if($keys!=''){
			
		}
		$this->assign('keys',$keys);


		return $this->fetch();
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