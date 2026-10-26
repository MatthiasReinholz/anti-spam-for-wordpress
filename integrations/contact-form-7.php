<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-asfw-contact-form-policy.php';
require_once __DIR__ . '/class-asfw-contact-form-7.php';

if ( asfw_plugin_active( 'contact-form-7' ) ) {
	ASFW_Contact_Form_7::register();
}
