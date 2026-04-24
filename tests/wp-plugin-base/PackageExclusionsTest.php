<?php
declare(strict_types=1);

final class PackageExclusionsTest extends AsfwPluginTestCase
{
    public function test_distribution_rules_exclude_development_tooling_paths(): void
    {
        $root = dirname(__DIR__, 2);
        $distignore = file_get_contents($root . '/.distignore');
        $gitattributes = file_get_contents($root . '/.gitattributes');
        $buildWrapper = file_get_contents($root . '/scripts/ci/build_zip.sh');

        $this->assertIsString($distignore);
        $this->assertStringContainsString('/.wp-plugin-base-admin-ui', $distignore);
        $this->assertStringContainsString('/.wp-plugin-base-quality-pack', $distignore);
        $this->assertStringContainsString('/node_modules', $distignore);

        $this->assertIsString($gitattributes);
        $this->assertStringContainsString('/.wp-plugin-base-admin-ui export-ignore', $gitattributes);

        $this->assertIsString($buildWrapper);
        $this->assertStringContainsString('.wp-plugin-base-admin-ui', $buildWrapper);
        $this->assertStringContainsString('node_modules', $buildWrapper);
    }
}
