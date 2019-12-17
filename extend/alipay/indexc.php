<?php
	require_once 'aop/AopClient.php';
	require_once 'aop/request/AlipayTradeQueryRequest.php';
	require_once 'aop/SignData.php';
	$aop     = new AopClient();
	$request = new AlipayTradeQueryRequest();
?>