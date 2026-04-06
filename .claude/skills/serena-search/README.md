# Serena Search Skill - Quick Start

## 🎯 What This Skill Does

Automatically activates Serena MCP plugin and performs intelligent semantic code searches in the `genealog` project.

**Token Efficiency:** 95% savings compared to reading full files!

---

## 🚀 How to Use

### Method 1: Explicit `/serena` Command

```bash
/serena find the equipment table component
/serena show me structure of AppSidebar.tsx
/serena where is MediaPickerDialog used?
/serena fix equipment table to show Name from attributes
```

### Method 2: Natural Language (Auto-Detection)

The skill **automatically activates** when you use these keywords:

```bash
"find the handleSubmit function"
"search for all console.log statements"
"locate the MediaPicker component"
"show me file structure of equipment page"
"where is handleLogout defined?"
"refactor handleClick to handleSubmit"
```

---

## 📋 Common Use Cases

### 1. Find Component/Function

**Input:**

```
/serena find MediaPickerDialog
```

**Claude will:**

1. ✅ Activate Serena project
2. 🔍 Search for symbol using `find_symbol`
3. 📍 Return file location + line numbers
4. 📝 Show code if requested

---

### 2. Analyze File Structure

**Input:**

```
/serena show structure of equipment page
```

**Claude will:**

1. ✅ Activate Serena
2. 🔍 Locate equipment page file
3. 📊 Get symbols overview (functions, components, exports)
4. 📋 Present organized structure

---

### 3. Fix Table Column

**Input:**

```
/serena fix equipment table to show Name from attributes
```

**Claude will:**

1. ✅ Activate Serena
2. 🔍 Find table definition
3. 📊 Analyze columns array
4. 🔎 Check attributes usage
5. ✅ Propose solution with code changes

---

### 4. Find All Usages

**Input:**

```
/serena where is handleRecentClick used?
```

**Claude will:**

1. ✅ Activate Serena
2. 🔍 Find symbol definition
3. 🔗 Find all references
4. 📋 Show all files + line numbers + code snippets

---

### 5. Search Pattern

**Input:**

```
/serena find all TODO comments in frontend
```

**Claude will:**

1. ✅ Activate Serena
2. 🔍 Search for pattern using regex
3. 📋 Show all matches with context
4. 📊 Organize by file

---

### 6. Refactor Symbol

**Input:**

```
/serena rename handleClick to handleSubmit
```

**Claude will:**

1. ✅ Activate Serena
2. 🔍 Find symbol location
3. 🔗 Check all usages (impact analysis)
4. ✅ Rename symbol + update all references

---

## 🎨 Skill Activation Triggers

### Keywords (Explicit)

- `/serena` (command prefix)
- `serena`, `use serena`, `with serena`
- `find code`, `search code`, `locate code`
- `search function`, `find function`, `locate function`
- `search component`, `find component`, `locate component`
- `fix table`, `update table`
- `analyze structure`, `file structure`, `code structure`
- `refactor function`, `rename function`
- `semantic search`, `code navigation`
- `symbol search`, `method search`, `class search`
- `find usages`, `find references`, `show dependencies`
- `code exploration`, `navigate code`

### Intent Patterns (Implicit)

Auto-detects these patterns in your prompts:

- `(find|locate|search) ... (code|function|component|class|method|symbol)`
- `(fix|update|modify) ... table`
- `(show|display|get) ... (structure|dependencies|references|usages)`
- `(refactor|rename) ... (function|method|component|class)`
- `analyze ... (code|structure|file)`
- `where is ... (defined|used|located)`
- `search for ... (pattern|todo|console)`

---

## 💡 What You Get

When the skill activates, you'll see:

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎯 SKILL ACTIVATION CHECK
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

📚 RECOMMENDED SKILLS:
  → serena-search

ACTION: Use Skill tool BEFORE responding
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

Claude will then **automatically**:

1. ✅ Activate genealog project in Serena
2. 🔍 Perform intelligent code search based on your request
3. 📊 Present findings in structured format
4. ✅ Suggest next steps or code changes

---

## 🛠️ Serena Tools Used

The skill leverages these Serena MCP tools:

| Tool                       | Purpose              | When Used         |
| -------------------------- | -------------------- | ----------------- |
| `activate_project`         | Activate genealog    | **Always first!** |
| `get_symbols_overview`     | File structure       | "show structure"  |
| `find_symbol`              | Find specific symbol | "find function"   |
| `find_referencing_symbols` | Find usages          | "where is used"   |
| `search_for_pattern`       | Pattern search       | "find all TODO"   |
| `rename_symbol`            | Refactor name        | "rename function" |
| `replace_content`          | Batch replace        | "replace pattern" |

---

## 📊 Performance Benefits

| Task                     | Standard Claude Tools | Serena Search Skill      | Token Savings |
| ------------------------ | --------------------- | ------------------------ | ------------- |
| **Read 500-line file**   | Read (500 lines)      | get_symbols_overview     | **~95%**      |
| **Find method in class** | Read + search         | find_symbol              | **~90%**      |
| **Find all usages**      | Grep + filter         | find_referencing_symbols | **~80%**      |
| **Refactor function**    | Manual replace all    | rename_symbol            | **~70%**      |

---

## 📖 Examples with Full Context

### Example 1: Equipment Table Fix

**Your Input:**

```
/serena fix equipment table to show Name from attributes
```

**Claude's Response:**

```
✅ Serena activated for genealog project

🔍 Searching for: Equipment table columns
📁 Scope: apps/frontend/src/app/(protected)/equipment
🎯 Strategy:
  1. Locate equipment page file
  2. Find DataTable component
  3. Analyze columns definition
  4. Check attributes inclusion

📋 Found:
  - File: apps/frontend/src/app/(protected)/equipment/page.tsx
  - Component: EquipmentTable (line 45-120)
  - Columns: defined at line 67 (currently missing Name)

📝 Current Implementation:
  {
    accessorKey: "id",
    header: "ID",
  },
  {
    accessorKey: "status",
    header: "Status",
  }
  // Missing Name column from attributes

🎯 Issue Identified:
  Attributes are fetched but Name field is not displayed in table

✅ Solution:
  Add Name column to columns array:
  {
    accessorKey: "attributes.name",
    header: "Name",
    cell: ({ row }) => row.original.attributes?.name || "—"
  }

🚀 Next Steps:
  1. Edit page.tsx to add Name column
  2. Update useEquipmentQuery to include attributes
  3. Test on localhost:3000/equipment
```

---

### Example 2: Find Component

**Your Input:**

```
/serena where is MediaPickerDialog defined?
```

**Claude's Response:**

```
✅ Serena activated for genealog project

🔍 Searching for: MediaPickerDialog

📍 Found:
  File: apps/frontend/src/components/media-library/MediaPickerDialog.tsx
  Lines: 15-245
  Type: React Component (Function)

📝 Exports:
  export const MediaPickerDialog: React.FC<MediaPickerDialogProps>

📦 Dependencies:
  - @ui components (Dialog, Button, Input)
  - useMediaLibrary hook
  - MediaCard component

🔗 Used by:
  - EquipmentForm.tsx (line 123)
  - ImageAttributeInput.tsx (line 67)
```

---

### Example 3: Search Pattern

**Your Input:**

```
/serena find all console.log in frontend
```

**Claude's Response:**

```
✅ Serena activated for genealog project

🔍 Searching for: console.log statements
📁 Scope: apps/frontend/src

📊 Found 12 matches in 8 files:

📁 apps/frontend/src/app/(protected)/equipment/page.tsx
  Line 45: console.log("Equipment data:", data)
  Line 89: console.log("Filter applied:", filters)

📁 apps/frontend/src/components/forms/EquipmentForm.tsx
  Line 123: console.log("Form submitted:", values)

📁 apps/frontend/src/queries/equipment.queries.ts
  Line 67: console.log("API error:", error)

[... 5 more files]

⚠️ Recommendation: Replace with logger.debug() for production
```

---

## 🎓 Learning Curve

**Beginner (Day 1):**

- Use `/serena` prefix for explicit activation
- Try basic commands: find, show structure

**Intermediate (Week 1):**

- Use natural language without `/serena`
- Understand automatic skill activation
- Combine with standard Claude tools

**Advanced (Month 1):**

- Chain multiple Serena searches
- Leverage pattern search for refactoring
- Master symbol navigation workflows

---

## 🔗 Related Documentation

- **Complete Serena Guide:** `docs/tools/SERENA_MCP_GUIDE.md`
- **Quick Reference Card:** `docs/tools/SERENA_QUICK_REF.md`
- **Skill Configuration:** `.claude/skills/skill-rules.json`
- **Project Memories:** Use `read_memory` tool
  - `codebase_structure`
  - `code_style_conventions`

---

## ✅ Testing the Skill

Try these commands to test:

```bash
# Test 1: Basic find
/serena find the AppSidebar function

# Test 2: Structure analysis
/serena show me structure of equipment page

# Test 3: Usage search
/serena where is handleRecentClick used?

# Test 4: Pattern search
/serena find all TODO comments

# Test 5: Auto-activation (no /serena prefix)
find the MediaPickerDialog component
```

---

## 🆘 Troubleshooting

### Skill Not Activating

**Problem:** Claude doesn't use Serena automatically

**Solution:**

1. Use explicit `/serena` command
2. Check if keywords are in your prompt
3. Verify `.claude/skills/skill-rules.json` exists
4. Check hook is installed: `.claude/hooks/skill-activation-prompt.ts`

---

### Serena "No Active Project"

**Problem:** Tools fail with "No active project"

**Solution:** Skill handles this automatically! It always activates project first.

---

## 🎉 Success!

Your Serena Search skill is ready to use!

**Next Step:** Try it out:

```
/serena find the equipment table component
```

Enjoy **95% token savings** and intelligent code navigation! 🚀

---

**Skill Version:** 1.0.0
**Created:** 2026-02-10
**Project:** genealog
**Status:** ✅ Active
