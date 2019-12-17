<?php
namespace think;

/**
 * AES加密类
 * @author      zoshny<zxy@zoshny.com>
 * @version     1.0.0
 */

class AES{
	/**
	 * var string $method 加解密方法
	 */
	protected static $method = "AES-128-ECB";
 
	/**
	 * var string $secret_key 密钥
	 */
	protected static $secret_key = "fEnGcHaOwAn.c0m";
 
	/**
	 * var string $iv 向量
	 */
	protected static $iv = '';
 
	/**
	 * var string $options 
	 */
	protected static $options = 0;


	/**
	 * 构造函数
	 *
	 */
	public function __construct(){}

	/**
	 * 设置密钥
	 *
	 * @param string $key 密钥
	 */
	public static function setSecretKey($key = "fEnGcHaOwAn.c0m")
	{
		self::$secret_key = $key;
		return $this;
	}

	/**
	 * 加密方法，对数据进行加密，返回加密后的数据
	 *
	 * @param string $data 要加密的数据
	 *
	 * @return string
	 *
	 */
	public static function encrypt($data = "")
	{
		return openssl_encrypt($data, self::$method, self::$secret_key, self::$options, self::$iv);
	}
 
	/**
	 * 解密方法，对数据进行解密，返回解密后的数据
	 *
	 * @param string $data 要解密的数据
	 *
	 * @return string
	 *
	 */
	public static function decrypt($data = ""){
		return openssl_decrypt($data, self::$method, self::$secret_key, self::$options, self::$iv);
	}


}