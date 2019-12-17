<?php
namespace app\api\controller;
use \think\Controller;
use app\api\controller\Base;
use \think\Db;
use \think\Session;
use \think\Cookie;
use \think\Request;
use \think\AES;
class Index extends Base{
	public function __construct(){
		parent::__construct(); //使用父类的构造方法
		//处理跨域问题
        header('Access-Control-Allow-Origin:*'); 
        header('Access-Control-Max-Age:86400'); // 允许访问的有效期
        header('Access-Control-Allow-Headers:*'); 
        header('Access-Control-Allow-Methods:OPTIONS,GET,POST,DELETE');
    }
    public function index(){
    	$one = Db::name('webconfig')->field(['id','banben'])->where('id','1')->find();
    	$returnData['version'] = $one['banben'];
		return json(['code'=>0,'message'=>'SUCCESS','returnData'=>$returnData]);
    }
}