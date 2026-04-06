---
name: serena-search
description: Automatically activate Serena MCP plugin and perform intelligent semantic code searches in genealog project. Use when user mentions /serena command, wants to find code, search symbols, analyze structure, refactor functions, locate components, fix tables, update pages, or perform any code exploration task. Triggers on keywords like serena, find code, search function, locate component, fix table, analyze structure, refactor, semantic search, code navigation, symbol search, file structure, dependencies, references, method search, class search.
---

# Serena Search - Intelligent Code Exploration

## Purpose

Automatically activate the Serena MCP plugin for the `genealog` project and perform intelligent semantic code searches based on user requests. Serena provides 95% token efficiency compared to reading full files through semantic symbol-based navigation.

## When This Skill Activates

**Explicit triggers:**
- `/serena` command (e.g., `/serena fix table on equipment page`)
- "Use Serena to find..."
- "Search with Serena..."

**Implicit triggers:**
- Requests to find, locate, or search for code
- Analyzing file structure or component dependencies
- Refactoring functions or renaming symbols
- Finding all usages of a component/function
- Understanding code architecture
- Navigating large codebases

## Core Workflow

### Step 1: Auto-Activate Project

**ALWAYS** start by activating the Serena project:

```typescript
mcp__plugin_serena_serena__activate_project({ project: "genealog" })
```

This is **required** before any Serena tool can be used. Without activation, all tools will fail with "No active project" error.

### Step 2: Parse User Intent

Analyze the user's request to determine:

**A. Find/Locate (Search for code)**
- "Find the Equipment table component"
- "Locate handleSubmit function"
- "Where is UserService defined?"

→ Use: `find_symbol`

**B. Analyze Structure (Understand organization)**
- "Show me the structure of EquipmentTable"
- "What's in the equipment-images controller?"
- "Analyze the equipment page"

→ Use: `get_symbols_overview`

**C. Find Usages (Dependencies & References)**
- "Where is AppSidebar used?"
- "Find all places that import MediaPicker"
- "Show dependencies of handleLogout"

→ Use: `find_referencing_symbols`

**D. Search Patterns (Code-wide search)**
- "Find all TODO comments"
- "Search for console.log statements"
- "Locate all useTranslations calls"

→ Use: `search_for_pattern`

**E. Refactor (Rename or modify)**
- "Rename handleClick to handleSubmit"
- "Replace console.log with logger.debug"
- "Update import from old-lib to new-lib"

→ Use: `rename_symbol` or `replace_content`

### Step 3: Execute Intelligent Search

Based on intent, use appropriate Serena tool(s):

#### For "Fix table on equipment page" Example:

1. **Locate the page file:**
   ```typescript
   search_for_pattern({
     substring_pattern: "localhost:3000/equipment",
     relative_path: "apps/frontend/src/app",
     restrict_search_to_code_files: true
   })
   ```

2. **Find table component:**
   ```typescript
   search_for_pattern({
     substring_pattern: "DataTable|Table",
     relative_path: "apps/frontend/src/app/(protected)/equipment",
     restrict_search_to_code_files: true
   })
   ```

3. **Analyze table structure:**
   ```typescript
   get_symbols_overview({
     relative_path: "<discovered-file-path>",
     depth: 1
   })
   ```

4. **Find column definitions:**
   ```typescript
   find_symbol({
     name_path_pattern: "columns",
     relative_path: "<discovered-file-path>",
     include_body: true
   })
   ```

5. **Check attributes usage:**
   ```typescript
   search_for_pattern({
     substring_pattern: "attributes.*?name|Name.*?attributes",
     relative_path: "<discovered-file-path>"
   })
   ```

### Step 4: Present Findings

Provide clear summary:
- **Files found:** List of relevant files with locations
- **Symbols identified:** Functions, components, types
- **Current implementation:** Code snippets showing current state
- **Proposed changes:** What needs to be modified
- **Next steps:** Suggested actions (edit files, add code, etc.)

## Common Use Cases

### Use Case 1: Find Component Location

**User:** `/serena find the MediaPicker component`

**Workflow:**
```typescript
// 1. Activate
activate_project({ project: "genealog" })

// 2. Find symbol
find_symbol({
  name_path_pattern: "MediaPicker",
  substring_matching: true,
  include_body: false
})

// Result: File location + line numbers
```

### Use Case 2: Analyze Page Structure

**User:** `/serena show me structure of equipment page`

**Workflow:**
```typescript
// 1. Activate
activate_project({ project: "genealog" })

// 2. Find page file
search_for_pattern({
  substring_pattern: "/equipment",
  relative_path: "apps/frontend/src/app/(protected)",
  restrict_search_to_code_files: true
})

// 3. Get structure
get_symbols_overview({
  relative_path: "apps/frontend/src/app/(protected)/equipment/page.tsx",
  depth: 1
})

// Result: Functions, Components, Exports
```

### Use Case 3: Fix Table Column

**User:** `/serena fix equipment table to show Name from attributes`

**Workflow:**
```typescript
// 1. Activate
activate_project({ project: "genealog" })

// 2. Locate table definition
search_for_pattern({
  substring_pattern: "columns.*?equipment|ColumnDef.*?Equipment",
  relative_path: "apps/frontend/src/app/(protected)/equipment"
})

// 3. Find columns array
find_symbol({
  name_path_pattern: "columns",
  relative_path: "<discovered-file>",
  include_body: true
})

// 4. Check if attributes are fetched
search_for_pattern({
  substring_pattern: "attributes|equipmentAttributes",
  relative_path: "<discovered-file>"
})

// 5. Present findings + solution
```

### Use Case 4: Find All Usages

**User:** `/serena where is handleRecentClick used?`

**Workflow:**
```typescript
// 1. Activate
activate_project({ project: "genealog" })

// 2. Find definition first
find_symbol({
  name_path_pattern: "handleRecentClick",
  include_body: false
})

// 3. Find all references
find_referencing_symbols({
  name_path: "handleRecentClick",
  relative_path: "<discovered-file>"
})

// Result: All files + line numbers where used
```

### Use Case 5: Search Pattern

**User:** `/serena find all console.log in frontend`

**Workflow:**
```typescript
// 1. Activate
activate_project({ project: "genealog" })

// 2. Search pattern
search_for_pattern({
  substring_pattern: "console\\.log\\(",
  relative_path: "apps/frontend/src",
  restrict_search_to_code_files: true,
  context_lines_before: 1,
  context_lines_after: 1
})

// Result: All matches with context
```

### Use Case 6: Refactor Symbol

**User:** `/serena rename handleClick to handleSubmit`

**Workflow:**
```typescript
// 1. Activate
activate_project({ project: "genealog" })

// 2. Find symbol location
find_symbol({
  name_path_pattern: "handleClick",
  include_body: false
})

// 3. Check usages first
find_referencing_symbols({
  name_path: "handleClick",
  relative_path: "<discovered-file>"
})

// 4. Rename symbol
rename_symbol({
  name_path: "handleClick",
  relative_path: "<discovered-file>",
  new_name: "handleSubmit"
})

// Result: Symbol renamed + all references updated
```

## Serena Tools Reference

### 1. activate_project (REQUIRED FIRST!)

```typescript
mcp__plugin_serena_serena__activate_project({
  project: "genealog"
})
```

**When:** Always first command in every task

### 2. get_symbols_overview

```typescript
mcp__plugin_serena_serena__get_symbols_overview({
  relative_path: "apps/frontend/src/components/organisms/AppSidebar.tsx",
  depth: 1  // 0 = top-level, 1 = + children, 2 = + grandchildren
})
```

**When:** Understanding file structure without reading full content
**Returns:** Functions, Classes, Methods, Properties grouped by type

### 3. find_symbol

```typescript
mcp__plugin_serena_serena__find_symbol({
  name_path_pattern: "AppSidebar",  // or "AppSidebar/handleClick"
  relative_path: "apps/frontend/src",  // Optional: limit scope
  include_body: true,  // true = return source code
  depth: 0,  // 0 = definition only, 1 = + children
  substring_matching: false  // true = partial match
})
```

**When:** Finding specific functions, classes, methods
**Returns:** Symbol location + optional source code

### 4. find_referencing_symbols

```typescript
mcp__plugin_serena_serena__find_referencing_symbols({
  name_path: "handleRecentClick",
  relative_path: "apps/frontend/src/components/organisms/AppSidebar.tsx",
  include_info: false
})
```

**When:** Finding all usages/references of a symbol
**Returns:** All files + line numbers + code snippets

### 5. search_for_pattern

```typescript
mcp__plugin_serena_serena__search_for_pattern({
  substring_pattern: "useTranslations\\(\\)",  // Regex
  relative_path: "apps/frontend/src",  // Optional: limit scope
  restrict_search_to_code_files: true,
  context_lines_before: 2,
  context_lines_after: 2
})
```

**When:** Searching for patterns/text across codebase
**Returns:** All matches with context

### 6. rename_symbol

```typescript
mcp__plugin_serena_serena__rename_symbol({
  name_path: "handleClick",
  relative_path: "apps/frontend/src/components/Button.tsx",
  new_name: "handleSubmit"
})
```

**When:** Refactoring symbol names
**Returns:** Confirmation + updated references

### 7. replace_content

```typescript
mcp__plugin_serena_serena__replace_content({
  relative_path: "apps/frontend/src/utils/logger.ts",
  needle: "console\\.log\\((.*?)\\)",  // Regex
  repl: "logger.debug($!1)",  // $!1 = backreference
  mode: "regex",  // or "literal"
  allow_multiple_occurrences: true
})
```

**When:** Batch replacements (imports, patterns, refactoring)
**Returns:** Confirmation of replacements

## Project-Specific Patterns

### Equipment Module Structure

```
apps/frontend/src/app/(protected)/equipment/
├── page.tsx                    # Main equipment list page
├── [id]/page.tsx              # Equipment detail page
└── components/
    └── EquipmentTable.tsx     # DataTable component

apps/backend/src/app/
├── equipment/
│   ├── equipment.controller.ts
│   ├── equipment.service.ts
│   └── equipment.repository.ts
├── equipment-images/
└── equipment-media/
```

**Common searches:**
- Find equipment table: `search_for_pattern({ substring_pattern: "DataTable.*?equipment" })`
- Find controller: `find_symbol({ name_path_pattern: "EquipmentController" })`
- Find service methods: `get_symbols_overview({ relative_path: "apps/backend/src/app/equipment/equipment.service.ts", depth: 1 })`

### Media Library Structure

```
apps/frontend/src/
├── app/(protected)/media-library/
│   └── page.tsx
├── components/media-library/
│   ├── MediaLibraryView.tsx
│   ├── MediaCard.tsx
│   ├── MediaDetailPanel.tsx
│   ├── UploadDialog.tsx
│   └── MediaPickerDialog.tsx
└── queries/
    └── media-library.queries.ts

apps/backend/src/app/
├── media-library/
└── equipment-media/
```

**Common searches:**
- Find MediaPicker: `find_symbol({ name_path_pattern: "MediaPickerDialog" })`
- Find upload logic: `search_for_pattern({ substring_pattern: "upload.*?media|MediaUpload" })`

## Best Practices

### 1. Always Activate First

```typescript
// ❌ WRONG - Will fail
find_symbol({ name_path_pattern: "AppSidebar" })

// ✅ CORRECT
activate_project({ project: "genealog" })
find_symbol({ name_path_pattern: "AppSidebar" })
```

### 2. Limit Scope for Speed

```typescript
// ❌ SLOW - Searches entire project
search_for_pattern({
  substring_pattern: "TODO:",
  relative_path: "."
})

// ✅ FAST - Limited to frontend
search_for_pattern({
  substring_pattern: "TODO:",
  relative_path: "apps/frontend/src"
})
```

### 3. Use Structure Before Reading

```typescript
// ❌ WASTEFUL - Reads 500 lines
read_file({ file_path: "apps/frontend/src/components/organisms/AppSidebar.tsx" })

// ✅ EFFICIENT - Gets structure (95% token savings)
get_symbols_overview({
  relative_path: "apps/frontend/src/components/organisms/AppSidebar.tsx",
  depth: 1
})
```

### 4. Check Usages Before Refactoring

```typescript
// ✅ SAFE - Check impact first
find_referencing_symbols({ name_path: "handleClick" })
// Review usages...
rename_symbol({ name_path: "handleClick", new_name: "handleSubmit" })
```

### 5. Use Substring Matching for Fuzzy Search

```typescript
// ❌ Exact match only
find_symbol({ name_path_pattern: "handleRecentClick" })

// ✅ Finds: handleClick, handleSubmit, handleLogout
find_symbol({
  name_path_pattern: "handle",
  substring_matching: true
})
```

## Troubleshooting

### Error: "No active project"

**Cause:** Forgot to activate project
**Fix:** Always call `activate_project` first

### Symbol Not Found

**Cause:** Wrong name or path
**Solutions:**
1. Use `substring_matching: true`
2. Search with pattern first: `search_for_pattern`
3. Check file exists: verify relative_path

### Slow Performance

**Solutions:**
1. Add `relative_path` to limit scope
2. Use `restrict_search_to_code_files: true`
3. Reduce `depth` parameter
4. Reduce `context_lines_before/after`

### Max Answer Chars Exceeded

**Solutions:**
1. Use `include_body: false` for symbols
2. Limit scope with `relative_path`
3. Reduce `depth` to 0
4. Only if necessary: `max_answer_chars: -1`

## Response Format

When using this skill, provide:

### 1. Activation Confirmation
```
✅ Serena activated for genealog project
```

### 2. Search Summary
```
🔍 Searching for: [user's request]
📁 Scope: apps/frontend/src/app/(protected)/equipment
🎯 Strategy: [describe approach]
```

### 3. Findings
```
📋 Found:
  - File: apps/frontend/src/app/(protected)/equipment/page.tsx
  - Component: EquipmentTable (line 45-120)
  - Columns: defined at line 67

📝 Current Implementation:
  [relevant code snippet]

🎯 Issue Identified:
  [what's missing or wrong]

✅ Solution:
  [what needs to be added/changed]
```

### 4. Next Steps
```
🚀 Next Steps:
  1. Add Name column to columns array
  2. Update useEquipmentQuery to include attributes
  3. Test on localhost:3000/equipment
```

## Examples

### Example 1: Fix Table Column

**User Input:**
```
/serena fix equipment table to show Name from attributes
```

**Response:**
```typescript
// 1. Activate
activate_project({ project: "genealog" })

// 2. Find table file
search_for_pattern({
  substring_pattern: "DataTable.*?equipment|ColumnDef",
  relative_path: "apps/frontend/src/app/(protected)/equipment"
})

// 3. Analyze columns
find_symbol({
  name_path_pattern: "columns",
  relative_path: "apps/frontend/src/app/(protected)/equipment/page.tsx",
  include_body: true
})

// 4. Check attributes
search_for_pattern({
  substring_pattern: "attributes|equipmentAttributes",
  relative_path: "apps/frontend/src/app/(protected)/equipment"
})

// Present findings + solution
```

### Example 2: Find Component

**User Input:**
```
/serena where is MediaPickerDialog defined?
```

**Response:**
```typescript
activate_project({ project: "genealog" })

find_symbol({
  name_path_pattern: "MediaPickerDialog",
  include_body: false
})

// Result: apps/frontend/src/components/media-library/MediaPickerDialog.tsx
```

### Example 3: Refactor Function

**User Input:**
```
/serena rename handleRecentClick to handleTenantSelect
```

**Response:**
```typescript
activate_project({ project: "genealog" })

// Check usages first
find_referencing_symbols({
  name_path: "handleRecentClick",
  relative_path: "apps/frontend/src/components/organisms/AppSidebar.tsx"
})

// Show impact, get confirmation, then rename
rename_symbol({
  name_path: "handleRecentClick",
  relative_path: "apps/frontend/src/components/organisms/AppSidebar.tsx",
  new_name: "handleTenantSelect"
})
```

## Integration with Standard Tools

After Serena finds the code, use standard Claude tools for modifications:

```typescript
// 1. Serena finds the code
activate_project({ project: "genealog" })
find_symbol({ name_path_pattern: "columns", include_body: true })

// 2. Standard Edit tool modifies
Edit({
  file_path: "apps/frontend/src/app/(protected)/equipment/page.tsx",
  old_string: "existing column definition",
  new_string: "updated column definition with Name"
})
```

## Performance Optimization

### Token Savings

| Task | Standard | Serena | Savings |
|------|----------|--------|---------|
| Read 500-line file | 500 lines | Structure only | 95% |
| Find method | Read + search | `find_symbol` | 90% |
| Find usages | Grep + filter | `find_referencing_symbols` | 80% |
| Refactor name | Manual replace | `rename_symbol` | 70% |

### Speed Tips

1. **Limit scope:** Always use `relative_path`
2. **Shallow depth:** Use `depth: 0` unless needed
3. **Restrict files:** Enable `restrict_search_to_code_files: true`
4. **Minimal context:** Use 1-2 lines, not 10+

## Related Documentation

- **Complete Guide:** `docs/tools/SERENA_MCP_GUIDE.md`
- **Quick Reference:** `docs/tools/SERENA_QUICK_REF.md`
- **Project Memories:** Read with `read_memory` tool
  - `codebase_structure`
  - `code_style_conventions`
  - `project_overview`

---

**Skill Status:** Active ✅
**Auto-Activation:** Yes (on /serena command or code search requests)
**Token Efficiency:** 95% savings vs full file reads
**Project:** genealog
