#!/usr/bin/env node
const fs = require('fs');
const path = require('path');

const HOOKS_DIR = path.resolve(__dirname, '..', '.git', 'hooks');
const PRE_COMMIT_HOOK = path.join(HOOKS_DIR, 'pre-commit');

const hookScript = `#!/bin/sh
# pre-commit hook to run i18n audit

echo "Running i18n Audit..."
node tests/i18n_audit.js

if [ $? -ne 0 ]; then
  echo ""
  echo "❌ i18n Audit Failed. Please ensure all new UI text uses data-i18n attributes."
  echo "   If you believe this is a false positive, you can skip this hook using --no-verify."
  exit 1
fi

echo "✅ i18n Audit Passed."
exit 0
`;

if (!fs.existsSync(HOOKS_DIR)) {
  fs.mkdirSync(HOOKS_DIR, { recursive: true });
}

fs.writeFileSync(PRE_COMMIT_HOOK, hookScript, { mode: 0o755 });
console.log('✅ Git pre-commit hook installed successfully at .git/hooks/pre-commit');
