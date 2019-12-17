<?php
namespace app\admin\controller;
use \think\Controller;
use app\admin\controller\Base;
use \think\Db;
use \think\Cookie;
use \think\Session;
use \think\Request;
class Vision extends Base{
	public function __construct(){
		parent::__construct(); //使用父类的构造方法
	}
/******************************************************/
/** 视力对照表
/******************************************************/
	public function index(){
		$where[] = ['is_del','eq','n'];
		$age = $this->request->param('age') ? $this->request->param('age') : 'all';
		if($age!="all"){
			$where[] = ['age','=',$age];
		}
		$this->assign('age',$age);
		$jieguo = $this->request->param('jieguo') ? $this->request->param('jieguo') : 'all';
		if($jieguo!="all"){
			if($jieguo=="1"){
				$jieguoa = '高危';
			}else if($jieguo=="2"){
				$jieguoa = '中危';
			}else{
				$jieguoa = '低危';
			}
			$where[] = ['jieguo','=',$jieguoa];
		}
		$this->assign('jieguo',$jieguo);

		$list = Db::name('shili_duizhao')->where($where)->order('age asc')->paginate(10);
		$count = Db::name('shili_duizhao')->field('id')->where($where)->count();
		$agelist = [];
		for($i=1;$i<=8;$i++){
			$agelist[] = $i;
		}
		$agelist = array_unique($agelist);
		$this->assign('agelist',$agelist);
		$this->assign('list',$list);
	    $this->assign('count',$count);
		return $this->fetch('vision/duizhao/index');
	}
	public function shili_publish(){
		$id  = $this->request->param('id')  ? $this->request->param('id')  : '0';
		if($this->request->isPost()){
    		$post = $this->request->post();
	       	$validate = new \think\Validate();
            $rule =   [
            	'age'      => 'require',
                'min_num'  => 'require',
                'max_num'  => 'require',
                'jieguo'   => 'require'
            ];
            $message  =   [
            	'age.require'     => '年龄不能为空',
                'min_num.require' => '最小值不能为空',
                'max_num.require' => '最大值不能为空',
                'jieguo.require'  => '请选分析结果'
            ];
            $validate->message($message);
            //验证部分数据合法性
            if (!$validate->check($post,$rule)) {
                return json(['code'=>2,'msg'=>$validate->getError(),'returnData'=>'']);die;
            }
	       	if(isset($post['id'])){
	       		$check = Db::name('shili_duizhao')->field('id')->where(['age'=>$post['age'],'jieguo'=>$post['jieguo']])->find();
	       		if($check){
	       			$res = Db::name('shili_duizhao')->where(['id'=>$check['id']])->update($post);
	       		}else{
	       			$res = Db::name('shili_duizhao')->where(['id'=>$id])->update($post);
	       		}
	       	}else{
	       		$check = Db::name('shili_duizhao')->field('id')->where(['age'=>$post['age'],'jieguo'=>$post['jieguo']])->find();
	       		if($check){
	       			$res = Db::name('shili_duizhao')->where(['id'=>$check['id']])->update($post);
	       		}else{
	       			$res = Db::name('shili_duizhao')->insert($post);
	       		}
	       	}
	       	if($res){
	       		return json(['code'=>0,'msg'=>'操作成功']);
	       	}else{
	       		return json(['code'=>1,'msg'=>'操作失败']);
	       	}
	    }else{
	    	if($id>0){
				$one = Db::name('shili_duizhao')->where('id',$id)->find();
				$this->assign('one',$one);
			}
			return $this->fetch('vision/duizhao/publish');
	    }
	}
	public function shili_del(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
            if(empty($post['id'])){
                return json(['code'=>2,'msg'=>'请选择要删除的记录']);
            }
    		$res = Db::name("shili_duizhao")->where('id','in',$post['id'])->delete();
    		if($res){
    			return json(['code'=>0,'msg'=>'删除成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'删除失败']);
    		}
    	}
	}
/******************************************************/
/** 屈光度对照表
/******************************************************/
	public function quguangdu(){
		$where[] = ['id','neq','0'];
		$age = $this->request->param('age') ? $this->request->param('age') : 'all';
		if($age!="all"){
			$where[] = ['age','=',$age];
		}
		$this->assign('age',$age);
		$jieguo = $this->request->param('jieguo') ? $this->request->param('jieguo') : 'all';
		if($jieguo!="all"){
			if($jieguo=="1"){
				$jieguoa = '高危';
			}else if($jieguo=="2"){
				$jieguoa = '中危';
			}else{
				$jieguoa = '低危';
			}
			$where[] = ['jieguo','=',$jieguoa];
		}
		$this->assign('jieguo',$jieguo);

		$list = Db::name('shili_quguang')->where($where)->order('age asc')->paginate(10);
		$count = Db::name('shili_quguang')->field('id')->where($where)->count();
		$agelist = [];
		for($i=1;$i<=8;$i++){
			$agelist[] = $i;
		}
		$agelist = array_unique($agelist);
		$this->assign('agelist',$agelist);
		$this->assign('list',$list);
	    $this->assign('count',$count);
		return $this->fetch('vision/quguang/index');
	}
	public function quguangdu_publish(){
		$id  = $this->request->param('id')  ? $this->request->param('id')  : '0';
		if($this->request->isPost()){
    		$post = $this->request->post();
			$validate = new \think\Validate();
            $rule =   [
            	'age'      => 'require',
                'min_num'  => 'require',
                'max_num'  => 'require',
                'jieguo'   => 'require'
            ];
            $message  =   [
            	'age.require'     => '年龄不能为空',
                'min_num.require' => '最小值不能为空',
                'max_num.require' => '最大值不能为空',
                'jieguo.require'  => '请选分析结果'
            ];
            $validate->message($message);
            //验证部分数据合法性
            if (!$validate->check($post,$rule)) {
                return json(['code'=>2,'msg'=>$validate->getError(),'returnData'=>'']);die;
            }
	       	if(isset($post['id'])){
	       		$check = Db::name('shili_quguang')->field('id')->where(['age'=>$post['age'],'jieguo'=>$post['jieguo']])->find();
	       		if($check){
	       			$res = Db::name('shili_quguang')->where(['id'=>$check['id']])->update($post);
	       		}else{
	       			$res = Db::name('shili_quguang')->where(['id'=>$id])->update($post);
	       		}
	       	}else{
	       		$check = Db::name('shili_quguang')->field('id')->where(['age'=>$post['age'],'jieguo'=>$post['jieguo']])->find();
	       		if($check){
	       			$res = Db::name('shili_quguang')->where(['id'=>$check['id']])->update($post);
	       		}else{
	       			$res = Db::name('shili_quguang')->insert($post);
	       		}
	       	}
	       	if($res){
	       		return json(['code'=>0,'msg'=>'操作成功']);
	       	}else{
	       		return json(['code'=>1,'msg'=>'操作失败']);
	       	}
	    }else{
	    	if($id>0){
				$one = Db::name('shili_quguang')->where('id',$id)->find();
				$this->assign('one',$one);
			}
			return $this->fetch('vision/quguang/publish');
	    }
	}
	public function quguangdu_del(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
            if(empty($post['id'])){
                return json(['code'=>2,'msg'=>'请选择要删除的记录']);
            }
    		$res = Db::name("shili_quguang")->where('id','in',$post['id'])->delete();
    		if($res){
    			return json(['code'=>0,'msg'=>'删除成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'删除失败']);
    		}
    	}
	}
/******************************************************/
/** BMI指数对照表
/******************************************************/
	public function bmi(){
		$where[] = ['id','neq',0];
		$sex = $this->request->param('sex') ? $this->request->param('sex') : 'all';
		if($sex!="all"){
			$where[] = ['sex','=',$sex];
		}
		$this->assign('sex',$sex);
		$age = $this->request->param('age') ? $this->request->param('age') : 'all';
		if($age!="all"){
			$where[] = ['age','=',$age];
		}
		$this->assign('age',$age);
		$jieguo = $this->request->param('jieguo') ? $this->request->param('jieguo') : 'all';
		if($jieguo!="all"){
			if($jieguo=="1"){
				$jieguoa = '高危';
			}else if($jieguo=="2"){
				$jieguoa = '中危';
			}else{
				$jieguoa = '低危';
			}
			$where[] = ['jieguo','=',$jieguoa];
		}
		$this->assign('jieguo',$jieguo);

		$list = Db::name('shili_bmi')->where($where)->order('age asc')->paginate(10);
		$count = Db::name('shili_bmi')->field('id')->where($where)->count();
		$agelist = [];
		for($i=1;$i<=8;$i++){
			$agelist[] = $i;
		}
		$agelist = array_unique($agelist);
		$this->assign('agelist',$agelist);
		$this->assign('list',$list);
	    $this->assign('count',$count);
		return $this->fetch('vision/bmi/index');
	}
	public function bmi_publish(){
		$id  = $this->request->param('id')  ? $this->request->param('id')  : '0';
		if($this->request->isPost()){
    		$post = $this->request->post();
			$validate = new \think\Validate();
            $rule =   [
            	'age'      => 'require',
                'min_num'  => 'require',
                'max_num'  => 'require',
                'jieguo'   => 'require'
            ];
            $message  =   [
            	'age.require'     => '年龄不能为空',
                'min_num.require' => '最小值不能为空',
                'max_num.require' => '最大值不能为空',
                'jieguo.require'  => '请选分析结果'
            ];
            $validate->message($message);
            //验证部分数据合法性
            if (!$validate->check($post,$rule)) {
                return json(['code'=>2,'msg'=>$validate->getError(),'returnData'=>'']);die;
            }
	       	if(isset($post['id'])){
	       		$check = Db::name('shili_bmi')->field('id')->where(['age'=>$post['age'],'sex'=>$post['sex'],'jieguo'=>$post['jieguo']])->find();
	       		if($check){
	       			$res = Db::name('shili_bmi')->where(['id'=>$check['id']])->update($post);
	       		}else{
	       			$res = Db::name('shili_bmi')->where(['id'=>$id])->update($post);
	       		}
	       	}else{
	       		$check = Db::name('shili_bmi')->field('id')->where(['age'=>$post['age'],'sex'=>$post['sex'],'jieguo'=>$post['jieguo']])->find();
	       		if($check){
	       			$res = Db::name('shili_bmi')->where(['id'=>$check['id']])->update($post);
	       		}else{
	       			$res = Db::name('shili_bmi')->insert($post);
	       		}
	       	}
	       	if($res){
	       		return json(['code'=>0,'msg'=>'操作成功']);
	       	}else{
	       		return json(['code'=>1,'msg'=>'操作失败']);
	       	}
	    }else{
	    	if($id>0){
				$one = Db::name('shili_bmi')->where('id',$id)->find();
				$this->assign('one',$one);
			}
			return $this->fetch('vision/bmi/publish');
	    }
	}
	public function bmi_del(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
            if(empty($post['id'])){
                return json(['code'=>2,'msg'=>'请选择要删除的记录']);
            }
    		$res = Db::name("shili_bmi")->where('id','in',$post['id'])->delete();
    		if($res){
    			return json(['code'=>0,'msg'=>'删除成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'删除失败']);
    		}
    	}
	}
/******************************************************/
/** 视力养护操
/******************************************************/
	public function yanghucao(){
		$list = Db::name('shili_yanghucao')->order("id asc,addtime desc")->select();
		$count = Db::name('shili_yanghucao')->field(['id'])->count();
	    $this->assign('count',$count);
	    $this->assign('list',$list);
    	return $this->fetch('vision/yanghucao/index');
	}
	public function yanghucao_publish(){
		$id  = $this->request->param('id')  ? $this->request->param('id')  : '0';
    	if($this->request->isPost()){
    		$post = $this->request->post();
    		$admininfo = $this->admininfo;
	       	$validate = new \think\Validate();
            $rule =   [
            	'img'      => 'require',
                'title'  => 'require'
            ];
            $message  =   [
            	'img.require'     => '请上传封面',
                'title.require' => '标题不能为空'
            ];
            $validate->message($message);
            //验证部分数据合法性
            if (!$validate->check($post,$rule)) {
                return json(['code'=>2,'msg'=>$validate->getError(),'returnData'=>'']);die;
            }
	       	if(isset($post['id'])){
				$post['updatetime'] = time();
				$post['update_admin'] = $admininfo['id'];
				$res = Db::name('shili_yanghucao')->where('id',$post['id'])->update($post);
				if($res){
		       		return json(['code'=>0,'msg'=>'操作成功']);
		       	}else{
		       		return json(['code'=>1,'msg'=>'操作失败']);
		       	}
			}else{
				$post['addtime'] = $post['updatetime'] = time();
				$post['admin_id'] = $post['update_admin'] = $admininfo['id'];
				$res = Db::name('shili_yanghucao')->insert($post);
				if($res){
		       		return json(['code'=>0,'msg'=>'操作成功']);
		       	}else{
		       		return json(['code'=>1,'msg'=>'操作失败']);
		       	}
			}
    	}else{
    		if($id>0){
				$one = Db::name('shili_yanghucao')->where('id',$id)->find();
				$this->assign('one',$one);
			}
    		return $this->fetch('vision/yanghucao/publish');
    	}
	}
	public function upload_video(){
		
	}
}