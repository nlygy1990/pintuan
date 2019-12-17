打开WxPay.Config.php文件，配置以下常量值：
APPID 应用APPID，开户邮件中可获取。
MCHID 商户号，开户邮件中可获取。
KEY API密钥(32位数字或英文字符)，登录商户平台的API安全中设置。
NOTIFY_URL 商户号，订单通知URL地址。
部署服务器后访问index.php获取订单，需要提交total参数(单位为元)，如： http://demo.dcloud.net.cn/payment/wxpayv3.HBuilder/?total=1 这是可用于生成在HBuilder调试基座可使用的订单示例地址，其中total值为要支付的金额。