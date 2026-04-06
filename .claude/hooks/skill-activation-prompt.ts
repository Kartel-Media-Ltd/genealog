#!/usr/bin/env node
import { readFileSync } from 'fs';
import { join } from 'path';

interface HookInput {
    session_id: string;
    transcript_path: string;
    cwd: string;
    permission_mode: string;
    prompt: string;
}

interface PromptTriggers {
    keywords?: string[];
    intentPatterns?: string[];
}

interface SkillRule {
    type: 'guardrail' | 'domain';
    enforcement: 'block' | 'suggest' | 'warn';
    priority: 'critical' | 'high' | 'medium' | 'low';
    promptTriggers?: PromptTriggers;
}

interface SkillRules {
    version: string;
    skills: Record<string, SkillRule>;
}

interface MatchedSkill {
    name: string;
    matchType: 'keyword' | 'intent';
    config: SkillRule;
}

// ANSI color codes
const C = {
    reset: '\x1b[0m',
    bold: '\x1b[1m',
    dim: '\x1b[2m',
    red: '\x1b[31m',
    green: '\x1b[32m',
    yellow: '\x1b[33m',
    blue: '\x1b[34m',
    magenta: '\x1b[35m',
    cyan: '\x1b[36m',
    white: '\x1b[37m',
    bgRed: '\x1b[41m',
    bgMagenta: '\x1b[45m',
    bgCyan: '\x1b[46m',
    bgYellow: '\x1b[43m',
};

// Command banner config
const COMMAND_BANNERS: Record<string, { color: string; bg: string; icon: string; label: string; desc: string }> = {
    '/ultra': {
        color: C.red,
        bg: C.bgRed,
        icon: '⚡',
        label: 'ULTRA',
        desc: 'Feature Orchestrator v2',
    },
    '/quick-fix': {
        color: C.cyan,
        bg: C.bgCyan,
        icon: '🔧',
        label: 'QUICK-FIX',
        desc: 'Diagnoza → Fix → Weryfikacja',
    },
    '/dev-docs': {
        color: C.magenta,
        bg: C.bgMagenta,
        icon: '📋',
        label: 'DEV-DOCS',
        desc: 'Plan strategiczny',
    },
    '/dev-docs-execute': {
        color: C.yellow,
        bg: C.bgYellow,
        icon: '🚀',
        label: 'EXECUTE',
        desc: 'Wykonanie fazy',
    },
};

function printCommandBanner(prompt: string): boolean {
    const trimmed = prompt.trim();
    for (const [cmd, cfg] of Object.entries(COMMAND_BANNERS)) {
        if (trimmed.startsWith(cmd)) {
            const args = trimmed.slice(cmd.length).trim();
            const line = '━'.repeat(44);
            let banner = '';
            banner += `${cfg.color}${C.bold}${line}${C.reset}\n`;
            banner += `${cfg.bg}${C.white}${C.bold}  ${cfg.icon}  ${cfg.label}  ${C.reset}`;
            banner += `${cfg.color}${C.bold}  ${cfg.desc}${C.reset}\n`;
            if (args) {
                banner += `${C.dim}  Task: ${args.substring(0, 60)}${args.length > 60 ? '...' : ''}${C.reset}\n`;
            }
            banner += `${cfg.color}${C.bold}${line}${C.reset}\n`;
            process.stderr.write(banner);
            return true;
        }
    }
    return false;
}

async function main() {
    try {
        // Read input from stdin
        const input = readFileSync(0, 'utf-8');
        const data: HookInput = JSON.parse(input);
        const prompt = data.prompt.toLowerCase();

        // Print colored command banner to terminal (stderr)
        printCommandBanner(data.prompt);

        // Load skill rules
        const projectDir = process.env.CLAUDE_PROJECT_DIR || '$HOME/project';
        const rulesPath = join(projectDir, '.claude', 'skills', 'skill-rules.json');
        const rules: SkillRules = JSON.parse(readFileSync(rulesPath, 'utf-8'));

        const matchedSkills: MatchedSkill[] = [];

        // Check each skill for matches
        for (const [skillName, config] of Object.entries(rules.skills)) {
            const triggers = config.promptTriggers;
            if (!triggers) {
                continue;
            }

            // Keyword matching
            if (triggers.keywords) {
                const keywordMatch = triggers.keywords.some(kw =>
                    prompt.includes(kw.toLowerCase())
                );
                if (keywordMatch) {
                    matchedSkills.push({ name: skillName, matchType: 'keyword', config });
                    continue;
                }
            }

            // Intent pattern matching
            if (triggers.intentPatterns) {
                const intentMatch = triggers.intentPatterns.some(pattern => {
                    const regex = new RegExp(pattern, 'i');
                    return regex.test(prompt);
                });
                if (intentMatch) {
                    matchedSkills.push({ name: skillName, matchType: 'intent', config });
                }
            }
        }

        // Generate output if matches found
        if (matchedSkills.length > 0) {
            let output = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n';
            output += '🎯 SKILL ACTIVATION CHECK\n';
            output += '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n';

            // Group by priority
            const critical = matchedSkills.filter(s => s.config.priority === 'critical');
            const high = matchedSkills.filter(s => s.config.priority === 'high');
            const medium = matchedSkills.filter(s => s.config.priority === 'medium');
            const low = matchedSkills.filter(s => s.config.priority === 'low');

            if (critical.length > 0) {
                output += '⚠️ CRITICAL SKILLS (REQUIRED):\n';
                critical.forEach(s => output += `  → ${s.name}\n`);
                output += '\n';
            }

            if (high.length > 0) {
                output += '📚 RECOMMENDED SKILLS:\n';
                high.forEach(s => output += `  → ${s.name}\n`);
                output += '\n';
            }

            if (medium.length > 0) {
                output += '💡 SUGGESTED SKILLS:\n';
                medium.forEach(s => output += `  → ${s.name}\n`);
                output += '\n';
            }

            if (low.length > 0) {
                output += '📌 OPTIONAL SKILLS:\n';
                low.forEach(s => output += `  → ${s.name}\n`);
                output += '\n';
            }

            output += 'ACTION: Use Skill tool BEFORE responding\n';
            output += '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n';

            console.log(output);
        }

        process.exit(0);
    } catch (err) {
        console.error('Error in skill-activation-prompt hook:', err);
        process.exit(1);
    }
}

main().catch(err => {
    console.error('Uncaught error:', err);
    process.exit(1);
});
