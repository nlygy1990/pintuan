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
class Finance extends Base{
	public function __construct(){
		parent::__construct(); //使用父类的构造方法
	}

	public function index(){

	}
/************************************************************************/
/** 体现明细
/************************************************************************/
	public function tixian(){
		$where[] = ['is_del','=','n'];
		$status = $this->request->param('status') ? $this->request->param('status') : 'all';
		$this->assign('status',$status);
		if($status!="all"){
			if(in_array($status,['1','2','3'])){
				$where[] = ['status','=',($status-1)];
			}else{
				$where[] = ['status','=',"-1"];
			}
		}
		$keys = $this->request->param('keys') ? $this->request->param('keys') : '';
		if($keys!=""){
			$where[] = ["bankbody",'like','%'.$keys.'%'];
		}
		$list = Db::name('member_tixian')->field('id,title,uid,bankbody,money,addtime,status')->where($where)->order('addtime desc')->paginate(20);
		$this->assign('list',$list);
		$count = Db::name('member_tixian')->where($where)->count();
		$this->assign('count',$count);
		return $this->fetch();
	}
	public function tixianShenhe(){
		if($this->request->isPost()){
			$data = $this->request->post();
			if($data['status']=="0"){

			}else if($data['status']=="1"){
				$data['shenhetime'] = time();
				$data['shenheday']  = date('Ymd');
			}else if($data['status']=="2"){
				$data['paytime'] = time();
				$data['payday']  = date('Ymd');
			}
			$res = Db::name('member_tixian')->where('id',$data['id'])->update($data);
			if($res){
				$one = Db::name('member_tixian')->where('id',$data['id'])->find();
				if($data['status']=="1"){
					$arr['status'] = 3;
					$arr['finish_time'] = time();
					$arr['finish_day']  = date("Ymd");
					$reeaa = Db::name('member_distribution')->where('id','in',$one['tids'])->update($arr);
				}else if($data['status']=="2"){
					$arr['status'] = 1;
					$arr['pay_time'] = 0;
					$arr['pay_day']  = 0;
					$reeaa = Db::name('member_distribution')->where('id','in',$one['tids'])->update($arr);
				}
				return json(['code'=>'0','msg'=>'操作成功']);
			}else{
				return json(['code'=>'1','msg'=>'操作失败']);
			}
		}else{
			$id = $this->request->param('id');
			$one = Db::name('member_tixian')->where('id',$id)->find();
			$this->assign('one',$one);
			return $this->fetch();
		}
	}
	public function tixian_daochu(){
		$where[] = ['is_del','=','n'];
		$status = $this->request->param('status') ? $this->request->param('status') : 'all';
		$this->assign('status',$status);
		if($status!="all"){
			if(in_array($status,['1','2','3'])){
				$where[] = ['status','=',($status-1)];
			}else{
				$where[] = ['status','=',"-1"];
			}
		}
		$keys = $this->request->param('keys') ? $this->request->param('keys') : '';
		if($keys!=""){
			$where[] = ["bankbody",'like','%'.$keys.'%'];
		}
		$list = Db::name('member_tixian')->field('id,title,uid,bankbody,money,addtime,status,shouxufei,remark')->where($where)->order('addtime desc')->select();
		$name = '提现清单（'.date("Y-m-d").'）';
		$objPHPExcel = new \PHPExcel();
		$objPHPExcel->getProperties()->setCreator("无崖子")->setLastModifiedBy("无崖子")->setTitle("数据EXCEL导出")->setSubject("数据EXCEL导出")->setDescription("数据EXCEL导出")->setKeywords("excel")->setCategory("result file");
        $objPHPExcel->getActiveSheet()->setCellValue('A1', '请填写业务参考号');
        $objPHPExcel->getActiveSheet()->setCellValue('B1',"\t".date('YmdHis',time()));
        //表头
        $objPHPExcel->getActiveSheet()->setCellValue('A2','城市');
	    $objPHPExcel->getActiveSheet()->setCellValue('B2','银行名称');
	    $objPHPExcel->getActiveSheet()->setCellValue('C2','开户行名称');
	    $objPHPExcel->getActiveSheet()->setCellValue('D2','收款方姓名');
	    $objPHPExcel->getActiveSheet()->setCellValue('E2','收款方银行账号');
	    $objPHPExcel->getActiveSheet()->setCellValue('F2','金额');
	    $objPHPExcel->getActiveSheet()->setCellValue('G2','备注');
	    $objPHPExcel->getActiveSheet()->setCellValue('H2','商家订单号');
	    $objPHPExcel->getActiveSheet()->setCellValue('I2','打款状态(0打款中；1打款成功；2打款失败)');
	    $objPHPExcel->getActiveSheet()->setCellValue('J2','手续费');
	    $objPHPExcel->getActiveSheet()->setCellValue('K2','实际金额');
	    // $wcon = Db::name('groups_configs')->field('shouxu,shouxu_j')->where('id','1')->find();
	    foreach($list as $k=>$v){
	    	$num = $k+3;
	    	$bankbody = json_decode($v['bankbody'],1);
	    	$bankbody['city'] = isset($bankbody['city']) ? $bankbody['city'] : '未知';
	    	$objPHPExcel->getActiveSheet()->setCellValue('A'.$num,$bankbody['city']);
	    	$objPHPExcel->getActiveSheet()->setCellValue('B'.$num,$bankbody['bankname']);
	    	$objPHPExcel->getActiveSheet()->setCellValue('C'.$num,$bankbody['banknamea']);
	    	$objPHPExcel->getActiveSheet()->setCellValue('D'.$num,$bankbody['name']);
	    	$objPHPExcel->getActiveSheet()->setCellValue('E'.$num,"\t".$bankbody['cardid']);
	    	$objPHPExcel->getActiveSheet()->setCellValue('F'.$num,$v['money']);
	    	$objPHPExcel->getActiveSheet()->setCellValue('G'.$num,$v['remark']);
	    	$objPHPExcel->getActiveSheet()->setCellValue('H'.$num,$v['id']);
	    	$objPHPExcel->getActiveSheet()->setCellValue('I'.$num,$v['status']);
	    	$objPHPExcel->getActiveSheet()->setCellValue('J'.$num,$v['shouxufei']);
	    	$objPHPExcel->getActiveSheet()->setCellValue('K'.$num,($v['money']-$v['shouxufei']));
	    }

	    $numc = count($list)+3;
	    $ends = 'K';
	    //设置单元格属性------------
        //合并单元格
        // $objPHPExcel->getActiveSheet()->mergeCells('A1:'.$ends.'1');
        // $objPHPExcel->getActiveSheet()->mergeCells('A2:'.$ends.'2');
        //设置单元格字体
        // $objPHPExcel->getActiveSheet()->getStyle('A1')->getFont()->setName('黑体');
        // $objPHPExcel->getActiveSheet()->getStyle('A1')->getFont()->setSize(20);
        // $objPHPExcel->getActiveSheet()->getStyle('A1')->getFont()->setBold(true);
        // $objPHPExcel->getActiveSheet()->getStyle('A'.$numc.':'.($ends.$numc))->getFont()->setBold(true);
        //设置宽度
        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(35);
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(35);
        $objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('H')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('I')->setWidth(30);
        $objPHPExcel->getActiveSheet()->getColumnDimension('J')->setWidth(15);
        //设置表头行高
        $objPHPExcel->getActiveSheet()->getRowDimension(1)->setRowHeight(35);
        $objPHPExcel->getActiveSheet()->getRowDimension(2)->setRowHeight(22);
        
        //设置自动换行
        $objPHPExcel->getActiveSheet()->getStyle('A3:'.($ends.$numc))->getAlignment()->setWrapText(true);


        $objPHPExcel->getActiveSheet()->setTitle('提现清单');
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
	public function tixian_daoru(){
		return $this->fetch();
	}
	public function tixianDakuan(){
		if($this->request->isPost()){
			$data = $this->request->post();
			dump($data);
		}else{
			$id = $this->request->param('id');
			$one = Db::name('member_tixian')->where('id',$id)->find();
			$this->assign('one',$one);
			return $this->fetch();
		}
	}
}