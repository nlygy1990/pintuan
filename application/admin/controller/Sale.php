<?php
namespace app\admin\controller;
use \think\Controller;
use app\admin\controller\Base;
use \think\Db;
use \think\Cookie;
use \think\Session;
use \think\Request;
class Sale extends Base{
	public function __construct(){
		parent::__construct(); //使用父类的构造方法
		$typeslist[0]['keys']  = 'manjian';
		$typeslist[0]['title'] = '满额立减';
		$typeslist[1]['keys']  = 'baoyou';
		$typeslist[1]['title'] = '满额包邮';
		$this->typeslist = $typeslist;
	}
/*****************************************************************************************/
/** 基本功能
/*****************************************************************************************/
	public function enough(){
		$list = Db::name('sale')->order('id asc')->select();
		$count = Db::name('sale')->count();
		$typeslist = $this->typeslist;
		$this->assign('typeslist',$typeslist);
		$this->assign('list',$list);
		$this->assign('count',$count);
		return $this->fetch();
	}
	public function enough_publish(){
		$id = $this->request->param('id') ? $this->request->param('id') :'0';
		if($this->request->isAjax()){
			$post = $this->request->post();
			if($post['types']=="baoyou"){
				if(!$post['man']){
					return json(['code'=>1,'msg'=>'请填写满额包邮金额']);die;
				}
			}else if($post['types']=="manjian"){
				if(!$post['man']){
					return json(['code'=>1,'msg'=>'请填写满减金额']);die;
				}
				if(!$post['减']){
					return json(['code'=>1,'msg'=>'请填写满减金额']);die;
				}
			}
			$admininfo = $this->admininfo;
			if(isset($post['id'])){
				$menu = Db::name('sale')->where('id',$post['id'])->find();
	            if(empty($menu)) {
	            	return json(['code'=>3,'msg'=>'ID不正确','returnData'=>'']);die;
	            }
				$post['updatetime'] = time();
				$post['update_admin'] = $admininfo['id'];
				$res = Db::name('sale')->where('id',$post['id'])->update($post);
	       		if($res) {
	            	return json(['code'=>0,'msg'=>'修改成功','returnData'=>'']);die;
	        	} else {
	            	return json(['code'=>4,'msg'=>'修改失败','returnData'=>'']);die;
	       		}
			}else{
				$post['addtime']  = $post['updatetime'] = time();
				$post['admin_id'] = $post['update_admin'] = $admininfo['id'];
				$res = Db::name('sale')->insert($post);
	       		if($res) {
                    return json(['code'=>0,'msg'=>'添加成功','returnData'=>'']);die;
                } else {
                    return json(['code'=>5,'msg'=>'添加失败','returnData'=>'']);die;
                }
			}
		}else{
			$typeslist = $this->typeslist;
			$this->assign('typeslist',$typeslist);
			$one = Db::name('sale')->where('id',$id)->find();
			$this->assign('one',$one);
			return $this->fetch();
		}
	}
	public function enough_del(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
            if(empty($post['id'])){
                return json(['code'=>2,'msg'=>'请选择要删除的优惠']);
            }
    		$res = Db::name('sale')->where('id','in',$post['id'])->delete();
    		if($res){
    			return json(['code'=>0,'msg'=>'删除成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'删除失败']);
    		}
    	}
	}
/*****************************************************************************************/
/** 优惠券
/*****************************************************************************************/
	public function coupons(){
		$where[] = ['is_del','=','n'];
		$list  = Db::name('sale_coupons')->where($where)->order("addtime desc")->paginate(10);
		$this->assign('list',$list);
		$count = Db::name('sale_coupons')->where($where)->count();
		$this->assign('count',$count);
		return $this->fetch();
	}
	public function coupons_publish(){
		$id = $this->request->param('id') ? $this->request->param('id') :'0';
		if($this->request->isAjax()){
			$post = $this->request->post();
			$admininfo = $this->admininfo;
			if(isset($post)){
				if($post['total']>'0'){
					$post['total'] = $post['total'];
				}else{
					$post['total'] = '-1';
				}
			}else{
				$post['total'] = '-1';
			}
			if($post['starttime']){
				$post['starttime'] = strtotime($post['starttime']);
			}
			if($post['endtime']){
				$post['endtime'] = strtotime($post['endtime']);
			}
			if(isset($post['goodsid'])){
				$post['goodsids'] = implode(",",$post['goodsid']);
			}
			if(isset($post['level'])){
				$post['levels'] = implode(",",$post['level']);
			}
			if(isset($post['user'])){
				$post['users'] = implode(",",$post['user']);
			}
			unset($post['goodsid']);unset($post['level']);unset($post['user']);
			if(isset($post['id'])){
				$post['updatetime']   = time();
				$post['update_admin'] = $admininfo['id'];
				$res = Db::name('sale_coupons')->where('id',$post['id'])->update($post);
				if($res) {
	            	return json(['code'=>0,'msg'=>'修改成功','returnData'=>'']);die;
	        	} else {
	            	return json(['code'=>4,'msg'=>'修改失败','returnData'=>'']);die;
	       		}
			}else{
				$post['addtime']  = $post['updatetime']   = time();
				$post['admin_id'] = $post['update_admin'] = $admininfo['id'];
				$res = Db::name('sale_coupons')->insert($post);
				if($res) {
                    return json(['code'=>0,'msg'=>'添加成功','returnData'=>'']);die;
                } else {
                    return json(['code'=>5,'msg'=>'添加失败','returnData'=>'']);die;
                }
			}
		}else{
			$goods = Db::name('goods')->field('id,title')->where('is_show','y')->where('is_del','n')->select();
			$this->assign('goods',$goods);
			$level = Db::name('member_cate')->field('id,title')->where(['is_del'=>'n'])->select();
			$this->assign('level',$level);
			$users = Db::name('member')->field('id,username,nickname')->where(['is_del'=>'n'])->select();
			$this->assign('users',$users);

			$one = Db::name('sale_coupons')->where('id',$id)->find();
			if($one['goodsids']>'0'){
				$one['goodsid'] = explode(",",$one['goodsids']);
			}
			if($one['levels']>'0'){
				$one['level'] = explode(",",$one['levels']);
			}
			if($one['users']>'0'){
				$one['user'] = explode(",",$one['users']);
			}
			$this->assign('one',$one);
			return $this->fetch();
		}
	}
	public function coupons_show(){
		if($this->request->isPost()){
    		$post = $this->request->post();
    		if($post['va']=='n'){
    			$va = 'y';
    		}else{
    			$va = 'n';
    		}
    		$data['is_show'] = $va;
    		$res = Db::name('sale_coupons')->where('id',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'操作成功','va'=>$va]);
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
    	}
	}
	public function coupons_send(){
		$id = $this->request->param('id') ? $this->request->param('id') : '0';
		$one = Db::name('sale_coupons')->field('id,title')->where('id',$id)->find();
		$this->assign('one',$one);
		$where[] = ['coupons_id','=',$id];
		$where[] = ['is_del','=','n'];
		$list = Db::name('member_coupons')->where($where)->order("createtime desc")->paginate(10);
		$count = Db::name('member_coupons')->where($where)->count();
		$this->assign('list',$list);
		$this->assign('count',$count);
		return $this->fetch();
	}
	public function coupons_fafang(){
		if($this->request->isPost()){
			$post = $this->request->post();
			$one = Db::name('sale_coupons')->field('is_show,is_del,addtime,admin_id,updatetime,update_admin',true)->where('id',$post['cid'])->find();
			$validate = new \think\Validate();
            $rule =   [
                'cid'  => 'require'
            ];
            $message  =   [
                'cid.require' => '请选择要发放的优惠券'
            ];
            $validate->message($message);
            //验证部分数据合法性
            if (!$validate->check($post,$rule)) {
                return json(['code'=>2,'msg'=>$validate->getError(),'returnData'=>'']);die;
            }
            $whereu[] = ['is_del','=','n'];
            $whereu[] = ['is_show','=','y'];
            if(isset($post['level'])){
            	$level = implode(",",$post['level']);
            	if($level=='0'){

            	}else{
            		$whereu[] = ['level','in',$post['level']];
            	}
            }else{
            	return json(['code'=>3,'msg'=>'请选择要发放的会员组','returnData'=>'']);die;
            }
            if(isset($post['user'])){
            	$user = implode(",",$post['user']);
            	if($user=='0'){

            	}else{
            		$whereu[] = ['id','in',$post['user']];
            	}
            }else{
            	return json(['code'=>4,'msg'=>'请选择要发放的会员','returnData'=>'']);die;
            }
            $member = Db::name('member')->field('id,username,nickname')->where($whereu)->select();
            $addarr = [];
            $msgarr = [];
            foreach($member as $k=>$v){
            	$addarr[$k]['user_id']     = $v['id'];
            	$addarr[$k]['coupons_id']  = $post['cid'];
            	$addarr[$k]['title']       = $one['title'];
            	$addarr[$k]['description'] = $one['description'];
            	$addarr[$k]['thumb']       = $one['thumb'];
            	$addarr[$k]['man']         = $one['man'];
            	$addarr[$k]['jian']        = $one['jian'];
            	$addarr[$k]['starttime']   = $one['starttime'];
            	$addarr[$k]['endtime']     = $one['endtime'];
            	$addarr[$k]['goodsids']    = $one['goodsids'];
            	$addarr[$k]['types']       = "后台批量发放";
            	$addarr[$k]['createtime']  = time();
            	if($post['message']=="y"){
            		if($post['messagetxt']==""){
            			$post['messagetxt'] = "尊敬的{{name}}用户，恭喜你获得【{{couponsname}}】优惠券一张，赶快去使用吧";
            		}
            		$content = str_replace("{{name}}",$v['nickname'],$post['messagetxt']);
            		$content = str_replace("{{couponsname}}",$one['title'],$content);
            		$msgarr[$k]['userid']     = $v['id'];
            		$msgarr[$k]['title']      = "恭喜您获得一张优惠券";
            		$msgarr[$k]['desc']       = "恭喜您获得一张优惠券，赶快去使用吧";
            		$msgarr[$k]['content']    = $content;
            		$msgarr[$k]['senduid']    = $v['id'];
            		$msgarr[$k]['sendtime']   = time();
            		$msgarr[$k]['sendday']    = date("Ymd");
            		$msgarr[$k]['createtime'] = time();
            		$msgarr[$k]['createday']  = date("Ymd");
            		$msgarr[$k]['types']      = "youhui";
            	}
            }
            if($one['total']=="-1"){
            	$res = Db::name('member_coupons')->insertAll($addarr);
            }else{
            	if($one['total']>=count($member)){
            		$res = Db::name('member_coupons')->insertAll($addarr);
            	}else{
            		return json(['code'=>6,'msg'=>'优惠券数量不足，缺少'.(count($member)-$one['total'])."张",'returnData'=>'']);die;
            	}
            }
            if($res){
            	if($one['total']!="-1"){
            		$uparr['total'] = $one['total']-$res;
            		$resup = Db::name('sale_coupons')->where('id',$post['cid'])->update($uparr);
            	}
            	//是否发站内信
            	if($post['message']=="y"){ //发站内信
            		$resmsg = Db::name('member_message')->insertAll($msgarr);
            	}
             	return json(['code'=>0,'msg'=>'发放成功！已成功发放'.$res.'张优惠券','returnData'=>'']);die;
            }else{
            	return json(['code'=>5,'msg'=>'发放失败','returnData'=>'']);die;
            }
		}else{
			$cid = $this->request->param('cid') ? $this->request->param('cid') : '0';
			$one = Db::name('sale_coupons')->field('id,title,levels,users')->where(['id'=>$cid])->find();
			$this->assign('cid',$cid);
			$coupons = Db::name('sale_coupons')->field('id,title')->where(['is_del'=>'n'])->select();
			$wherel[] = ['is_del','=','n'];
			$whereu[] = ['is_del','=','n'];
			if($one){
				if($one['levels']!='0'){
					$one['levels'] = explode(",",$one['levels']);
					$wherel[] = ['level','in',$one['levels']];
				}
				if($one['users']!='0'){
					$one['users'] = explode(",",$one['users']);
					$whereu[] = ['id','in',$one['users']];
				}
			}
			$level = Db::name('member_cate')->field('id,title')->where($wherel)->select();
			$users = Db::name('member')->field('id,username,nickname')->where($whereu)->select();
			$this->assign('level',$level);
			$this->assign('coupons',$coupons);
			$this->assign('users',$users);
			return $this->fetch();
		}
	}
	public function coupons_send_del(){
		if($this->request->isAjax()){
			$post = $this->request->post();
            if(empty($post['id'])){
                return json(['code'=>2,'msg'=>'请选择要收回的优惠券']);
            }
    		$res = Db::name('member_coupons')->where('id','in',$post['id'])->update(['is_del'=>'y']);
			if($res){
				$list = Db::name('member_coupons')->field('coupons_id')->where('id','in',$post['id'])->select();
    			foreach($list as $k=>$v){
    				$one = Db::name('sale_coupons')->field('id,total')->where('id',$v['coupons_id'])->find();
    				if($one['total']=="-1"){

    				}else{
    					$uparr['total'] = $v['total']+1;
    					$resup = Db::name('sale_coupons')->field('id,total')->where('id',$v['coupons_id'])->update($uparr);
    				}
    			}
    			return json(['code'=>0,'msg'=>'删除成功']);
			}else{
				return json(['code'=>1,'msg'=>'删除失败']);
			}
		}
	}
	public function coupons_del(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
            if(empty($post['id'])){
                return json(['code'=>2,'msg'=>'请选择要删除的优惠券']);
            }
    		$res = Db::name('sale_coupons')->where('id','in',$post['id'])->update(['is_del'=>'y']);
    		if($res){
    			return json(['code'=>0,'msg'=>'删除成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'删除失败']);
    		}
    	}
	}
/*****************************************************************************************/
/** 人人分销
/*****************************************************************************************/
	public function fenxiao(){
		if($this->request->isPost()){
			$post = $this->request->post();
			if(!isset($post['isdistribution'])){
				$post['isdistribution'] = "0";
			}
			if(!isset($post['newuseropen'])){
				$post['newuseropen'] = "0";
			}
			$admininfo = $this->admininfo;
			$post['updatetime']   = time();
			$post['update_admin'] = $admininfo['id'];
			$res = Db::name('sale_fenxiao')->where('id',1)->update($post);
			if($res){
    			return json(['code'=>0,'msg'=>'操作成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
		}else{
			$one = Db::name('sale_fenxiao')->where('id',1)->find();
			$this->assign('one',$one);
			return $this->fetch();
		}
	}
}