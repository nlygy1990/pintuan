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
class Groups extends Base{
	public function __construct(){
		parent::__construct(); //使用父类的构造方法
	}

	public function index(){

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
		$list = Db::name('groups_goods_cate')->where($where)->order('orders asc,addtime desc')->select();
		foreach($list as $k=>$v){
			$child = DB::name('groups_goods_cate')->where(['pid'=>$v['id'],'is_del'=>'n'])->order('orders asc,addtime asc')->select();
    		foreach($child as $key=>$val){
    			$childa = DB::name('groups_goods_cate')->where(['pid'=>$val['id'],'is_del'=>'n'])->order('orders asc,addtime asc')->select();
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
	            $menu = Db::name('groups_goods_cate')->where('id',$post['id'])->find();
	            if(empty($menu)) {
	            	return json(['code'=>3,'msg'=>'ID不正确','returnData'=>'']);die;
	            }
	       		$post['updatetime']   = time();
	       		$post['update_admin'] = $admininfo['id'];
                $res = Db::name('groups_goods_cate')->where('id',$post['id'])->update($post);
	       		if($res) {
	            	return json(['code'=>0,'msg'=>'修改成功','returnData'=>'']);die;
	        	} else {
	            	return json(['code'=>4,'msg'=>'修改失败','returnData'=>'']);die;
	       		}
	       	}else{
	       		$post['addtime']  = $post['updatetime']   = time();
	       		$post['admin_id'] = $post['update_admin'] = $admininfo['id'];
                $res = Db::name('groups_goods_cate')->insert($post);
	       		if($res) {
                    return json(['code'=>0,'msg'=>'添加成功','returnData'=>'']);die;
                } else {
                    return json(['code'=>5,'msg'=>'添加失败','returnData'=>'']);die;
                }
	       	}
    	}else{
	    	if($id>0){
	    		$one = Db::name('groups_goods_cate')->where(['id'=>$id])->find();
	    		$this->assign('one',$one);
	    	}
	    	$tylist = Db::name('groups_goods_cate')->field(['id','title'])->where(['pid'=>'0','is_del'=>'n'])->select();
	    	foreach($tylist as $k=>$v){
	    		$tylist[$k]['child'] = Db::name('groups_goods_cate')->field(['id','title'])->where(['pid'=>$v['id'],'is_del'=>'n'])->select();
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
    		$res = Db::name('groups_goods_cate')->where('id',$post['id'])->update($data);
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
    		$res = Db::name('groups_goods_cate')->where(['id'=>['in',$post['id']]])->save($data);
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
    		$res = Db::name('groups_goods_cate')->where('id',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'操作成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
    	}
	}
/************************************************************************/
/** 商品管理
/************************************************************************/
	public function goods(){
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
		$list = Db::name('groups_goods')->alias('g')
				->field('g.id,g.title,gc.title as cate_title,g.thumb,g.tags,g.sales,g.marketprice,g.groupsprice,g.hasoption,g.total,g.is_show')
				->where($where)
				->join('groups_goods_cate gc', 'gc.id=g.pid', 'left')
				->order('g.addtime desc')
				->paginate(10,false,['query' => request()->param()]);
		$this->assign('list',$list);
		$count = $list = Db::name('groups_goods')->alias('g')->where($where)->count();
		$this->assign('count',$count);

		return $this->fetch();
	}

	public function goods_publish(){
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
            $post['groupsprice'] = $post['groupsprice'] ? $post['groupsprice'] : '0';
            $post['goodsprice']  = $post['goodsprice'] ? $post['goodsprice'] : '0';
            $post['marketprice'] = $post['marketprice'] ? $post['marketprice'] : '0';
            $post['costprice']   = $post['costprice'] ? $post['costprice'] : '0';
            $post['groupsnum']   = $post['groupsnum'] ? $post['groupsnum'] : 0;
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
            $post['isdistribution'] = isset($post['isdistribution']) ? $post['isdistribution'] : 0;
            $post['distribution'] = isset($post['distribution']) ? $post['distribution'] : 0;
            $post['distribution_2'] = isset($post['distribution_2']) ? $post['distribution_2'] : 0;
            $post['distribution_3'] = isset($post['distribution_3']) ? $post['distribution_3'] : 0;
            $post['tuanyuan_jiangli'] = isset($post['tuanyuan_jiangli']) ? $post['tuanyuan_jiangli'] : 0;
            $post['tuanzhang_jiangli'] = isset($post['tuanzhang_jiangli']) ? $post['tuanzhang_jiangli'] : 0;
            $post['storeid']      = isset($post['storeid']) ? $post['storeid'] : 0;
            $post['fan_jifen']    = isset($post['fan_jifen']) ? $post['fan_jifen'] : 0;
            $post['fan_yue']      = isset($post['fan_yue']) ? $post['fan_yue'] : 0;
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
	            $menu = Db::name('groups_goods')->field('id')->where('id',$post['id'])->find();
	            if(empty($menu)) {
	            	return json(['code'=>3,'msg'=>'ID不正确','returnData'=>'']);die;
	            }
	       		$post['updatetime']   = time();
	       		$post['update_admin'] = $admininfo['id'];
                $res = Db::name('groups_goods')->where('id',$post['id'])->update($post);
	       		if($res) {
	       			//redis 记录库存缓存
	       			redis()->set('groups_'.$post['id'],$post['total']);
	            	return json(['code'=>0,'msg'=>'修改成功','returnData'=>'']);die;
	        	} else {
	            	return json(['code'=>4,'msg'=>'修改失败','returnData'=>'']);die;
	       		}
	       	}else{
	       		$post['addtime']  = $post['updatetime']   = time();
	       		$post['admin_id'] = $post['update_admin'] = $admininfo['id'];
                $res = Db::name('groups_goods')->insertGetId($post);
	       		if($res) {
	       			//redis 记录库存缓存
	       			redis()->set('groups_'.$res,$post['total']);
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
	    		$one = Db::name('groups_goods')->where(['id'=>$id])->find();
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

	public function goods_show(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
    		if($post['va']=='n'){
    			$va = 'y';
    		}else{
    			$va = 'n';
    		}
    		$data['is_show'] = $va;
    		$res = Db::name('groups_goods')->where('id',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'操作成功','va'=>$va]);
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
    	}
	}
	public function goods_del(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
            if(empty($post['id'])){
                return json(['code'=>2,'msg'=>'请选择要删除的商品']);
            }
    		$data['is_del'] = 'y';
    		$res = Db::name('groups_goods')->where('id','in',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'删除成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'删除失败']);
    		}
    	}
	}
	public function goods_options(){
		$id  = $this->request->param('id')  ? $this->request->param('id')  : '0';
		if($this->request->isPost()){
			$post = $this->request->post();
			$validate = new \think\Validate();
            $rule =   [
                'total'  => 'require',
                'groupsprice' => 'require',
                'marketprice' => 'require',
                'goodsprice' => 'require',
                // 'costprice' => 'require'
            ];
            $message  =   [
                'total.require' => '库存不能为空',
                'groupsprice.require' => '团购价不能为空',
                'marketprice.require' => '单购价不能为空',
                'goodsprice.require' => '原价不能为空',
                // 'costprice.require' => '成本价不能为空'
            ];
            $validate->message($message);
            //验证部分数据合法性
            foreach($post['item'] as $k=>$v){
            	if (!$validate->check($v,$rule)) {
                	return json(['code'=>2,'msg'=>$validate->getError(),'returnData'=>'']);die;
            	}
            	//redis 记录库存缓存
	       		redis()->set('groups_goods_'.$v['desc'],$v['total']);
            }
            //先删掉旧的
            $dels = Db::name('groups_goods_options_item_desc')->where(['goodsid'=>$post['goodsid']])->delete();
            $res = Db::name('groups_goods_options_item_desc')->insertAll($post['item']);
            if($res){
            	return json(['code'=>0,'msg'=>'保存成功','returnData'=>'']);die;
            }else{
            	return json(['code'=>5,'msg'=>'保存失败','returnData'=>'']);die;
            }
		}else{
			$lista = Db::name('groups_goods_options')->where(['goodsid'=>$id,'is_del'=>'n'])->order('orders asc,id desc')->select();
			$desc_item = [];
			if($lista){
				$desc = [];
				$desca = [];
				foreach($lista as $k=>$v){
					$childa = Db::name('groups_goods_options_item')->where(['goodsid'=>$id,'optionid'=>$v['id'],'is_del'=>'n'])->count();
					if($childa>0){
						$list[] = $v;
					}
					$child = Db::name('groups_goods_options_item')->field('is_del',true)->where(['goodsid'=>$id,'optionid'=>$v['id'],'is_del'=>'n'])->order('orders asc,id desc')->select();
					$lista[$k]['child'] = $child;
				}
				foreach($list as $k=>$v){
					$child = Db::name('groups_goods_options_item')->field('id,title')->where(['goodsid'=>$id,'optionid'=>$v['id'],'is_del'=>'n'])->order('orders asc,id desc')->select();
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
				$descs = Db::name('groups_goods_options_item_desc')->where(['desc'=>$v['desc'],'goodsid'=>$id])->find();
				$descs['desc_title'] = $v['desc_title'];
				$descs['desc']       = $v['desc'];
				$desc_item[$k] = $descs;
			}
			$one = Db::name('groups_goods')->field('id,title')->where('id',$id)->find();
			$this->assign('one',$one);
			$this->assign('desc_item',$desc_item);
			$this->assign('list',$lista);
			return $this->fetch();
		}
	}

	public function goods_options_publish(){
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
	            $menu = Db::name('groups_goods_options')->where('id',$post['id'])->find();
	            if(empty($menu)) {
	            	return json(['code'=>3,'msg'=>'ID不正确','returnData'=>'']);die;
	            }
	       		$post['updatetime']   = time();
	       		$post['update_admin'] = $admininfo['id'];
                $res = Db::name('groups_goods_options')->where('id',$post['id'])->update($post);
	       		if($res) {
	            	return json(['code'=>0,'msg'=>'修改成功','returnData'=>'']);die;
	        	} else {
	            	return json(['code'=>4,'msg'=>'修改失败','returnData'=>'']);die;
	       		}
	       	}else{
	       		$post['addtime']  = $post['updatetime']   = time();
	       		$post['admin_id'] = $post['update_admin'] = $admininfo['id'];
                $res = Db::name('groups_goods_options')->insert($post);
	       		if($res) {
                    return json(['code'=>0,'msg'=>'添加成功','returnData'=>'']);die;
                } else {
                    return json(['code'=>5,'msg'=>'添加失败','returnData'=>'']);die;
                }
	       	}
		}else{
			$one = Db::name('groups_goods_options')->where(['id'=>$id])->find();
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
	public function goods_options_item_publish(){
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
	            $menu = Db::name('groups_goods_options_item')->where('id',$post['id'])->find();
	            if(empty($menu)) {
	            	return json(['code'=>3,'msg'=>'ID不正确','returnData'=>'']);die;
	            }
	       		$post['updatetime']   = time();
	       		$post['update_admin'] = $admininfo['id'];
                $res = Db::name('groups_goods_options_item')->where('id',$post['id'])->update($post);
	       		if($res) {
	            	return json(['code'=>0,'msg'=>'修改成功','returnData'=>'']);die;
	        	} else {
	            	return json(['code'=>4,'msg'=>'修改失败','returnData'=>'']);die;
	       		}
	       	}else{
	       		$post['addtime']  = $post['updatetime']   = time();
	       		$post['admin_id'] = $post['update_admin'] = $admininfo['id'];
                $res = Db::name('groups_goods_options_item')->insert($post);
	       		if($res) {
                    return json(['code'=>0,'msg'=>'添加成功','returnData'=>'']);die;
                } else {
                    return json(['code'=>5,'msg'=>'添加失败','returnData'=>'']);die;
                }
	       	}
		}else{
			$one = Db::name('groups_goods_options_item')->where(['id'=>$id])->find();
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
	public function goods_options_shows(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
    		if($post['va']=='n'){
    			$va = 'y';
    		}else{
    			$va = 'n';
    		}
    		$data['is_show'] = $va;
    		$res = Db::name('groups_goods_'.$post['tb'])->where('id',$post['id'])->update($data);
    		if($post['tb']=="options"){
    			$resa = Db::name('groups_goods_options_item')->where('optionid',$post['id'])->update($data);
    		}
    		if($res){
    			return json(['code'=>0,'msg'=>'操作成功','va'=>$va]);
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
    	}
	}
	public function goods_options_order(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
    		$data['orders'] = $post['va'];
    		$res = Db::name('groups_goods_'.$post['tb'])->where('id',$post['id'])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'操作成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'操作失败']);
    		}
    	}
	}
	public function goods_options_del(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
            if(empty($post['id'])){
                return json(['code'=>2,'msg'=>'请选择要删除的规格']);
            }
    		$data['is_del'] = 'y';
    		$res = Db::name('groups_goods_'.$post['tb'])->where(['id'=>['in',$post['id']]])->update($data);
    		if($res){
    			return json(['code'=>0,'msg'=>'删除成功']);
    		}else{
    			return json(['code'=>1,'msg'=>'删除失败']);
    		}
    	}
	}
	public function goods_options_delall(){
		if($this->request->isAjax()){
    		$post = $this->request->post();
    		if(empty($post['id'])){
                return json(['code'=>2,'msg'=>'请选择要删除的规格']);
            }
    		$data['is_del'] = 'y';
    		foreach($post['id'] as $k=>$v){
    			$dd = explode("-",$v);
    			$resc = Db::name('groups_goods_'.$dd[1])->where('id',$dd[0])->update($data);
    		}
    		return json(['code'=>0,'msg'=>'删除成功']);
    	}
	}
/************************************************************************/
/** 拼团管理
/************************************************************************/
	public function lists(){
		$where[] = ['g.status','in',['1','2','3','4']];
		$status = $this->request->param('status') ? $this->request->param('status') : 'all';
		if($status!='all'){
			$where[] = ['g.status','=',$status];
		}
		$this->assign('status',$status);
		$keys = $this->request->param('keys') ? $this->request->param('keys') : '';
		$whereM[] = ['m.id','neq','0'];
		if($keys!=''){
			$whereM[] = ['m.nickname|m.phone','like','%'.$keys.'%'];
		}
		$this->assign('keys',$keys);

		$list = Db::name('groups')->alias('g')
				->field('g.id,g.ordersn,g.max_num,g.now_num,g.tuanzhang_id,g.status,gg.thumb,gg.title,gg.groupsprice,gg.description,m.nickname,m.avatar,m.phone')
				->where($where)
				->join('groups_goods gg','gg.id=g.goods_id','left')
				->join('member m','m.id=g.tuanzhang_id','left')
				->where($whereM)
				->order('g.addtime desc')
				->paginate(10,false,['query' => request()->param()]);
		$this->assign('list',$list);
		$count = Db::name('groups')->alias('g')->where($where)->count();
		$this->assign('count',$count);
		return $this->fetch();
	}
	public function tuan_detail(){
		$id = $this->request->param('id') ? $this->request->param('id') : '';
		$one = Db::name('groups')->alias('g')->field('g.id,g.ordersn,g.addtime,m.nickname,m.avatar,m.level,g.status,g.finishtime,g.now_num,g.max_num,g.tuanzhang_id')->join('member m','m.id=g.tuanzhang_id','left')->where('g.id',$id)->find();
		$this->assign("one",$one);
		$list = Db::name('groups_order')->field('id,user_id,status,ordersn,number')->where(['groups_id'=>$id,'is_del'=>'n','tid'=>'0'])->where('status','in',['0','1','2','3','4'])->order('createtime desc')->select();
		$this->assign('list',$list);
		return $this->fetch();
	}
	public function tuan_chuli(){

	}
/************************************************************************/
/** 订单管理
/************************************************************************/
	public function orders(){
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
		
		$list = Db::name('groups_order')->alias('or')
				->field('or.id,or.user_id,or.ordersn,or.consignee_name,or.consignee_mobile,or.consignee_address,price,or.status,or.postageprice,or.createtime,or.discount,or.pay_type,or.express_company,or.send_time,or.shou_time')
				->where($where)
				->order('or.createtime desc')
				->paginate(10,false,['query' => request()->param()]);
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
				$res = Db::name('groups_order')->where('id',$v['id'])->update($uparr);
				$this->paylog($v['id'],'订单已取消','订单超时自动取消',$v['user_id']);
				$quxiaoid[] = $v['id'];
				$v['status'] = "-1";
				groupshuankuncun($v['id']);
			}
			//超时自动收货
			$sendcha = time()-$v['send_time'];
			$shouhuotime = isset($configs['shouhuotime']) ? $configs['shouhuotime'] : 7;
			if($v['status']=="2" && $sendcha>=(60*60*24*$shouhuotime)){
				$shouhuoid[] = $v['id'];
				$uparr['shou_time'] = $v['send_time']+(60*60*24*$shouhuotime);
				$uparr['shou_day']  = date("Ymd",($v['send_time']+(60*60*24*$shouhuotime)));
				$uparr['status']      = "3";
				$res = Db::name('groups_order')->where('id',$v['id'])->update($uparr);
				$this->paylog($v['id'],'订单已收货','订单已成功收货',$v['user_id']);
				$v['status'] = "3";
			}
			//超时自动完成
			$shoucha = time()-$v['shou_time'];
			$wanchengtime = isset($configs['wanchengtime']) ? $configs['wanchengtime'] : 7;
			if($v['status']=="3" && $shoucha>=(60*60*24*$wanchengtime)){
				$wanchengid[] = $v['id'];
				$uparr['finish_time'] = $v['shou_time']+(60*60*24*$wanchengtime);
				$uparr['finish_day']  = date("Ymd",($v['shou_time']+(60*60*24*$wanchengtime)));
				$uparr['status']      = "4";
				$res = Db::name('groups_order')->where('id',$v['id'])->update($uparr);
				$this->paylog($v['id'],'订单已完成','订单已完成，感谢您的支持',$v['user_id']);
				$v['status'] = "4";
			}
		}
		$count = Db::name('groups_order')->alias('or')->where($where)->count();
		if(!empty($ids)){
			$goods = Db::name('groups_order_goods')->field('id,order_id,title,desc_title,image,marketprice,goodsprice,realprice,total')->where('order_id','in',$ids)->order('id asc')->select();
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
	public function order_daochu(){
		$admininfo = $this->admininfo;
		$ids = [];
		$zid = $this->request->param('zid') ? $this->request->param('zid') : '0';
		if($admininfo['pid']=='0' && $admininfo['shop_id']>0){ //店铺管理员 40店铺
			$where[] = ['or.shop_id','=',$admininfo['shop_id']];
        }else{
        	if($zid=="0"){
        		$where[] = ['or.parent_id','=','0'];
        	}else{
        		$where[] = ['or.parent_id','=',$zid];
        	}
        }
        $where[] = ['tid','=','0'];

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
		//按支付方式
		$pay_type = $this->request->param('pay_type') ? $this->request->param('pay_type') : 'all';
		if($pay_type!="all"){
			$where[] = ['or.pay_type','=',$pay_type];
		}
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
		//搜索关键字
		$keys_key = $this->request->param('keys_key') ? $this->request->param('keys_key') : 'order_sn';
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
		
		$counts = Db::name('groups_order')->alias('or')
				->where($where)
				->count();
		$limit = ceil($counts/1000);

		set_time_limit(600);
		$name = '订单清单（'.date("Y-m-d").'）';
		$objPHPExcel = new \PHPExcel();
		$objPHPExcel->getProperties()->setCreator("无崖子")->setLastModifiedBy("无崖子")->setTitle("数据EXCEL导出")->setSubject("数据EXCEL导出")->setDescription("数据EXCEL导出")->setKeywords("excel")->setCategory("result file");
		// $objPHPExcel->getActiveSheet()->setCellValue('A1', '请填写业务参考号');
  //       $objPHPExcel->getActiveSheet()->setCellValue('B1',"\t".date('YmdHis',time()));
        //表头
        $objPHPExcel->getActiveSheet()->setCellValue('A1','订单ID');
	    $objPHPExcel->getActiveSheet()->setCellValue('B1','订单号');
	    $objPHPExcel->getActiveSheet()->setCellValue('C1','收货人姓名');
	    $objPHPExcel->getActiveSheet()->setCellValue('D1','收货人电话');
	    $objPHPExcel->getActiveSheet()->setCellValue('E1','省');
	    $objPHPExcel->getActiveSheet()->setCellValue('F1','市');
	    $objPHPExcel->getActiveSheet()->setCellValue('G1','区');
	    $objPHPExcel->getActiveSheet()->setCellValue('H1','地址');
	    $objPHPExcel->getActiveSheet()->setCellValue('I1','物流公司');
	    $objPHPExcel->getActiveSheet()->setCellValue('J1','物流公司code');
	    $objPHPExcel->getActiveSheet()->setCellValue('K1','物流编号');
	    $objPHPExcel->getActiveSheet()->setCellValue('L1','付款金额');
	    $objPHPExcel->getActiveSheet()->setCellValue('M1','状态');
	    $n = 1;
	    $i = 2;
	    while($n <= $limit){
	    	$list = Db::name('groups_order')->alias('or')
				->field('or.id,or.user_id,or.ordersn,or.number,or.consignee_name,or.consignee_mobile,or.consignee_address,price,or.status,or.postageprice,or.createtime,or.discount,or.pay_type,or.express_company,or.send_time,or.shou_time,or.consignee_province,or.consignee_city,or.consignee_area,or.express,or.express_sn')
				->where($where)
				->order('or.createtime desc')
				->limit(($n-1)*$limit,10000)
				->select();
		    foreach($list as $k=>$v){
		    	$shengs = Db::name('region')->field('region_name')->where('region_id',$v['consignee_province'])->find();
		    	$sheng = "";
		    	if($shengs){
		    		$sheng = $shengs['region_name'];
		    	}
		    	$shis = Db::name('region')->field('region_name')->where('region_id',$v['consignee_city'])->find();
		    	$shi = "";
		    	if($shis){
		    		$shi = $shis['region_name'];
		    	}
		    	$qus = Db::name('region')->field('region_name')->where('region_id',$v['consignee_area'])->find();
		    	$qu = "";
		    	if($qus){
		    		$qu = $qus['region_name'];
		    	}
		    	$zhuangtai = '';
		    	if($v['status']=="0"){
		    		$zhuangtai = "待付款";
		    	}else if($v['status']=="1"){
		    		$zhuangtai = "待发货";
		    	}else if($v['status']=="2"){
		    		$zhuangtai = "待收货";
		    	}else if($v['status']=="3"){
		    		$zhuangtai = "已完成";
		    	}else if($v['status']=="-1"){
		    		$zhuangtai = "已取消";
		    	}
		    	$objPHPExcel->getActiveSheet()->setCellValue('A'.$i,$v['id']);
		    	$objPHPExcel->getActiveSheet()->setCellValue('B'.$i,"\t".$v['number']);
		    	$objPHPExcel->getActiveSheet()->setCellValue('C'.$i,$v['consignee_name']);
		    	$objPHPExcel->getActiveSheet()->setCellValue('D'.$i,$v['consignee_mobile']);
		    	$objPHPExcel->getActiveSheet()->setCellValue('E'.$i,$sheng);
		    	$objPHPExcel->getActiveSheet()->setCellValue('F'.$i,$shi);
		    	$objPHPExcel->getActiveSheet()->setCellValue('G'.$i,$qu);
		    	$objPHPExcel->getActiveSheet()->setCellValue('H'.$i,$v['consignee_address']);
		    	$objPHPExcel->getActiveSheet()->setCellValue('I'.$i,$v['express_company']);
		    	$objPHPExcel->getActiveSheet()->setCellValue('J'.$i,$v['express']);
		    	$objPHPExcel->getActiveSheet()->setCellValue('K'.$i,$v['express_sn']);
		    	$objPHPExcel->getActiveSheet()->setCellValue('L'.$i,$v['price']);
		    	$objPHPExcel->getActiveSheet()->setCellValue('M'.$i,$zhuangtai);
		    	if($i  == 10000){
					sleep(10);
				}
				$i++;
		    }
		    $n++;
			if($n == 300){
				break;
			}
		}
	    $numc = count($list)+2;
	    $ends = 'M';
	    //设置宽度
        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('H')->setWidth(30);
        $objPHPExcel->getActiveSheet()->getColumnDimension('I')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('J')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('K')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('L')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('M')->setWidth(15);
        //设置表头行高
        $objPHPExcel->getActiveSheet()->getRowDimension(1)->setRowHeight(15);
        // $objPHPExcel->getActiveSheet()->getRowDimension(2)->setRowHeight(15);
        
        //设置自动换行
        $objPHPExcel->getActiveSheet()->getStyle('A2:'.($ends.$numc))->getAlignment()->setWrapText(true);


        $objPHPExcel->getActiveSheet()->setTitle('订单清单');
        //设置水平居中
	    $objPHPExcel->getActiveSheet()->getStyle('A1:'.$ends.($numc))->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
	    // $objPHPExcel->getActiveSheet()->getStyle('A2:'.$ends.'2')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
	            
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
	public function daoru(){
		return $this->fetch();
	}
	public function daoru_publish(){
		if($this->request->isPost()){
			$post = $this->request->post();
			$excel = new \PHPExcel();
			if($post['thumb']==""){
				return json(['code'=>1,'msg'=>'请上传文件']);die;
			}
			$path = ltrim($post['thumb'],'/');
			$suffix = "xlsx";
			if($suffix=="xlsx"){
                $reader = \PHPExcel_IOFactory::createReader('Excel2007');
            }else{
                $reader = PHPExcel_IOFactory::createReader('Excel5');
            }
            $excel = $reader->load("$path",$encode = 'utf-8');
        	//读取第一张表
        	$sheet = $excel->getSheet(0);
       		//获取总行数
        	$row_num = $sheet->getHighestRow();
        	//获取总列数
        	$col_num = $sheet->getHighestColumn();
        	$today = date("Ymd");
        	$data = []; //数组形式获取表格数据
        	$add  = [];
        	for ($i = 4; $i <= $row_num; $i ++) {
        		if($this->request->isPost()){
			$post = $this->request->post();
			$excel = new \PHPExcel();
			if($post['txt']==""){
				return json(['code'=>1,'msg'=>'请上传文件']);die;
			}
			$path = ltrim($post['txt'],'/');
			$suffix = "xlsx";
			if($suffix=="xlsx"){
                $reader = \PHPExcel_IOFactory::createReader('Excel2007');
            }else{
                $reader = PHPExcel_IOFactory::createReader('Excel5');
            }
            $excel = $reader->load("$path",$encode = 'utf-8');
        	//读取第一张表
        	$sheet = $excel->getSheet(0);
       		//获取总行数
        	$row_num = $sheet->getHighestRow();
        	//获取总列数
        	$col_num = $sheet->getHighestColumn();
        	$today = date("Ymd");
        	$data = []; //数组形式获取表格数据
        	$add  = [];
        	for ($i = 4; $i <= $row_num; $i ++) {
        		$data[$i]['c_number'] = $add['c_number'] = $c_number = $sheet->getCell("A".$i)->getValue();
        		$data[$i]['c_name']   = $add['c_name']   = $c_name   = $sheet->getCell("B".$i)->getValue();
        		$data[$i]['c_school'] = $add['c_school'] = $c_school = $sheet->getCell("F".$i)->getValue();
        		$data[$i]['c_class']  = $add['c_school'] = $c_class = $sheet->getCell("G".$i)->getValue();
        		// https://gitlab.chenyi-tech.com/wanyi/api.git
        	}
        	$res = Db::name('member_shili')->insertAll($data);
			if($res){
				return json(['code'=>0,'msg'=>'导入成功']);
			}else{
				return json(['code'=>1,'msg'=>'导出失败']);
			}
		}else{
			return $this->fetch();
		}
        	}
        	$res = Db::name('member_shili')->insertAll($data);
			if($res){
				return json(['code'=>0,'msg'=>'导入成功']);
			}else{
				return json(['code'=>1,'msg'=>'导出失败']);
			}
		}else{
			return $this->fetch();
		}
	}
	public function uploada($name='image',$size=1024*1024*20,$ext='xls,xlsx',$save_dir='./',$rule='date',$module='admin',$use='admin'){
		$data = input();
		$name = isset($data['name']) ? $data['name'] : $name; //提交的文件name
		$size = isset($data['size']) ? $data['size'] : $size; //限制上传的文件大小
		$ext  = isset($data['ext'])  ? $data['ext']  : $ext;  //文件格式
		$save_dir = isset($data['save_dir']) ? $data['save_dir'] : $save_dir; //保存路径
		$rule = isset($data['rule']) ? $data['rule'] : $rule; //生成的文件命名方式，默认支持：date根据日期和微秒数生成，md5对文件使用md5_file散列生成,sha1对文件使用sha1_file散列生成
		$module = isset($data['module']) ? $data['module'] : $module;
		$use = isset($data['use']) ? $data['use'] : $use;
	    if($this->request->file('file')){
	    	$file = $this->request->file('file');
		    $info = $file->validate(['size'=>$size,'ext'=>$ext])->rule($rule)->move($save_dir);
		    if($info){
		    	$url = $info->getSaveName();
		    	$arr['url'] = $save_dir.''.$url;
	            $arr['url']  = ltrim($arr['url'],'.');
	            $arr['url']  = str_replace("\\","/",$arr['url']);
		        return json(['code'=>0,'msg'=>'上传成功','returnData'=>$arr]);
		    }else{
		        return json(['code'=>1,'msg'=>'上传失败','returnData'=>$file->getError()]);
	    	}
	    }else{
	    	return json(['code'=>2,'msg'=>'请选择上传的图片','returnData'=>'']);
	    }
	}
	public function orders_detail(){
		$id = $this->request->param('id') ? $this->request->param('id') : '0';
		$one = Db::name('groups_order')->where('id',$id)->find();
		$goods = Db::name('groups_order_goods')->where('order_id',$id)->order('id asc')->select();
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
			$res = Db::name('groups_order')->where('id',$one['id'])->update($uparr);
			$this->paylog($one['id'],'订单已取消','订单超时自动取消',$one['user_id']);
			$one['status'] = "-1";
			groupshuankuncun($one['id']);
		}
		//超时自动收货
		$sendcha = time()-$one['send_time'];
		$shouhuotime = isset($configs['shouhuotime']) ? $configs['shouhuotime'] : 7;
		if($one['status']=="2" && $sendcha>=(60*60*24*$shouhuotime)){
			$one['status'] = "3";
			$uparr['shou_time'] = $one['send_time']+(60*60*24*$shouhuotime);
			$uparr['shou_day']  = date("Ymd",($one['send_time']+(60*60*24*$shouhuotime)));
			$uparr['status']      = "3";
			$res = Db::name('groups_order')->where('id',$one['id'])->update($uparr);
			$this->paylog($one['id'],'订单已收货','订单已成功收货',$one['user_id']);
		}
		//超时自动完成
		$shoucha = time()-$one['shou_time'];
		$wanchengtime = isset($configs['wanchengtime']) ? $configs['wanchengtime'] : 7;
		if($one['status']=="3" && $shoucha>=(60*60*24*$wanchengtime)){
			$one['status'] = '4';
			$uparr['finish_time'] = $one['shou_time']+(60*60*24*$wanchengtime);
			$uparr['finish_day']  = date("Ymd",($one['shou_time']+(60*60*24*$wanchengtime)));
			$uparr['status']      = "4";
			$res = Db::name('groups_order')->where('id',$one['id'])->update($uparr);
			$this->paylog($one['id'],'订单已完成','订单已完成，感谢您的支持',$one['user_id']);
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
			$one = Db::name('groups_order')->field('id,remark')->where('id',$id)->find();
			$this->assign('one',$one);
			return $this->fetch();
		}
	}
	//修改收货信息
	public function orders_address(){
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
            $res = Db::name('groups_order')->where('id',$post['id'])->update($post);
            if($res){
            	$this->paylog($post['id'],'修改收货信息成功','后台管理员将收货地址修改为：'.$post['consignee_address'],$post['user_id']);
            	return json(['code'=>0,'msg'=>'保存成功','returnData'=>'']);die;
            }else{
            	return json(['code'=>3,'msg'=>'保存失败','returnData'=>'']);die;
            }
		}else{
			$one = Db::name('groups_order')->field('id,user_id,consignee_name,consignee_mobile,consignee_address,consignee_province,consignee_city,consignee_area')->where('id',$id)->find();
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
	public function orders_fukuan(){
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
				$url = $this->hostname.url('api/payment/WxMppayCheck',['id'=>$post['id'],'ty'=>'ht']);
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
			$count = Db::name('groups_order')->where('parent_id',$post['id'])->count();
			if($count>0){
				$uparr['is_show'] = 'n';
			}
			$uparr['pay_type'] = $post['pay_type'];
			$uparr['status']   = 1;
			$uparr['pay_ordersn'] = $post['pay_ordersn'];
			$uparr['pay_transid'] = $post['pay_transid'];
			$uparr['pay_status']  = "SUCCESS";
			$uparr['pay_money']   = $post['pay_money'];
			$res = Db::name('groups_order')->where('id',$post['id'])->update($uparr);
			if($res){
				//付款减库存
				$goods = Db::name('groups_order_goods')->field('id,goods_id,desc,total')->where('order_id',$post['id'])->select();
				foreach($goods as $k=>$v){
					$oneg = Db::name('groups_goods')->field('id,total,totalcnf,fan_yue,fan_jifen')->where('id',$v['goods_id'])->find();
					if($oneg['totalcnf']=="1"){ //付款减库存
						if($v['desc']==""){
							$ntotal = $oneg['total'] - $v['total'];
							$resa = Db::name('groups_goods')->where('id',$v['goods_id'])->update(['total'=>$ntotal]);
						}else{
							$onega = Db::name('groups_goods_options_item_desc')->field('total,desc')->where('desc',$v['desc'])->find();
							$ntotal = $onega['total'] - $v['total'];
							$resa = Db::name('groups_goods_options_item_desc')->where('desc',$v['desc'])->update(['total'=>$ntotal]);
						}
					}
				}
				//更新日志
				$this->paylog($post['id'],$title,$content,$post['user_id']);
				if($count>0){
					$uparr['is_show'] = 'y';
					$resa = Db::name('groups_order')->where('parent_id',$post['id'])->update($uparr);
					$zidans = Db::name('groups_order')->field('id,user_id')->where('parent_id',$post['id'])->select();
					$yids = [];
					$yids[] = 0;
					foreach($zidans as $k=>$v){
						$this->paylog($v['id'],$title,$content,$v['user_id']);
						$yids[] = $v['id'];
					}
				}else{
				
				}
            	return json(['code'=>0,'msg'=>'保存成功','returnData'=>'']);die;
            }else{
            	return json(['code'=>3,'msg'=>'保存失败','returnData'=>'']);die;
            }
		}else{
			$onelog = Db::name('order_payment_log')->field('id,pay_ordersn,pay_status,pay_body')->where(['orderid'=>$id,'paytype'=>'wxpay'])->order('createtime desc')->limit(1)->find();
			$oneloa = Db::name('order_payment_log')->field('id,pay_ordersn,pay_status,pay_body')->where(['orderid'=>$id,'paytype'=>'alipay'])->order('createtime desc')->limit(1)->find();
			$one = Db::name('groups_order')->field('id,pay_transid,pay_ordersn,price,user_id')->where('id',$id)->find();
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
				$url = $this->hostname.url('api/payment/WxMppayCheck',['id'=>$post['id'],'ty'=>'ht']);
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
			$one = Db::name('groups_order')->field('id,pay_transid,pay_ordersn,price,user_id')->where('id',$id)->find();
			$this->assign('one',$one);
			$this->assign('onelog',$onelog);
			$this->assign('oneloa',$oneloa);
			return $this->fetch();
		}
	}
	public function orders_fahuo(){
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
            $res = Db::name('groups_order')->where('id',$post['id'])->update($uparr);
			if($res){
				$this->paylog($post['id'],"订单已发货","订单已发货，已交付".$one['name'].'，运单号：'.$post['express_sn'],$post['user_id']);
            	return json(['code'=>0,'msg'=>'保存成功','returnData'=>'']);die;
            }else{
            	return json(['code'=>3,'msg'=>'保存失败','returnData'=>'']);die;
            }
		}else{
			$express = Db::name('express')->field('id,name,express')->where('status','1')->order('displayorder asc')->select();
			$this->assign('express',$express);
			$one = Db::name('groups_order')->field('id,express_company,express_sn,express,user_id')->where('id',$id)->find();
			$this->assign('one',$one);
			return $this->fetch();
		}
	}
	public function orders_fahuoquxiao(){
		if($this->request->isAjax()){
			$post = $this->request->post();
			$one = Db::name('groups_order')->field('id,user_id')->where('id',$post['id'])->find();
			$uparr['status']    = 1;
	        $uparr['send_time'] = 0;
	        $uparr['send_day']  = 0;
	        $uparr['express_company'] = '';
	        $uparr['express_sn']      = '';
	        $uparr['express']         = '';
	        $res = Db::name('groups_order')->where('id',$post['id'])->update($uparr);
			if($res){
				$this->paylog($post['id'],"订单已取消发货","订单取消发货成功",$one['user_id']);
            	return json(['code'=>0,'msg'=>'保存成功','returnData'=>'']);die;
            }else{
            	return json(['code'=>3,'msg'=>'保存失败','returnData'=>'']);die;
            }
	    }
	}
	public function orders_shouhuo(){
		if($this->request->isAjax()){
			$post = $this->request->post();
			$one = Db::name('groups_order')->field('id,user_id')->where('id',$post['id'])->find();
			$uparr['status'] = 3;
			$uparr['shou_time'] = time();
			$uparr['shou_day']  = date("Ymd");
	        $res = Db::name('groups_order')->where('id',$post['id'])->update($uparr);
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
	public function orders_wancheng(){
		if($this->request->isAjax()){
			$post = $this->request->post();
			$one = Db::name('groups_order')->field('id,user_id')->where('id',$post['id'])->find();
			$uparr['status'] = 4;
			$uparr['finish_time'] = time();
			$uparr['finish_day']  = date("Ymd");
	        $res = Db::name('groups_order')->where('id',$post['id'])->update($uparr);
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
	public function orders_wuliu(){
		$id = $this->request->param('id');
		$one = Db::name('groups_order')->field(['id','ordersn','express_sn','express'])->where(['id'=>$id])->find();
		$lista = Db::name('groups_order_log')->field('id,title,content,createtime')->where('orderid',$id)->where('is_del','n')->select();
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

/************************************************************************/
/** 基础设置
/************************************************************************/
	public function configs(){
		if($this->request->isPost()){
			$post = $this->request->post();
			$pt = $post['pt'];
			if(isset($post['hy'])){
				foreach($post['hy'] as $k=>$v){
					$resm = Db::name('member_cate')->where('id',$v['id'])->update($v);
				}
			}
			$pt['updatetime']   = time();
			$admininfo = $this->admininfo;
			$pt['update_admin'] = $admininfo['id'];
			$respt = Db::name('groups_configs')->where('id','1')->update($pt);
			if($respt){
				return json(['code'=>0,'msg'=>'保存成功','returnData'=>'']);die;
			}else{
				return json(['code'=>1,'msg'=>'保存失败','returnData'=>'']);die;
			}
		}else{
			$member_cate = Db::name('member_cate')->where(['is_del'=>'n'])->order('orders asc')->select();
			$this->assign('member_cate',$member_cate);
			$one = Db::name('groups_configs')->where('id','1')->find();
			$this->assign('one',$one);
			$tuan = Db::name('groups')->field('id')->where('status','in',['2','3'])->where('addday',date("Ymd"))->select();
			$tids = [];
			$tids[] = 0;
			foreach($tuan as $k=>$v){
				$tids[] = $v['id'];
			}
			$list = Db::name('groups_order')->field('price')->where('groups_id','in',$tids)->select();
			$dryeji = [];
			foreach($list as $k=>$v){
				$dryeji[] = $v['price'];
			}
			$yeji = sprintf("%.2f",array_sum($dryeji));
			$this->assign('yeji',$yeji);
			return $this->fetch();
		}
	}
}