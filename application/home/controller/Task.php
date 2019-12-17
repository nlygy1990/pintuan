<?php
namespace app\home\controller;
use \think\Controller;
use \think\Db;
use \think\Session;
use \think\Cookie;
use \think\Request;
use \think\AES;
class Task extends Controller{
	//分红任务
	public function index(){
		$cates = Db::name('member_cate')->where(['is_del'=>'n','is_show'=>'y'])->select();
		$today = date('Ymd',(time()-60*60*24));
		$zongye  = Db::name('groups_order')->alias('o')
					->field('o.price')
					->where('o.status','in',['1','2','3','4','5'])
					->join('groups g','g.id=o.groups_id','left')
					->where('g.finishday',$today)
					->select();
		$zongyeji = [];
		foreach($zongye as $k=>$v){
			$zongyeji[] = $v['price'];
		}
		$zongyejicount = sprintf("%.2f",array_sum($zongyeji));
		$wcon = Db::name('groups_configs')->field('pt_yejibili')->where('id',1)->find();
                $zongfen = [];
		foreach($cates as $k=>$v){
                        $fen = Db::name('member')->where('oldlevel',$v['id'])->where('fenhong_end','n')->count();
                        $zongfen[] = $fen*$v['auto_group'];
		}
                $zongfencount = array_sum($zongfen);
                foreach($cates as $k=>$v){
                        $this->dengjifen($v['id'],$zongyejicount,$v['auto_group'],$wcon['pt_yejibili'],$today,$zongfencount);
                }
	}
	public function dengjifen($cateid,$yeji,$fenmu,$bili,$today,$zongfencount){
		// $tuancount = Db::name('groups')->where('status','>','0')->count();
                $tuancount = $zongfencount;
		if($fenmu>0){
			$qian = ($fenmu/$tuancount)*($yeji*($bili/100));
		}else{
			$qian = 0;
		}
                $qian = sprintf("%.2f",$qian);
		if($qian>'0'){
			$list = Db::name('member')->field('id,level,oldlevel')->where('oldlevel',$cateid)->where('fenhong_end','n')->select();
			foreach($list as $k=>$v){
				$this->fenhong($v['id'],$qian,$v['level'],$v['oldlevel']);
			}
		}
	}
	public function fenhong($uid,$money,$level,$oldlevel){
		$add['user_id']        = $uid;
                $add['groups_id']      = 0;
                $add['money']          = $money; 
                $add['title']          = "业绩分红";
                $add['createtime']     = time();
                $add['createday']      = date("Ymd");
                $add['status']         = 1;
                $add['types']          = "fenhong";
                //查询总的分红数
                $list = Db::name('member_distribution')->field('money')->where('user_id',$uid)->where('status','>','0')->select();
                $zong = [];
                foreach($list as $k=>$v){
                	$zong[] = $v['money'];
                }
                $zongshu = array_sum($zong);
                $member = Db::name('member')->alias('m')
                			->field('m.level,mc.group_fenhong')
                			->where('m.id','=',$uid)
                			->join('member_cate mc','m.level=mc.id','left')
                			->find();
                $upmem['oldlevel'] = $level;
                if($zongshu>$member['group_fenhong']){ //已经超过分红权

                }else{
                	$nzongshu = $zongshu+$money;
                	if($nzongshu>=$member['group_fenhong']){
                		$add['money']          = $member['group_fenhong']-$zongshu;
                		$upmem['fenhong_end']  = 'y';
                	}
                }
                if($level!=$oldlevel){
                	$upmem['fenhong_end']  = 'n';
                }
                Db::startTrans();
                try{
                        $resup = Db::name('member')->where('id',$uid)->update($upmem);
                }catch (\Exception $e) {
                        dump($e->getMessage());
                        // 回滚事务
                        Db::rollback();
                        //注意：我们做了回滚处理，所以id为1039的数据还在
                }
                //判断是否已经分过红
                $ck = Db::name('member_distribution')->field('id')->where(['user_id'=>$uid,'createday'=>$add['createday'],'types'=>'fenhong'])->find();
                if($ck){

                	$resadd = Db::name('member_distribution')->where('id',$ck['id'])->update($add);
                }else{
                	$resadd = Db::name('member_distribution')->insert($add);
                }
	}
}