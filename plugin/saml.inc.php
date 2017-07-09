<?php
// PukiWiki - Yet another WikiWikiWeb clone.
// saml.inc.php
// Copyright
//   2017 PukiWiki Development Team
// License: GPL v2 or (at your option) any later version
//
// PukiWiki SAML Plugin

require 'vendor/autoload.php';
require_once 'vendor/onelogin/php-saml/_toolkit_loader.php';

define('PLUGIN_SAML_AUTHUSER_ID_ATTR', 'UserId');
define('PLUGIN_SAML_AUTHUSER_DISPLAYNAME_ATTR', 'DisplayName');

/**
 *  SAML Handler
 */
function plugin_saml_action() {
	global $vars;
	require_once 'saml_settings.php';

	pkwk_log("vars:");
	pkwk_log(print_r($vars, true));

	$auth = new OneLogin_Saml2_Auth($settingsInfo);

	if (isset($vars['sso'])) {
		// sso: Sign in endpoint before IdP
		$url_after_login = $vars['url_after_login'];
		$auth->login($url_after_login);
	} else if (isset($vars['slo'])) {
		// sso: Sign out endpoint before IdP
		$returnTo = null;
		$paramters = array();
		$nameId = null;
		$sessionIndex = null;
		if (isset($_SESSION['samlNameId'])) {
			$nameId = $_SESSION['samlNameId'];
		}
		if (isset($_SESSION['samlSessionIndex'])) {
			$sessionIndex = $_SESSION['samlSessionIndex'];
		}
		$auth->logout($returnTo, $paramters, $nameId, $sessionIndex);
	} else if (isset($vars['acs'])) {
		// acs: Sign in endpoint after IdP
		$auth->processResponse();
		pkwk_log('AAA');

		$errors = $auth->getErrors();

		if (!empty($errors)) {
			return array('msg' => 'SAML Error', print_r('<p>'.implode(', ', $errors).'</p>'));
		}

		if (!$auth->isAuthenticated()) {
			return array('msg' => 'SAML sign in', 'body' => '<p>Not authenticated</p>');
		}
		$attrs = $auth->getAttributes();
		$_SESSION['samlUserdata'] = $attrs;
		$_SESSION['samlNameId'] = $auth->getNameId();
		$_SESSION['samlSessionIndex'] = $auth->getSessionIndex();
		if (isset($attrs[PLUGIN_SAML_AUTHUSER_ID_ATTR])) {
			// PukiWiki ExternalAuth requirement
			$_SESSION['authenticated_user'] = $attrs[PLUGIN_SAML_AUTHUSER_ID_ATTR];
		}
		if (isset($attrs[PLUGIN_SAML_AUTHUSER_DISPLAYNAME_ATTR])) {
			// PukiWiki ExternalAuth requirement
			$_SESSION['authenticated_user_fullname'] = $attrs[PLUGIN_SAML_AUTHUSER_DISPLAYNAME_ATTR];
		}

		if (isset($_POST['RelayState']) && OneLogin_Saml2_Utils::getSelfURL() != $_POST['RelayState']) {
			pkwk_log("CCC'". $_POST['RelayState'] . "'");
			$auth->redirectTo($_POST['RelayState']);
		}
		pkwk_log("wwwq");
		return array('msg' => 'SAML sign in', 'body' => 'SAML Sined in. but no redirection');
	} else if (isset($vars['sls'])) {
		// sls: Sign out endpoint after IdP
		// onelone/php-saml only supports Redirect SingleLogout
		$is_post = $_SERVER['REQUEST_METHOD'] === 'POST';
		pkwk_log("AA;");
		if ($is_post) {
			pkwk_log("BB;");
			session_destroy();
			$_SESSION = array();
		} else {
			pkwk_log("CC;");
			$auth->processSLO();
			$errors = $auth->getErrors();
			$msg = '';
			if (empty($errors)) {
				$msg .= '<p>Sucessfully logged out</p>';
			} else {
				$msg .= '<p>'.implode(', ', $errors).'</p>';
			}
		}
		return array('msg' => 'SAML sign out', 'body' => 'SAML Sined out. ' . $msg);
	} else if (isset($vars['metadata'])) {
		// metadata: SP metadata endpoint
		try {
			$auth = new OneLogin_Saml2_Auth($settingsInfo);
			$settings = $auth->getSettings();
			$metadata = $settings->getSPMetadata();
			$errors = $settings->validateMetadata($metadata);
			if (empty($errors)) {
				header('Content-Type: text/xml');
				echo $metadata;
			} else {
				throw new OneLogin_Saml2_Error(
					'Invalid SP metadata: '.implode(', ', $errors),
					OneLogin_Saml2_Error::METADATA_SP_INVALID
				);
			}
		} catch (Exception $e) {
			echo $e->getMessage();
		}
		exit;
	}
	return array('msg' => 'Error', 'body' => 'SAML Invalid state srror');
}
