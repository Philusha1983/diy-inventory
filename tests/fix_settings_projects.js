const fs = require('fs');
const path = require('path');

const ROOT_DIR = path.resolve(__dirname, '..');

// 1. Fix chat.php
const chatPath = path.join(ROOT_DIR, 'chat.php');
let chat = fs.readFileSync(chatPath, 'utf8');

chat = chat.replace(
    'placeholder="Ask anything about your lab inventory…"',
    'placeholder="Ask anything about your lab inventory…" data-i18n-placeholder="chat.ask_anything_placeholder"'
);

fs.writeFileSync(chatPath, chat, 'utf8');

// 2. Fix settings.php
const settingsPath = path.join(ROOT_DIR, 'settings.php');
let settings = fs.readFileSync(settingsPath, 'utf8');

settings = settings.replace(
    '<label for="lab_tagline" class="form-label">Tag Line',
    '<label for="lab_tagline" class="form-label" data-i18n-text="settings.tag_line">Tag Line'
);

settings = settings.replace(
    '<label for="lab_mini_tagline" class="form-label">Mini Tag Line',
    '<label for="lab_mini_tagline" class="form-label" data-i18n-text="settings.mini_tag_line">Mini Tag Line'
);

settings = settings.replace(
    'placeholder="Enter your current password"',
    'placeholder="Enter your current password" data-i18n-placeholder="settings.enter_current_password"'
);

settings = settings.replace(
    '<label for="new_password" class="form-label">New Password',
    '<label for="new_password" class="form-label" data-i18n-text="settings.new_password">New Password'
);

settings = settings.replace(
    'placeholder="Enter your new password"',
    'placeholder="Enter your new password" data-i18n-placeholder="settings.enter_new_password"'
);

settings = settings.replace(
    'placeholder="Repeat your new password"',
    'placeholder="Repeat your new password" data-i18n-placeholder="settings.repeat_new_password"'
);

fs.writeFileSync(settingsPath, settings, 'utf8');

// 3. Fix projects.php
const projectsPath = path.join(ROOT_DIR, 'projects.php');
let projects = fs.readFileSync(projectsPath, 'utf8');

// Inject formatDuration
const formatDurationFunc = `
function formatDuration(d) {
  if (!d) return '';
  return d.replace(/hours/gi, '<span data-i18n-text="projects.hours">hours</span>')
          .replace(/hour/gi, '<span data-i18n-text="projects.hour">hour</span>')
          .replace(/days/gi, '<span data-i18n-text="projects.days">days</span>')
          .replace(/day/gi, '<span data-i18n-text="projects.day">day</span>')
          .replace(/minutes/gi, '<span data-i18n-text="projects.minutes">minutes</span>')
          .replace(/minute/gi, '<span data-i18n-text="projects.minute">minute</span>')
          .replace(/weeks/gi, '<span data-i18n-text="projects.weeks">weeks</span>')
          .replace(/week/gi, '<span data-i18n-text="projects.week">week</span>');
}
`;

projects = projects.replace(
    'function esc(s) {',
    formatDurationFunc + '\nfunction esc(s) {'
);

// Replace duration rendering
projects = projects.replace(
    '${p.duration     ? `<span class="tag">⏱ ${esc(p.duration)}</span>` : \'\'}',
    '${p.duration     ? `<span class="tag">⏱ ${formatDuration(esc(p.duration))}</span>` : \'\'}'
);

// Replace complexity rendering
const complexityOriginal = '<span class="${complexityClass(p.complexity)} text-xs px-3 py-1 rounded-full font-medium flex-shrink-0">${esc(p.complexity)}</span>';
const complexityNew = `
        <span class="\${complexityClass(p.complexity)} text-xs px-3 py-1 rounded-full font-medium flex-shrink-0">
          \${['beginner','intermediate','expert'].includes((p.complexity||'').toLowerCase()) ? 
            \`<span data-i18n-text="projects.complexity_\${(p.complexity||'').toLowerCase()}">\${esc(p.complexity)}</span>\` : 
            esc(p.complexity)}
        </span>
`.trim();

projects = projects.replace(complexityOriginal, complexityNew);

fs.writeFileSync(projectsPath, projects, 'utf8');
console.log('Fixed chat, settings, and projects.php');
