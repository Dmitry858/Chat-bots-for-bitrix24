<?php
error_reporting(0);

#####################
### CONFIG OF BOT ###
#####################
define('DEBUG_FILE_NAME', ''); // if you need read debug log, you should write unique log name
define('CLIENT_ID', ''); // like 'app.67efrrt2990977.85678329' or 'local.57062d3061fc71.97850406' - This code should take in a partner's site, needed only if you want to write a message from Bot at any time without initialization by the user
define('CLIENT_SECRET', ''); // like '8bb00435c88aaa3028a0d44320d60339' - This code should take in a partner's site, needed only if you want to write a message from Bot at any time without initialization by the user

$appsConfig = Array();
if (file_exists(__DIR__.'/config.php')) include(__DIR__.'/config.php');
include(__DIR__.'/functions.php');

// событие на получение чат-ботом сообщения
if ($_REQUEST['event'] == 'ONIMBOTMESSAGEADD')
{
	// check the event - authorize this event or not
	if (!isset($appsConfig[$_REQUEST['auth']['application_token']]))
		return false;

	list($message) = explode(" ", $_REQUEST['data']['PARAMS']['MESSAGE']);
	// send answer message
	if ($message == '1')
	{
		$result = restCommand('imbot.message.add', Array(
			"DIALOG_ID" => $_REQUEST['data']['PARAMS']['DIALOG_ID'],
			"MESSAGE" => 'Меня зовут Тестовый Бот! Пока я умею только искать просроченные задачи, но скоро смогу находить и другую важную информацию',
		), $_REQUEST["auth"]);
	}
	else if ($message == '2')
	{
		$arResult = getBadTasks($_REQUEST['data']['PARAMS']['FROM_USER_ID']);

		$result = restCommand('imbot.message.add', Array(
			"DIALOG_ID" => $_REQUEST['data']['PARAMS']['DIALOG_ID'],
			"MESSAGE" => $arResult['title'] . "\n" . $arResult['report'] . "\n",
			"ATTACH" => $arResult['attach'] ? $arResult['attach'] : '',
		), $_REQUEST["auth"]);
	}
	else
	{
		$message = 'Пожалуйста, выберите один из вариантов:[br]'.
				   '[send=1]1. Расскажи о своих возможностях[/send][br]'.
				   '[send=2]2. Покажи просроченные задачи[/send]';
		
		$result = restCommand('imbot.message.add', Array(
			"DIALOG_ID" => $_REQUEST['data']['PARAMS']['DIALOG_ID'],
			"MESSAGE" => $message,
		), $_REQUEST["auth"]);
	}
}

// когда бот присоединился к чату
else if ($_REQUEST['event'] == 'ONIMBOTJOINCHAT')
{
	// check the event - authorize this event or not
	if (!isset($appsConfig[$_REQUEST['auth']['application_token']]))
		return false;

	$message = 'Здравствуйте! Что Вас интересует?[br]'.
			   '[send=1]1. Расскажи о своих возможностях[/send][br]'.
			   '[send=2]2. Покажи просроченные задачи[/send]';

	// send help message how to use chat-bot. For private chat and for group chat need send different instructions.
	$result = restCommand('imbot.message.add', Array(
		"DIALOG_ID" => $_REQUEST['data']['PARAMS']['DIALOG_ID'],
		"MESSAGE" => $message,
	), $_REQUEST["auth"]);
}

// событие на установку приложения
else if ($_REQUEST['event'] == 'ONAPPINSTALL')
{
	// handler for events
	$handlerBackUrl = ($_SERVER['SERVER_PORT']==443||$_SERVER["HTTPS"]=="on"? 'https': 'http')."://".$_SERVER['SERVER_NAME'].(in_array($_SERVER['SERVER_PORT'], Array(80, 443))?'':':'.$_SERVER['SERVER_PORT']).$_SERVER['SCRIPT_NAME'];

	// If your application supports different localizations
	// use $_REQUEST['data']['LANGUAGE_ID'] to load correct localization

	// register new bot
	$result = restCommand('imbot.register', Array(
		'CODE' => 'mytestbot',
		'TYPE' => 'B',
		'EVENT_MESSAGE_ADD' => $handlerBackUrl,
		'EVENT_WELCOME_MESSAGE' => $handlerBackUrl,
		'EVENT_BOT_DELETE' => $handlerBackUrl,
		'OPENLINE' => 'Y', // this flag only for Open Channel mode http://bitrix24.ru/~bot-itr
		'PROPERTIES' => Array(
			'NAME' => 'Тестовый бот',
			'COLOR' => 'GREEN',
			'WORK_POSITION' => 'My test bot',
			'PERSONAL_PHOTO' => base64_encode(file_get_contents(__DIR__.'/avatar.png')),
		)
	), $_REQUEST["auth"]);

	$botId = $result['result'];

	$result = restCommand('event.bind', Array(
		'EVENT' => 'OnAppUpdate',
		'HANDLER' => $handlerBackUrl
	), $_REQUEST["auth"]);

	// save params
	$appsConfig[$_REQUEST['auth']['application_token']] = Array(
		'BOT_ID' => $botId,
		'LANGUAGE_ID' => $_REQUEST['data']['LANGUAGE_ID'],
		'AUTH' => $_REQUEST['auth'],
	);
	saveParams($appsConfig);

	// пример записи логов
	writeToLog(Array($botId), 'Регистрация тестового бота');
}
