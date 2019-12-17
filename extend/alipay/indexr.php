<?php
	require_once 'aop/AopClient.php';
	require_once 'aop/request/AlipayTradeRefundRequest.php';
	require_once 'aop/SignData.php';
	$aop     = new AopClient();
	$request = new AlipayTradeRefundRequest();
?>