<?php
/**
 * Send rest query to Bitrix24.
 *
 * @param $method - Rest method, ex: methods
 * @param array $params - Method params, ex: Array()
 * @param array $auth - Authorize data, received from event
 * @param boolean $authRefresh - If authorize is expired, refresh token
 * @return mixed
 */
function restCommand($method, array $params = Array(), array $auth = Array(), $authRefresh = true)
{
	$queryUrl = $auth["client_endpoint"].$method;
	$queryData = http_build_query(array_merge($params, array("auth" => $auth["access_token"])));

	$curl = curl_init();

	curl_setopt_array($curl, array(
		CURLOPT_POST => 1,
		CURLOPT_HEADER => 0,
		CURLOPT_RETURNTRANSFER => 1,
		CURLOPT_SSL_VERIFYPEER => 1,
		CURLOPT_URL => $queryUrl,
		CURLOPT_POSTFIELDS => $queryData,
	));

	$result = curl_exec($curl);
	curl_close($curl);

	$result = json_decode($result, 1);

	if ($authRefresh && isset($result['error']) && in_array($result['error'], array('expired_token', 'invalid_token')))
	{
		$auth = restAuth($auth);
		if ($auth)
		{
			$result = restCommand($method, $params, $auth, false);
		}
	}

	return $result;
}

/**
 * Get new authorize data if you authorize is expire.
 *
 * @param array $auth - Authorize data, received from event
 * @return bool|mixed
 */
function restAuth($auth)
{
	if (!CLIENT_ID || !CLIENT_SECRET)
		return false;

	if(!isset($auth['refresh_token']))
		return false;

	$queryUrl = 'https://oauth.bitrix.info/oauth/token/';
	$queryData = http_build_query($queryParams = array(
		'grant_type' => 'refresh_token',
		'client_id' => CLIENT_ID,
		'client_secret' => CLIENT_SECRET,
		'refresh_token' => $auth['refresh_token'],
	));

	$curl = curl_init();

	curl_setopt_array($curl, array(
		CURLOPT_HEADER => 0,
		CURLOPT_RETURNTRANSFER => 1,
		CURLOPT_URL => $queryUrl.'?'.$queryData,
	));

	$result = curl_exec($curl);
	curl_close($curl);

	$result = json_decode($result, 1);
	if (!isset($result['error']))
	{
		$appsConfig = Array();
		if (file_exists(__DIR__.'/config.php'))
			include(__DIR__.'/config.php');

		$result['application_token'] = $auth['application_token'];
		$appsConfig[$auth['application_token']]['AUTH'] = $result;
		saveParams($appsConfig);
	}
	else
	{
		$result = false;
	}

	return $result;
}

/**
 * Save application configuration.
 *
 * @param $params
 * @return bool
 */
function saveParams($params)
{
	$config = "<?php\n";
	$config .= "\$appsConfig = ".var_export($params, true).";\n";

	file_put_contents(__DIR__."/config.php", $config);

	return true;
}

/**
 * Write data to log file. (by default disabled)
 *
 * @param mixed $data
 * @param string $title
 * @return bool
 */
function writeToLog($data, $title = '')
{
	if (!DEBUG_FILE_NAME) return false;

	$log = "\n------------------------\n";
	$log .= date("d.m.Y H:i:s")."\n";
	$log .= (strlen($title) > 0 ? $title : 'DEBUG')."\n";
	$log .= print_r($data, 1);
	$log .= "\n------------------------\n";

	file_put_contents(__DIR__."/".DEBUG_FILE_NAME, $log, FILE_APPEND);

	return true;
}

/**
 * Get overdue tasks.
 *
 * @param $userId
 * @return array
 */
function getBadTasks($userId)
{
	$tasks = restCommand('tasks.task.list', array(
	   'order' => array('DEADLINE' => 'desc'),
	   'filter' => array(
		   'RESPONSIBLE_ID' => $userId, 
		   '<DEADLINE' => date('Y-m-d H:i:s')
		),
	   'select' => array('ID', 'TITLE', 'RESPONSIBLE_ID', 'DEADLINE')
	), $_REQUEST["auth"]);

	if (isset($tasks['result']['tasks']) && count($tasks['result']['tasks']) > 0) 
	{
		$arTasks = array();
		foreach ($tasks['result']['tasks'] as $id => $arTask) 
		{
			$arTasks[] = array(
				'LINK' => array(
					'NAME' => $arTask['title'],
					'LINK' => 'https://'.$_REQUEST['auth']['domain'].'/company/personal/user/'.$arTask['responsibleId'].'/tasks/task/view/'.$arTask['id'].'/'
				)
			);
			$arTasks[] = array(
				'DELIMITER' => array(
					'SIZE' => 400,
					'COLOR' => '#c6c6c6'
				)
			);
		}
		$arReport = array(
			'title' => 'Вот, что нашёл по просроченным задачам:',
			'report'  => '',
			'attach' => $arTasks
		);
	}
	else 
	{
		$arReport = array(
			'title' => 'Шикарно работаете!',
			'report'  => 'Нечем даже огорчить - ни одной просроченной задачи',
		);
	}
	return $arReport;
}
