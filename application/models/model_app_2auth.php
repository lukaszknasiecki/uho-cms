<?php

use Huncwot\UhoFramework\_uho_fx;
use RobThree\Auth\TwoFactorAuth;
use RobThree\Auth\Providers\Qr\QRServerProvider;

require_once('model_app.php');

/**
 * Model class for the 2Auth module.
 */
class model_app_2auth extends model_app
{
	/**
	 * Retrieves data for the reports page module.
	 *
	 * @param array|null $params Optional parameters
	 * @return array Report data, including file list and selected report content
	 */
	public function getContentData($params = null): array
	{

		$errors = [];
		$success = [];

		$user = $this->clients->getClient();

		if (!$user) {
			return [
				'result' => false,
				'errors' => ['User not found']
			];
		}
		if (!$user['email']) {
			return [
				'result' => false,
				'errors' => ['User email not defined found']
			];
		}


		$tfa = new TwoFactorAuth(new QRServerProvider(), $params['title']);

		$qr_regenerate = $_POST['qr_regenerate'] ?? null;
		$code = $_POST['code'] ?? null;

		if ($qr_regenerate) {
			$this->removeUserTokens($user['id']);
		}

		/*
			Verification
		*/
		if ($code) {

			$token = $this->orm->get(
				'cms_users_tokens',
				[
					'user' => $user['id'],
					//'session' => intval($_SESSION['login_session_id']),
					//'valid_to' => $date->format('Y-m-d H:i:s'),
				],
				true
			);

			if (!$token) {
				return [
					'result' => false,
					'errors' => ['2FA token not found']
				];
			}

			if ($tfa->verifyCode($token['token'], $code))
			{
				return [
					'result' => true,
					'authenticated'=>true,
					'success' => ['2FA verification successful']
				];
			} else {
				return [
					'result' => false,
					'errors' => ['2FA verification failed']
				];
			}
		}

		/*
			Start Verification - check if user has token
		*/

		$token = $this->orm->get(
			'cms_users_tokens',
			[
				'user' => $user['id']
			],
			true
		);

		if ($token) {
			$qrCodeUrl='exists';
		}

		/*
			Create QR Code
		*/ else {

			$secret = $tfa->createSecret();

			$date = new DateTime();
			$date->modify('+15 minutes');

			$this->removeUserTokens($user['id']);

			$r = $this->orm->post(
				'cms_users_tokens',
				[
					'user' => $user['id'],
					'session' => intval($_SESSION['login_session_id']),
					'valid_to' => $date->format('Y-m-d H:i:s'),
					'token' => $secret
				]
			);
			if ($r === false)
				return [
					'result' => false,
					'errors' => ['System Error']
				];

			$qrCodeUrl = $tfa->getQRCodeImageAsDataUri($user['email'], $secret);

			if (!$qrCodeUrl) {
				return [
					'result' => false,
					'errors' => ['Failed to generate QR code']
				];
			}
		}
		return [
			'result'  => true,
			'errors'  => $errors,
			'success' => $success,
			'qrCodeUrl' => $qrCodeUrl
		];
	}

	private function removeUserTokens($user_id)
	{
		$this->orm->delete(
			'cms_users_tokens',
			[
				'user' => $user_id
			]
		);
	}
}
