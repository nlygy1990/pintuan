<?php
namespace app\api\controller;
use \think\Controller;
use \think\Db;
use \think\Session;
use \think\Cookie;
use \think\Request;
use think\Console;
use \think\AES;
use think\captcha\Captcha;
use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;
class Base extends Controller{
	public function __construct(){
		parent::__construct(); //使用父类的构造方法
		//处理跨域问题
        header('Access-Control-Allow-Origin:*'); 
        header('Access-Control-Max-Age:86400'); // 允许访问的有效期
        header('Access-Control-Allow-Headers:*'); 
        header('Access-Control-Allow-Methods:OPTIONS,GET,POST,DELETE');
        
        //域名
       	$hostname = $this->request->root(true);
        $this->hostname = $hostname;
        //地图密钥
        $txMapKey = "KLQBZ-MRPCX-3LX4C-7ZCSV-XQD55-ZKF44";
        $this->txMapKey = $txMapKey;
    }

    public function getphonecode(){
        $data = input();
        $validate = new \think\Validate();
        $rule =   [
            'phone'  => 'require|mobile'
        ];
        $message  =   [
            'phone.require' => '请填写手机号码',
            'phone.mobile'  => '请填写正确的手机号码'
        ];
        $validate->message($message);
        //验证部分数据合法性
        if (!$validate->check($data,$rule)) {
            return json(['code'=>2,'msg'=>$validate->getError()]);die;
        }
        $aes = new AES();
        $data['code']  = rand(100000,999999);
        $data['time']  = time();
        $token  = urlencode($aes->encrypt(json_encode($data)));
        $tmid = "SMS_179140164";
        $res = $this->smsVerify($data['phone'],$data['code'],$tmid);
        if($res == 'ok'){
            return json(['code'=>'0','msg'=>'发送成功','token'=>$token]);
        }else{
            return json(['code'=>'1','msg'=>$res,'token'=>'']);
        }
    }

    public function getSchool(){
        $list = Db::name('school')->field('id,title,logo')->where(['is_del'=>'n','is_show'=>'y'])->order('orders asc,addtime desc')->select();
        foreach($list as $k=>$v){
            $list[$k]['logo'] = $this->hostname.getImage($v['logo']);
        }
        if($list){
            return json(['code'=>'0','message'=>'SUCCESS','returnData'=>$list]);
        }else{
            return json(['code'=>'1','message'=>'未获取到任何学校信息','returnData'=>'']);
        }
    }
    public function getClass(){
        $post = input();
        $id = isset($post['id']) ? $post['id'] : '1';
        $list = Db::name('school_class')->field('id,title')->where(['school_id'=>$id])->order('orders asc,addtime desc')->select();
        if($list){
            return json(['code'=>0,'returnData'=>$list]);
        }else{
            return json(['code'=>1,'returnData'=>'']);
        }
    }


    //get 方式推送信息
    public function get_contents($url){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_URL, $url);
        $response =  curl_exec($ch);
        curl_close($ch);
        //-------请求为空
        if(empty($response)){
            exit("50001");
        }
        // var_dump($response);die;
        return $response;
    }
    //post 方式推送信息
    public function http_post($url='',$data){ 
        $curl = curl_init();
        curl_setopt($curl,CURLOPT_URL,$url);
        curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,false);
        curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,false);
        if(!empty($data)){
            curl_setopt($curl,CURLOPT_POST,1);
            curl_setopt($curl,CURLOPT_POSTFIELDS,$data);
        }
        curl_setopt($curl,CURLOPT_RETURNTRANSFER,1);
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }
    public function http_posta($api,$data){
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $api);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }
    public function CheckToken($tk){
        $aes = new AES();
        $token = $aes->decrypt(urldecode($tk));
        if($token){
            $tkarr = json_decode($token,1);
            if($tkarr){
                $checkmember = Db::name('member')->field(['password','wx_openid','qq_openid','sn_openid','is_del','is_show','reg_ip','reg_time','sjmobile','sjsystem','sjplatform'],true)->where(['id'=>$tkarr['id'],'is_show'=>'y','is_del'=>'n'])->find();
                if($checkmember){
                    if($checkmember['last_ip']==$tkarr['last_ip'] && $checkmember['last_login']==$tkarr['last_login']){
                        if($checkmember['head_pic']){
                            $checkmember['head_pic'] = $this->hostname.$checkmember['head_pic'];
                        }else{
                            if($checkmember['avatar']){
                                $checkmember['head_pic'] = $checkmember['avatar'];
                            }else{
                                $checkmember['head_pic'] = '';
                            }                           
                        }
                        if(!$checkmember['phone']){
                            $checkmember['phone'] = '';
                        }
                        if(!$checkmember['country']){
                            $checkmember['country'] = '';
                        }
                        if(!$checkmember['province']){
                            $checkmember['province'] = '';
                        }
                        if(!$checkmember['city']){
                            $checkmember['city'] = '';
                        }
                        if(!$checkmember['address']){
                            $checkmember['address'] = '';
                        }
                        return array('code'=>'0','myinfo'=>$checkmember);
                    }else{
                        return array('code'=>'3','msg'=>'您的账号已在其他设备登录');
                    }
                }else{
                    return array('code'=>'2','msg'=>'用户不存在或者已被拉黑');
                }
            }else{
                return array('code'=>'1','msg'=>'token错误');
            }
        }else{
            return array('code'=>'1','msg'=>'token错误');
        }
    }
    public function getDaili($uid,$zong=0,$daili=0,$pn=0,$arr=array()){
        if(!empty($arr)){
            //如果已经排查过，退出递归，防止循环递归
            if(in_array($uid,$arr)){
                return array(['pid1'=>$daili]);
            }
        }
        $pn = $pn+1;
        if($pn>100){ //设定最高递归100次，超过100次跳出递归
            return array(['pid2'=>$zong,'pid1'=>$daili]);
        }else{
            if($daili=='0'){ //还没找到代理团长
                $one = Db::name('member')->field('id,user_id,tz_level')->where('id',$uid)->find();
                if($one){
                    $arr[] = $one['id'];
                    if($one['tz_level']=="2"){ //找到总团长，递归结束
                        return array(['pid2'=>$one['id'],'pid1'=>$daili]);
                    }else{ //没找到总团长，继续找代理团长
                        if($one['tz_level']=="1"){ //找到代理团长，继续找总团长
                            $this->getDaili($one['user_id'],$zong,$one['id'],$pn,$arr);
                        }else{ //啥都没找到，继续递归找
                            $this->getDaili($one['user_id'],$zong,$daili,$pn,$arr);
                        }
                    }
                }else{
                    return array(['pid2'=>$zong,'pid1'=>$daili]);
                }
            }else{ //已找到代理团长,找总团长
                if($zong=='0'){
                    $one = Db::name('member')->field('id,user_id,tz_level')->where('id',$uid)->find();
                    if($one){
                        $arr[] = $one['id'];
                        if($one['tz_level']=="2"){ //总团长，代理团长都有了，跳出递归
                            return array(['pid2'=>$one['id'],'pid1'=>$daili]);
                        }else{ //没找到总团长，继续找
                            $this->getDaili($one['user_id'],$zong,$daili,$pn,$arr);
                        }
                    }else{
                        return array(['pid2'=>$zong,'pid1'=>$daili]);
                    }
                }else{ //防止进错递归，直接出递归
                    return array(['pid2'=>$zong,'pid1'=>$daili]);
                }
            }
        }   
    }
    public function getUpmem(){
        $id = $this->request->param('id');
        $res = $this->getDaili($id);
        $up = Db::name('member')->where('id',$id)->update(['pid1'=>$res['pid1'],'pid2'=>$res['pid2']]);
        return "SUCCESS";
    }
    public function smsVerify($mobile, $code, $tempId){
        AlibabaCloud::accessKeyClient('LTAI4FeBSNn2mvWyj7PKuBbH', 'bLMJs60DWamcvr8yfe8BMMLVIALVc2')
            ->regionId('cn-hangzhou')
            ->asDefaultClient();
        try {
            $result = AlibabaCloud::rpc()
            ->product('Dysmsapi')
            // ->scheme('https') // https | http
            ->version('2017-05-25')
            ->action('SendSms')
            ->method('POST')
            ->host('dysmsapi.aliyuncs.com')
            ->options([
                'query' => [
                    'RegionId' => "cn-hangzhou",
                    'PhoneNumbers' => $mobile,
                    'SignName' => "万一乐购",
                    'TemplateCode' => $tempId,
                    'TemplateParam' => "{\"code\":\"".$code."\"}",
                ],
            ])
            ->request();
            $resarr = $result->toArray();
            if(isset($resarr['Message']) && $resarr['Message']=="OK"){
                return "ok";
            }else{
                return $resarr['Message'];
            }
        } catch (ClientException $e) {
            return $e->getErrorMessage() . PHP_EOL;
        } catch (ServerException $e) {
            return $e->getErrorMessage() . PHP_EOL;
        }
    }
}