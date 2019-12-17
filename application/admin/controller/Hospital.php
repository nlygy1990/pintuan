<?php
namespace app\admin\controller;
use \think\Controller;
use app\admin\controller\Base;
use \think\Db;
use \think\Cookie;
use \think\Session;
use \think\Request;
class Hospital extends Base{
	public function __construct(){
		parent::__construct(); //使用父类的构造方法
	}
/************************************************************************/
/** 医院管理
/************************************************************************/
	public function lists(){
		$where[] = ['h.is_del','=','n'];
		$sheng = Db::name('region')->where(['region_parent_id'=>1])->order('region_order asc,region_id asc')->select();
    	$this->assign('sheng',$sheng);
		$sheng_id = $this->request->param('sheng') ? $this->request->param('sheng') : '';
		if($sheng_id!=''){
			$where[] = ['h.sheng','=',$sheng_id];
			$shi = Db::name('region')->where(['region_parent_id'=>$sheng_id])->order('region_order asc,region_id asc')->select();
			$this->assign('shi',$shi);
			$shi_id = $this->request->param('shi') ? $this->request->param('shi') : '';
			if($shi_id!=''){
				$where[] = ['h.shi','=',$shi_id];
				$qu = Db::name('region')->where(['region_parent_id'=>$shi_id])->order('region_order asc,region_id asc')->select();
				$this->assign('qu',$qu);
				$qu_id = $this->request->param('qu') ? $this->request->param('qu') : '';
				if($qu_id!=''){
					$where[] = ['h.qu','=',$qu_id];
				}
				$this->assign('qu_id',$qu_id);
			}
			$this->assign('shi_id',$shi_id);
		}
		$this->assign('sheng_id',$sheng_id);

		$admininfo = $this->admininfo;
		if($admininfo['hospital_id']>0){
			$where[] = ['h.id','=',$admininfo['hospital_id']];
		}

		$keys = $this->request->param('keys') ? $this->request->param('keys') : '';
		if($keys!=''){
			$where[] = ['h.title|h.description','like','%'.$keys.'%'];
		}
		$this->assign('keys',$keys);
		$list = Db::name('hospital')
				->field('h.id,h.logo,h.title,h.no,h.diqu,h.address,h.orders,h.is_show,s.title as level_title')
				->alias('h')
				->where($where)
				->join('hospital_level s', 's.id = h.level', 'left')
				->order('h.orders asc,h.addtime desc')->paginate(20);
		$this->assign('list',$list);
		$count = Db::name('hospital')->alias('h')->where($where)->count();
		$this->assign('count',$count);
		return $this->fetch();
	}
	public function publish(){
		$id  = $this->request->param('id')  ? $this->request->param('id')  : '0';
    	if($this->request->isPost()){
    		$post = $this->request->post();
    		$admininfo = $this->admininfo;
    		$validate = new \think\Validate();
            $rule =   [
            	'level'  => 'require',
                'title'  => 'require',
                'no'     => 'require',
                'sheng'  => 'require',
                'shi'    => 'require',
                'qu'     => 'require',
                'address'=> 'require'
            ];
            $message  =   [
            	'level.require' => '请选择医院级别',
                'title.require' => '医院名称不能为空',
                'no.require'    => '医院代码不能为空',
                'sheng.require' => '请选择所在地',
                'shi.require'   => '请选择所在地',
                'qu.require'    => '请选择所在地',
                'address.require'=> '医院地址不能为空'
            ];
            $validate->message($message);
            //验证部分数据合法性
            if (!$validate->check($post,$rule)) {
                return json(['code'=>2,'msg'=>$validate->getError(),'returnData'=>'']);die;
            }
            if(!$post['logo']){
            	return json(['code'=>3,'msg'=>'请上传LOGO','returnData'=>'']);die;
            }
            if(isset($post['pics'])){
            	$post['pics'] = implode(",",$post['pics']);
            }
    		if(empty($post['is_show'])){
	       		$post['is_show'] = 'n';
	       	}
	       	$diqu = '';
	       	if($post['sheng']>0){
	       		$cksheng = Db::name('region')->where('region_id',$post['sheng'])->find();
	       		$diqu .= $cksheng['region_name'];
	       	}else{
	       		$post['sheng'] = 0;
	       	}
	       	if($post['shi']>0){
	       		$ckshi = Db::name('region')->where('region_id',$post['shi'])->find();
	       		$diqu .= '-'.$ckshi['region_name'];
	       	}else{
	       		$post['shi'] = 0;
	       	}
	       	if($post['qu']>0){
	       		$ckqu = Db::name('region')->where('region_id',$post['qu'])->find();
	       		$diqu .= '-'.$ckqu['region_name'];
	       	}else{
	       		$post['qu'] = 0;
	       	}
	       	$post['diqu'] = $diqu;
	       	if(isset($post['id'])){
	       		//验证菜单是否存在
	            $menu = Db::name('hospital')->field('id')->where('id',$post['id'])->find();
	            if(empty($menu)) {
	            	return json(['code'=>3,'msg'=>'ID不正确','returnData'=>'']);die;
	            }
	       		$post['updatetime']   = time();
	       		$post['update_admin'] = $admininfo['id'];
                $res = Db::name('hospital')->where('id',$post['id'])->update($post);
	       		if($res) {
	            	return json(['code'=>0,'msg'=>'修改成功','returnData'=>'']);die;
	        	} else {
	            	return json(['code'=>4,'msg'=>'修改失败','returnData'=>'']);die;
	       		}
	       	}else{
	       		$post['addtime']  = $post['updatetime']   = time();
	       		$post['admin_id'] = $post['update_admin'] = $admininfo['id'];
                $res = Db::name('hospital')->insert($post);
	       		if($res) {
                    return json(['code'=>0,'msg'=>'添加成功','returnData'=>'']);die;
                } else {
                    return json(['code'=>5,'msg'=>'添加失败','returnData'=>'']);die;
                }
	       	}
    	}else{
    		$sheng = Db::name('region')->where(['region_parent_id'=>1])->order('region_order asc,region_id asc')->select();
    		$this->assign('sheng',$sheng);
    		$level = Db::name('hospital_level')->order('orders asc')->select();
    		$this->assign('level',$level);
    		$one = Db::name('hospital')->where('id',$id)->find();
    		if($one){
    			if($one['sheng']>0){
    				$shi = Db::name('region')->where(['region_parent_id'=>$one['sheng']])->order('region_order asc,region_id asc')->select();
    				$this->assign('shi',$shi);
    			}
    			if($one['shi']>0){
    				$qu = Db::name('region')->where(['region_parent_id'=>$one['shi']])->order('region_order asc,region_id asc')->select();
    				$this->assign('qu',$qu);
    			}
    		}
    		$this->assign('one',$one);
    		return $this->fetch();
    	}
	}
	public function shows(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
    		if($post['va']=='n'){
    			$va = 'y';
    		}else{
    			$va = 'n';
    		}
    		$data['is_show'] = $va;
    		$res = Db::name('hospital')->where('id',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'操作成功','va'=>$va]);
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
    	}
	}
	public function orders(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
    		$data['orders'] = $post['va'];
    		$res = Db::name('hospital')->where('id',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'操作成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
    	}
	}
	public function del(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
            if(empty($post['id'])){
                return json(['code'=>2,'msg'=>'请选择要删除的医院']);
            }
    		$data['is_del'] = 'y';
    		$res = Db::name('hospital')->where('id','in',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'删除成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'删除失败']);
    		}
    	}
	}
}