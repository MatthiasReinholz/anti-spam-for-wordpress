const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { createRequire } = require('node:module');

const root = path.resolve(__dirname, '../..');
const requireAdmin = createRequire(path.join(root, '.wp-plugin-base-admin-ui/package.json'));
const parser = requireAdmin('@typescript-eslint/parser');

test('admin dependency overrides preserve typed linting for default-project files', t => {
  const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'asfw-admin-dependency-'));
  t.after(() => {
    try {
      parser.clearCaches();
    } finally {
      fs.rmSync(directory, { recursive: true, force: true });
    }
  });
  parser.clearCaches();
  const filePath = path.join(directory, 'fixture.js');
  const source = 'const value = 1;\n';
  fs.writeFileSync(filePath, source);

  const result = parser.parseForESLint(source, {
    filePath,
    tsconfigRootDir: directory,
    projectService: { allowDefaultProject: ['fixture.js'] },
  });

  assert.ok(result.services.program, 'Expected a typed default-project program');
  assert.equal(result.services.program.getSourceFile(filePath)?.text, source);
});
