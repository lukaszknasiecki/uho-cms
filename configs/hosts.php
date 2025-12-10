<?php

if (getenv('S3_HOST'))
	$s3 = [
		'host' =>	getenv('S3_HOST'),
		'key' =>	getenv('S3_KEY'),
		'secret' =>	getenv('S3_SECRET'),
		'bucket' =>	getenv('S3_BUCKET'),
		'region' =>	getenv('S3_REGION'),
		'folder' =>	getenv('S3_FOLDER'),
		'acl' =>	getenv('S3_ACL'),
		'cache' => '/cache/s3.files'
	];
else $s3 = null;


$cfg_domains = [

	getenv('DOMAIN') =>
	[
		'sql_host' =>                                       getenv('SQL_HOST'),
		'sql_user' =>                                       getenv('SQL_USER'),
		'sql_pass' =>                                       getenv('SQL_PASS'),
		'sql_base' =>                                       getenv('SQL_BASE'),
		'params'        =>
		[
			"ffmpeg"        =>                              getenv('FFMPEG_PATH'),
			"ffprobe"        =>                              getenv('FFPROBE_PATH'),
		],
		'smtp' =>
		[
			'server' => getenv('SMTP_SERVER'),
			'port' => getenv('SMTP_PORT'),
			'secure' => getenv('SMTP_SECURE'),
			'login' => getenv('SMTP_LOGIN'),
			'pass' => getenv('SMTP_PASS'),
			'fromEmail' => getenv('SMTP_LOGIN'),
			'fromName' => getenv('SMTP_NAME')
		],
		"api_keys" =>
		[
			'youtube' => getenv('YOUTUBE_TOKEN'),
			"vimeo" =>
			[
				'client' => getEnv('VIMEO_CLIENT'),
				'secret' => getEnv('VIMEO_SECRET'),
				'token' => getEnv('VIMEO_TOKEN')
			]
		],
		"s3" => $s3
	]
];
