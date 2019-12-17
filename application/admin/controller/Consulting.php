<?php
namespace app\admin\controller;
use \think\Controller;
use app\admin\controller\Base;
use \think\Db;
use \think\Cookie;
use \think\Session;
use \think\Request;
class Consulting extends Base{
	public function __construct(){
		parent::__construct(); //使用父类的构造方法
	}

	public function index(){
		$zixun = Db::name('zixun');
    	$where[] = ['a.is_del','=','n'];
    	$uid = $this->request->param('uid') ? $this->request->param('uid') : 'all';
    	if($uid!="all"){
    		$where[] = ['a.uid','=',$uid];
    	}
    	$this->assign('uid',$uid);
    	$status = $this->request->param('status') ? $this->request->param('status') : 'all';
    	if($status!="all"){
    		$ck = Db::name('zixun_huifu')->field(['pid','id'])->where(['is_show'=>'y','is_del'=>'n','types'=>'huifu'])->select();
    		$ids = [];
    		$ids[] = 0;
    		foreach($ck as $k=>$v){
    			$ids[] = $v['pid'];
    		}
    		if($status=="1"){
    			$where[] = ['a.id','in',$ids];
    		}else{
    			$where[] = ['a.id','not in',$ids];
    		}
    	}
    	$this->assign('status',$status);
    	$keys = $this->request->param('keys') ? $this->request->param('keys') : '';
    	if($keys!=""){
    		$where[] = ['a.content','like','%'.$keys.'%'];
    	}
    	$this->assign('keys',$keys);
    	$list = $zixun->alias('a')
    			->field('a.id,a.content,a.addtime,a.uid,ac.nickname,ac.username')
    			->join('member ac', 'ac.id=a.uid','left')
    			->where($where)
    			->order('a.addtime desc')->paginate(20);
    	$count = $zixun->alias('a')->field('id')->where($where)->count();
    	$this->assign('list',$list);
    	$this->assign('count',$count);
    	return $this->fetch();
	}
	public function huifu(){
    	$zixun = Db::name('zixun');
    	$zixunh = Db::name('zixun_huifu');
    	$id = $this->request->param('id') ? $this->request->param('id') : '0';
    	$one = $zixun->where(['id'=>$id,'is_del'=>'n'])->find();
        $one['pic'] = explode(",",$one['pic']);
    	$list = $zixunh->where(['pid'=>$id,'is_del'=>'n'])->order('addtime asc')->select();
    	$this->assign('list',$list);
    	$this->assign('one',$one);
    	return $this->fetch();
    }
    public function huifu_publish(){
    	if($this->request->isAjax()){
    		$data = $this->request->post();
    		if($data['content']==""){
    			return json(['code'=>2,'msg'=>'请填写回复内容']);
    		}
    		$admininfo = $this->admininfo;
    		$add['uid'] = $data['uid'];
    		$add['aid'] = $admininfo['id'];
    		$add['pid'] = $data['pid'];
    		$add['content'] = $data['content'];
    		$add['addtime'] = time();
    		$add['types']   = "huifu";
    		$add['is_show'] = 'y';
    		$add['is_del']  = 'n';
    		$res = Db::name('zixun_huifu')->insertGetId($add);
    		if($res){
    			$htmll = '<div class="hflist" data-id="'.$res.'">
					          <a href="javascript:void(0);" onclick="del(this,\''.$res.'\')" class="del">X删除</a>
					          <span class="wen">答</span>
					          <span class="ren">'.getAdmin($add['aid']).' &nbsp;&nbsp;'.date("Y-m-d H:i:s",$add['addtime']).'</span>
					          <div class="clear"></div>
					          <p style="padding-top:15px;text-align: right;">'.$add['content'].'</p>
					      </div>';
    			return json(['code'=>0,'msg'=>'操作成功','id'=>$res,'htmls'=>$htmll]);
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
    	}
    }
    public function huifu_new(){
    	$id = $this->request->param('id') ? $this->request->param('id') : '0';
    	$one = Db::name('zixun_huifu')->field(['id','pid','addtime'])->where(['id'=>$id])->find();
    	$list = Db::name('zixun_huifu')->where(['pid'=>$one['pid']])->where('addtime','gt',$one['addtime'])->order('addtime asc')->select();
    	$htmll = '';
    	foreach($list as $k=>$v){
    		if($v['types']=="huifu"){
    			$htmll .= '<div class="hflist" data-id="'.$v['id'].'">
					          <a href="javascript:void(0);" onclick="del(this,\''.$v['id'].'\')" class="del">X删除</a>
					          <span class="wen">答</span>
					          <span class="ren">'.getAdmin($v['aid']).' &nbsp;&nbsp;'.date("Y-m-d H:i:s",$v['addtime']).'</span>
					          <div class="clear"></div>
					          <p style="padding-top:15px;text-align: right;">'.$v['content'].'</p>
					      </div>';
    		}else{
    			$htmll .= '<div class="hflist" data-id="'.$v['id'].'">
					          <a href="javascript:void(0);" onclick="del(this,\''.$v['id'].'\')" class="del">X删除</a>
					          <span class="wena">追问</span>
					          <span class="rena">'.getMember($v['uid']).' &nbsp;&nbsp;'.date("Y-m-d H:i:s",$v['addtime']).'</span>
					          <div class="clear"></div>
					          <p style="padding-top:15px;text-align: left;">'.$v['content'].'</p>
					      </div>';
    		}
    	}
    	return json(['code'=>'0',"htmls"=>$htmll]);
    }
    public function huifu_del(){
    	if($this->request->isAjax()){
    		$zixunf = Db::name('zixun_huifu');
    		$post = $this->request->post();
            if(empty($post['id'])){
                return json(['code'=>2,'msg'=>'请选择要删除的回复内容']);
            }
    		$data['is_del'] = 'y';
    		$res = $zixunf->where('id','in',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'删除成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'删除失败']);
    		}
    	}
    }

	public function del(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
            if(empty($post['id'])){
                return json(['code'=>2,'msg'=>'请选择要删除的咨询']);
            }
    		$data['is_del'] = 'y';
    		$res = Db::name('zixun')->where('id','in',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'删除成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'删除失败']);
    		}
    	}
	}
}