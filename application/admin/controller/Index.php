<?php
namespace app\admin\controller;
use \think\Controller;
use app\admin\controller\Base;
use \think\Db;
use \think\Session;
use \think\Cookie;
use \think\Request;
use \think\AES;
class Index extends Base{
    public function index(){
        // redis()->set('task_num_1',3);
        // redis()->decr('task_num_1', 3);
        // $num = redis()->get('task_num_1');
        // dump($num);
        $admininfo = $this->admininfo;
        $where[] = ['is_del','=','n'];
        $where[] = ['is_show','=','y'];
        $wherea = 0;
        if($admininfo['id']=='1'){ //顶级超级管理员
            
        }else if($admininfo['pid']=='1'){ //超级管理员
            
        }else if($admininfo['pid']=='2'){ //地区管理员
            $cates = Db::name('admin_cate')->field('quanxian')->where('id','2')->find();
            if($cates['quanxian']){
                $where[] = ['id','in',$cates['quanxian']];
            }
            $wherea = "179";
        }else if($admininfo['pid']>'2'){//平台管理员
            $cates = Db::name('admin_cate')->field('quanxian')->where('id',$admininfo['pid'])->find();
            if($cates['quanxian']){
                $where[] = ['id','in',$cates['quanxian']];
            }
        }else if($admininfo['pid']=='0' && $admininfo['shop_id']>0){ //店铺管理员 40店铺
            $wherea = "1,6,30,50,66,110,153,154,162";
        }else if($admininfo['pid']=='0' && $admininfo['stores_id']>0){ //门店店员及店长 30门店
            $wherea = "1,6,19,40,50,66,110,127,153,154,162";
        }else if($admininfo['pid']=='0' && $admininfo['school_id']>0){ //学校管理员 66学校
            $wherea = "1,6,19,30,40,50,110,127,153,154,162";
        }else if($admininfo['pid']=='0' && $admininfo['hospital_id']>0){ //医院管理员 50医院
            $wherea = "1,6,19,30,40,66,110,127,153,154,162";
        }
        $wherepa = $where;
        $wherepa[] = ['pid','=',0];
    	$menus = Db::name('admin_menus')->field('id,title,mm,cc,aa,alis,icon')->where($wherepa)->where('id','not in',$wherea)->order('orders asc,addtime asc')->select();
        foreach($menus as $k=>$v){
            $menus[$k]['url'] = url($v['mm'].'/'.$v['cc'].'/'.$v['aa']);
            $wherepb = $where;
            $wherepb[] = ['pid','=',$v['id']];
            $child = Db::name('admin_menus')->field('id,title,mm,cc,aa,alis,icon')->where($wherepb)->where('id','not in',$wherea)->order('orders asc,addtime asc')->select();
            foreach($child as $ka=>$va){
                $child[$ka]['url'] = url($va['mm'].'/'.$va['cc'].'/'.$va['aa']);
            }
            $menus[$k]['child'] = $child;
        }
    	$this->assign('menus',$menus);
    	return $this->fetch();
    }
    public function main(){
        //php版本
        $info['php'] = PHP_VERSION;
        //操作系统
        $info['win'] = PHP_OS;
        //最大上传限制
        $info['upload_size'] = ini_get('upload_max_filesize');
        //脚本执行时间限制
        $info['execution_time'] = ini_get('max_execution_time').'S';
        //环境
        $info['environment'] = $_SERVER["SERVER_SOFTWARE"];
        $version = Db::query('SELECT VERSION() AS ver');
        $info['mysql'] = $version[0]['ver'];
        //剩余空间大小
        // echo disk_free_space("/");
        // $info['disk'] = round(disk_free_space("/")/1024/1024/1024,3).'G';
        $info['disk'] = "未知";
        $this->assign('info',$info);

        $tongji = [];
        $tongji['huiyuan']  = Db::name('member')->where('is_del','n')->where('is_show','y')->count();
        $tongji['wenzhang'] = Db::name('news')->where('is_del','n')->where('is_show','y')->count();
        $tongji['shangpin'] = Db::name('goods')->where('is_del','n')->where('is_show','y')->count();
        $tongji['dianpu']   = Db::name('shop')->where('is_del','n')->where('is_show','y')->count();
        $tongji['mendian']  = Db::name('stores')->where('is_del','n')->where('is_show','y')->count();
        $tongji['dingdan']  = Db::name('order')->where('is_del','n')->where('is_show','y')->count();
        $this->assign('tongji',$tongji);
    	return $this->fetch();
    }
}
