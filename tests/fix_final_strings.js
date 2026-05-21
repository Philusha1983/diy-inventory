const fs = require('fs');
const path = require('path');

const ROOT_DIR = path.resolve(__dirname, '..');

// 1. Fix index.php
const indexPath = path.join(ROOT_DIR, 'index.php');
let index = fs.readFileSync(indexPath, 'utf8');

index = index.replace(
    'Enter Lab',
    '<span data-i18n-text="index.enter_lab">Enter Lab</span>'
);

fs.writeFileSync(indexPath, index, 'utf8');


// 2. Fix settings.php
const settingsPath = path.join(ROOT_DIR, 'settings.php');
let settings = fs.readFileSync(settingsPath, 'utf8');

settings = settings.replace(
    'placeholder="Leave blank for system default"',
    'placeholder="Leave blank for system default" data-i18n-placeholder="settings.leave_blank_for_system_default"'
);

settings = settings.replace(
    `placeholder="<?= $has_key ? '●●●●●●●●●●●● (key saved — re-enter to change)' : 'Enter your API key…' ?>"`,
    `<?= $has_key ? 'placeholder="●●●●●●●●●●●● (key saved — re-enter to change)" data-i18n-placeholder="settings.key_saved_reenter"' : 'placeholder="Enter your API key…" data-i18n-placeholder="settings.enter_api_key"' ?>`
);

settings = settings.replace(
    '<p class="text-sm text-slate-400">Drop an image or <span',
    '<p class="text-sm text-slate-400"><span data-i18n-text="settings.drop_an_image_or">Drop an image or</span> <span'
);

settings = settings.replace(
    '<p class="text-xs text-slate-600">JPEG · PNG · WebP · GIF &mdash; max 5 MB &mdash; auto-cropped to square</p>',
    '<p class="text-xs text-slate-600" data-i18n-text="settings.image_upload_specs">JPEG · PNG · WebP · GIF &mdash; max 5 MB &mdash; auto-cropped to square</p>'
);

settings = settings.replace(
    'Or paste an image URL instead',
    '<span data-i18n-text="settings.or_paste_an_image_url">Or paste an image URL instead</span>'
);

fs.writeFileSync(settingsPath, settings, 'utf8');


// 3. Fix bug_reporter.php
const bugRepPath = path.join(ROOT_DIR, 'includes/bug_reporter.php');
let bugRep = fs.readFileSync(bugRepPath, 'utf8');

bugRep = bugRep.replace(
    'placeholder="What went wrong? Please be descriptive..."',
    'placeholder="What went wrong? Please be descriptive..." data-i18n-placeholder="bug_reporter.what_went_wrong"'
);

bugRep = bugRep.replace(
    'Description <span',
    '<span data-i18n-text="bug_reporter.description">Description</span> <span'
);

bugRep = bugRep.replace(
    'Your Email <span',
    '<span data-i18n-text="bug_reporter.your_email">Your Email</span> <span'
);

bugRep = bugRep.replace(
    'Screenshot <span',
    '<span data-i18n-text="bug_reporter.screenshot">Screenshot</span> <span'
);

fs.writeFileSync(bugRepPath, bugRep, 'utf8');

console.log('Fixed final translation strings');
