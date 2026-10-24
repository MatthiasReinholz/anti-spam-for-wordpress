<?php
declare(strict_types=1);

final class SignupAndCommentRequestsTest extends AsfwPluginTestCase
{
    private array $server;
    private $page;

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        $this->page = $GLOBALS['pagenow'] ?? null;
        parent::setUp();
        $GLOBALS['asfw_test_multisite'] = true;
        update_option('asfw_integration_wordpress_register', 'captcha');
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        $GLOBALS['pagenow'] = $this->page;
        parent::tearDown();
    }

    public function test_native_multisite_user_signup_rejects_missing_and_accepts_valid_proof(): void
    {
        $_SERVER['SCRIPT_NAME'] = '/network/wp-signup.php';
        $result = ['errors' => new WP_Error()];
        self::assertSame('asfw_verification', apply_filters('wpmu_validate_user_signup', $result)['errors']->get_error_code());
        $this->seedPostedWidget('wordpress:multisite-register', 'asfw_register');
        self::assertSame('', apply_filters('wpmu_validate_user_signup', ['errors' => new WP_Error()])['errors']->get_error_code());
        self::assertSame('asfw_verification', apply_filters('wpmu_validate_user_signup', ['errors' => new WP_Error()])['errors']->get_error_code());
    }

    public function test_multistep_signup_renders_fresh_protection_at_each_stage_and_does_not_double_consume(): void
    {
        foreach (['validate-user', 'validate-site', 'create-another-site'] as $stage) {
            ob_start(); do_action('signup_hidden_fields', $stage); $markup = ob_get_clean();
            self::assertStringContainsString('wordpress:multisite-register', $markup);
        }
        $GLOBALS['pagenow'] = 'wp-signup.php';
        $this->seedPostedWidget('wordpress:multisite-register', 'asfw_register');
        self::assertSame('', apply_filters('wpmu_validate_user_signup', ['errors' => new WP_Error()])['errors']->get_error_code());
        self::assertSame('', apply_filters('wpmu_validate_blog_signup', ['errors' => new WP_Error()])['errors']->get_error_code());
    }

    public function test_existing_user_site_signup_requires_its_own_proof(): void
    {
        $GLOBALS['pagenow'] = 'wp-signup.php';
        $GLOBALS['asfw_test_user_logged_in'] = true;
        $_POST['stage'] = 'gimmeanotherblog';
        self::assertSame('asfw_verification', apply_filters('wpmu_validate_blog_signup', ['errors' => new WP_Error()])['errors']->get_error_code());
    }

    public function test_programmatic_multisite_validation_remains_available(): void
    {
        $GLOBALS['pagenow'] = 'admin.php';
        $_SERVER['SCRIPT_NAME'] = '/wp-admin/admin.php';
        self::assertSame('', apply_filters('wpmu_validate_user_signup', ['errors' => new WP_Error()])['errors']->get_error_code());
    }

    public function test_regular_customer_rest_comments_require_proof_and_accept_json_once(): void
    {
        $GLOBALS['asfw_test_user_logged_in'] = true;
        $comment = ['comment_type' => 'comment', 'comment_post_ID' => 123];
        self::assertInstanceOf(WP_Error::class, apply_filters('rest_pre_insert_comment', $comment, new WP_REST_Request()));
        $this->seedPostedWidget('wordpress:comments');
        $fields = $_POST; $_POST = [];
        $request = new WP_REST_Request($fields);
        self::assertSame($comment, apply_filters('rest_pre_insert_comment', $comment, $request));
        self::assertInstanceOf(WP_Error::class, apply_filters('rest_pre_insert_comment', $comment, $request));
        self::assertSame([], $_POST);
    }

    public function test_rest_comment_email_policy_uses_the_prepared_author_not_spoofed_aliases(): void
    {
        update_option('asfw_feature_disposable_email_enabled', 1);
        update_option('asfw_feature_disposable_email_mode', 'block');
        $GLOBALS['asfw_test_user_logged_in'] = true;
        $this->seedPostedWidget('wordpress:comments');
        $fields = $_POST; $_POST = [];
        $fields['email'] = 'benign@example.com';
        $fields['author_email'] = 'benign@example.com';
        $comment = ['comment_type' => 'comment', 'comment_author_email' => 'spam@trashmail.com', 'comment_content' => 'Test'];
        self::assertInstanceOf(WP_Error::class, apply_filters('rest_pre_insert_comment', $comment, new WP_REST_Request($fields)));
    }

    public function test_authorized_moderation_and_preexisting_errors_are_preserved(): void
    {
        $GLOBALS['asfw_test_user_logged_in'] = true;
        $GLOBALS['asfw_test_capabilities'] = ['moderate_comments' => true];
        $comment = ['comment_type' => 'comment', 'comment_post_ID' => 123];
        self::assertSame($comment, apply_filters('rest_pre_insert_comment', $comment, new WP_REST_Request()));
        $error = new WP_Error('other_validation', 'Rejected');
        self::assertSame($error, apply_filters('rest_pre_insert_comment', $error, new WP_REST_Request()));
    }
}
