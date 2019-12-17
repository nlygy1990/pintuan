<?php
namespace app\admin\controller;
use \think\Controller;
use app\admin\controller\Base;
use \think\Db;
use \think\Cookie;
use \think\Session;
use \think\Request;
class Goods extends Base{
	public function __construct(){
		parent::__construct(); //使用父类的构造方法
	}

	public function index(){

	}
/************************************************************************/
/** 商品管理
/************************************************************************/
	public function lists(){
		$where[] = ['g.is_del','=','n'];
		$admininfo = $this->admininfo;
		$keys = $this->request->param('keys') ? $this->request->param('keys') : '';
		if($keys!=''){
			$where[] = ['g.title|g.id|g.keywords|g.description','like','%'.$keys.'%'];
		}
		$this->assign('keys',$keys);

		if($admininfo['shop_id']>0){
			$where[] = ['g.storeid','=',$admininfo['shop_id']];
		}
		$list = Db::name('goods')->alias('g')
				->field('g.id,g.title,gc.title as cate_title,g.thumb,g.tags,g.sales,g.marketprice,g.hasoption,g.total,g.is_show')
				->where($where)
				->join('goods_cate gc', 'gc.id=g.pid', 'left')
				->order('g.addtime desc')
				->paginate(20);
		$this->assign('list',$list);
		$count = $list = Db::name('goods')->alias('g')->where($where)->count();
		$this->assign('count',$count);
		return $this->fetch();
	}
	public function publish(){
		$id  = $this->request->param('id')  ? $this->request->param('id')  : '0';
		$admininfo = $this->admininfo;
    	if($this->request->isPost()){
    		$post = $this->request->post();
    		$validate = new \think\Validate();
            $rule =   [
                'title'  => 'require'
            ];
            $message  =   [
                'title.require' => '商品标题不能为空'
            ];
            $validate->message($message);
            //验证部分数据合法性
            if (!$validate->check($post,$rule)) {
                return json(['code'=>2,'msg'=>$validate->getError(),'returnData'=>'']);die;
            }
            if(!$post['thumb']){
            	return json(['code'=>3,'msg'=>'请上传封面','returnData'=>'']);die;
            }
            if(isset($post['tags'])){
            	$post['tags'] = implode(",",$post['tags']);
            }
            if(isset($post['pics'])){
            	$post['pics'] = implode(",",$post['pics']);
            }
            $post['goodsprice']  = $post['goodsprice'] ? $post['goodsprice'] : '0';
            $post['marketprice'] = $post['marketprice'] ? $post['marketprice'] : '0';
            $post['costprice']   = $post['costprice'] ? $post['costprice'] : '0';
            $post['ispresell']   = isset($post['ispresell']) ? $post['ispresell'] : 0;
            $post['presellprice'] = isset($post['presellprice']) ? $post['presellprice'] : 0;
            $post['ispostage']    = isset($post['ispostage']) ? $post['ispostage'] : 0;
            $post['postagetype']  = $post['postagetype'] ? $post['postagetype'] : 0;
            $post['postageprice'] = $post['postageprice'] ? $post['postageprice'] : 0;
            $post['postageid']    = $post['postageid'] ? $post['postageid'] : 0;
            $post['ednum']        = $post['ednum'] ? $post['ednum'] : 0;
            $post['edmoney']      = $post['edmoney'] ? $post['edmoney'] : 0;
            $post['warehouse_id'] = isset($post['warehouse_id']) ? $post['warehouse_id'] : 0;
            $post['cash']         = isset($post['cash']) ? $post['cash'] : 0;
            $post['starttime']    = $post['starttime'] ? $post['starttime'] : 0;
            $post['endtime']      = $post['endtime'] ? $post['endtime'] : 0;
            $post['autoreceive']  = $post['autoreceive'] ? $post['autoreceive'] : 0;
            $post['autocancle']   = $post['autocancle'] ? $post['autocancle'] : 0;
            $post['total']        = $post['total'] ? $post['total'] : 0;
  			//redis 记录库存缓存
            $post['sales']        = $post['sales'] ? $post['sales'] : 0;
            $post['showtotal']    = $post['showtotal'] ? $post['showtotal'] : 0;
            $post['showsales']    = $post['showsales'] ? $post['showsales'] : 0;
            $post['totalcnf']     = $post['totalcnf'] ? $post['totalcnf'] : 0;
            $post['hasoption']    = isset($post['hasoption']) ? $post['hasoption'] : 0;
            $post['j_weight']     = $post['j_weight'] ? $post['j_weight'] : 0;
            $post['m_weight']     = $post['m_weight'] ? $post['m_weight'] : 0;
            $post['minbuy']       = $post['minbuy'] ? $post['minbuy'] : 0;
            $post['maxbuy']       = $post['maxbuy'] ? $post['maxbuy'] : 0;
            $post['totalbuy']     = $post['totalbuy'] ? $post['totalbuy'] : 0;
            $post['isdistribution'] = $post['isdistribution'] ? $post['isdistribution'] : 0;
            $post['distribution'] = $post['distribution'] ? $post['distribution'] : 0;
            $post['distribution_2'] = $post['distribution_2'] ? $post['distribution_2'] : 0;
            $post['distribution_3'] = $post['distribution_3'] ? $post['distribution_3'] : 0;
            $post['storeid']      = $post['storeid'] ? $post['storeid'] : 0;
            $post['fan_jifen']    = $post['fan_jifen'] ? $post['fan_jifen'] : 0;
            $post['fan_yue']      = $post['fan_yue'] ? $post['fan_yue'] : 0;
            $post['status']       = 0;
            if(empty($post['is_show'])){
	       		$post['is_show'] = 'n';
	       	}
	       	if(isset($post['cates'])){
	       		$post['cates'] = implode(',',$post['cates']);
	       	}else{
	       		$post['cates'] = 0;
	       	}
	       	if(isset($post['usergroupread'])){
	       		$post['usergroupread'] = implode(',',$post['usergroupread']);
	       	}else{
	       		$post['usergroupread'] = 0;
	       	}
	       	if(isset($post['usergroupbuy'])){
	       		$post['usergroupbuy'] = implode(',',$post['usergroupbuy']);
	       	}else{
	       		$post['usergroupbuy'] = 0;
	       	}
	       	if(isset($post['edareas'])){
	       		$post['edareas'] = implode(',',$post['edareas']);
	       	}else{
	       		$post['edareas'] = 0;
	       	}

	       	$diqu = '';
	       	if($post['provance']>0){
	       		$cksheng = Db::name('region')->where('region_id',$post['provance'])->find();
	       		$diqu .= $cksheng['region_name'];
	       	}else{
	       		$post['provance'] = 0;
	       	}
	       	if($post['city']>0){
	       		$ckshi = Db::name('region')->where('region_id',$post['city'])->find();
	       		$diqu .= '-'.$ckshi['region_name'];
	       	}else{
	       		$post['city'] = 0;
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
	            $menu = Db::name('goods')->field('id')->where('id',$post['id'])->find();
	            if(empty($menu)) {
	            	return json(['code'=>3,'msg'=>'ID不正确','returnData'=>'']);die;
	            }
	       		$post['updatetime']   = time();
	       		$post['update_admin'] = $admininfo['id'];
                $res = Db::name('goods')->where('id',$post['id'])->update($post);
	       		if($res) {
	            	return json(['code'=>0,'msg'=>'修改成功','returnData'=>'']);die;
	        	} else {
	            	return json(['code'=>4,'msg'=>'修改失败','returnData'=>'']);die;
	       		}
	       	}else{
	       		$post['addtime']  = $post['updatetime']   = time();
	       		$post['admin_id'] = $post['update_admin'] = $admininfo['id'];
                $res = Db::name('goods')->insert($post);
	       		if($res) {
                    return json(['code'=>0,'msg'=>'添加成功','returnData'=>'']);die;
                } else {
                    return json(['code'=>5,'msg'=>'添加失败','returnData'=>'']);die;
                }
	       	}
    	}else{
    		$pid = 0;
    		$sheng = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>1])->order('region_order asc,region_id asc')->select();
    		$this->assign('sheng',$sheng);
	    	if($id>0){
	    		$one = Db::name('goods')->where(['id'=>$id])->find();
	    		if($one['tags']){
	    			$one['tags'] = explode(",",$one['tags']);
	    		}
	    		if($one['pics']){
	    			$one['pics'] = explode(",",$one['pics']);
	    		}
	    		if($one['cates']){
	    			$one['cates'] = explode(",", $one['cates']);
	    		}
	    		if($one['usergroupread']){
	    			$one['usergroupread'] = explode(",", $one['usergroupread']);
	    		}
	    		if($one['usergroupbuy']){
	    			$one['usergroupbuy'] = explode(",", $one['usergroupbuy']);
	    		}
	    		if($one['edareas']){
	    			$one['edareas'] = explode(",", $one['edareas']);
	    		}
	    		$this->assign('one',$one);
	    		$pid = $one['pid'];

	    		if($one['provance']>0){
    				$shi = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>$one['provance']])->order('region_order asc,region_id asc')->select();
    				$this->assign('shi',$shi);
    			}
    			if($one['city']>0){
    				$qu = Db::name('region')->field('region_id,region_name')->where(['region_parent_id'=>$one['city']])->order('region_order asc,region_id asc')->select();
    				$this->assign('qu',$qu);
    			}
	    	}
	    	$catelist = Db::name('goods_cate')->field(['id','title'])->where(['pid'=>'0','is_del'=>'n'])->select();
	    	foreach($catelist as $k=>$v){
	    		$child = Db::name('goods_cate')->field(['id','title'])->where(['pid'=>$v['id'],'is_del'=>'n'])->order('orders asc,addtime asc')->select();
	    		foreach($child as $key=>$val){
	    			$childa = Db::name('goods_cate')->field(['id','title'])->where(['pid'=>$val['id'],'is_del'=>'n'])->order('orders asc,addtime asc')->select();
	    			$child[$key]['childa'] = $childa;
	    		}
	    		$catelist[$k]['child'] = $child;
	    	}
	    	if($admininfo['shop_id']>0){
	    		$shop = Db::name('shop')->field('id,title,description,logo')->where(['id'=>$admininfo['shop_id']])->order('orders asc,addtime desc')->select();
	    		$catelista = Db::name('goods_cate')->field(['id','title'])->where(['pid'=>'0','is_del'=>'n','shop_id'=>$admininfo['shop_id']])->select();
		    	foreach($catelista as $k=>$v){
		    		$child = Db::name('goods_cate')->field(['id','title'])->where(['pid'=>$v['id'],'is_del'=>'n'])->order('orders asc,addtime asc')->select();
		    		foreach($child as $key=>$val){
		    			$childa = Db::name('goods_cate')->field(['id','title'])->where(['pid'=>$val['id'],'is_del'=>'n'])->order('orders asc,addtime asc')->select();
		    			$child[$key]['childa'] = $childa;
		    		}
		    		$catelista[$k]['child'] = $child;
		    	}
		    	$this->assign('catelista',$catelista);

	    	}else{
	    		$shop = Db::name('shop')->field('id,title')->where(['is_del'=>'n','is_show'=>'y'])->order('orders asc,addtime desc')->select();
	    	}
	    	$this->assign('shop',$shop);
	    	$usercate = Db::name('member_cate')->where('is_del','n')->order('orders asc')->select();
	    	$this->assign('usercate',$usercate);
	    	$this->assign('catelist',$catelist);
	    	$this->assign('pid',$pid);
	    	$chengshi = Db::name('region')->field('region_id,region_name')->where('region_type','2')->order('region_order asc,region_id asc')->select();
	    	$this->assign('chengshi',$chengshi);

	    	$fenxiao = Db::name('sale_fenxiao')->where('id',1)->find();
			$this->assign('fenxiao',$fenxiao);
    		return $this->fetch();
    	}
	}
	public function yulan(){
		echo '无预览权限';
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
    		$res = Db::name('goods')->where('id',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'操作成功','va'=>$va]);
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
    	}
	}
	public function del(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
            if(empty($post['id'])){
                return json(['code'=>2,'msg'=>'请选择要删除的商品']);
            }
    		$data['is_del'] = 'y';
    		$res = Db::name('goods')->where('id','in',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'删除成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'删除失败']);
    		}
    	}
	}
	public function options(){
		$id  = $this->request->param('id')  ? $this->request->param('id')  : '0';
		if($this->request->isPost()){
			$post = $this->request->post();
			$validate = new \think\Validate();
            $rule =   [
                'total'  => 'require',
                'marketprice' => 'require',
                'goodsprice' => 'require',
                'costprice' => 'require'
            ];
            $message  =   [
                'total.require' => '库存不能为空',
                'marketprice.require' => '售价不能为空',
                'goodsprice.require' => '原价不能为空',
                'costprice.require' => '成本价不能为空'
            ];
            $validate->message($message);
            //验证部分数据合法性
            foreach($post['item'] as $k=>$v){
            	if (!$validate->check($v,$rule)) {
                	return json(['code'=>2,'msg'=>$validate->getError(),'returnData'=>'']);die;
            	}
            }
            //先删掉旧的
            $dels = Db::name('goods_options_item_desc')->where(['goodsid'=>$post['goodsid']])->delete();
            $res = Db::name('goods_options_item_desc')->insertAll($post['item']);
            if($res){
            	return json(['code'=>0,'msg'=>'保存成功','returnData'=>'']);die;
            }else{
            	return json(['code'=>5,'msg'=>'保存失败','returnData'=>'']);die;
            }
		}else{
			$lista = Db::name('goods_options')->where(['goodsid'=>$id,'is_del'=>'n'])->order('orders asc,id desc')->select();
			$desc_item = [];
			if($lista){
				$desc = [];
				$desca = [];
				foreach($lista as $k=>$v){
					$childa = Db::name('goods_options_item')->where(['goodsid'=>$id,'optionid'=>$v['id'],'is_del'=>'n'])->count();
					if($childa>0){
						$list[] = $v;
					}
					$child = Db::name('goods_options_item')->field('is_del',true)->where(['goodsid'=>$id,'optionid'=>$v['id'],'is_del'=>'n'])->order('orders asc,id desc')->select();
					$lista[$k]['child'] = $child;
				}
				foreach($list as $k=>$v){
					$child = Db::name('goods_options_item')->field('id,title')->where(['goodsid'=>$id,'optionid'=>$v['id'],'is_del'=>'n'])->order('orders asc,id desc')->select();
					foreach($child as $ka=>$va){
						$desc[$k][]  = $va['id'];
						$desca[$k][] = $va['title'];
					}
					$list[$k]['child'] = $child;
				}
				if($desc){
					$dea  = getArrSet($desc);
				}else{
					$dea = [];
				}
				if($desca){
					$deaa = getArrSet($desca);
				}else{
					$deaa = [];
				}
				foreach($dea as $k=>$v){
					$desc_item[$k]['desc'] = implode("_",$v);
					foreach($deaa as $ka=>$va){
						if($ka==$k){
							$desc_item[$k]['desc_title'] = implode("_",$va);
						}
					}
				}
			}
			foreach($desc_item as $k=>$v){
				$descs = Db::name('goods_options_item_desc')->where(['desc'=>$v['desc'],'goodsid'=>$id])->find();
				$descs['desc_title'] = $v['desc_title'];
				$descs['desc']       = $v['desc'];
				$desc_item[$k] = $descs;
			}
			$one = Db::name('goods')->field('id,title')->where('id',$id)->find();
			$this->assign('one',$one);
			$this->assign('desc_item',$desc_item);
			$this->assign('list',$lista);
			return $this->fetch();
		}
	}
	public function options_publish(){
		$id  = $this->request->param('id')  ? $this->request->param('id')  : '0';
		if($this->request->isPost()){
			$post = $this->request->post();
			$admininfo = $this->admininfo;
			$validate = new \think\Validate();
            $rule =   [
                'title'  => 'require'
            ];
            $message  =   [
                'title.require' => '规格名称不能为空'
            ];
            $validate->message($message);
            //验证部分数据合法性
            if (!$validate->check($post,$rule)) {
                return json(['code'=>2,'msg'=>$validate->getError(),'returnData'=>'']);die;
            }
	       	if(empty($post['is_show'])){
	       		$post['is_show'] = 'n';
	       	}
	       	if(isset($post['id'])){
	       		//验证菜单是否存在
	            $menu = Db::name('goods_options')->where('id',$post['id'])->find();
	            if(empty($menu)) {
	            	return json(['code'=>3,'msg'=>'ID不正确','returnData'=>'']);die;
	            }
	       		$post['updatetime']   = time();
	       		$post['update_admin'] = $admininfo['id'];
                $res = Db::name('goods_options')->where('id',$post['id'])->update($post);
	       		if($res) {
	            	return json(['code'=>0,'msg'=>'修改成功','returnData'=>'']);die;
	        	} else {
	            	return json(['code'=>4,'msg'=>'修改失败','returnData'=>'']);die;
	       		}
	       	}else{
	       		$post['addtime']  = $post['updatetime']   = time();
	       		$post['admin_id'] = $post['update_admin'] = $admininfo['id'];
                $res = Db::name('goods_options')->insert($post);
	       		if($res) {
                    return json(['code'=>0,'msg'=>'添加成功','returnData'=>'']);die;
                } else {
                    return json(['code'=>5,'msg'=>'添加失败','returnData'=>'']);die;
                }
	       	}
		}else{
			$one = Db::name('goods_options')->where(['id'=>$id])->find();
			if($one){
				$pid = $one['goodsid'];
			}else{
				$pid   = $this->request->param('pid')  ? $this->request->param('pid')  : '0';
			}
			$this->assign('one',$one);
			$this->assign('pid',$pid);
			return $this->fetch();
		}
	}
	public function options_item_publish(){
		$id  = $this->request->param('id')  ? $this->request->param('id')  : '0';
		if($this->request->isPost()){
			$post = $this->request->post();
			$admininfo = $this->admininfo;
			$validate = new \think\Validate();
            $rule =   [
                'title'  => 'require'
            ];
            $message  =   [
                'title.require' => '规格名称不能为空'
            ];
            $validate->message($message);
            //验证部分数据合法性
            if (!$validate->check($post,$rule)) {
                return json(['code'=>2,'msg'=>$validate->getError(),'returnData'=>'']);die;
            }
	       	if(empty($post['is_show'])){
	       		$post['is_show'] = 'n';
	       	}
	       	if(isset($post['id'])){
	       		//验证菜单是否存在
	            $menu = Db::name('goods_options_item')->where('id',$post['id'])->find();
	            if(empty($menu)) {
	            	return json(['code'=>3,'msg'=>'ID不正确','returnData'=>'']);die;
	            }
	       		$post['updatetime']   = time();
	       		$post['update_admin'] = $admininfo['id'];
                $res = Db::name('goods_options_item')->where('id',$post['id'])->update($post);
	       		if($res) {
	            	return json(['code'=>0,'msg'=>'修改成功','returnData'=>'']);die;
	        	} else {
	            	return json(['code'=>4,'msg'=>'修改失败','returnData'=>'']);die;
	       		}
	       	}else{
	       		$post['addtime']  = $post['updatetime']   = time();
	       		$post['admin_id'] = $post['update_admin'] = $admininfo['id'];
                $res = Db::name('goods_options_item')->insert($post);
	       		if($res) {
                    return json(['code'=>0,'msg'=>'添加成功','returnData'=>'']);die;
                } else {
                    return json(['code'=>5,'msg'=>'添加失败','returnData'=>'']);die;
                }
	       	}
		}else{
			$one = Db::name('goods_options_item')->where(['id'=>$id])->find();
			if($one){
				$pid = $one['goodsid'];
				$pid1 = $one['optionid'];
			}else{
				$pid   = $this->request->param('pid')  ? $this->request->param('pid')  : '0';
				$pid1  = $this->request->param('pid1')  ? $this->request->param('pid1')  : '0';
			}
			$this->assign('one',$one);
			$this->assign('pid',$pid);
			$this->assign('pid1',$pid1);
			return $this->fetch();
		}
	}
	public function options_shows(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
    		if($post['va']=='n'){
    			$va = 'y';
    		}else{
    			$va = 'n';
    		}
    		$data['is_show'] = $va;
    		$res = Db::name('goods_'.$post['tb'])->where('id',$post['id'])->update($data);
    		if($post['tb']=="options"){
    			$resa = Db::name('goods_options_item')->where('optionid',$post['id'])->update($data);
    		}
    		if($res){
    			return json(['code'=>0,'msg'=>'操作成功','va'=>$va]);
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
    	}
	}
	public function options_order(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
    		$data['orders'] = $post['va'];
    		$res = Db::name('goods_'.$post['tb'])->where('id',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'操作成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
    	}
	}
	public function options_del(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
            if(empty($post['id'])){
                return json(['code'=>2,'msg'=>'请选择要删除的规格']);
            }
    		$data['is_del'] = 'y';
    		$res = Db::name('goods_'.$post['tb'])->where(['id'=>['in',$post['id']]])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'删除成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'删除失败']);
    		}
    	}
	}
	public function options_delall(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
    		if(empty($post['id'])){
                return json(['code'=>2,'msg'=>'请选择要删除的规格']);
            }
    		$data['is_del'] = 'y';
    		foreach($post['id'] as $k=>$v){
    			$dd = explode("-",$v);
    			$resc = Db::name('goods_'.$dd[1])->where('id',$dd[0])->update($data);
    		}
    		return json(['code'=>0,'msg'=>'删除成功']);
    	}
	}
/************************************************************************/
/** 商品类型
/************************************************************************/
	public function cate(){
		$admininfo = $this->admininfo;
		$where['pid']    = '0';
		$where['is_del'] = 'n';
		if($admininfo['shop_id']=='0' && $admininfo['stores_id']=='0'){
			$where['shop_id']   = 0;
			$where['stores_id'] = 0;
		}else if($admininfo['shop_id']>'0' && $admininfo['stores_id']=='0'){
			$where['shop_id']   = $admininfo['shop_id'];
			$where['stores_id'] = 0;
		}else if($admininfo['shop_id']=='0' && $admininfo['stores_id']>'0'){
			$where['shop_id']   = 0;
			$where['stores_id'] = $admininfo['stores_id'];
		}else{
			$where['shop_id']   = $admininfo['shop_id'];
			$where['stores_id'] = $admininfo['stores_id'];
		}
		$list = Db::name('goods_cate')->where($where)->order('orders asc,addtime desc')->select();
		foreach($list as $k=>$v){
			$child = DB::name('goods_cate')->where(['pid'=>$v['id'],'is_del'=>'n'])->order('orders asc,addtime asc')->select();
    		foreach($child as $key=>$val){
    			$childa = DB::name('goods_cate')->where(['pid'=>$val['id'],'is_del'=>'n'])->order('orders asc,addtime asc')->select();
    			$child[$key]['childa'] = $childa;
    		}
    		$list[$k]['child'] = $child;
		}
		$this->assign('list',$list);
		$count = count($list);
		$this->assign('count',$count);
		return $this->fetch();
	}
	public function cate_publish(){
		$pid = $this->request->param('pid') ? $this->request->param('pid') : '0';
    	$id  = $this->request->param('id')  ? $this->request->param('id')  : '0';
    	if($this->request->isPost()){
    		$post = $this->request->post();
    		$admininfo = $this->admininfo;
    		$validate = new \think\Validate();
            $rule =   [
                'title'  => 'require'
            ];
            $message  =   [
                'title.require' => '类型名不能为空'
            ];
            $validate->message($message);
            //验证部分数据合法性
            if (!$validate->check($post,$rule)) {
                return json(['code'=>2,'msg'=>$validate->getError(),'returnData'=>'']);die;
            }
	       	if(empty($post['is_show'])){
	       		$post['is_show'] = 'n';
	       	}
	       	$post['shop_id']   = $admininfo['shop_id'];
	       	$post['stores_id'] = $admininfo['stores_id'];
	       	if(isset($post['id'])){
	       		//验证菜单是否存在
	            $menu = Db::name('goods_cate')->where('id',$post['id'])->find();
	            if(empty($menu)) {
	            	return json(['code'=>3,'msg'=>'ID不正确','returnData'=>'']);die;
	            }
	       		$post['updatetime']   = time();
	       		$post['update_admin'] = $admininfo['id'];
                $res = Db::name('goods_cate')->where('id',$post['id'])->update($post);
	       		if($res) {
	            	return json(['code'=>0,'msg'=>'修改成功','returnData'=>'']);die;
	        	} else {
	            	return json(['code'=>4,'msg'=>'修改失败','returnData'=>'']);die;
	       		}
	       	}else{
	       		$post['addtime']  = $post['updatetime']   = time();
	       		$post['admin_id'] = $post['update_admin'] = $admininfo['id'];
                $res = Db::name('goods_cate')->insert($post);
	       		if($res) {
                    return json(['code'=>0,'msg'=>'添加成功','returnData'=>'']);die;
                } else {
                    return json(['code'=>5,'msg'=>'添加失败','returnData'=>'']);die;
                }
	       	}
    	}else{
	    	if($id>0){
	    		$one = Db::name('goods_cate')->where(['id'=>$id])->find();
	    		$this->assign('one',$one);
	    	}
	    	$tylist = Db::name('goods_cate')->field(['id','title'])->where(['pid'=>'0','is_del'=>'n'])->select();
	    	foreach($tylist as $k=>$v){
	    		$tylist[$k]['child'] = Db::name('goods_cate')->field(['id','title'])->where(['pid'=>$v['id'],'is_del'=>'n'])->select();
	    	}
	    	$this->assign('tylist',$tylist);
	    	$this->assign('pid',$pid);
	    	return $this->fetch();
	    }
	}
	public function cate_show(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
    		if($post['va']=='n'){
    			$va = 'y';
    		}else{
    			$va = 'n';
    		}
    		$data['is_show'] = $va;
    		$res = Db::name('goods_cate')->where('id',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'操作成功','va'=>$va]);
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
    	}
	}
	public function cate_del(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
            if(empty($post['id'])){
                return json(['code'=>2,'msg'=>'请选择要删除的类型']);
            }
    		$data['is_del'] = 'y';
    		$res = Db::name('goods_cate')->where(['id'=>['in',$post['id']]])->save($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'删除成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'删除失败']);
    		}
    	}
	}
	public function cate_order(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
    		$data['orders'] = $post['va'];
    		$res = Db::name('goods_cate')->where('id',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'操作成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
    	}
	}
}