#!/bin/bash
# Auto-fix ESLint errors on changed .ts/.tsx files after Edit/Write
# Runs as PostToolUse hook — 0 LLM tokens cost
# Catches: unused imports, import ordering, consistent-type-imports, formatting

cd "$CLAUDE_PROJECT_DIR" || exit 0

# Get all changed .ts/.tsx files (staged + unstaged vs HEAD, plus untracked new files)
FILES=$(
  {
    git diff --name-only --diff-filter=ACMR HEAD 2>/dev/null
    git ls-files --others --exclude-standard 2>/dev/null
  } | grep -E '\.(ts|tsx)$' | grep -v 'node_modules' | grep -v 'generated' | sort -u | head -20
)

if [ -n "$FILES" ]; then
  # Run ESLint with --fix to auto-fix:
  # - unused-imports/no-unused-imports (removes unused imports)
  # - import/order (fixes import ordering)
  # - @typescript-eslint/consistent-type-imports (adds 'type' keyword)
  # - self-closing-comp (self-closing JSX tags)
  npx eslint --fix --no-error-on-unmatched-pattern $FILES 2>/dev/null
fi

exit 0
