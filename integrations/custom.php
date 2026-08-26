<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Custom forms are integrated through [anti_spam_widget]. The renderer emits
// complete server-side attributes and requests the shared widget assets only
// when that shortcode (or another protected integration) actually renders.
