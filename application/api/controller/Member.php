<?php
namespace app\api\controller;
use \think\Controller;
use app\api\controller\Base;
use \think\Db;
use \think\Session;
use \think\Cookie;
use \think\Request;
use \think\AES;
class Member extends Base{
    public function __construct(){
        parent::__construct(); //使用父类的构造方法
    }
    /********************************************************************************/
    /** 个人信息
    /********************************************************************************/
    public function getMyinfo(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $aa = openssl_encrypt($token['myinfo']['id'],"AES-128-ECB",'a',0,'');
            $token['myinfo']['jifen']     = $this->getJifen($token['myinfo']['id']);
            // $token['myinfo']['yaoqingma'] = str_replace("==","",$aa);
            if($token['myinfo']['phone']){
                $token['myinfo']['ycphone']   = substr_replace($token['myinfo']['phone'],'****',3,4);
            }else{
                $token['myinfo']['ycphone']   = "";
            }
            if($token['myinfo']['level']=="0"){
                $level['id'] = 0;
                $level['title'] = "普通团员";
                $token['myinfo']['level'] = $level;
            }else{
                $token['myinfo']['level'] = Db::name('member_cate')->field('id,title,thumb')->where('id',$token['myinfo']['level'])->find();
                $token['myinfo']['level']['thumb'] = getImage($token['myinfo']['level']['thumb']);
            }
            $token['myinfo']['haoyou'] = Db::name('member')->where('user_id',$token['myinfo']['id'])->count();
            if($token['myinfo']['user_id']>'0'){
                $mme = Db::name('member')->field('nickname,username')->where('id',$token['myinfo']['user_id'])->find();
                $token['myinfo']['yaoqingren'] = $mme['nickname'];
            }
            $tixian = Db::name('member_tixian')->field('money,status')->where(['uid'=>$token['myinfo']['id']])->select();
            $chenggong = []; $daidakuan = []; $shenhe = []; $yishixiao = [];
            foreach($tixian as $k=>$v){
                if($v['status']=="0"){
                    $shenhe[] = $v['money'];
                }else if($v['status']=="1"){
                    $daidakuan[] = $v['money'];
                }else if($v['status']=="2"){
                    $chenggong[] = $v['money'];
                }else if($v['status']=="-1"){
                    $yishixiao[] = $v['money'];
                }
            }
            $chenggongdakuan = array_sum($chenggong);
            $token['myinfo']['chenggongdakuan'] = sprintf("%.2f",$chenggongdakuan);
            $yongjinlist = Db::name('member_distribution')->field('money,status')->where(['user_id'=>$token['myinfo']['id']])->select();
            $yongjin = []; $ketiyongjin = [];
            foreach($yongjinlist as $k=>$v){
                $yongjin[] = $v['money'];
                if($v['status']=="4" || $v['status']=="3"){
                    $ketiyongjin[] = $v['money'];
                }
            }
            $yongjinc = array_sum($yongjin);
            $ketiyongjinc = array_sum($ketiyongjin);
            $token['myinfo']['yongjin'] = sprintf("%.2f",$yongjinc);
            $token['myinfo']['ketiyongjin'] = sprintf("%.2f",$ketiyongjinc);
            $token['myinfo']['tixianc'] = count($tixian);
            // $token['myinfo']['jie']       = openssl_decrypt(str_replace("==","",$aa),"AES-128-ECB",'a',0,'');
            $order['daifukuan']  = Db::name('order')->where(['user_id'=>$token['myinfo']['id'],'status'=>'0','is_show'=>'y','is_del'=>'n'])->count();
            $order['daifahuo']   = Db::name('order')->where(['user_id'=>$token['myinfo']['id'],'status'=>'1','is_show'=>'y','is_del'=>'n'])->count();
            $order['daishouhuo'] = Db::name('order')->where(['user_id'=>$token['myinfo']['id'],'status'=>'2','is_show'=>'y','is_del'=>'n'])->count();
            $order['daipingjia'] = Db::name('order')->where(['user_id'=>$token['myinfo']['id'],'status'=>'3','is_show'=>'y','is_del'=>'n'])->count();
            $kaiguan = 0;
            return json(['code'=>0,'myinfo'=>$token['myinfo'],'order'=>$order,'kaiguan'=>$kaiguan]);
        }else{
            return json($token);
        }
    }
    public function tixianKt(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $yongjinlist = Db::name('member_distribution')->field('id,money,status')->where(['user_id'=>$token['myinfo']['id']])->select();
            $yongjin = []; $ketiyongjin = [];
            $tids = [];
            foreach($yongjinlist as $k=>$v){
                $yongjin[] = $v['money'];
                if($v['status']=="1"){
                    $ketiyongjin[] = $v['money'];
                }
                $tids[] = $v['id'];
            }
            $yongjinc           = array_sum($yongjin);
            $ketiyongjinc       = array_sum($ketiyongjin);
            $one['yongjin']     = sprintf("%.2f",$yongjinc);
            $one['ketiyongjin'] = sprintf("%.2f",$ketiyongjinc);
            $one['shuoming']['zuidi']    = 0;
            $one['shuoming']['zuigao']   = 0;
            $one['tids'] = $tids;
            $wcon = Db::name('groups_configs')->field('shouxu,shouxu_j')->where('id','1')->find();
            $wcon['shouxus'] = $wcon['shouxu']/100;
            $one['wcon'] = $wcon;
            $one['shiji'] = $one['ketiyongjin']-sprintf("%.2f",$one['ketiyongjin']*$wcon['shouxus']);
            if($one['shiji']<='0'){
                $one['shiji'] = "0.00";
            }
            $one['shiji'] = sprintf("%.2f",$one['shiji']);
            return json(['code'=>0,'returnData'=>$one]);
        }else{
            return json($token);
        }
    }
    public function tixianMx(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $yongjinlist = Db::name('member_distribution')->field('money,status')->where(['user_id'=>$token['myinfo']['id']])->select();
            $yongjin = []; $ketiyongjin = []; $daishouyongjin = [];
            foreach($yongjinlist as $k=>$v){
                $yongjin[] = $v['money'];
                if($v['status']=="4" || $v['status']=="3"){
                    $ketiyongjin[] = $v['money'];
                }else if($v['status']=="2" || $v['status']=="1"){
                    $daishouyongjin = $v['money'];
                }
            }
            $yongjinc = array_sum($yongjin);
            $ketiyongjinc = array_sum($ketiyongjin);
            $daishouyongjinc = array_sum($daishouyongjin);
            $one['yongjin']     = sprintf("%.2f",$yongjinc);
            $one['ketiyongjin'] = sprintf("%.2f",$ketiyongjinc);
            $one['daishouyongjin'] = sprintf("%.2f",$daishouyongjinc);

            $tixian = Db::name('member_tixian')->field('money,status')->where(['uid'=>$token['myinfo']['id']])->select();
            $shentx = []; $daitx = []; $yitx = []; $shitx = [];
            foreach($tixian as $k=>$v){
                if($v['status']=="0"){
                    $shentx[] = $v['money'];
                }else if($v['status']=="1"){
                    $daitx[]  = $v['money'];
                }else if($v['status']=="2"){
                    $yitx[]   = $v['money'];
                }else{
                    $shitx[]  = $v['money'];
                }
            }
            $shentxc = array_sum($shentx);$daitxc = array_sum($daitx);$yitxc = array_sum($yitx);$shitxc = array_sum($shitx);
            $one['shentx']     = sprintf("%.2f",$shentxc);
            $one['daitx']     = sprintf("%.2f",$daitxc);
            $one['yitx']     = sprintf("%.2f",$yitxc);
            $one['shitx']     = sprintf("%.2f",$shitxc);
            return json(['code'=>0,'returnData'=>$one]);
        }else{
            return json($token);
        }
    }
    public function tixianShenqing(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $tids = $data['tids'];
            $bb = json_decode($data['bankData'],1);
            $bank = Db::name('member_bank')->where('id',$bb['id'])->find();
            $arr['uid'] = $token['myinfo']['id'];
            $shiji = isset($data['shiji']) ? $data['shiji'] : '0.00';
            $arr['title'] = "余额提现";
            $arr['money'] = $data['txprice'] ? $data['txprice'] : 0;
            $arr['shouxufei'] = $data['txprice']-$shiji;
            $arr['bankid']    = $bb['id'];
            $arr['bankbody']  = json_encode($bank,JSON_UNESCAPED_UNICODE);
            $arr['addtime']   = time();
            $arr['addday']    = date("Ymd");
            $arr['tids']      = $tids;
            $res = Db::name('member_tixian')->insertGetId($arr);
            if($res){
                $arrs['status'] = 2;
                $arrs['pay_time'] = time();
                $arrs['pay_day']  = date("Ymd");
                $resa = DB::name('member_distribution')->where(['user_id'=>$token['myinfo']['id'],'status'=>'1'])->where('id','in',$tids)->update($arrs);
                //发送站内信
                $bid = substr_replace($bank['cardid'],' **** **** ',6,8);
                $uparr['orderid'] = 0;
                $uparr['userid']  = $token['myinfo']['id'];
                $uparr['title']   = "提现申请已提交";
                $uparr['desc']    = "提现申请已提交，等待审核";
                $uparr['content'] = "提现申请已提交，等待审核；提现金额：".$data['txprice'].'元；提现至：'.$bank['bankname'].'('.$bid.')';
                $uparr['sendtime'] = time();
                $uparr['sendday']  = date('Ymd');
                $uparr['types']    = 'tixian';
                $uparr['createtime'] = time();
                $uparr['createday']  = date('Ymd');
                $resa = Db::name('member_message')->insert($uparr);
                return json(['code'=>0,'msg'=>'提交成功','returnData'=>$res]);
            }else{
                return json(['code'=>1,'msg'=>'提交失败','returnData'=>0]);
            }
        }else{
            return json($token);
        }
    }
    public function changeHead(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $uparr['head_pic'] = str_replace("\\","/",$data['head_pic']);
            $res  = Db::name('member')->where('id',$token['myinfo']['id'])->update($uparr);
            return json(['code'=>0,'msg'=>'上传成功']);
        }else{
            return json($token);
        }
    }
    public function getJifen($uid){
        $jifenlist = Db::name('member_jifen')->field(['types','jifen'])->where(['uid'=>$uid])->order('addtime asc')->select();
        $addarr = [];
        $delarr = [];
        foreach($jifenlist as $k=>$v){
            if($v['types']=="1"){
                $addarr[] = $v['jifen'];
            }else{
                $delarr[] = $v['jifen'];
            }
        }
        return array_sum($addarr)-array_sum($delarr);
    }
    public function changeMyinfo(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $uparr['updatetime'] = time();
            if(isset($data['nickname'])){
                $uparr['nickname'] = $data['nickname'];
            }
            if(isset($data['diqu'])){
                $diqu = explode("-",$data['diqu']);
                if(isset($diqu[0])){
                    $uparr['province'] = $diqu[0];
                }
                if(isset($diqu[1])){
                    $uparr['city'] = $diqu[1];
                }
            }
            if(isset($data['address'])){
                $uparr['address'] = $data['address'];
            }
            if(isset($data['password'])){
                $uparr['password'] = MD5(MD5($data['password']));
            }
            if(isset($data['head_pic'])){
                $uparr['head_pic'] = $data['head_pic'];
            }
            if(isset($data['phone'])){
                $uparr['phone'] = $data['phone'];
            }
            $res = Db::name('member')->where(['id'=>$token['myinfo']['id']])->update($uparr);
            if($res){
                return json(['code'=>0,'msg'=>'操作成功']);
            }else{
                return json(['code'=>1,'msg'=>'操作失败']);
            }
        }else{
            return json($token);
        }
    }
    public function changepwd(){
        $data = input();
        if(!isset($data['oldpwd'])){
            return json(['code'=>11,'msg'=>'原始密码不能为空']);die;
        }
        if(!isset($data['newpwd'])){
            return json(['code'=>11,'msg'=>'新密码不能为空']);die;
        }
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $check = Db::name('member')->field(['id','password'])->where(['id'=>$token['myinfo']['id']])->find();
            if($check['password']==MD5(MD5($data['oldpwd']))){
                $newpwd = MD5(MD5($data['newpwd']));
                $res = Db::name('member')->where(['id'=>$token['myinfo']['id']])->update(['password'=>$newpwd]);
                if($res){
                    $ip = $this->request->ip();
                    //操作日志
                    $add_log['uid']     = $token['myinfo']['id'];
                    $add_log['title']   = "密码修改成功";
                    $add_log['content'] = "修改密码成功！操作信息：IP（".$ip."）；时间（".date('Y.m.d H:i:s')."）；";
                    $add_log['addtime'] = time();
                    $resa = Db::name('member_log')->insert($add_log);
                    return json(['code'=>0,'msg'=>'修改成功']);
                }else{
                    return json(['code'=>13,'msg'=>'操作失败']);
                }
            }else{
                return json(['code'=>12,'msg'=>'原始密码错误']);
            }
        }else{
            return json($token);
        }
    }
    public function getYzBphone(){
        $data = input();
        if(!isset($data['code'])){
            return json(['code'=>'11','msg'=>'手机验证码不能为空']);die;
        }
        if(!isset($data['phonetoken'])){
            return json(['code'=>'11','msg'=>'手机验证码参数错误']);die;
        }
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $aes = new AES();
            $ptoken = $aes->decrypt(urldecode($data['phonetoken']));
            if($ptoken){
                $ptoken = json_decode($ptoken,1);
                if($data['code'] == $ptoken['code'] && $data['phone']==$ptoken['phone']){
                    return json(['code'=>0,'msg'=>'验证成功']);
                }else{
                    return json(['code'=>'12','msg'=>'验证码错误']);die;
                }
            }else{
                return json(['code'=>'12','msg'=>'验证码信息被篡改']);die;
            }
        }else{
            return json($token);
        }
    }
    public function getYzBphonea(){
        $data = input();
        if(!isset($data['code'])){
            return json(['code'=>'11','msg'=>'手机验证码不能为空']);die;
        }
        if(!isset($data['phonetoken'])){
            return json(['code'=>'11','msg'=>'手机验证码参数错误']);die;
        }
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $aes = new AES();
            $ptoken = $aes->decrypt(urldecode($data['phonetoken']));
            if($ptoken){
                $ptoken = json_decode($ptoken,1);
                if($data['code'] == $ptoken['code'] && $data['phone']==$ptoken['phone']){
                    $res = Db::name('member')->where('id',$token['myinfo']['id'])->update(['phone'=>$data['phone']]);
                    if($res){
                        $ip = $this->request->ip();
                        //操作日志
                        $add_log['uid']     = $token['myinfo']['id'];
                        $add_log['title']   = "修改绑定手机成功";
                        $add_log['content'] = "修改绑定手机成功！操作信息：IP（".$ip."）；时间（".date('Y.m.d H:i:s')."）；";
                        $add_log['addtime'] = time();
                        $resa = Db::name('member_log')->insert($add_log);
                        return json(['code'=>0,'msg'=>'提交成功']);
                    }else{
                        return json(['code'=>13,'msg'=>'提交失败']);
                    }
                }else{
                    return json(['code'=>'12','msg'=>'验证码错误']);die;
                }
            }else{
                return json(['code'=>'12','msg'=>'验证码信息被篡改']);die;
            }
        }else{
            return json($token);
        }
    }
    public function getPhoneCode(){
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
        $data['code'] = rand(100000,999999);
        $token  = urlencode($aes->encrypt(json_encode($data)));
        return json(['code'=>'0','msg'=>'！'.$data['code'],'token'=>$token]);
    }
    public function mymsg(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $pn = isset($data['pn']) ? $data['pn'] : 1;
            $limit = 15;
            $start = ($pn-1)*$limit;
            $list = Db::name('member_message')->field('id,title,content,desc,readtime,createtime,orderid,types')->where('userid',$token['myinfo']['id'])->limit($start,$limit)->order('sendtime desc')->select();
            if($list){
                foreach($list as $k=>$v){
                    $list[$k]['createtime'] = date('Y.m.d H:i:s',$v['createtime']);
                    $goods = [];
                    if($v['orderid']>'0'){
                        $goods = Db::name('order_goods')->field('id,title,desc_title,image')->where('order_id',$v['orderid'])->order('id asc')->select();
                        foreach($goods as $key=>$val){
                            $goods[$key]['image'] = $this->hostname.$val['image'];
                        }
                        $list[$k]['order'] = Db::name('order')->field('id,ordersn')->where('id',$v['orderid'])->find();
                    }
                    $list[$k]['goods'] = $goods;
                }
                $count = Db::name('member_message')->where('userid',$token['myinfo']['id'])->count();
                return json(['code'=>0,'msg'=>'获取成功','list'=>$list,'count'=>$count]);
            }else{
                return json(['code'=>1,'msg'=>'没有更多了','list'=>$list]);
            }
        }else{
            return json($token);
        }
    }
    /********************************************************************************/
    /** 绑定手机
    /********************************************************************************/
    public function bindphone(){
        $data = input();
        $tokena = $this->CheckToken($data['token']);
        if($tokena['code']=='0'){
            $validate = new \think\Validate();
            $rule =   [
                'phone'  => 'require|mobile',
                'code'  => 'require',
                'phonetoken'   => 'require'
            ];
            $message  =   [
                'phone.require' => '请填写手机号码',
                'phone.mobile'  => '请填写正确的手机号码',
                'code.require' => '请填写手机验证码',
                'phonetoken.require'    => '参数错误'
            ];
            $validate->message($message);
            //验证部分数据合法性
            if (!$validate->check($data,$rule)) {
                return json(['code'=>2,'msg'=>$validate->getError()]);die;
            }
            $aes = new AES();
            $token = $aes->decrypt(urldecode($data['phonetoken']));
            if(empty($token)){
                return json(['code'=>1,'msg'=>'参数错误']);
            }else{
                $tkarr = json_decode($token,1);
                if($data['code']==$tkarr['code'] && $data['phone']==$tkarr['phone']){
                    $cha = time()-$tkarr['time'];
                    if($cha>=(60*5)){
                        return json(['code'=>3,'msg'=>'手机验证码过期']);
                    }else{
                        $res = Db::name('member')->where('id',$tokena['myinfo']['id'])->update(['phone'=>$data['phone']]);
                        if($res){
                            return json(['code'=>0,'msg'=>'绑定成功']);
                        }else{
                            return json(['code'=>4,'msg'=>'绑定失败']);
                        }
                    }
                }else{
                    return json(['code'=>2,'msg'=>'手机验证码错误']);
                }
            }
        }else{
            return json($tokena);
        }
    }
    public function deletebindphone(){
        $data = input();
        $tokena = $this->CheckToken($data['token']);
        if($tokena['code']=='0'){
            $validate = new \think\Validate();
            $rule =   [
                'phone'  => 'require|mobile',
                'code'  => 'require',
                'phonetoken'   => 'require'
            ];
            $message  =   [
                'phone.require' => '请填写手机号码',
                'phone.mobile'  => '请填写正确的手机号码',
                'code.require' => '请填写手机验证码',
                'phonetoken.require'    => '参数错误'
            ];
            $validate->message($message);
            //验证部分数据合法性
            if (!$validate->check($data,$rule)) {
                return json(['code'=>2,'msg'=>$validate->getError()]);die;
            }
            $aes = new AES();
            $token = $aes->decrypt(urldecode($data['phonetoken']));
            if(empty($token)){
                return json(['code'=>1,'msg'=>'参数错误']);
            }else{
                $tkarr = json_decode($token,1);
                if($data['code']==$tkarr['code'] && $data['phone']==$tkarr['phone']){
                    $cha = time()-$tkarr['time'];
                    if($cha>=(60*5)){
                        return json(['code'=>3,'msg'=>'手机验证码过期']);
                    }else{
                        $res = Db::name('member')->where('id',$tokena['myinfo']['id'])->update(['phone'=>""]);
                        if($res){
                            return json(['code'=>0,'msg'=>'解绑成功']);
                        }else{
                            return json(['code'=>4,'msg'=>'解绑失败']);
                        }
                    }
                }else{
                    return json(['code'=>2,'msg'=>'手机验证码错误']);
                }
            }
        }else{
            return json($tokena);
        }
    }

    /********************************************************************************/
    /** 银行卡信息
    /********************************************************************************/
    public function banklist(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $list = Db::name('member_bank')->field('addtime,updatetime',true)->where(['uid'=>$token['myinfo']['id'],'is_del'=>'n'])->order('addtime desc')->select();
            foreach($list as $k=>$v){
                $bank = Db::name('bank')->field('addtime,updatetime',true)->where('id',$v['bankid'])->find();
                $list[$k]['logo']    = $this->hostname.$bank['logo'];
                $list[$k]['bgcolor'] = $bank['bgcolor'];
                $list[$k]['cardid']  = substr_replace($v['cardid'],' **** **** ',6,8);
                $list[$k]['name']    = $this->substr_cut($v['name']);
            }
            if($list){
                return json(['code'=>'0','data'=>$list]);
            }else{
                return json(['code'=>'1','data'=>'暂时没有相关银行']);
            }
        }else{
            return json($token);
        }
    }
    public function substr_cut($user_name){
        $strlen     = mb_strlen($user_name, 'utf-8');
        $firstStr     = mb_substr($user_name, 0, 1, 'utf-8');
        $lastStr     = mb_substr($user_name, -1, 1, 'utf-8');
        return $strlen == 2 ? $firstStr . str_repeat(' * ', mb_strlen($user_name, 'utf-8') - 1) : $firstStr . str_repeat(" * ", $strlen - 2) . $lastStr;
    }
    public function bank_publish(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $bank = Db::name('bank')->where('id',$data['bankid'])->find();
            $arr['uid']       = $token['myinfo']['id'];
            $arr['name']      = $data['name'];
            $arr['bankid']    = $data['bankid'];
            $arr['cardid']    = $data['cardid'];
            $arr['banknamea'] = $data['banknamea'];
            $arr['phone']     = $data['phone'];
            $arr['bankname']  = $bank['name'];
            if($data['id']>"0"){
                $arr['updatetime'] = time();
                $res = Db::name('member_bank')->where('id',$data['id'])->update($arr);
            }else{
                $arr['addtime'] = $arr['updatetime'] = time();
                $res = Db::name('member_bank')->insertGetId($arr);
            }
            if($res){
                return json(['code'=>0,'msg'=>'保存成功']);
            }else{
                return json(['code'=>1,'msg'=>'保存失败']);
            }
        }else{
            return json($token);
        }
    }
    public function bank_del(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $res = Db::name('member_bank')->where('id',$data['id'])->update(['is_del'=>'y']);
            if($res){
                return json(['code'=>0,'msg'=>'删除成功']);
            }else{
                return json(['code'=>1,'msg'=>'删除失败']);
            }
        }else{
            return json($token);
        }
    }

    /********************************************************************************/
    /** 我的好友
    /********************************************************************************/
    public function huiyuanlist(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $pn = isset($data['pn']) ? $data['pn'] : 1;
            $limit = isset($data['limit']) ? $data['limit'] : 15;
            $start = ($pn-1)*$limit;
            $state = isset($data['state']) ? $data['state'] : "0";
            $where[] = ['user_id','eq',$token['myinfo']['id']];
            $where[] = ['is_show','eq','y'];
            $where[] = ['is_del','eq','n'];
            if($state=="0"){  //我的团长
                $where[] = ['level','neq','0'];
            }else{
                $where[] = ['level','eq','0'];
            }
            $list = Db::name('member')->field('id,nickname,username,phone,avatar,addtime')->where($where)->order('addtime desc')->limit($start,$limit)->select();
            if($list){
                foreach($list as $k=>$v){
                    $list[$k]['addtime'] = date('Y-m-d',$v['addtime']);
                }
                return json(['code'=>0,'msg'=>'获取成功','list'=>$list]);
            }else{
                return json(['code'=>1,'msg'=>'没有更多了','list'=>$list]);
            }
        }else{
            return json($token);
        }
    }
    public function huiyuanOrders(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $pn = isset($data['pn']) ? $data['pn'] : 1;
            $limit = isset($data['limit']) ? $data['limit'] : 15;
            $start = ($pn-1)*$limit;
            $list = Db::name('groups_order')->alias('g')
                ->field('g.ordersn,g.id,g.createtime,g.status,m.id as uid,m.avatar,m.nickname,m.phone,gg.title,gg.marketprice,gg.description,gg.image')
                ->where('g.is_del','n')
                ->join('member m','g.user_id=m.id','left')
                ->join('groups_order_goods gg','gg.order_id=g.id','left')
                ->limit($start,$limit)
                ->where('m.user_id','=',$token['myinfo']['id'])
                ->where('m.is_del','=','n')
                ->order('g.createtime desc')
                ->select();
            $count = Db::name('groups_order')->alias('g')->join('member m','g.user_id=m.id','left')
                ->join('groups_order_goods gg','gg.order_id=g.id','left')->where('m.is_del','=','n')->where('m.user_id','=',$token['myinfo']['id'])->count();
            if($list){
                foreach($list as $k=>$v){
                    $list[$k]['createtime'] = date("Y-m-d H:i:s",$v['createtime']);
                    $list[$k]['image'] = $this->hostname.$v['image'];
                }
                return json(['code'=>0,'msg'=>'获取成功','list'=>$list,'count'=>$count]);
            }else{
                return json(['code'=>1,'msg'=>'没有更多了','list'=>$list,'count'=>$count]);
            }
        }else{
            return json($token);
        }
    }
    public function getMyfriend(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $pn = isset($data['pn']) ? $data['pn'] : 1;
            $limit = isset($data['limit']) ? $data['limit'] : 15;
            $start = ($pn-1)*$limit;
            $list = Db::name('member')->field(['id','nickname','username','avatar','head_pic','addtime'])->where(['is_del'=>'n','user_id'=>$token['myinfo']['id']])->order('addtime desc')->limit($start,$limit)->select();
            if($list){
                foreach($list as $k=>$v){
                    $list[$k]['addtime'] = date("Y-m-d H:i:s",$v['addtime']);
                    if($v['head_pic']){
                        $list[$k]['head_pic'] = $this->hostname.$v['head_pic'];
                    }else{
                        $list[$k]['head_pic'] = $v['avatar'];
                    }
                    $zhijie = Db::name('member')->field('id')->where('user_id',$v['id'])->select();
                    $list[$k]['zhijie'] = count($zhijie);
                }
                return json(['code'=>0,'msg'=>'操作成功','data'=>$list]);
            }else{
                return json(['code'=>1,'msg'=>'操作成功','data'=>$list]);
            }
        }else{
            return json($token);
        }
    }
    /********************************************************************************/
    /** 提现
    /********************************************************************************/
    public function getTxlist(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $where = [];
            $where[] = ['uid','=',$token['myinfo']['id']];
            $pn    = isset($data['pn']) ? $data['pn'] : 1;
            $limit = isset($data['limit']) ? $data['limit'] : 15;
            $start = ($pn-1)*$limit;
            $state = isset($data['state']) ? $data['state'] : 0;
            if($state==0){

            }else if(in_array($state,['1','2','3'])){
                $where[] = ['status','=',$state-1];
            }else{
                $where[] = ['status','=','-1'];
            }
            $riqi = isset($data['riqi']) ? $data['riqi'] : 0;
            if($riqi!=0 && $state=="0"){
                $where[] = ["addday",'=',$riqi];
            }
            if($riqi!=0 && $state=="1"){
                $where[] = ["addday",'=',$riqi];
            }
            if($riqi!=0 && $state=="2"){
                $where[] = ["shenheday",'=',$riqi];
            }
            if($riqi!=0 && $state=="3"){
                $where[] = ["payday",'=',$riqi];
            }
            if($riqi!=0 && $state=="4"){
                $where[] = ["shixiaoday",'=',$riqi];
            }
            $list = Db::name('member_tixian')->field('addday,shenheday,payday,shixiaoday,is_del',true)->where($where)->order('addtime desc')->limit($start,$limit)->select();
            $xiaoji = [];
            $lista = Db::name('member_tixian')->field('money')->where($where)->select();
            foreach($lista as $k=>$v){
                $xiaoji[] = $v['money'];
            }
            $xiaojic = array_sum($xiaoji);
            $xiaojic = sprintf("%.2f",$xiaojic);
            if($list){
                foreach($list as $k=>$v){
                    $list[$k]['addtime']     = date("Y-m-d H:i:s",$v['addtime']);
                    $list[$k]['shenhetime']  = date("Y-m-d H:i:s",$v['shenhetime']);
                    $list[$k]['paytime']     = date("Y-m-d H:i:s",$v['paytime']);
                    $list[$k]['shixiaotime'] = date("Y-m-d H:i:s",$v['shixiaotime']);
                    $bankbody = json_decode($v['bankbody'],1);
                    $yinhang = Db::name('bank')->field('logo')->where('id',$bankbody['bankid'])->find();
                    $bankbody['logo'] = $this->hostname.$yinhang['logo'];
                    $list[$k]['bankbody'] = $bankbody;
                }
                return json(['code'=>0,'msg'=>'操作成功','data'=>$list,'xiaoji'=>$xiaojic]);
            }else{
                return json(['code'=>1,'msg'=>'操作成功','data'=>$list,'xiaoji'=>$xiaojic]);
            }
        }else{
            return json($token);
        }
    }
    public function getMymingxi(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $one = [];
            //个人开团
            $one['grkts'] = Db::name('groups')->where('tuanzhang_id',$token['myinfo']['id'])->where('status','in',['2','3'])->count();
            //团队开团
            $tdlist = DB::name('member')->field('id')->where('pid1','=',$token['myinfo']['id'])->whereOr('pid2','=',$token['myinfo']['id'])->whereOr('user_id','=',$token['myinfo']['id'])->where(['is_del'=>'n','is_show'=>'y'])->select();
            $tduids = [];
            $tduids[] = 0;
            foreach($tdlist as $k=>$v){
                $tduids[] = $v['id'];
            }
            $one['tdkts'] = Db::name('groups')->where('tuanzhang_id','in',$tduids)->where('status','in',['2','3'])->count();
            $shouyi = Db::name('member_distribution')->field('money,createday,types,status')->where('user_id',$token['myinfo']['id'])->where('status','>','0')->select();
            $shouyiz = [];
            $jinriz  = [];
            $jrfh = [];
            $ktjl = [];
            $wdfh = [];
            $ktprice = [];
            foreach($shouyi as $k=>$v){
                $shouyiz[] = $v['money'];
                if($v['createday']==date("Ymd")){
                    $jinriz[] = $v['money'];
                }
                if($v['createday']==date("Ymd",(time()-60*60*24))){
                    //$jinriz[] = $v['money'];
                    if($v['types']=="fenhong"){
                        $jrfh[] = $v['money'];
                    }
                }
                if($v['types']=="kaituanjiangli"){
                    $ktjl[] = $v['money'];
                }
                if($v['types']=="fenhong"){
                    $wdfh[] = $v['money'];
                }
                if($v['status']=="1"){
                    $ktprice[] = $v['money'];
                }
            }
            $cate = Db::name('member_cate')->field('id,title,thumb,group_fenhong')->where('id',$token['myinfo']['level'])->find();
            $cate['thumb'] = getImage($cate['thumb']);

            $one['zongshouyi']     = sprintf("%.2f",array_sum($shouyiz));
            $one['jinrishouyi']    = sprintf("%.2f",array_sum($jinriz));
            $one['jinrifenhong']   = sprintf("%.2f",array_sum($jrfh));
            $one['kaituanjiangli'] = sprintf("%.2f",array_sum($ktjl));

            $one['yidefenhong']  = sprintf("%.2f",array_sum($wdfh));
            if($cate['group_fenhong']=="-1"){
                $one['wodefenhong'] = "不限";
                $one['kedefenhong'] = "不限";
            }else{
                $one['wodefenhong'] = sprintf("%.2f",$cate['group_fenhong']);
                $one['kedefenhong']  = sprintf("%.2f",($one['wodefenhong']-$one['yidefenhong']));
            }
            $one['keti'] = sprintf("%.2f",array_sum($ktprice));
            return json(['code'=>0,'one'=>$one,'myinfo'=>$token['myinfo'],'level'=>$cate]);
        }else{
            return json($token);
        }
    }
    public function jiangliMx(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $pn    = isset($data['pn']) ? $data['pn'] : 1;
            $limit = isset($data['limit']) ? $data['limit'] : 20;
            $types = isset($data['types']) ? $data['types'] : 'all';
            if($types!="all"){
                $where[] = ['types','=',$types];
            }
            $where[] = ['user_id','=',$token['myinfo']['id']];
            $where[] = ['status','>','0'];
            $start = ($pn-1)*$limit;
            $list = Db::name('member_distribution')->field('money,id,createtime,groups_id')->where($where)->order('createtime desc')->limit($start,$limit)->select();
            $count  = Db::name('member_distribution')->where($where)->count();
            if($list){
                foreach($list as $k=>$v){
                    $list[$k]['createtime'] = date('Y-m-d H:i:s',$v['createtime']);
                    $list[$k]['money'] = sprintf("%.2f",$v['money']);
                    if($v['groups_id']>'0'){
                        $list[$k]['groups'] = Db::name('groups')->field('ordersn')->where('id',$v['groups_id'])->find();
                    }
                }
                return json(['code'=>0,'list'=>$list,'count'=>$count]);
            }else{
                return json(['code'=>1,'list'=>$list,'count'=>$count]);
            }
        }else{
            return json($token);
        }
    }
    public function Mytuanlist(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $pn    = isset($data['pn']) ? $data['pn'] : 1;
            $limit = isset($data['limit']) ? $data['limit'] : 20;
            $state = isset($data['state']) ? $data['state'] : '0';
            $start = ($pn-1)*$limit;
            if($state=="0"){ //个人开团
                $list = Db::name('groups')->alias('g')
                    ->field('g.ordersn,g.addtime,gg.id,gg.thumb,gg.title,gg.groupsprice,gg.description')
                    ->where('g.status','in',['2','3'])
                    ->where('g.uid','=',$token['myinfo']['id'])
                    ->join('groups_goods gg','g.goods_id=gg.id','left')
                    ->order('g.addtime desc')
                    ->limit($start,$limit)
                    ->select();
                foreach($list as $k=>$v){
                    $list[$k]['thumb'] = getImage($v['thumb']);
                    $list[$k]['addtime'] = date("YmdHis",$v['addtime']);
                }
                $count = Db::name('groups')->alias('g')
                    ->where('g.status','in',['1','2','3'])
                    ->where('g.uid','=',$token['myinfo']['id'])
                    ->join('groups_goods gg','g.goods_id=gg.id','left')->count();
            }else{ //团队开团
                $tdlist = DB::name('member')->field('id')->where('pid1','=',$token['myinfo']['id'])->whereOr('pid2','=',$token['myinfo']['id'])->whereOr('user_id','=',$token['myinfo']['id'])->where(['is_del'=>'n','is_show'=>'y'])->select();
                $tduids = [];
                $tduids[] = 0;
                foreach($tdlist as $k=>$v){
                    $tduids[] = $v['id'];
                }
                $list = Db::name('groups')->alias('g')
                    // ->field('g.ordersn,g.addtime,m.nickname,m.avatar,m.id,m.level,m.phone')
                    ->field('g.ordersn,g.addtime,gg.id,gg.thumb,gg.title,gg.groupsprice,gg.description')
                    ->where('g.status','in',['2','3'])
                    ->where('g.uid','in',$tduids)
                    // ->join('member m','m.id=g.uid','left')
                    ->join('groups_goods gg','g.goods_id=gg.id','left')
                    ->order('g.addtime desc')
                    ->limit($start,$limit)
                    ->select();
                foreach($list as $k=>$v){
                    $list[$k]['thumb'] = getImage($v['thumb']);
                    $list[$k]['addtime'] = date("YmdHis",$v['addtime']);
                }
                $count = Db::name('groups')->alias('g')
                    ->field('g.ordersn,g.addtime,m.nickname,m.avatar,m.id,m.level,m.phone')
                    ->where('g.status','in',['1','2','3'])
                    ->where('g.uid','in',$tduids)
                    // ->join('member m','m.id=g.uid','left')
                    ->join('groups_goods gg','g.goods_id=gg.id','left')
                    ->count();
            }
            if($list){
                return json(['code'=>0,'list'=>$list,'count'=>$count]);
            }else{
                return json(['code'=>1,'list'=>$list,'count'=>$count]);
            }
        }else{
            return json($token);
        }
    }

    /********************************************************************************/
    /** 孩子信息
    /********************************************************************************/
    public function getMyChildren(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $list = Db::name('school_student')->field(['id','sex','name','school','classs','no'])->where(['is_del'=>'n','user_id'=>$token['myinfo']['id']])->order('updatetime desc')->select();
            return json(['code'=>0,'msg'=>'操作成功','list'=>$list]);
        }else{
            return json($token);
        }
    }
    public function getMyChildrenDetails(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $one = Db::name('school_student')->field(['id','name','sex','birthday','school','school_id','classs','class_id','no','diqu','diquid'])->where(['id'=>$data['id'],'user_id'=>$token['myinfo']['id'],'is_del'=>'n'])->find();
            if($one){
                $diquid = explode("-",$one['diquid']);
                $one['diquid'] = $diquid;
                $one['age'] = birthday($one['birthday']);
                return json(['code'=>0,'msg'=>'操作成功','one'=>$one]);
            }else{
                return json(['code'=>1,'msg'=>'没有相关数据']);
            }
        }else{
            return json($token);
        }
    }
    public function children_publish(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $uparr['updatetime'] = time();
            $uparr['user_id']      = $token['myinfo']['id'];
            $uparr['name']     = $data['name'];
            $uparr['sex']      = $data['sex'];
            $uparr['birthday'] = $data['date'];
            $uparr['school']   = $data['school'];
            $uparr['classs']    = $data['class'];
            $uparr['no']       = $data['number'];
            $uparr['age']      = birthday($data['date']);
            $uparr['diqu']     = $data['diqu'];
            $uparr['diquid']   = $data['diquid'];
            $uparr['school_id']= $data['schoolid'];
            $uparr['class_id'] = $data['classid'];
            $chengshi = explode("-", $data['diqu']);
            if(isset($chengshi[0])){
                $shengt = str_replace("市",'',$chengshi[0]);
                $shengt = str_replace("自治区",'',$shengt);
                $shengt = str_replace("省",'',$shengt);
                $sheng = Db::name('region')->field('region_id,region_name')->where('region_name','like','%'.$shengt)->where('region_type','1')->find();
                $uparr['sheng'] = $sheng['region_id'];
            }
            if(isset($chengshi[1])){
                $shit = str_replace("市",'',$chengshi[1]);
                $shi = Db::name('region')->field('region_id,region_name')->where('region_name','like','%'.$shit)->where('region_type','2')->find();
                if($shi){
                    $uparr['shi'] = $shi['region_id'];
                }else{
                    $shengt = str_replace("市",'',$chengshi[0]);
                    $shengt = str_replace("自治区",'',$shengt);
                    $shengt = str_replace("省",'',$shengt);
                    $sheng = Db::name('region')->field('region_id,region_name')->where('region_name','like','%'.$shengt)->where('region_type','2')->find();
                    $uparr['shi'] = $sheng['region_id'];
                }
            }
            if(isset($chengshi[2])){
                $qu = Db::name('region')->field('region_id,region_name')->where('region_name','like','%'.$chengshi[2])->where('region_type','3')->find();
                $uparr['qu'] = $qu['region_id'];
            }
            if(isset($data['id'])){
                if($data['id']=='0'){
                    $uparr['addtime'] = $uparr['updatetime'];
                    $res = Db::name('school_student')->insertGetId($uparr);
                }else{
                    $res = Db::name('school_student')->where(['id'=>$data['id'],'user_id'=>$token['myinfo']['id'],'is_del'=>'n'])->update($uparr);
                }
            }else{
                $uparr['addtime'] = $uparr['updatetime'];
                $res = Db::name('school_student')->insertGetId($uparr);
            }
            if($res){
                return json(['code'=>0,'msg'=>'提交成功']);
            }else{
                return json(['code'=>1,'msg'=>'提交失败']);
            }
        }else{
            return json($token);
        }
    }
    public function deleteMychild(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $res = Db::name('school_student')->where(['id'=>$data['id'],'user_id'=>$token['myinfo']['id'],'is_del'=>'n'])->update(['is_del'=>'y','updatetime'=>time()]);
            if($res){
                return json(['code'=>0,'msg'=>'提交成功']);
            }else{
                return json(['code'=>1,'msg'=>'提交失败']);
            }
        }else{
            return json($token);
        }
    }
    /********************************************************************************/
    /** 测评信息
    /********************************************************************************/
    public function getTest(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $limit = 10;
            $where['uid'] = $token['myinfo']['id'];
            $list = Db::name('school_student_ceping')->field(['id','name','zongping','addtime'])->where($where)->order('addtime desc')->limit($limit)->select();
            foreach($list as $k=>$v){
                $list[$k]['addtime'] = Tii($v['addtime']);
            }
            return json(['code'=>0,'list'=>$list]);
        }else{
            return json($token);
        }
    }
    public function getTestList(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $limit = 10;
            $pn = isset($data['page']) ? $data['page'] : '1';
            $start = $pn*$limit;
            $where['uid'] = $token['myinfo']['id'];
            $list = Db::name('school_student_ceping')->field(['id','name','zongping','addtime'])->where($where)->order('addtime desc')->limit($start,$limit)->select();
            foreach($list as $k=>$v){
                $list[$k]['addtime'] = Tii($v['addtime']);
            }
            return json(['code'=>0,'list'=>$list,'pn'=>$pn+1]);
        }else{
            return json($token);
        }
    }
    public function getTestDetail(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $id = $data['id'];
            $one = Db::name('school_student_ceping')->field(['id','addtime','data'],true)->where(['id'=>$id,'uid'=>$token['myinfo']['id']])->find();
            if($one){
                $color = "";
                if($one['zongping']=="良好"){
                    $color = "#23a5f0";
                }else{
                    $color = "#f8891d";
                }
                $one['bmi_num']   = round($one['bmi_num'],1);
                $one['pingce']    = str_replace($one['zongping'],"<span style='color:".$color.";'>".$one['zongping']."</span>",$one['pingce']);
                $one['childinfo'] = Db::name('school_student')->field(['name','sex','birthday'])->where(['id'=>$one['child_id']])->find();
                $one['childinfo']['birthday'] = str_replace("-",'.', $one['childinfo']['birthday']);
                return json(['code'=>0,'one'=>$one]);
            }else{
                return json(['code'=>1,'msg'=>'测评不存在或id错误']);
            }
        }else{
            return json($token);
        }
    }
    public function test_publish(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $add = [];
            foreach($data as $k=>$v){
                if($k=='id'){
                    $add['child_id'] = $v;
                }else if($k=="token"){

                }else{
                    $add[$k] = $v;
                }
            }
            $mychild = Db::name('school_student')->field(['id','name','sex','birthday','school','classs','no'])->where(['id'=>$data['id'],'user_id'=>$token['myinfo']['id'],'is_del'=>'n'])->find();
            if($mychild){
                $diwei = []; $zhongwei =[]; $gaowei =[];
                //BMI指数
                $bmi = $this->checkBMI($add['weight'],$add['height'],$add['age'],$mychild['sex']);
                $add_arr['data']      = json_encode($add);
                $add_arr['uid']       = $token['myinfo']['id'];
                $add_arr['child_id']  = $add['child_id'];
                $add_arr['l_dushu']   = $add['l_dushu'];
                $add_arr['r_dushu']   = $add['r_dushu'];
                $add_arr['l_quguang']  = $add['l_quguang'];
                $add_arr['r_quguang']  = $add['r_quguang'];
                $add_arr['height']     = $add['height'];
                $add_arr['weight']     = $add['weight'];
                $add_arr['age']        = $add['age'];
                $add_arr['name']       = $mychild['name'];
                $add_arr['bmi_num']     = $add['weight']/(($add['height']/100)*($add['height']/100));
                $add_arr['bmi_jieguo']  = $bmi;
                if($bmi=="过轻" || $bmi=="过重"){
                    $zhongwei[] = 1;
                    $add_arr['bmi_ceping'] = "中危";
                }else if($bmi=='肥胖'){
                    $gaowei[] = 1;
                    $add_arr['bmi_ceping'] = "高危";
                }else{
                    $diwei[]  = 1;
                    $add_arr['bmi_ceping'] = "低危";
                }
                $ldushu = $this->checkDushu($add['age'],$add['l_dushu']);
                $add_arr['l_dushu_ceping'] = $ldushu;
                if($ldushu=="高危"){
                    $gaowei[] = 1;
                }else if($ldushu=="中危"){
                    $zhongwei[] = 1;
                }else{
                    $diwei[] = 1;
                }

                $rdushu = $this->checkDushu($add['age'],$add['r_dushu']);
                $add_arr['r_dushu_ceping'] = $rdushu;
                if($rdushu=="高危"){
                    $gaowei[] = 1;
                }else if($rdushu=="中危"){
                    $zhongwei[] = 1;
                }else{
                    $diwei[] = 1;
                }

                $lquguang = $this->checkQuguang($add['age'],$add['l_quguang']);
                $add_arr['l_quguang_ceping'] = $lquguang;
                if($lquguang=="高危"){
                    $gaowei[] = 1;
                }else if($lquguang=="中危"){
                    $zhongwei[] = 1;
                }else{
                    $diwei[] = 1;
                }
                $rquguang = $this->checkQuguang($add['age'],$add['r_quguang']);
                $add_arr['r_quguang_ceping'] = $rquguang;
                if($rquguang=="高危"){
                    $gaowei[] = 1;
                }else if($rquguang=="中危"){
                    $zhongwei[] = 1;
                }else{
                    $diwei[] = 1;
                }
                //母亲视力情况
                if($add['mothor_ds']=="A"){ //低于300
                    $add_arr['mothor_shili'] = '低于300度';
                }else if($add['mothor_ds']=="B"){ // 300~600
                    $add_arr['mothor_shili'] = '300~600度';
                }else if($add['mothor_ds']=="C"){ // 600以上
                    $add_arr['mothor_shili'] = '大于600度';
                }else{
                    $add_arr['mothor_shili'] = '无';
                }
                //父亲亲视力情况
                if($add['father_ds']=="A"){ //低于300
                    $add_arr['father_shili'] = '低于300度';
                }else if($add['father_ds']=="B"){ // 300~600
                    $add_arr['father_shili'] = '300~600度';
                }else if($add['father_ds']=="C"){ // 600以上
                    $add_arr['father_shili'] = '大于600度';
                }else{
                    $add_arr['father_shili'] = '无';
                }
                //父母亲综合视力分析
                if($add['mothor_ds']=="D" && $add['father_ds']=="D"){
                    $diwei[] = 1;
                    $add_arr['fumu_ceping'] = "低危";
                }else if($add['mothor_ds']=="C" || $add['father_ds'] == "C"){
                    $gaowei[] = 1;
                    $add_arr['fumu_ceping'] = "高危";
                }else{
                    $zhongwei[] = 1;
                    $add_arr['fumu_ceping'] = "中危";
                }
                //孩子既往史
                $add_arr['jiwangshi'] = $add['jiwangshi'];
                if($add['jiwangshi']=="足月"){
                    $diwei[] = 1;
                    $add_arr['jiwangshi_ceping'] = "低危";
                }else{
                    $jiwangshi = explode(',',$add['jiwangshi']);
                    if(in_array("鼻炎",$jiwangshi) || in_array("肺炎",$jiwangshi) || in_array("肝炎",$jiwangshi) || in_array("容易感冒",$jiwangshi)){
                        $zhongwei[] = 1;
                        $add_arr['jiwangshi_ceping'] = "中危";
                    }else{
                        $gaowei[] = 1;
                        $add_arr['jiwangshi_ceping'] = "高危";
                    }
                }
                //孩子饮食情况
                $add_arr['yinshi'] = $add['yinshi'];
                if($add['yinshi']=="均衡（一日三餐按时，且无挑食情况）"){
                    $diwei[] = 1;
                    $add_arr['yinshi_ceping'] = "低危";
                }else{
                    $yinshi = explode(",",$add['yinshi']);
                    if(in_array("喜甜食",$yinshi) || in_array("挑食",$yinshi) || in_array("喜油炸热气食物",$yinshi) || in_array("不喜欢吃水果",$yinshi)){
                        $gaowei[] = 1;
                        $add_arr['yinshi_ceping'] = "高危";
                    }else{
                        $zhongwei[] = 1;
                        $add_arr['yinshi_ceping'] = "中危";
                    }
                }
                //孩子睡眠时长
                if($add['shuimian']=="A"){
                    $add_arr['shuimian'] = "小于等于7小时";
                    $gaowei[] = 1;
                    $add_arr['shuimian_ceping'] = "高危";
                }else if($add['shuimian']=="B"){
                    $add_arr['shuimian'] = "8~9小时";
                    $zhongwei[] = 1;
                    $add_arr['shuimian_ceping'] = "中危";
                }else{
                    $add_arr['shuimian'] = "大于等于9小时";
                    $diwei[] = 1;
                    $add_arr['shuimian_ceping'] = "低危";
                }
                //孩子户外运动
                if($add['yundong']=="A"){
                    $add_arr['yundong'] = "1小时";
                    $gaowei[] = 1;
                    $add_arr['yundong_ceping'] = "高危";
                }else if($add['yundong']=="B"){
                    $add_arr['yundong'] = "1~2小时";
                    $zhongwei[] = 1;
                    $add_arr['yundong_ceping'] = "中危";
                }else if($add['yundong']=="C"){
                    $add_arr['yundong'] = "2~3小时";
                    $zhongwei[] = 1;
                    $add_arr['yundong_ceping'] = "中危";
                }else{
                    $add_arr['yundong'] = "3小时以上";
                    $diwei[] = 1;
                    $add_arr['yundong_ceping'] = "低危";
                }
                //孩子用眼情况
                if($add['yongyan']=="A"){
                    $add_arr['yongyan'] = "30分钟以内";
                    $diwei[] = 1;
                    $add_arr['yongyan_ceping'] = "低危";
                }else if($add['yongyan']=="B"){
                    $add_arr['yongyan'] = "30~60分钟";
                    $zhongwei[] = 1;
                    $add_arr['yongyan_ceping'] = "中危";
                }else if($add['yongyan']=="C"){
                    $add_arr['yongyan'] = "60~90分钟";
                    $zhongwei[] = 1;
                    $add_arr['yongyan_ceping'] = "中危";
                }else{
                    $add_arr['yongyan'] = "90分钟以上";
                    $gaowei[] = 1;
                    $add_arr['yongyan_ceping'] = "高危";
                }
                //孩子情况
                $add_arr['qingkuang'] = $add['qingkuang'];
                if($add['qingkuang']=="无以上情况"){
                    $diwei[] = 1;
                    $add_arr['qingkuang_ceping'] = "低危";
                }else{
                    $qingkuang = explode(",",$add['qingkuang']);
                    if(in_array("眼睛红血丝严重",$qingkuang) || in_array("揉眼睛",$qingkuang)){
                        $zhongwei[] = 1;
                        $add_arr['qingkuang_ceping'] = "中危";
                    }else{
                        $gaowei[] = 1;
                        $add_arr['qingkuang_ceping'] = "高危";
                    }
                }
                //预防近视的方法
                $add_arr['yufang'] = $add['yufang'];
                if($add['yufang']=="无任何方法"){
                    $gaowei[] = 1;
                    $add_arr['yufang_ceping'] = "高危";
                }else{
                    $yufang = explode(",",$add['yufang']);
                    if(in_array("眼保健操",$yufang) || in_array("增加户外运动",$yufang)){
                        $diwei[] = 1;
                        $add_arr['yufang_ceping'] = "低危";
                    }else{
                        $zhongowei[] = 1;
                        $add_arr['yufang_ceping'] = "中危";
                    }
                }
                $add_arr['diwei']    = array_sum($diwei);
                $add_arr['zhongwei'] = array_sum($zhongwei);
                $add_arr['gaowei']   = array_sum($gaowei);
                if($add_arr['gaowei']>=6){
                    $add_arr['zongping'] = "高危";
                    $add_arr['pingce'] = $mychild['name']."同学为近视高危人群，未来极易发展为高度近视，同时有可能出现眼底病变，建议立即对视力进行干预，定期对眼底进行检查。";
                }else if($add_arr['gaowei']>=3 && $add_arr['gaowei']<6){
                    $add_arr['zongping'] = "高危";
                    $add_arr['pingce'] = $mychild['name']."同学为近视高危人群，未来极易发展为高度近视，需要立即对视力进行干预";
                }else if($add_arr['gaowei']<3 && $add_arr['gaowei']>0){
                    if($add_arr['zhongwei']>=5){
                        $add_arr['zongping'] = "中危";
                        $add_arr['pingce'] = $mychild['name']."同学为近视中危人群，未来极可能发展为中高度近视，需要尽早对视力进行干预";
                    }else{
                        $add_arr['zongping'] = "中危";
                        $add_arr['pingce'] = $mychild['name']."同学为近视中危人群，未来极易发展为中度近视，需要对视力进行干预，定期检测视力";
                    }
                }else{
                    if($add_arr['zhongwei']>=4){
                        $add_arr['zongping'] = "中危";
                        $add_arr['pingce'] = $mychild['name']."同学为近视中危人群，未来极易发展为中度近视，需要尽早对视力进行干预";
                    }else if($add_arr['zhongwei']<4 && $add_arr['zhongwei']>=2){
                        $add_arr['zongping'] = "疲劳";
                        $add_arr['pingce'] = $mychild['name']."同学为视疲劳人群，未来有可能发展为低度近视，建议定期检测视力";
                    }else if($add_arr['zhongwei']>0){
                        $add_arr['zongping'] = "不良";
                        $add_arr['pingce'] = $mychild['name']."同学为用眼不良人群，未来有可能出现视力问题,建议定期检测视力";
                    }else{
                        $add_arr['zongping'] = "良好";
                        $add_arr['pingce'] = "恭喜您！".$mychild['name']."同学用眼情况良好，暂时没有近视风险,请继续保持";
                    }
                }
                $add_arr['addtime'] = time();
                $add_arr['day']     = date('Ymd',time());
                $checkold = Db::name('school_student_ceping')->field(['id'])->where(['uid'=>$add_arr['uid'],'child_id'=>$add_arr['child_id'],'day'=>$add_arr['day']])->find();
                if($checkold){
                    //$res = Db::name('school_student_ceping')->where(['id'=>$id])->update($add_arr);
                    $id = $checkold['id'];
                }else{
                    $res = Db::name('school_student_ceping')->insertGetId($add_arr);
                    $id = $res;
                }
                if($res){
                    return json(['code'=>0,'msg'=>'测评成功，等待测评结果','id'=>$id]);
                }else{
                    return json(['code'=>1,'msg'=>'测评失败！请联系管理员']);
                }
            }else{
                return json(['code'=>1,'msg'=>'没有相关数据']);
            }
        }else{
            return json($token);
        }
    }
    public function checkBMI($weight,$height,$age,$sex){
        $bmi = $weight/(($height/100)*($height/100));
        $where = [];
        $where[] = ['sex','=',$sex];
        $where[] = ['age','=',$age];
        $where[] = ['min_num',['eq','0'],['elt',$bmi],'or'];
        $where[] = ['max_num',['eq','0'],['gt',$bmi],'or'];
        $checkbmi = Db::name('shili_bmi')->field('id,jieguo')->where($where)->find();
        if($checkbmi){
            return $checkbmi['jieguo'];
        }else{
            if($bmi<18.5){
                return '过轻';
            }else if($bmi>=18.5 && $bmi<24){
                return '正常';
            }else if($bmi>=24 && $bmi<27){
                return '过重';
            }else{
                return '肥胖';
            }
        }
    }
    public function checkDushu($age,$dushu){
        $where = [];
        $where[] = ['age','=',$age];
        $where[] = ['min_num',['eq','0'],['elt',$dushu],'or'];
        $where[] = ['max_num',['eq','0'],['gt',$dushu],'or'];
        $check = Db::name('shili_duizhao')->field(['id','jieguo'])->where($where)->find();
        if($check){
            return $check['jieguo'];
        }else{
            if($dushu<0.8){
                return '高危';
            }else if($dushu>=0.8 && $dushu<1.2){
                return '中危';
            }else{
                return '低危';
            }
        }
    }
    public function checkQuguang($age,$quguang){
        $where = [];
        $where[] = ['age','=',$age];
        $where[] = ['min_num',['eq','0'],['elt',$quguang],'or'];
        $where[] = ['max_num',['eq','0'],['gt',$quguang],'or'];
        $check = Db::name('shili_quguang')->field('id,jieguo')->where($where)->find();
        if($check){
            return $check['jieguo'];
        }else{
            if($quguang<-100){
                return '高危';
            }else if($quguang>=-100 && $quguang<-50){
                return '中危';
            }else{
                return '低危';
            }
        }
    }
    /********************************************************************************/
    /** 咨询信息
    /********************************************************************************/
    public function getZixun(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $limit = 15;
            $list = Db::name('zixun')->field(['is_show','is_del'],true)->where(['uid'=>$token['myinfo']['id'],'is_del'=>'n','is_show'=>'y'])->order('addtime desc')->limit(0,$limit)->select();
            foreach($list as $k=>$v){
                $list[$k]['addtime'] = Tii($v['addtime']);
                $list[$k]['huifu']   = Db::name('zixun_huifu')->field('id')->where(['pid'=>$v['id'],'is_show'=>'y','is_del'=>'n','types'=>'huifu'])->count();
            }
            return json(['code'=>0,'list'=>$list]);
        }else{
            return json($token);
        }
    }
    public function getZixunDetail(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $one = Db::name('zixun')->field(['is_show','is_del'],true)->where(['id'=>$data['id'],'uid'=>$token['myinfo']['id'],'is_del'=>'n','is_show'=>'y'])->find();
            if($one){
                $one['addtime'] = Tii($one['addtime']);
                if($one['pic']){
                    $piclist = explode(",",$one['pic']);
                    foreach($piclist as $k=>$v){
                        $cc = str_replace("./",'/',$v);
                        $cc = str_replace("\\",'/',$cc);
                        $piclist[$k] = $this->hostname.$cc;
                    }
                    $one['piclist'] = $piclist;
                }
                $list = Db::name('zixun_huifu')->field(['is_show','is_del'],true)->where(['pid'=>$data['id'],'is_del'=>'n','is_show'=>'y'])->order('addtime asc')->select();
                foreach($list as $k=>$v){
                    $list[$k]['addtime'] = Tii($v['addtime']);
                }
                return json(['code'=>0,'one'=>$one,'list'=>$list]);
            }else{
                return json(['cdoe'=>'11','msg'=>'没有该咨询详情']);
            }
        }else{
            return json($token);
        }
    }
    //在线咨询
    public function addZixun(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            if($data['content']==""){
                return json(['code'=>'12','msg'=>'请填写你要咨询的内容']);
            }
            $add['uid']     = $token['myinfo']['id'];
            $add['content'] = $data['content'];
            $add['pic']     = $data['pic'];
            $add['addtime'] = time();
            $res = Db::name('zixun')->insert($add);
            if($res){
                return json(['code'=>'0','msg'=>'提交成功']);
            }else{
                return json(['code'=>'11','msg'=>'提交失败']);
            }
        }else{
            return json($token);
        }
    }
    public function addZixunZhuiwen(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            if($data['content']==""){
                return json(['code'=>'12','msg'=>'请填写你要咨询的内容']);
            }
            $add['uid']     = $token['myinfo']['id'];
            $add['pid']     = $data['id'];
            $add['content'] = $data['content'];
            $add['types']   = "zhuiwen";
            $add['addtime'] = time();
            $add['is_show'] = "y";
            $add['is_del']  = "n";
            $res = Db::name('zixun_huifu')->insertGetId($add);
            if($res){
                $add['id'] = $res;
                $add['addtime'] = Tii($add['addtime']);
                return json(['code'=>'0','msg'=>'提交成功','one'=>$add]);
            }else{
                return json(['code'=>'11','msg'=>'提交失败']);
            }
        }else{
            return json($token);
        }
    }
    public function uploadfabu($size=1024*1024*10,$ext='jpg,png,gif,jpeg',$save_dir='./uploads',$rule='date'){
        if($this->request->file('file')){
            $file = $this->request->file('file');
            $info = $file->validate(['size'=>$size,'ext'=>$ext])->rule($rule)->move($save_dir);
            if($info){
                $url = $info->getSaveName();
                $filePath = str_replace("./","/",$save_dir.'/'.$url);
                return $filePath;
            }else{
                $res['code'] = 1;
                $res['msg']  ='上传失败：'.$file->getError();
                return json($res);die;
            }
        }else{
            $res['code'] = 1;
            $res['msg']  = '没有上传图片';
            return json($res);die;
        }
    }
    /********************************************************************************/
    /** 视力档案信息
    /********************************************************************************/
    public function getMyShili(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $list = Db::name('school_student')->field(['id','sex','name','school','classs','no'])->where(['is_del'=>'n','user_id'=>$token['myinfo']['id']])->order('updatetime desc')->select();
            foreach($list as $k=>$v){
                $onefind = Db::name('school_student_shili')->field(['id','addtime'])->where(['c_number'=>$v['no']])->order('addtime desc')->limit(1)->find();
                if($onefind){
                    $list[$k]['last_addtime'] = date("Y-m-d",$onefind['addtime']);
                }else{
                    $list[$k]['last_addtime'] = "未做筛查";
                }
            }
            return json(['code'=>0,'msg'=>'操作成功','list'=>$list]);
        }else{
            return json($token);
        }
    }
    public function getMyShiliDetail(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $one = Db::name('school_student')->field(['id','name','sex','school','classs','no'])->where(['id'=>$data['id'],'user_id'=>$token['myinfo']['id'],'is_del'=>'n'])->find();
            if($one){
                $list = Db::name('school_student_shili')->field(['is_del'],true)->where(['c_number'=>$one['no']])->order('addtime desc')->select();
                foreach($list as $k=>$v){
                    $list[$k]['addtime'] = date("Y-m-d",$v['addtime']);
                    $list[$k]['updatetime'] = date("Y-m-d",$v['updatetime']);
                }
                return json(['code'=>0,'msg'=>'操作成功','one'=>$one,'list'=>$list]);
            }else{
                return json(['code'=>1,'msg'=>'没有相关数据']);
            }
        }else{
            return json($token);
        }
    }
    /********************************************************************************/
    /** 收藏信息
    /********************************************************************************/
    public function getMyshoucang(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $limit = 15;
            //社区
            $sqlist = Db::name('member_collection')->alias('a')
                ->join('shequ s', 'a.sq_id=s.id', 'left')
                ->field('s.title,s.addtime,s.uid,s.aid,s.pic,s.id,s.content')
                ->where(['a.uid'=>$token['myinfo']['id'],'a.wz_id'=>'0','a.jz_id'=>'0'])
                ->where('a.sq_id','neq','0')
                ->order('a.addtime desc')
                ->limit($limit)
                ->select();
            $sqids = [];
            foreach ($sqlist as $k => $v) {
                $sqlist[$k]['addtime'] = Tiia($v['addtime']);
                $sqlist[$k]['pinglunums'] = Db::name('shequ_pinglun')->field('id')->where(['pid'=>$v['id'],'pl_id'=>'0','is_del'=>'n'])->count();
                if($v['pic']){
                    $piclist = explode(",",$v['pic']);
                    foreach($piclist as $key=>$val){
                        $piclist[$key] = $this->hostname.$val;
                    }
                    $sqlist[$k]['pic']     = $piclist[0];
                }else{
                    $sqlist[$k]['pic']     = "";
                }
                if($v['uid']=="0"){
                    $sqlist[$k]['author']  = getAdmin($v['aid']);
                }else{
                    $sqlist[$k]['author']  = getMember($v['uid']);
                }
                $sqids[] = $v['id'];
            }
            //资讯
            $newsids = [];
            $newslist = Db::name('member_collection')->alias('a')
                ->join('news s', 'a.wz_id=s.id', 'left')
                ->field('s.title,s.updatetime,s.admin_id,s.thumb,s.id,s.description')
                ->where(['a.uid'=>$token['myinfo']['id'],'a.sq_id'=>'0','a.jz_id'=>'0'])
                ->where('a.wz_id','neq','0')
                ->order('a.addtime desc')
                ->limit($limit)
                ->select();
            foreach($newslist as $k=>$v){
                $newslist[$k]['thumb'] = getImage($v['thumb']);
                $newslist[$k]['updatetime'] = Tiia($v['updatetime']);
                $newslist[$k]['zan']  = Db::name('member_zan')->field('id')->where(['wz_id'=>$v['id']])->count();
                $newsids[] = $v['id'];
            }
            //讲座
            $jzids = [];
            $jzlist = Db::name('member_collection')->alias('a')
                ->join('shili_video s', 'a.jz_id=s.id', 'left')
                ->field('s.title,s.updatetime,s.admin_id,s.img,s.id,s.description')
                ->where(['a.uid'=>$token['myinfo']['id'],'a.sq_id'=>'0','a.wz_id'=>'0'])
                ->where('a.jz_id','neq','0')
                ->order('a.addtime desc')
                ->limit($limit)
                ->select();
            foreach($jzlist as $k=>$v){
                $jzlist[$k]['img'] = $this->getImage($v['img']);
                $jzlist[$k]['updatetime'] = Tiia($v['updatetime']);
                $jzids[] = $v['id'];
            }
            return json(['code'=>0,'msg'=>'操作成功','sqlist'=>$sqlist,'sqids'=>implode(",",$sqids),'newslist'=>$newslist,'newsids'=>implode(",",$newsids),'jzlist'=>$jzlist,'jzids'=>implode(",",$jzids)]);
        }else{
            return json($token);
        }
    }
    public function delShoucang(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $where['uid'] = $token['myinfo']['id'];
            if($data['ind']=="0"){ //社区
                if($data['quanxuan']=="all"){
                    $where['sq_id'] = ['in',$data['allids']];
                }else{
                    $where['sq_id'] = ['in',$data['ids']];
                }
            }else if($data['ind']=="1"){ //资讯
                if($data['quanxuan']=="all"){
                    $where['wz_id'] = ['in',$data['allids']];
                }else{
                    $where['wz_id'] = ['in',$data['ids']];
                }
            }else{ //讲座
                if($data['quanxuan']=="all"){
                    $where['jz_id'] = ['in',$data['allids']];
                }else{
                    $where['jz_id'] = ['in',$data['ids']];
                }
            }
            if(($data['quanxuan']=="no" && $data['ids']=="") OR ($data['quanxuan']=="all" && $data['allids']=="")){
                return json(['code'=>'1','msg'=>'请选择要删除的内容']);
            }else{
                $res = Db::name('member_collection')->where($where)->delete();
                if($res){
                    return json(['code'=>'0','msg'=>'提交成功']);
                }else{
                    return json(['code'=>'1','msg'=>'提交失败']);
                }
            }
        }else{
            return json($token);
        }
    }
    public function addShoucang(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $types = isset($data['types'])?$data['types']:'wz_id';
            $add['uid']   = $token['myinfo']['id'];
            $add[$types] = $data['id'];
            $ck = Db::name('member_collection')->where($add)->find();
            if($ck){
                $res = Db::name('member_collection')->where($add)->delete();
            }else{
                $add['addtime'] = time();
                $add['is_del']  = 'n';
                $res = Db::name('member_collection')->insert($add);
            }
            if($res){
                return json(['code'=>'0','msg'=>'操作成功']);
            }else{
                return json(['code'=>'1','msg'=>'操作失败']);
            }
        }else{
            return json($token);
        }
    }
    public function addZan(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $types = isset($data['types'])?$data['types']:'wz_id';
            $add['uid']   = $token['myinfo']['id'];
            $add[$types] = $data['id'];
            $ck = Db::name('member_zan')->where($add)->find();
            if($ck){
                $res = Db::name('member_zan')->where($add)->delete();
            }else{
                $add['addtime'] = time();
                $add['is_del']  = 'n';
                $res = Db::name('member_zan')->insert($add);
            }
            if($res){
                return json(['code'=>'0','msg'=>'操作成功']);
            }else{
                return json(['code'=>'1','msg'=>'操作失败']);
            }
        }else{
            return json($token);
        }
    }
    /********************************************************************************/
    /** 社区信息
    /********************************************************************************/
    public function getMyShequ(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $sq_limit  = 15;
            $sq_pn = isset($data['sq_pn']) ? $data['sq_pn'] : '1';
            $sq_start = ($sq_pn-1)*$sq_limit;
            $sqlist = Db::name('shequ')->field(['is_del'=>'n'],true)->where(['uid'=>$token['myinfo']['id'],'is_del'=>'n'])->order("addtime desc")->limit($sq_start,$sq_limit)->select();
            $sqcount = Db::name('shequ')->where(['uid'=>$token['myinfo']['id'],'is_del'=>'n'])->count();
            foreach ($sqlist as $k => $v) {
                $sqlist[$k]['addtime'] = Tiia($v['addtime']);
                $sqlist[$k]['pinglunums'] = Db::name('shequ_pinglun')->field('id')->where(['pid'=>$v['id'],'pl_id'=>'0','is_del'=>'n'])->count();
                if($v['pic']){
                    $piclist = explode(",",$v['pic']);
                    foreach($piclist as $key=>$val){
                        $piclist[$key] = $this->hostname.$val;
                    }
                    $sqlist[$k]['pic']     = $piclist[0];
                }else{
                    $sqlist[$k]['pic']     = "";
                }
            }

            $pl_limit  = 15;
            $pl_pn = isset($data['pl_pn']) ? $data['pl_pn'] : '1';
            $pl_start = ($pl_pn-1)*$pl_limit;
            $pllist = Db::name('shequ_pinglun')->where(['is_del'=>'n','huifu_uid'=>$token['myinfo']['id']])->order("addtime desc")->limit($pl_start,$pl_limit)->select();
            foreach($pllist as $k=>$v){
                $pllist[$k]['addtime'] = Tiia($v['addtime']);
            }
            $plcount = Db::name('shequ_pinglun')->where(['is_del'=>'n','huifu_uid'=>$token['myinfo']['id']])->count();

            return json(['code'=>0,'msg'=>'操作成功','sqlist'=>$sqlist,'sqlimit'=>$sq_limit,'sqpn'=>$sq_pn,'sqcount'=>$sqcount,'pllist'=>$pllist,'pllimit'=>$pl_limit,'plpn'=>$pl_pn,'plcount'=>$plcount]);
        }else{
            return json($token);
        }
    }
    public function getMyShequlist(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $sq_limit  = 15;
            $sq_pn = isset($data['sq_pn']) ? $data['sq_pn'] : '1';
            $sq_start = ($sq_pn-1)*$sq_limit;
            $sqlist = Db::name('shequ')->field(['is_del'=>'n'],true)->where(['uid'=>$token['myinfo']['id'],'is_del'=>'n'])->order("addtime desc")->limit($sq_start,$sq_limit)->select();
            foreach ($sqlist as $k => $v) {
                $sqlist[$k]['addtime'] = Tiia($v['addtime']);
                $sqlist[$k]['pinglunums'] = Db::name('shequ_pinglun')->field('id')->where(['pid'=>$v['id'],'pl_id'=>'0','is_del'=>'n'])->count();
                if($v['pic']){
                    $piclist = explode(",",$v['pic']);
                    foreach($piclist as $key=>$val){
                        $piclist[$key] = $this->hostname.$val;
                    }
                    $sqlist[$k]['pic']     = $piclist[0];
                }else{
                    $sqlist[$k]['pic']     = "";
                }
            }
            return json(['code'=>0,'msg'=>'操作成功','sqlist'=>$sqlist,'sqpn'=>$sq_pn]);
        }else{
            return json($token);
        }
    }
    public function getMyShequlista(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $pl_limit  = 15;
            $pl_pn = isset($data['pl_pn']) ? $data['pl_pn'] : '1';
            $pl_start = ($pl_pn-1)*$pl_limit;
            $pllist = Db::name('shequ_pinglun')->where(['is_del'=>'n','huifu_uid'=>$token['myinfo']['id']])->order("addtime desc")->limit($pl_start,$pl_limit)->select();
            foreach($pllist as $k=>$v){
                $pllist[$k]['addtime'] = Tiia($v['addtime']);
            }
            $plcount = Db::name('shequ_pinglun')->where(['is_del'=>'n','huifu_uid'=>$token['myinfo']['id']])->count();

            return json(['code'=>0,'msg'=>'操作成功','pllist'=>$pllist,'plpn'=>$pl_pn]);
        }else{
            return json($token);
        }
    }
    public function getMyShequDetail(){
        $data = input();
        $id = isset($data['sl_id']) ? $data['sl_id'] : '0';
        $token = isset($data['token']) ? $data['token'] : '';
        $one = Db::name('shequ')->field(['is_show','is_del'],true)->where(['id'=>$id,'is_del'=>'n','is_show'=>'y'])->find();
        if($one){
            if($one['uid']=="0"){
                $one['author']  = getAdmin($one['aid']);
            }else{
                $one['author']  = getMember($one['uid']);
            }
            $piclist = explode(",",$one['pic']);
            $one['img']       = $piclist;
            $one['author_qz'] = '发表于';
            $one['addtime'] = Tii($one['addtime']);
            $where['pid']    = $id;
            $where['is_del'] = 'n';
            $limit = 10;
            $pllist = Db::name('shequ_pinglun')->where($where)->limit($limit)->order('addtime desc')->select();
            foreach($pllist as $k=>$v){
                if($v['fabu_uid']=="0"){
                    $pllist[$k]['author'] = Db::name('admin')->field(['nickname','username','head_pic'])->where(['id'=>$v['fabu_aid']])->find();
                    if($pllist[$k]['author']['head_pic']){
                        $pllist[$k]['author']['head_pic'] = $this->hostname.$pllist[$k]['author']['head_pic'];
                    }else{
                        $pllist[$k]['author']['head_pic'] = "";
                    }
                }else{
                    $pllist[$k]['author'] = Db::name('member')->field(['nickname','username','head_pic','avatar'])->where(['id'=>$v['fabu_uid']])->find();
                    if($pllist[$k]['author']['head_pic']){
                        $pllist[$k]['author']['head_pic'] = $this->hostname.$pllist[$k]['author']['head_pic'];
                    }else{
                        $pllist[$k]['author']['head_pic'] = "";
                    }
                    if($pllist[$k]['author']['avatar']){
                        $pllist[$k]['author']['avatar'] = $this->hostname.$pllist[$k]['author']['avatar'];
                    }else{
                        $pllist[$k]['author']['avatar'] = "";
                    }
                }
                $pllist[$k]['addtime'] = Tiia($v['addtime']);
            }
            $one['pinglun'] = $pllist;
            $one['plcount'] = Db::name('shequ_pinglun')->field('id')->where(['pid'=>$id,'is_del'=>'n'])->count();
            return json(['code'=>'0','data'=>$one,'id'=>$id]);
        }else{
            return json(['code'=>'1','data'=>'没有相关话题','id'=>$id]);
        }
    }
    /********************************************************************************/
    /** 地址信息
    /********************************************************************************/
    public function getMyAddressList(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $list = Db::name('member_address')->field(['id','name','phone','address','menpai','country','province','city','diqu','diquid','aorder'])->where(['uid'=>$token['myinfo']['id'],'is_del'=>'n'])->order('aorder desc')->select();
            if($list){
                return json(['code'=>0,'msg'=>'操作成功','list'=>$list]);
            }else{
                return json(['code'=>0,'msg'=>'没有相关数据']);
            }
        }else{
            return json($token);
        }
    }
    public function getMyAddress(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $one = Db::name('member_address')->field(['id','name','phone','address','menpai','country','province','city','diqu','diquid','aorder'])->where(['id'=>$data['id'],'uid'=>$token['myinfo']['id'],'is_del'=>'n'])->find();
            if($one){
                return json(['code'=>0,'msg'=>'操作成功','one'=>$one]);
            }else{
                return json(['code'=>0,'msg'=>'没有相关数据']);
            }
        }else{
            return json($token);
        }
    }
    public function address_publish(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $uparr['updatetime'] = time();
            $uparr['uid']     = $token['myinfo']['id'];
            $uparr['name']    = $data['name'];
            $uparr['phone']   = $data['phone'];
            $uparr['diqu']    = $data['diqu'];
            $uparr['diquid']  = $data['diquid'];
            $uparr['address'] = $data['address'];
            $uparr['menpai']  = isset($data['menpai']) ? $data['menpai'] : '';
            $chengshi = explode("-", $data['diqu']);
            if(isset($chengshi[0])){
                $shengt = str_replace("市",'',$chengshi[0]);
                $shengt = str_replace("自治区",'',$shengt);
                $shengt = str_replace("省",'',$shengt);
                $sheng = Db::name('region')->field('region_id,region_name')->where('region_name','like','%'.$shengt)->where('region_type','1')->find();
                $uparr['province'] = $sheng['region_id'];
            }
            if(isset($chengshi[1])){
                $shit = str_replace("市",'',$chengshi[1]);
                $shi = Db::name('region')->field('region_id,region_name')->where('region_name','like','%'.$shit)->where('region_type','2')->find();
                if($shi){
                    $uparr['city'] = $shi['region_id'];
                }else{
                    $shengt = str_replace("市",'',$chengshi[0]);
                    $shengt = str_replace("自治区",'',$shengt);
                    $shengt = str_replace("省",'',$shengt);
                    $sheng = Db::name('region')->field('region_id,region_name')->where('region_name','like','%'.$shengt)->where('region_type','2')->find();
                    $uparr['city'] = $sheng['region_id'];
                }
            }
            if(isset($chengshi[2])){
                $qu = Db::name('region')->field('region_id,region_name')->where('region_name','like','%'.$chengshi[2])->where('region_type','3')->find();
                $uparr['qu'] = $qu['region_id'];
            }
            $aorder = isset($data['aorder']) ? $data['aorder'] : 0;
            if($aorder!=0){
                $gx = Db::name('member_address')->where('uid',$token['myinfo']['id'])->update(['aorder'=>0]);
                $uparr['aorder'] = 1;
            }else{
                $uparr['aorder'] = 0;
            }
            if(isset($data['id'])){
                if($data['id']=='0'){
                    $uparr['addtime'] = $uparr['updatetime'];
                    $res = Db::name('member_address')->insertGetId($uparr);
                    $uparr['id'] = $res;
                }else{
                    $res = Db::name('member_address')->where(['id'=>$data['id'],'uid'=>$token['myinfo']['id'],'is_del'=>'n'])->update($uparr);
                    $uparr['id'] = $data['id'];
                }
            }else{
                $uparr['addtime'] = $uparr['updatetime'];
                $res = Db::name('member_address')->insertGetId($uparr);
                $uparr['id'] = $res;
            }
            if($res){
                return json(['code'=>0,'msg'=>'操作成功','returnData'=>$uparr]);
            }else{
                return json(['code'=>1,'msg'=>'操作失败']);
            }
        }else{
            return json($token);
        }
    }
    public function deleteMyaddress(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $res = Db::name('member_address')->where(['id'=>$data['id'],'uid'=>$token['myinfo']['id'],'is_del'=>'n'])->update(['is_del'=>'y','updatetime'=>time()]);
            if($res){
                $list = Db::name('member_address')->field(['id','name','phone','address','menpai','country','province','city','diqu','diquid','aorder'])->where(['uid'=>$token['myinfo']['id'],'is_del'=>'n'])->order('aorder desc')->select();
                return json(['code'=>0,'msg'=>'操作成功','list'=>$list]);
            }else{
                return json(['code'=>1,'msg'=>'操作失败']);
            }
        }else{
            return json($token);
        }
    }
    public function morenMyaddress(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $rr = Db::name('member_address')->where(['uid'=>$token['myinfo']['id']])->update(['aorder'=>0]);
            $res = Db::name('member_address')->where(['id'=>$data['id'],'uid'=>$token['myinfo']['id'],'is_del'=>'n'])->update(['aorder'=>1,'updatetime'=>time()]);
            if($res){
                $list = Db::name('member_address')->field(['id','name','phone','address','menpai','country','province','city','diqu','diquid','aorder'])->where(['uid'=>$token['myinfo']['id'],'is_del'=>'n'])->order('aorder desc')->select();
                return json(['code'=>0,'msg'=>'操作成功','list'=>$list]);
            }else{
                return json(['code'=>1,'msg'=>'操作失败']);
            }
        }else{
            return json($token);
        }
    }
    /********************************************************************************/
    /** 打卡信息
    /********************************************************************************/
    public function getMydaka(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $token['myinfo']['jifen'] = $this->getJifen($token['myinfo']['id']);
            $dakalist = Db::name('member_daka')->field(['id',"day","time"])->where(['uid'=>$token['myinfo']['id'],'year'=>date("Y"),'mouth'=>date('m')])->order("day asc")->select();
            $dklist = [];
            $daylist = [];
            foreach($dakalist as $k=>$v){
                $dklist[] = $v['day'];
                $daylist[] = $v['time'];
            }
            $today = Db::name('member_daka')->field(['id',"day"])->where(['uid'=>$token['myinfo']['id'],'year'=>date("Y"),'mouth'=>date('m'),'day'=>date('d')])->find();
            if($today){
                $yq = 1;
            }else{
                $yq = 0;
            }
            $lxday = 0;
            if(!empty($daylist)){
                $lxday = getContinueDay(array_unique($daylist));
            }
            return json(['code'=>0,'myinfo'=>$token['myinfo'],'dakalist'=>$dklist,'today'=>$yq,'lxday'=>$lxday,'daylist'=>$daylist]);
        }else{
            return json($token);
        }
    }
    public function AddMydaka(){
        $data = input();
        $token = $this->CheckToken($data['token']);
        if($token['code']=='0'){
            $add['uid']   = $token['myinfo']['id'];
            $add['year']  = date('Y');
            $add['mouth'] = date('m');
            $add['day']   = date('d');
            $check = Db::name('member_daka')->field('id')->where($add)->find();
            if($check){
                return json(['code'=>11,'msg'=>'您今日已签到过啦']);
            }else{
                $add['time'] = time();
                $res = Db::name('member_daka')->insertGetId($add);
                if($res){
                    $jf_add['uid']     = $token['myinfo']['id'];
                    $jf_add['addtime'] = time();
                    $jf_add['types']   = '1';
                    $jf_add['day']     = date('Ymd',time());
                    $jf_add['jifen']   = $data['jifen'];
                    $jf_add['title']   = "每日签到积分";
                    $jf_add['pid']     = '1';
                    $jf_add['description'] = date("Y年m月d日",time())."每日签到获取积分 + ".$data['jifen'];
                    $cks = Db::name('member_jifen')->field('id')->where(['uid'=>$token['myinfo']['id'],'pid'=>'1','types'=>'1','day'=>$jf_add['day']])->find();
                    if(!$cks){
                        $raa = Db::name('member_jifen')->insert($jf_add);
                    }
                    return json(['code'=>0,'msg'=>'签到成功']);
                }else{
                    return json(['code'=>12,'msg'=>'操作失败']);
                }
            }
        }else{
            return json($token);
        }
    }
}